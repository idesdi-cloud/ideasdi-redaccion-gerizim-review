<?php
if (!defined('ABSPATH')) {
    exit;
}

final class IDG_Traceability_Recapture {
    private const OPTION_PREFIX = 'idg_traceability_recapture_event_';
    private const DEFAULT_BATCH = 20;

    public static function record(array $payload, string $status, string $dependency_key, string $error): bool {
        $key = self::clean_key((string) ($payload['idempotency_key'] ?? ''));
        if ($key === '') {
            return false;
        }

        $intent = self::build_intent($payload, $status, $dependency_key, $error);
        $option = self::option_name($key);

        // add_option() is the atomic creation primitive: concurrent requests for
        // the same idempotency key cannot overwrite one another.
        if (add_option($option, $intent, '', false)) {
            return true;
        }

        $existing = get_option($option, []);
        if (!is_array($existing) || empty($existing)) {
            return false;
        }

        if (!self::intents_compatible($existing, $intent)) {
            $existing['state'] = 'conflict';
            $existing['last_error'] = 'idempotency_payload_conflict';
            $existing['conflict_detected_at'] = self::now_iso();
            $existing['updated_at'] = self::now_iso();
            $existing['conflicting_identity'] = self::identity_summary($intent);
            update_option($option, $existing, false);
            return false;
        }

        // A compatible repeated capture is already durably represented. Only
        // refresh operational metadata; never replace the original payload or
        // occurred_at.
        $existing['updated_at'] = self::now_iso();
        $existing['last_error'] = (string) ($existing['last_error'] ?? 'outbox_insert_failed');
        update_option($option, $existing, false);
        return true;
    }

    public static function record_unresolved_import(array $workflow, string $error = 'invalid_import_occurred_at'): bool {
        $brief_id = absint($workflow['radar_brief_id'] ?? 0);
        $workflow_id = self::clean_key((string) ($workflow['workflow_id'] ?? ''));
        if ($brief_id <= 0 || $workflow_id === '') {
            return false;
        }
        $key = 'gerizim_imported:' . $brief_id . ':' . $workflow_id;
        $payload = [
            'event_type' => 'gerizim_imported',
            'brief_id' => $brief_id,
            'occurred_at' => null,
            'observed_at' => self::now_iso(),
            'source_system' => 'gerizim-wordpress',
            'source_record_id' => $workflow_id,
            'workflow_id' => $workflow_id,
            'wordpress_post_id' => null,
            'wordpress_status' => null,
            'idempotency_key' => $key,
            'evidence_payload' => [
                'contract_version' => class_exists('IDG_Traceability') ? IDG_Traceability::contract_version() : '1.1',
            ],
            'actor' => 'ideasdi-redaccion-gerizim',
        ];
        return self::record($payload, 'blocked', '', $error);
    }

    public static function recover_batch(int $limit = self::DEFAULT_BATCH, int $after_option_id = 0): array {
        $result = [
            'checked' => 0,
            'recovered' => 0,
            'conflicts' => 0,
            'remaining' => self::count(),
            'has_more' => false,
            'last_option_id' => $after_option_id,
        ];
        if (!class_exists('IDG_Traceability_Outbox') || !IDG_Traceability_Outbox::schema_ready()) {
            return $result;
        }

        $rows = self::option_rows(max(1, $limit), max(0, $after_option_id));
        if (empty($rows) && $after_option_id > 0) {
            // Complete a cycle and allow old unresolved rows to be revisited.
            $result['last_option_id'] = 0;
            $rows = self::option_rows(max(1, $limit), 0);
        }

        foreach ($rows as $row) {
            $result['checked']++;
            $result['last_option_id'] = max($result['last_option_id'], (int) ($row['option_id'] ?? 0));
            $option_name = (string) ($row['option_name'] ?? '');
            $intent = maybe_unserialize($row['option_value'] ?? null);
            if (!is_array($intent) || $option_name === '') {
                continue;
            }
            $key = self::clean_key((string) ($intent['idempotency_key'] ?? ''));
            if ($key === '') {
                self::mark_option_problem($option_name, $intent, 'invalid_event_identity');
                continue;
            }
            if ((string) ($intent['state'] ?? '') === 'conflict') {
                $result['conflicts']++;
                continue;
            }

            $existing = IDG_Traceability_Outbox::get_by_key($key);
            if ($existing) {
                if (self::row_matches_intent($existing, $intent)) {
                    if (self::delete_option_verified($option_name)) {
                        $result['recovered']++;
                    } else {
                        self::mark_option_problem($option_name, $intent, 'recapture_cleanup_failed');
                    }
                } else {
                    self::mark_option_problem($option_name, $intent, 'idempotency_payload_conflict', true);
                    $result['conflicts']++;
                }
                continue;
            }

            $payload = isset($intent['payload']) && is_array($intent['payload']) ? $intent['payload'] : [];
            if (self::event_occurred_at($payload) === '' && class_exists('IDG_Traceability')) {
                $rebuilt = IDG_Traceability::rebuild_recapture_intent($intent);
                if (!empty($rebuilt['excluded'])) {
                    if (self::delete_option_verified($option_name)) {
                        $result['recovered']++;
                    } else {
                        self::mark_option_problem($option_name, $intent, 'recapture_cleanup_failed');
                    }
                    continue;
                }
                if (empty($rebuilt['success'])) {
                    self::mark_option_problem($option_name, $intent, (string) ($rebuilt['reason'] ?? 'invalid_import_occurred_at'));
                    continue;
                }
                $payload = (array) ($rebuilt['payload'] ?? []);
                $intent['payload'] = $payload;
                $intent['status'] = (string) ($rebuilt['status'] ?? $intent['status'] ?? 'blocked');
                $intent['dependency_key'] = (string) ($rebuilt['dependency_key'] ?? $intent['dependency_key'] ?? '');
                $intent['initial_error'] = (string) ($rebuilt['error'] ?? $intent['initial_error'] ?? '');
                $intent['occurred_at'] = self::event_occurred_at($payload);
                $intent['updated_at'] = self::now_iso();
                update_option($option_name, $intent, false);
            }

            $insert = IDG_Traceability_Outbox::insert_event(
                $payload,
                (string) ($intent['status'] ?? 'blocked'),
                (string) ($intent['dependency_key'] ?? ''),
                (string) ($intent['initial_error'] ?? '')
            );
            if (!empty($insert['success'])) {
                $row_now = IDG_Traceability_Outbox::get_by_key($key);
                if ($row_now && self::row_matches_intent($row_now, $intent)) {
                    if (self::delete_option_verified($option_name)) {
                        $result['recovered']++;
                    } else {
                        self::mark_option_problem($option_name, $intent, 'recapture_cleanup_failed');
                    }
                } else {
                    self::mark_option_problem($option_name, $intent, 'recapture_verification_failed');
                }
            } else {
                $message = self::clean_error((string) ($insert['message'] ?? 'recapture_failed'));
                self::mark_option_problem($option_name, $intent, $message, $message === 'idempotency_payload_conflict');
                if ($message === 'idempotency_payload_conflict') {
                    $result['conflicts']++;
                }
            }
        }

        $result['remaining'] = self::count();
        // has_more means there is another page to process immediately. Durable
        // unresolved/conflicting intents remain for the next scheduled cycle
        // without causing an endless chain of async actions.
        $result['has_more'] = count($rows) >= max(1, $limit);
        return $result;
    }

    public static function count(): int {
        global $wpdb;
        if (isset($wpdb->options)) {
            $like = self::esc_like(self::OPTION_PREFIX) . '%';
            return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s", $like));
        }
        return count(self::fallback_all());
    }


    public static function conflict_count(): int {
        $count = 0;
        foreach (self::option_rows(1000, 0, false) as $row) {
            $intent = maybe_unserialize($row['option_value'] ?? null);
            if (is_array($intent) && (string) ($intent['state'] ?? '') === 'conflict') {
                $count++;
            }
        }
        return $count;
    }

    public static function problem_rows(int $limit = 20): array {
        $rows = self::option_rows(max(5, $limit * 5), 0, true);
        $out = [];
        foreach ($rows as $row) {
            $intent = maybe_unserialize($row['option_value'] ?? null);
            if (!is_array($intent)) {
                continue;
            }
            if (!in_array((string) ($intent['state'] ?? ''), ['conflict', 'blocked'], true)
                && !in_array((string) ($intent['last_error'] ?? ''), ['idempotency_payload_conflict', 'invalid_import_occurred_at', 'outbox_insert_failed', 'traceability_schema_incomplete', 'recapture_cleanup_failed', 'recapture_persistence_failed'], true)) {
                continue;
            }
            $out[] = [
                'idempotency_key' => (string) ($intent['idempotency_key'] ?? ''),
                'event_type' => (string) ($intent['event_type'] ?? ''),
                'status' => (string) ($intent['status'] ?? 'blocked'),
                'state' => (string) ($intent['state'] ?? 'pending'),
                'last_error' => (string) ($intent['last_error'] ?? ''),
                'updated_at' => (string) ($intent['updated_at'] ?? ''),
            ];
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }

    public static function clear_if_compatible(string $key, array $payload, string $dependency_key = ''): bool {
        $key = self::clean_key($key);
        if ($key === '') {
            return false;
        }
        $option = self::option_name($key);
        $existing = get_option($option, null);
        if ($existing === null) {
            return true;
        }
        if (!is_array($existing)) {
            return false;
        }
        $candidate = self::build_intent($payload, (string) ($existing['status'] ?? 'queued'), $dependency_key, (string) ($existing['initial_error'] ?? ''));
        if (!self::intents_compatible($existing, $candidate)) {
            self::mark_option_problem($option, $existing, 'idempotency_payload_conflict', true);
            return false;
        }
        return self::delete_option_verified($option);
    }

    public static function clear(string $key): void {
        $key = self::clean_key($key);
        if ($key !== '') {
            delete_option(self::option_name($key));
        }
    }

    public static function option_name_for_key(string $key): string {
        return self::option_name($key);
    }

    public static function intents_compatible(array $a, array $b): bool {
        $payload_a = isset($a['payload']) && is_array($a['payload']) ? $a['payload'] : [];
        $payload_b = isset($b['payload']) && is_array($b['payload']) ? $b['payload'] : [];
        return self::contract_payload($payload_a) === self::contract_payload($payload_b)
            && (string) ($a['dependency_key'] ?? '') === (string) ($b['dependency_key'] ?? '')
            && (string) ($a['event_type'] ?? '') === (string) ($b['event_type'] ?? '');
    }

    private static function build_intent(array $payload, string $status, string $dependency_key, string $error): array {
        $now = self::now_iso();
        return [
            'idempotency_key' => self::clean_key((string) ($payload['idempotency_key'] ?? '')),
            'event_type' => sanitize_key((string) ($payload['event_type'] ?? '')),
            'brief_id' => absint($payload['brief_id'] ?? 0),
            'workflow_id' => self::clean_key((string) ($payload['workflow_id'] ?? '')),
            'wordpress_post_id' => absint($payload['wordpress_post_id'] ?? 0),
            'wordpress_status' => sanitize_key((string) ($payload['wordpress_status'] ?? '')),
            'occurred_at' => self::event_occurred_at($payload),
            'observed_at' => self::clean_text((string) ($payload['observed_at'] ?? '')),
            'payload' => $payload,
            'status' => sanitize_key($status),
            'dependency_key' => self::clean_key($dependency_key),
            'initial_error' => sanitize_key($error),
            'last_error' => $error !== '' ? sanitize_key($error) : 'outbox_insert_failed',
            'state' => $status === 'blocked' ? 'blocked' : 'pending',
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private static function row_matches_intent(array $row, array $intent): bool {
        $stored = json_decode((string) ($row['payload_json'] ?? ''), true);
        if (!is_array($stored)) {
            return false;
        }
        $candidate = isset($intent['payload']) && is_array($intent['payload']) ? $intent['payload'] : [];
        return self::contract_payload($stored) === self::contract_payload($candidate)
            && (string) ($row['event_type'] ?? '') === (string) ($intent['event_type'] ?? '')
            && (string) ($row['dependency_key'] ?? '') === (string) ($intent['dependency_key'] ?? '');
    }

    private static function contract_payload(array $payload): string {
        unset($payload['observed_at']);
        self::ksort_recursive($payload);
        return (string) wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private static function ksort_recursive(array &$value): void {
        foreach ($value as &$item) {
            if (is_array($item)) {
                self::ksort_recursive($item);
            }
        }
        unset($item);
        ksort($value);
    }

    private static function identity_summary(array $intent): array {
        return [
            'event_type' => (string) ($intent['event_type'] ?? ''),
            'brief_id' => absint($intent['brief_id'] ?? 0),
            'workflow_id' => (string) ($intent['workflow_id'] ?? ''),
            'wordpress_post_id' => absint($intent['wordpress_post_id'] ?? 0),
            'wordpress_status' => (string) ($intent['wordpress_status'] ?? ''),
            'occurred_at' => (string) ($intent['occurred_at'] ?? ''),
            'dependency_key' => (string) ($intent['dependency_key'] ?? ''),
        ];
    }

    private static function mark_option_problem(string $option, array $intent, string $error, bool $conflict = false): void {
        $intent['last_error'] = self::clean_error($error);
        $intent['state'] = $conflict ? 'conflict' : ((string) ($intent['state'] ?? '') ?: 'blocked');
        $intent['updated_at'] = self::now_iso();
        update_option($option, $intent, false);
    }

    private static function option_rows(int $limit, int $after_option_id = 0, bool $descending = false): array {
        global $wpdb;
        if (isset($wpdb->options)) {
            $like = self::esc_like(self::OPTION_PREFIX) . '%';
            $direction = $descending ? 'DESC' : 'ASC';
            return (array) $wpdb->get_results($wpdb->prepare(
                "SELECT option_id,option_name,option_value FROM {$wpdb->options} WHERE option_name LIKE %s AND option_id > %d ORDER BY option_id {$direction} LIMIT %d",
                $like,
                max(0, $after_option_id),
                max(1, $limit)
            ), ARRAY_A);
        }
        $rows = [];
        $id = 0;
        foreach (self::fallback_all() as $name => $value) {
            $id++;
            if ($id <= $after_option_id) {
                continue;
            }
            $rows[] = ['option_id' => $id, 'option_name' => $name, 'option_value' => serialize($value)];
        }
        if ($descending) {
            $rows = array_reverse($rows);
        }
        return array_slice($rows, 0, max(1, $limit));
    }

    private static function fallback_all(): array {
        if (isset($GLOBALS['idg_test_options']) && is_array($GLOBALS['idg_test_options'])) {
            return array_filter($GLOBALS['idg_test_options'], static fn($value, $key): bool => str_starts_with((string) $key, self::OPTION_PREFIX), ARRAY_FILTER_USE_BOTH);
        }
        return [];
    }

    private static function option_name(string $key): string {
        return self::OPTION_PREFIX . hash('sha256', $key);
    }


    private static function delete_option_verified(string $option): bool {
        delete_option($option);
        return get_option($option, null) === null;
    }

    private static function event_occurred_at(array $payload): string {
        $value = $payload['occurred_at'] ?? '';
        return is_scalar($value) ? self::clean_text((string) $value) : '';
    }

    private static function clean_key(string $value): string {
        return self::clean_text($value);
    }

    private static function clean_text(string $value): string {
        return function_exists('sanitize_text_field') ? sanitize_text_field($value) : trim($value);
    }

    private static function clean_error(string $value): string {
        $value = self::clean_text($value);
        return function_exists('mb_substr') ? mb_substr($value, 0, 500) : substr($value, 0, 500);
    }

    private static function esc_like(string $value): string {
        global $wpdb;
        return method_exists($wpdb, 'esc_like') ? $wpdb->esc_like($value) : addcslashes($value, '_%\\');
    }

    private static function now_iso(): string {
        return gmdate('Y-m-d\TH:i:s\Z');
    }
}
