<?php
if (!defined('ABSPATH')) {
    exit;
}

final class IDG_Traceability {
    private const HISTORICAL_BRIEF_ID = 16;
    private const HISTORICAL_POST_ID = 36565;
    private const HISTORICAL_WORKFLOW_ID = 'idg_8bcd8df4-38ad-4e3b-ade1-b5a51c583455';
    private const DEFAULT_CONTRACT_VERSION = '1.1';
    private const MINIMUM_LIVE_CAPTURE_CUTOFF = '2026-07-04T14:53:06.000Z';

    public static function boot(): void {
        add_action('transition_post_status', [self::class, 'safe_capture_wordpress_published'], 10, 3);
        add_action(IDG_TRACEABILITY_ACTION_HOOK, [self::class, 'process_scheduled_queue']);
        add_action(IDG_TRACEABILITY_RECONCILE_HOOK, [self::class, 'run_reconciliation']);
        add_action('init', [self::class, 'maybe_schedule']);
    }

    public static function maybe_upgrade_database(): void {
        if (class_exists('IDG_Traceability_Outbox')) {
            IDG_Traceability_Outbox::install_or_upgrade();
        }
    }

    public static function capture_enabled(): bool {
        return self::config_bool('IDG_TRACEABILITY_CAPTURE_ENABLED', false);
    }

    public static function delivery_enabled(): bool {
        return self::config_bool('IDG_TRACEABILITY_DELIVERY_ENABLED', false);
    }

    public static function contract_version(): string {
        $version = self::config_string('IDG_TRACEABILITY_CONTRACT_VERSION', self::DEFAULT_CONTRACT_VERSION);
        return $version !== '' ? $version : self::DEFAULT_CONTRACT_VERSION;
    }

    public static function config_string(string $name, string $default = ''): string {
        if (defined($name)) {
            $value = constant($name);
            return is_scalar($value) ? trim((string) $value) : $default;
        }
        $value = getenv($name);
        if ($value === false) {
            return $default;
        }
        return trim((string) $value);
    }

    public static function config_bool(string $name, bool $default = false): bool {
        $raw = self::config_string($name, $default ? '1' : '0');
        $parsed = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $parsed === null ? $default : $parsed;
    }

    public static function validate_radar_url(string $url): array {
        $url = trim($url);
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return ['valid' => false, 'reason' => 'invalid_traceability_url'];
        }
        $parts = function_exists('wp_parse_url') ? wp_parse_url($url) : parse_url($url);
        if (!is_array($parts)) {
            $parts = parse_url($url);
        }
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));
        if ($host === '' || !in_array($scheme, ['https', 'http'], true)) {
            return ['valid' => false, 'reason' => 'invalid_traceability_url'];
        }
        if ($scheme === 'https') {
            return ['valid' => true, 'reason' => ''];
        }
        $localhost = in_array($host, ['localhost', '127.0.0.1', '::1'], true) || str_ends_with($host, '.localhost');
        if ($localhost) {
            return ['valid' => true, 'reason' => ''];
        }
        $environment = function_exists('wp_get_environment_type') ? wp_get_environment_type() : '';
        $explicit_development = self::config_bool('IDG_TRACEABILITY_ALLOW_INSECURE_HTTP', false)
            && in_array($environment, ['', 'local', 'development'], true);
        if ($explicit_development) {
            return ['valid' => true, 'reason' => ''];
        }
        return ['valid' => false, 'reason' => 'insecure_traceability_url'];
    }

    public static function live_capture_cutoff(): array {
        $raw = self::config_string('IDG_TRACEABILITY_LIVE_CAPTURE_STARTED_AT');
        $configured_timestamp = self::iso_to_timestamp($raw);
        $minimum_timestamp = self::iso_to_timestamp(self::MINIMUM_LIVE_CAPTURE_CUTOFF);
        $is_explicit_utc = (bool) preg_match('/(?:Z|\+00:00)$/i', $raw);
        $valid = $raw !== '' && $configured_timestamp > 0 && $is_explicit_utc;
        return [
            'raw' => $raw,
            'valid' => $valid,
            'configured_timestamp' => $configured_timestamp,
            'minimum' => self::MINIMUM_LIVE_CAPTURE_CUTOFF,
            'timestamp' => $valid ? max($configured_timestamp, $minimum_timestamp) : 0,
        ];
    }

    public static function iso_to_timestamp(string $value): int {
        $value = trim($value);
        if ($value === '' || str_starts_with($value, '0000-00-00')) {
            return 0;
        }
        try {
            $date = new DateTimeImmutable($value);
            return $date->getTimestamp();
        } catch (Throwable $e) {
            return 0;
        }
    }

    public static function validate_event_payload(array $payload, string $status = 'queued', string $error = ''): array {
        $allowed_keys = [
            'event_type', 'brief_id', 'occurred_at', 'observed_at', 'source_system',
            'source_record_id', 'workflow_id', 'wordpress_post_id', 'wordpress_status',
            'idempotency_key', 'evidence_payload', 'actor',
        ];
        $unexpected = array_diff(array_keys($payload), $allowed_keys);
        if (!empty($unexpected)) {
            return ['valid' => false, 'reason' => 'unexpected_payload_fields'];
        }

        $event_type = sanitize_key((string) ($payload['event_type'] ?? ''));
        $brief_id = absint($payload['brief_id'] ?? 0);
        $workflow_id = sanitize_text_field((string) ($payload['workflow_id'] ?? ''));
        $key = sanitize_text_field((string) ($payload['idempotency_key'] ?? ''));
        $observed_at = sanitize_text_field((string) ($payload['observed_at'] ?? ''));
        $occurred_at = $payload['occurred_at'] ?? null;
        $evidence = is_array($payload['evidence_payload'] ?? null) ? $payload['evidence_payload'] : [];

        if (!in_array($event_type, ['gerizim_imported', 'wordpress_post_created', 'wordpress_published'], true)) {
            return ['valid' => false, 'reason' => 'unsupported_event_type'];
        }
        if ($brief_id <= 0 || !preg_match('/^idg_[A-Za-z0-9-]{8,80}$/', $workflow_id) || strlen($key) > 191 || self::iso_to_timestamp($observed_at) <= 0) {
            return ['valid' => false, 'reason' => 'invalid_event_identity'];
        }
        if ((string) ($evidence['contract_version'] ?? '') !== self::contract_version()) {
            return ['valid' => false, 'reason' => 'invalid_contract_version'];
        }
        if (($occurred_at === null || self::iso_to_timestamp((string) $occurred_at) <= 0)
            && !($status === 'blocked' && $error === 'invalid_occurred_at')) {
            return ['valid' => false, 'reason' => 'invalid_occurred_at'];
        }

        $post_id = absint($payload['wordpress_post_id'] ?? 0);
        if ($event_type === 'gerizim_imported') {
            if (!empty(array_diff(array_keys($evidence), ['contract_version']))) {
                return ['valid' => false, 'reason' => 'unexpected_evidence_fields'];
            }
            $expected = 'gerizim_imported:' . $brief_id . ':' . $workflow_id;
            if ($key !== $expected
                || (string) ($payload['source_system'] ?? '') !== 'gerizim-wordpress'
                || (string) ($payload['source_record_id'] ?? '') !== $workflow_id
                || (string) ($payload['actor'] ?? '') !== 'ideasdi-redaccion-gerizim'
                || ($payload['wordpress_post_id'] ?? null) !== null
                || ($payload['wordpress_status'] ?? null) !== null) {
                return ['valid' => false, 'reason' => 'invalid_import_event'];
            }
            return ['valid' => true, 'reason' => ''];
        }

        if ($post_id <= 0 || (string) ($payload['source_record_id'] ?? '') !== (string) $post_id || (string) ($payload['source_system'] ?? '') !== 'wordpress') {
            return ['valid' => false, 'reason' => 'invalid_wordpress_event_identity'];
        }
        if ($event_type === 'wordpress_post_created') {
            if (!empty(array_diff(array_keys($evidence), ['contract_version', 'created_by']))) {
                return ['valid' => false, 'reason' => 'unexpected_evidence_fields'];
            }
            $expected = 'wordpress_post_created:' . $workflow_id . ':' . $post_id;
            if ($key !== $expected
                || (string) ($payload['wordpress_status'] ?? '') !== 'pending'
                || (string) ($payload['actor'] ?? '') !== 'ideasdi-redaccion-gerizim'
                || (string) ($evidence['created_by'] ?? '') !== 'ideasdi-redaccion-gerizim') {
                return ['valid' => false, 'reason' => 'invalid_post_created_event'];
            }
            return ['valid' => true, 'reason' => ''];
        }

        if (!empty(array_diff(array_keys($evidence), ['contract_version', 'previous_status', 'new_status']))) {
            return ['valid' => false, 'reason' => 'unexpected_evidence_fields'];
        }
        $expected = 'wordpress_published:' . $workflow_id . ':' . $post_id;
        if ($key !== $expected
            || (string) ($payload['wordpress_status'] ?? '') !== 'publish'
            || (string) ($payload['actor'] ?? '') !== 'wordpress'
            || (string) ($evidence['new_status'] ?? '') !== 'publish'
            || sanitize_key((string) ($evidence['previous_status'] ?? '')) === 'publish') {
            return ['valid' => false, 'reason' => 'invalid_published_event'];
        }
        return ['valid' => true, 'reason' => ''];
    }

    public static function persist_radar_import_provenance(string $workflow_id, int $brief_id): array {
        $workflow = IDG_Job_Runner::get_workflow($workflow_id);
        if ((string) ($workflow['workflow_id'] ?? '') !== $workflow_id
            || absint($workflow['radar_brief_id'] ?? 0) !== $brief_id
            || (string) ($workflow['radar_source'] ?? '') !== 'radar-editorial-ideasdi'
            || self::is_recurring_workflow($workflow)) {
            return ['success' => false, 'reason' => 'workflow_not_eligible'];
        }

        // The first trustworthy import time is immutable. This also upgrades
        // workflows imported by an older version that have a valid original
        // date but do not yet carry the structured identity marker.
        $existing_identity = (string) ($workflow['radar_import_identity'] ?? '');
        $expected_identity = self::radar_import_identity($brief_id, $workflow_id);
        if ($existing_identity !== '' && $existing_identity !== $expected_identity) {
            return ['success' => false, 'reason' => 'radar_import_identity_conflict'];
        }
        $resolved_original = self::resolve_import_occurred_at($workflow);
        if (empty($resolved_original['valid'])) {
            return ['success' => false, 'reason' => 'invalid_import_occurred_at'];
        }
        $is_new_import = !empty($workflow['radar_import_is_new'])
            && $existing_identity === ''
            && empty($workflow['radar_import_persisted']);
        $preserved = !$is_new_import;
        $workflow['radar_imported_at_utc'] = (string) $resolved_original['value'];
        $workflow['radar_import_persisted'] = true;
        $workflow['radar_import_identity'] = $expected_identity;
        if ($is_new_import) {
            $workflow['radar_import_persisted_at_utc'] = self::utc_now_iso();
        }
        unset($workflow['radar_import_is_new']);
        IDG_Job_Runner::save_workflow($workflow_id, $workflow);
        $verified = IDG_Job_Runner::get_workflow($workflow_id);
        $success = (string) ($verified['radar_import_identity'] ?? '') === $expected_identity
            && self::iso_to_timestamp((string) ($verified['radar_imported_at_utc'] ?? '')) > 0;
        return [
            'success' => $success,
            'preserved' => $preserved,
            'occurred_at' => (string) ($verified['radar_imported_at_utc'] ?? ''),
            'reason' => $success ? '' : 'workflow_persistence_verification_failed',
        ];
    }

    public static function resolve_import_occurred_at(array $workflow): array {
        $utc = sanitize_text_field((string) ($workflow['radar_imported_at_utc'] ?? ''));
        if (self::iso_to_timestamp($utc) > 0) {
            return ['valid' => true, 'value' => self::normalize_utc_iso($utc), 'source' => 'radar_imported_at_utc'];
        }
        $local = trim((string) ($workflow['radar_imported_at'] ?? ''));
        if ($local === '' || str_starts_with($local, '0000-00-00')) {
            return ['valid' => false, 'value' => '', 'source' => ''];
        }
        try {
            $timezone = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
            $date = new DateTimeImmutable($local, $timezone);
            if ($date->getTimestamp() <= 0) {
                return ['valid' => false, 'value' => '', 'source' => ''];
            }
            return [
                'valid' => true,
                'value' => $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\\TH:i:s\\Z'),
                'source' => 'radar_imported_at',
            ];
        } catch (Throwable $e) {
            return ['valid' => false, 'value' => '', 'source' => ''];
        }
    }

    public static function rebuild_recapture_intent(array $intent): array {
        $event_type = sanitize_key((string) ($intent['event_type'] ?? ''));
        if ($event_type !== 'gerizim_imported') {
            return ['success' => false, 'reason' => 'invalid_occurred_at'];
        }
        $workflow_id = sanitize_text_field((string) ($intent['workflow_id'] ?? ''));
        $workflow = IDG_Job_Runner::get_workflow($workflow_id);
        if (!self::is_radar_workflow_base($workflow, true)) {
            return ['success' => false, 'reason' => 'workflow_not_eligible'];
        }
        $resolved = self::resolve_import_occurred_at($workflow);
        if (empty($resolved['valid'])) {
            return ['success' => false, 'reason' => 'invalid_import_occurred_at'];
        }
        $occurred_at = (string) $resolved['value'];
        $date_state = self::capture_status_for_date($occurred_at, 'invalid_import_occurred_at');
        if (!empty($date_state['skip'])) {
            return ['success' => false, 'excluded' => true, 'reason' => 'historical_before_live_capture_cutoff'];
        }
        $brief_id = absint($workflow['radar_brief_id'] ?? 0);
        $key = 'gerizim_imported:' . $brief_id . ':' . $workflow_id;
        $payload = self::build_import_payload($brief_id, $workflow_id, $occurred_at, (string) ($intent['observed_at'] ?? self::utc_now_iso()));
        return [
            'success' => true,
            'payload' => $payload,
            'status' => (string) $date_state['status'],
            'error' => (string) $date_state['error'],
            'dependency_key' => '',
            'idempotency_key' => $key,
        ];
    }

    public static function capture_gerizim_imported(string $workflow_id): array {
        if (!self::capture_enabled()) {
            return ['success' => false, 'skipped' => true, 'reason' => 'capture_disabled'];
        }

        $workflow = IDG_Job_Runner::get_workflow($workflow_id);
        if (!self::is_radar_workflow_base($workflow, true)) {
            return ['success' => false, 'skipped' => true, 'reason' => 'workflow_not_eligible'];
        }
        $brief_id = absint($workflow['radar_brief_id'] ?? 0);
        if (self::is_historical_identity($brief_id, 0, $workflow_id)) {
            return ['success' => false, 'skipped' => true, 'reason' => 'historical_identity_excluded'];
        }

        $key = 'gerizim_imported:' . $brief_id . ':' . $workflow_id;
        $resolved = self::resolve_import_occurred_at($workflow);
        if (empty($resolved['valid'])) {
            $workflow['traceability_gerizim_imported_key'] = $key;
            $workflow['traceability_gerizim_imported_status'] = 'blocked';
            $workflow['traceability_gerizim_imported_synced_at_utc'] = self::utc_now_iso();
            IDG_Job_Runner::save_workflow($workflow_id, $workflow);
            $stored = class_exists('IDG_Traceability_Recapture')
                ? IDG_Traceability_Recapture::record_unresolved_import($workflow, 'invalid_import_occurred_at')
                : false;
            self::schedule_reconciliation();
            return ['success' => false, 'skipped' => false, 'reason' => 'invalid_import_occurred_at', 'recapture_scheduled' => $stored];
        }
        $occurred_at = (string) $resolved['value'];
        $cutoff_state = self::capture_status_for_date($occurred_at, 'invalid_import_occurred_at');
        if (!empty($cutoff_state['skip'])) {
            $workflow['traceability_gerizim_imported_key'] = '';
            $workflow['traceability_gerizim_imported_status'] = 'excluded_before_live_cutoff';
            $workflow['traceability_gerizim_imported_synced_at_utc'] = self::utc_now_iso();
            IDG_Job_Runner::save_workflow($workflow_id, $workflow);
            if (class_exists('IDG_Traceability_Recapture')) {
                IDG_Traceability_Recapture::clear($key);
            }
            return ['success' => false, 'skipped' => true, 'reason' => 'historical_before_live_capture_cutoff'];
        }

        $payload = self::build_import_payload($brief_id, $workflow_id, $occurred_at, self::utc_now_iso());
        $workflow['radar_imported_at_utc'] = $occurred_at;
        $workflow['traceability_gerizim_imported_key'] = $key;
        $workflow['traceability_gerizim_imported_status'] = '';
        $workflow['traceability_gerizim_imported_synced_at_utc'] = '';
        IDG_Job_Runner::save_workflow($workflow_id, $workflow);
        $verified = IDG_Job_Runner::get_workflow($workflow_id);
        if ((string) ($verified['workflow_id'] ?? '') !== $workflow_id
            || absint($verified['radar_brief_id'] ?? 0) !== $brief_id
            || (string) ($verified['traceability_gerizim_imported_key'] ?? '') !== $key
            || (string) ($verified['radar_imported_at_utc'] ?? '') !== $occurred_at) {
            return ['success' => false, 'skipped' => true, 'reason' => 'workflow_persistence_verification_failed'];
        }

        $result = self::capture_or_defer($payload, $cutoff_state['status'], '', $cutoff_state['error']);
        if (!empty($result['success'])) {
            self::schedule_processing();
        }
        return $result;
    }

    public static function safe_capture_gerizim_imported(string $workflow_id): array {
        try {
            return self::capture_gerizim_imported($workflow_id);
        } catch (Throwable $e) {
            self::safe_log_capture_error('gerizim_imported', $e, ['workflow_id' => $workflow_id]);
            return ['success' => false, 'skipped' => true, 'reason' => 'traceability_capture_exception'];
        }
    }

    public static function capture_wordpress_post_created(string $workflow_id, int $post_id): array {
        if (!self::capture_enabled()) {
            return ['success' => false, 'skipped' => true, 'reason' => 'capture_disabled'];
        }

        $workflow = IDG_Job_Runner::get_workflow($workflow_id);
        if (!self::eligible_radar_workflow($workflow)) {
            return ['success' => false, 'skipped' => true, 'reason' => 'workflow_not_eligible'];
        }
        if (self::is_recurring_workflow($workflow)) {
            return ['success' => false, 'skipped' => true, 'reason' => 'recurring_workflow_excluded'];
        }

        $post = get_post($post_id);
        $brief_id = absint($workflow['radar_brief_id'] ?? 0);
        if (!$post || $post->post_type !== 'post' || $post->post_status !== 'pending') {
            return ['success' => false, 'skipped' => true, 'reason' => 'post_verification_failed'];
        }
        if (self::is_historical_identity($brief_id, $post_id, $workflow_id)) {
            return ['success' => false, 'skipped' => true, 'reason' => 'historical_identity_excluded'];
        }

        $meta_workflow = sanitize_text_field((string) get_post_meta($post_id, '_idg_workflow_id', true));
        $meta_brief = absint(get_post_meta($post_id, '_idg_radar_brief_id', true));
        $meta_contract = sanitize_text_field((string) get_post_meta($post_id, '_idg_traceability_contract_version', true));
        if ($meta_workflow !== $workflow_id
            || $meta_brief !== $brief_id
            || $meta_contract !== self::contract_version()
            || absint($workflow['draft_post_id'] ?? 0) !== $post_id) {
            return ['success' => false, 'skipped' => true, 'reason' => 'post_identity_mismatch'];
        }

        $occurred_at = self::post_created_at_utc($post);
        $key = 'wordpress_post_created:' . $workflow_id . ':' . $post_id;
        $dependency_key = 'gerizim_imported:' . $brief_id . ':' . $workflow_id;
        if (!IDG_Traceability_Outbox::get_by_key($dependency_key)) {
            $import_capture = self::capture_gerizim_imported($workflow_id);
            if (($import_capture['reason'] ?? '') === 'historical_before_live_capture_cutoff') {
                return ['success' => false, 'skipped' => true, 'reason' => 'import_before_live_capture_cutoff'];
            }
        }
        $existing = IDG_Traceability_Outbox::get_by_key($key);
        if ($existing) {
            self::sync_row_reflection($existing);
            return ['success' => true, 'inserted' => false, 'row' => $existing];
        }
        $initial = self::initial_status($occurred_at, $dependency_key);
        if (!empty($initial['skip'])) {
            return ['success' => false, 'skipped' => true, 'reason' => (string) ($initial['error'] ?? 'historical_before_live_capture_cutoff')];
        }

        $payload = [
            'event_type' => 'wordpress_post_created',
            'brief_id' => $brief_id,
            'occurred_at' => $occurred_at !== '' ? $occurred_at : null,
            'observed_at' => self::utc_now_iso(),
            'source_system' => 'wordpress',
            'source_record_id' => (string) $post_id,
            'workflow_id' => $workflow_id,
            'wordpress_post_id' => $post_id,
            'wordpress_status' => 'pending',
            'idempotency_key' => $key,
            'evidence_payload' => [
                'contract_version' => self::contract_version(),
                'created_by' => 'ideasdi-redaccion-gerizim',
            ],
            'actor' => 'ideasdi-redaccion-gerizim',
        ];

        $result = self::capture_or_defer($payload, $initial['status'], $dependency_key, $initial['error']);
        if (!empty($result['success'])) {
            self::schedule_processing();
        }
        return $result;
    }

    public static function safe_capture_wordpress_post_created(string $workflow_id, int $post_id): array {
        try {
            return self::capture_wordpress_post_created($workflow_id, $post_id);
        } catch (Throwable $e) {
            self::safe_log_capture_error('wordpress_post_created', $e, ['workflow_id' => $workflow_id, 'post_id' => $post_id]);
            return ['success' => false, 'skipped' => true, 'reason' => 'traceability_capture_exception'];
        }
    }

    public static function capture_wordpress_published(string $new_status, string $old_status, $post): void {
        if ($old_status === $new_status || $new_status !== 'publish' || $old_status === 'publish') {
            return;
        }
        if (!$post instanceof WP_Post || $post->post_type !== 'post' || !self::capture_enabled()) {
            return;
        }

        $post_id = (int) $post->ID;
        $workflow_id = sanitize_text_field((string) get_post_meta($post_id, '_idg_workflow_id', true));
        $brief_id = absint(get_post_meta($post_id, '_idg_radar_brief_id', true));
        $contract = sanitize_text_field((string) get_post_meta($post_id, '_idg_traceability_contract_version', true));
        if ($workflow_id === '' || $brief_id <= 0 || $contract !== self::contract_version()) {
            return;
        }

        $workflow = IDG_Job_Runner::get_workflow($workflow_id);
        if (!self::eligible_radar_workflow($workflow)
            || self::is_recurring_workflow($workflow)
            || absint($workflow['draft_post_id'] ?? 0) !== $post_id
            || absint($workflow['radar_brief_id'] ?? 0) !== $brief_id) {
            return;
        }
        if (self::is_historical_identity($brief_id, $post_id, $workflow_id)) {
            return;
        }

        $occurred_at = self::utc_now_iso();
        $key = 'wordpress_published:' . $workflow_id . ':' . $post_id;
        $dependency_key = 'wordpress_post_created:' . $workflow_id . ':' . $post_id;
        $existing = IDG_Traceability_Outbox::get_by_key($key);
        if ($existing) {
            self::sync_row_reflection($existing);
            return;
        }
        $initial = self::initial_status($occurred_at, $dependency_key);
        if (!empty($initial['skip'])) {
            return;
        }
        update_post_meta($post_id, '_idg_published_at_utc', sanitize_text_field($occurred_at));

        $payload = [
            'event_type' => 'wordpress_published',
            'brief_id' => $brief_id,
            'occurred_at' => $occurred_at,
            'observed_at' => self::utc_now_iso(),
            'source_system' => 'wordpress',
            'source_record_id' => (string) $post_id,
            'workflow_id' => $workflow_id,
            'wordpress_post_id' => $post_id,
            'wordpress_status' => 'publish',
            'idempotency_key' => $key,
            'evidence_payload' => [
                'contract_version' => self::contract_version(),
                'previous_status' => sanitize_key($old_status),
                'new_status' => 'publish',
            ],
            'actor' => 'wordpress',
        ];

        $result = self::capture_or_defer($payload, $initial['status'], $dependency_key, $initial['error']);
        if (!empty($result['success'])) {
            self::schedule_processing();
        }
    }

    public static function safe_capture_wordpress_published(string $new_status, string $old_status, $post): void {
        try {
            self::capture_wordpress_published($new_status, $old_status, $post);
        } catch (Throwable $e) {
            self::safe_log_capture_error('wordpress_published', $e, [
                'post_id' => $post instanceof WP_Post ? (int) $post->ID : 0,
                'old_status' => $old_status,
                'new_status' => $new_status,
            ]);
        }
    }

    private static function capture_or_defer(array $payload, string $status, string $dependency_key, string $error): array {
        $result = IDG_Traceability_Outbox::insert_event($payload, $status, $dependency_key, $error);
        if (!empty($result['success'])) {
            if (class_exists('IDG_Traceability_Recapture')) {
                IDG_Traceability_Recapture::clear_if_compatible((string) ($payload['idempotency_key'] ?? ''), $payload, $dependency_key);
            }
            self::clear_recapture_failure_marker($payload);
            return $result;
        }
        $reason = sanitize_key((string) ($result['message'] ?? ''));
        if (in_array($reason, ['traceability_schema_incomplete', 'traceability_table_unavailable', 'outbox_insert_failed'], true)) {
            $stored = class_exists('IDG_Traceability_Recapture')
                ? IDG_Traceability_Recapture::record($payload, $status, $dependency_key, $error)
                : false;
            self::schedule_reconciliation();
            $result['recapture_scheduled'] = $stored;
            if ($stored) {
                // La opción individual vuelve a ser la representación durable y
                // la marca alternativa ya no es necesaria.
                self::clear_recapture_failure_marker($payload);
            } else {
                $marked = self::persist_recapture_failure_marker($payload, $status, $dependency_key, $error, $reason);
                $result['operational_error'] = 'recapture_persistence_failed';
                $result['recoverable_marked'] = $marked;
                self::log_recapture_persistence_error($payload, $reason, $marked);
            }
        }
        return $result;
    }

    private static function persist_recapture_failure_marker(array $payload, string $status, string $dependency_key, string $error, string $reason): bool {
        $key = sanitize_text_field((string) ($payload['idempotency_key'] ?? ''));
        $event_type = sanitize_key((string) ($payload['event_type'] ?? ''));
        if ($key === '' || $event_type === '') {
            return false;
        }
        $marker = [
            'version' => 1,
            'idempotency_key' => $key,
            'event_type' => $event_type,
            'status' => sanitize_key($status),
            'dependency_key' => sanitize_text_field($dependency_key),
            'initial_error' => sanitize_key($error),
            'persistence_error' => $reason !== '' ? $reason : 'recapture_persistence_failed',
            'payload' => $payload,
            'marked_at_utc' => self::utc_now_iso(),
        ];
        $marker['marker_hash'] = self::recapture_failure_marker_hash($marker);

        $workflow_id = sanitize_text_field((string) ($payload['workflow_id'] ?? ''));
        if ($event_type === 'gerizim_imported' && $workflow_id !== '') {
            $workflow = IDG_Job_Runner::get_workflow($workflow_id);
            if (empty($workflow)) {
                return false;
            }
            $workflow['traceability_recapture_failure'] = $marker;
            IDG_Job_Runner::save_workflow($workflow_id, $workflow);
            $verified = IDG_Job_Runner::get_workflow($workflow_id);
            $stored = isset($verified['traceability_recapture_failure']) && is_array($verified['traceability_recapture_failure'])
                ? $verified['traceability_recapture_failure']
                : [];
            return self::valid_recapture_failure_marker($stored, $key);
        }

        $post_id = absint($payload['wordpress_post_id'] ?? 0);
        if ($post_id <= 0) {
            return false;
        }
        update_post_meta($post_id, '_idg_traceability_recapture_failure', $marker);
        $stored = get_post_meta($post_id, '_idg_traceability_recapture_failure', true);
        return is_array($stored) && self::valid_recapture_failure_marker($stored, $key);
    }

    private static function clear_recapture_failure_marker(array $payload): void {
        $key = sanitize_text_field((string) ($payload['idempotency_key'] ?? ''));
        if ($key === '') {
            return;
        }
        $event_type = sanitize_key((string) ($payload['event_type'] ?? ''));
        $workflow_id = sanitize_text_field((string) ($payload['workflow_id'] ?? ''));
        if ($event_type === 'gerizim_imported' && $workflow_id !== '') {
            $workflow = IDG_Job_Runner::get_workflow($workflow_id);
            $stored = isset($workflow['traceability_recapture_failure']) && is_array($workflow['traceability_recapture_failure'])
                ? $workflow['traceability_recapture_failure']
                : [];
            if ((string) ($stored['idempotency_key'] ?? '') === $key) {
                unset($workflow['traceability_recapture_failure']);
                IDG_Job_Runner::save_workflow($workflow_id, $workflow);
            }
            return;
        }
        $post_id = absint($payload['wordpress_post_id'] ?? 0);
        if ($post_id > 0) {
            $stored = get_post_meta($post_id, '_idg_traceability_recapture_failure', true);
            if (is_array($stored) && (string) ($stored['idempotency_key'] ?? '') === $key) {
                delete_post_meta($post_id, '_idg_traceability_recapture_failure');
            }
        }
    }

    public static function recover_failure_markers(int $limit = 20): array {
        $result = ['checked' => 0, 'recovered' => 0, 'invalid' => 0, 'has_more' => false];
        if (!self::capture_enabled()) {
            return $result;
        }
        global $wpdb;
        if (!isset($wpdb->postmeta) || !method_exists($wpdb, 'get_results')) {
            return $result;
        }

        $limit = max(1, $limit);
        $cursor = absint(get_option('idg_traceability_recapture_failure_cursor', 0));
        $rows = self::recoverable_failure_marker_db_rows($limit, $cursor);
        if (empty($rows) && $cursor > 0) {
            update_option('idg_traceability_recapture_failure_cursor', 0, false);
            return $result;
        }

        foreach ($rows as $row) {
            $result['checked']++;
            $cursor = max($cursor, absint($row['meta_id'] ?? 0));
            $marker = maybe_unserialize($row['meta_value'] ?? null);
            $key = is_array($marker) ? sanitize_text_field((string) ($marker['idempotency_key'] ?? '')) : '';
            if (!is_array($marker) || $key === '' || !self::valid_recapture_failure_marker($marker, $key)) {
                $result['invalid']++;
                continue;
            }
            $payload = isset($marker['payload']) && is_array($marker['payload']) ? $marker['payload'] : [];
            if (empty($payload)) {
                $result['invalid']++;
                continue;
            }
            $capture = self::capture_or_defer(
                $payload,
                sanitize_key((string) ($marker['status'] ?? 'blocked')),
                sanitize_text_field((string) ($marker['dependency_key'] ?? '')),
                sanitize_key((string) ($marker['initial_error'] ?? ''))
            );
            if (!empty($capture['success']) || !empty($capture['recapture_scheduled'])) {
                self::clear_recapture_failure_marker($payload);
                $result['recovered']++;
            }
        }

        $result['has_more'] = count($rows) >= $limit;
        update_option('idg_traceability_recapture_failure_cursor', $result['has_more'] ? $cursor : 0, false);
        return $result;
    }

    public static function recoverable_failure_marker_count(): int {
        global $wpdb;
        if (!isset($wpdb->postmeta) || !method_exists($wpdb, 'get_var')) {
            return 0;
        }
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key=%s",
            '_idg_traceability_recapture_failure'
        ));
    }

    public static function recoverable_failure_marker_rows(int $limit = 20): array {
        $out = [];
        foreach (self::recoverable_failure_marker_db_rows(max(1, $limit), 0, true) as $row) {
            $marker = maybe_unserialize($row['meta_value'] ?? null);
            if (!is_array($marker)) {
                continue;
            }
            $key = sanitize_text_field((string) ($marker['idempotency_key'] ?? ''));
            $out[] = [
                'post_id' => absint($row['post_id'] ?? 0),
                'event_type' => sanitize_key((string) ($marker['event_type'] ?? '')),
                'idempotency_key' => $key,
                'persistence_error' => sanitize_key((string) ($marker['persistence_error'] ?? 'recapture_persistence_failed')),
                'marked_at_utc' => sanitize_text_field((string) ($marker['marked_at_utc'] ?? '')),
                'valid' => $key !== '' && self::valid_recapture_failure_marker($marker, $key),
            ];
        }
        return $out;
    }

    private static function recoverable_failure_marker_db_rows(int $limit, int $after_meta_id = 0, bool $descending = false): array {
        global $wpdb;
        if (!isset($wpdb->postmeta) || !method_exists($wpdb, 'get_results')) {
            return [];
        }
        $direction = $descending ? 'DESC' : 'ASC';
        return (array) $wpdb->get_results($wpdb->prepare(
            "SELECT meta_id,post_id,meta_value FROM {$wpdb->postmeta} WHERE meta_key=%s AND meta_id>%d ORDER BY meta_id {$direction} LIMIT %d",
            '_idg_traceability_recapture_failure',
            max(0, $after_meta_id),
            max(1, $limit)
        ), defined('ARRAY_A') ? ARRAY_A : 'ARRAY_A');
    }

    private static function valid_recapture_failure_marker(array $marker, string $expected_key): bool {
        $stored_hash = (string) ($marker['marker_hash'] ?? '');
        if ($stored_hash === '' || (string) ($marker['idempotency_key'] ?? '') !== $expected_key) {
            return false;
        }
        unset($marker['marker_hash']);
        return hash_equals($stored_hash, self::recapture_failure_marker_hash($marker));
    }

    private static function recapture_failure_marker_hash(array $marker): string {
        unset($marker['marker_hash']);
        self::sort_marker_value($marker);
        return hash('sha256', (string) wp_json_encode($marker, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private static function sort_marker_value(array &$value): void {
        foreach ($value as &$item) {
            if (is_array($item)) {
                self::sort_marker_value($item);
            }
        }
        unset($item);
        ksort($value);
    }

    private static function log_recapture_persistence_error(array $payload, string $reason, bool $marked): void {
        if (!class_exists('IDG_Logger')) {
            return;
        }
        IDG_Logger::log('traceability_recapture_persistence_error', 'No fue posible persistir la intención de recaptura; se conservó una marca operativa recuperable cuando el almacenamiento alternativo lo permitió.', [
            'traceability_event' => sanitize_key((string) ($payload['event_type'] ?? '')),
            'idempotency_key' => sanitize_text_field((string) ($payload['idempotency_key'] ?? '')),
            'workflow_id' => sanitize_text_field((string) ($payload['workflow_id'] ?? '')),
            'wordpress_post_id' => absint($payload['wordpress_post_id'] ?? 0),
            'reason' => $reason,
            'recoverable_marker_persisted' => $marked ? 'yes' : 'no',
        ]);
    }

    public static function schedule_reconciliation(): void {
        if (function_exists('as_enqueue_async_action')) {
            as_enqueue_async_action(IDG_TRACEABILITY_RECONCILE_HOOK, [], 'ideasdi-gerizim-traceability');
            return;
        }
        if (!wp_next_scheduled(IDG_TRACEABILITY_RECONCILE_HOOK)) {
            wp_schedule_single_event(time() + 30, IDG_TRACEABILITY_RECONCILE_HOOK);
        }
    }

    public static function sync_row_reflection(array $row): bool {
        $payload = json_decode((string) ($row['payload_json'] ?? ''), true);
        if (!is_array($payload)) {
            return false;
        }
        $event_type = sanitize_key((string) ($row['event_type'] ?? $payload['event_type'] ?? ''));
        $status = sanitize_key((string) ($row['status'] ?? ''));
        $key = sanitize_text_field((string) ($row['idempotency_key'] ?? ''));
        $workflow_id = sanitize_text_field((string) ($payload['workflow_id'] ?? ''));
        $post_id = absint($payload['wordpress_post_id'] ?? 0);
        $synced_at = self::utc_now_iso();

        if ($event_type === 'gerizim_imported' && $workflow_id !== '') {
            $workflow = IDG_Job_Runner::get_workflow($workflow_id);
            if (empty($workflow)) {
                return false;
            }
            $occurred_at = sanitize_text_field((string) ($payload['occurred_at'] ?? ''));
            $workflow['traceability_gerizim_imported_key'] = $key;
            $workflow['traceability_gerizim_imported_status'] = $status;
            if ($occurred_at !== '') {
                $workflow['radar_imported_at_utc'] = $occurred_at;
            }
            $workflow['traceability_gerizim_imported_synced_at_utc'] = $synced_at;
            IDG_Job_Runner::save_workflow($workflow_id, $workflow);
            $fresh = IDG_Job_Runner::get_workflow($workflow_id);
            return (string) ($fresh['traceability_gerizim_imported_key'] ?? '') === $key
                && (string) ($fresh['traceability_gerizim_imported_status'] ?? '') === $status
                && (string) ($fresh['traceability_gerizim_imported_synced_at_utc'] ?? '') === $synced_at
                && ($occurred_at === '' || (string) ($fresh['radar_imported_at_utc'] ?? '') === $occurred_at);
        }

        if ($post_id <= 0) {
            return false;
        }
        if ($event_type === 'wordpress_post_created') {
            return self::sync_post_reflection($post_id, '_idg_traceability_post_created_key', '_idg_traceability_post_created_status', '_idg_traceability_post_created_synced_at_utc', $key, $status, $synced_at);
        }
        if ($event_type === 'wordpress_published') {
            $occurred_at = sanitize_text_field((string) ($payload['occurred_at'] ?? ''));
            if ($occurred_at !== '') {
                update_post_meta($post_id, '_idg_published_at_utc', $occurred_at);
                if ((string) get_post_meta($post_id, '_idg_published_at_utc', true) !== $occurred_at) {
                    return false;
                }
            }
            return self::sync_post_reflection($post_id, '_idg_traceability_published_key', '_idg_traceability_published_status', '_idg_traceability_published_synced_at_utc', $key, $status, $synced_at);
        }
        return false;
    }

    private static function sync_post_reflection(int $post_id, string $key_meta, string $status_meta, string $synced_meta, string $key, string $status, string $synced_at): bool {
        update_post_meta($post_id, $key_meta, $key);
        update_post_meta($post_id, $status_meta, $status);
        update_post_meta($post_id, $synced_meta, $synced_at);
        return (string) get_post_meta($post_id, $key_meta, true) === $key
            && (string) get_post_meta($post_id, $status_meta, true) === $status
            && (string) get_post_meta($post_id, $synced_meta, true) === $synced_at;
    }

    public static function process_scheduled_queue(): void {
        if (class_exists('IDG_Traceability_Outbox')) {
            IDG_Traceability_Outbox::process_queue(10);
        }
    }

    public static function run_reconciliation(): void {
        $marker_recovery = self::recover_failure_markers(20);
        $recapture_cursor = absint(get_option('idg_traceability_recapture_cursor', 0));
        $recovered = ['recovered' => 0, 'remaining' => 0, 'has_more' => false, 'last_option_id' => $recapture_cursor];
        if (class_exists('IDG_Traceability_Recapture')) {
            $recovered = IDG_Traceability_Recapture::recover_batch(20, $recapture_cursor);
            update_option('idg_traceability_recapture_cursor', !empty($recovered['has_more']) ? (int) ($recovered['last_option_id'] ?? 0) : 0, false);
        }
        $result = ['has_more' => false];
        if (class_exists('IDG_Traceability_Outbox')) {
            $result = IDG_Traceability_Outbox::reconcile(50);
            if ((int) ($result['dependencies_released'] ?? 0) > 0
                || (int) ($result['cutoff_released'] ?? 0) > 0
                || (int) ($recovered['recovered'] ?? 0) > 0
                || (int) ($marker_recovery['recovered'] ?? 0) > 0) {
                self::schedule_processing();
            }
        }
        if (!empty($marker_recovery['has_more']) || !empty($recovered['has_more']) || !empty($result['has_more'])) {
            self::schedule_reconciliation();
        }
    }

    public static function schedule_processing(): void {
        if (!self::delivery_enabled()) {
            return;
        }
        if (function_exists('as_enqueue_async_action')) {
            as_enqueue_async_action(IDG_TRACEABILITY_ACTION_HOOK, [], 'ideasdi-gerizim-traceability');
            return;
        }
        if (!wp_next_scheduled(IDG_TRACEABILITY_ACTION_HOOK)) {
            wp_schedule_single_event(time() + 5, IDG_TRACEABILITY_ACTION_HOOK);
        }
    }

    public static function schedule_at(int $timestamp): void {
        if (!self::delivery_enabled()) {
            return;
        }
        $timestamp = max(time() + 1, $timestamp);
        if (function_exists('as_schedule_single_action')) {
            as_schedule_single_action($timestamp, IDG_TRACEABILITY_ACTION_HOOK, [], 'ideasdi-gerizim-traceability');
            return;
        }
        wp_schedule_single_event($timestamp, IDG_TRACEABILITY_ACTION_HOOK);
    }

    public static function maybe_schedule(): void {
        if (!self::capture_enabled() && !self::delivery_enabled()) {
            return;
        }
        if (function_exists('as_next_scheduled_action') && function_exists('as_schedule_recurring_action')) {
            if (!as_next_scheduled_action(IDG_TRACEABILITY_RECONCILE_HOOK, [], 'ideasdi-gerizim-traceability')) {
                as_schedule_recurring_action(time() + 300, HOUR_IN_SECONDS, IDG_TRACEABILITY_RECONCILE_HOOK, [], 'ideasdi-gerizim-traceability');
            }
            if (self::delivery_enabled() && !as_next_scheduled_action(IDG_TRACEABILITY_ACTION_HOOK, [], 'ideasdi-gerizim-traceability')) {
                as_schedule_recurring_action(time() + 60, 300, IDG_TRACEABILITY_ACTION_HOOK, [], 'ideasdi-gerizim-traceability');
            }
            return;
        }
        if (!wp_next_scheduled(IDG_TRACEABILITY_RECONCILE_HOOK)) {
            wp_schedule_event(time() + 300, 'hourly', IDG_TRACEABILITY_RECONCILE_HOOK);
        }
        if (self::delivery_enabled() && !wp_next_scheduled(IDG_TRACEABILITY_ACTION_HOOK)) {
            wp_schedule_event(time() + 60, 'hourly', IDG_TRACEABILITY_ACTION_HOOK);
        }
    }

    public static function delivery_configuration_valid(): bool {
        $cutoff_valid = !self::capture_enabled() || self::live_capture_cutoff()['valid'];
        $url = self::config_string('IDG_RADAR_TRACEABILITY_URL');
        $url_valid = self::validate_radar_url($url);
        return !empty($url_valid['valid'])
            && self::config_string('IDG_RADAR_TRACEABILITY_TOKEN') !== ''
            && self::contract_version() === self::DEFAULT_CONTRACT_VERSION
            && $cutoff_valid
            && class_exists('IDG_Traceability_Outbox')
            && IDG_Traceability_Outbox::schema_ready();
    }

    public static function diagnostics(): array {
        $cutoff = self::live_capture_cutoff();
        $url = self::config_string('IDG_RADAR_TRACEABILITY_URL');
        $url_validation = self::validate_radar_url($url);
        $schema = class_exists('IDG_Traceability_Outbox') ? IDG_Traceability_Outbox::schema_status() : ['valid' => false, 'errors' => ['class_unavailable']];
        return [
            'table_available' => class_exists('IDG_Traceability_Outbox') && IDG_Traceability_Outbox::table_exists(),
            'schema_ready' => !empty($schema['valid']),
            'schema_errors' => (array) ($schema['errors'] ?? []),
            'scheduler_available' => function_exists('as_enqueue_async_action') || function_exists('wp_schedule_event'),
            'url_configured' => $url !== '',
            'url_valid' => !empty($url_validation['valid']),
            'url_reason' => (string) ($url_validation['reason'] ?? ''),
            'token_configured' => self::config_string('IDG_RADAR_TRACEABILITY_TOKEN') !== '',
            'cutoff_valid' => $cutoff['valid'],
            'contract_compatible' => self::contract_version() === self::DEFAULT_CONTRACT_VERSION,
            'capture_enabled' => self::capture_enabled(),
            'delivery_enabled' => self::delivery_enabled(),
            'recapture_pending' => class_exists('IDG_Traceability_Recapture') ? IDG_Traceability_Recapture::count() : 0,
            'recapture_conflicts' => class_exists('IDG_Traceability_Recapture') ? IDG_Traceability_Recapture::conflict_count() : 0,
            'recoverable_failure_markers' => self::recoverable_failure_marker_count(),
        ];
    }

    private static function eligible_radar_workflow(array $workflow): bool {
        return self::is_radar_workflow_base($workflow, true);
    }

    private static function is_radar_workflow_base(array $workflow, bool $require_import_mark): bool {
        $workflow_id = sanitize_text_field((string) ($workflow['workflow_id'] ?? ''));
        if ((string) ($workflow['radar_source'] ?? '') !== 'radar-editorial-ideasdi') {
            return false;
        }
        $brief_id = absint($workflow['radar_brief_id'] ?? 0);
        if ($brief_id <= 0 || !preg_match('/^idg_[A-Za-z0-9-]{8,80}$/', $workflow_id)) {
            return false;
        }
        if (self::is_recurring_workflow($workflow)) {
            return false;
        }
        if ($require_import_mark) {
            $expected_identity = self::radar_import_identity($brief_id, $workflow_id);
            if (empty($workflow['radar_import_persisted'])
                || (string) ($workflow['radar_import_identity'] ?? '') !== $expected_identity) {
                return false;
            }
        }
        return true;
    }

    private static function is_recurring_workflow(array $workflow): bool {
        return (string) ($workflow['workflow_origin'] ?? '') === 'recurring_update'
            || !empty($workflow['recurring_target_post_id'])
            || (string) ($workflow['wordpress_content_type'] ?? '') === 'Evento';
    }

    private static function is_historical_identity(int $brief_id, int $post_id, string $workflow_id): bool {
        return $brief_id === self::HISTORICAL_BRIEF_ID
            || $post_id === self::HISTORICAL_POST_ID
            || $workflow_id === self::HISTORICAL_WORKFLOW_ID;
    }

    private static function capture_status_for_date(string $occurred_at, string $invalid_error = 'invalid_occurred_at'): array {
        if (self::contract_version() !== self::DEFAULT_CONTRACT_VERSION) {
            return ['status' => 'blocked', 'error' => 'incompatible_contract', 'skip' => false, 'reason' => ''];
        }
        $cutoff = self::live_capture_cutoff();
        if (!$cutoff['valid']) {
            return ['status' => 'blocked', 'error' => 'invalid_live_capture_cutoff', 'skip' => false, 'reason' => ''];
        }
        $occurred = self::iso_to_timestamp($occurred_at);
        if ($occurred <= 0) {
            return ['status' => 'blocked', 'error' => $invalid_error, 'skip' => false, 'reason' => ''];
        }
        if ($occurred < $cutoff['timestamp']) {
            return ['status' => 'blocked', 'error' => 'historical_before_live_capture_cutoff', 'skip' => true, 'reason' => 'historical_before_live_capture_cutoff'];
        }
        return ['status' => 'queued', 'error' => '', 'skip' => false, 'reason' => ''];
    }

    private static function initial_status(string $occurred_at, string $dependency_key): array {
        if ($occurred_at === '') {
            return ['status' => 'blocked', 'error' => 'invalid_occurred_at', 'skip' => false];
        }
        $cutoff_state = self::capture_status_for_date($occurred_at);
        if ($cutoff_state['skip']) {
            return ['status' => 'blocked', 'error' => $cutoff_state['reason'], 'skip' => true];
        }
        if ($cutoff_state['status'] === 'blocked') {
            return ['status' => 'blocked', 'error' => $cutoff_state['error'], 'skip' => false];
        }
        $parent = IDG_Traceability_Outbox::get_by_key($dependency_key);
        if (!$parent) {
            return ['status' => 'blocked', 'error' => 'missing_dependency', 'skip' => false];
        }
        if (in_array((string) ($parent['status'] ?? ''), ['failed', 'blocked'], true)) {
            return ['status' => 'blocked', 'error' => 'dependency_not_sent', 'skip' => false];
        }
        return ['status' => 'queued', 'error' => '', 'skip' => false];
    }

    private static function build_import_payload(int $brief_id, string $workflow_id, string $occurred_at, string $observed_at): array {
        return [
            'event_type' => 'gerizim_imported',
            'brief_id' => $brief_id,
            'occurred_at' => $occurred_at,
            'observed_at' => $observed_at,
            'source_system' => 'gerizim-wordpress',
            'source_record_id' => $workflow_id,
            'workflow_id' => $workflow_id,
            'wordpress_post_id' => null,
            'wordpress_status' => null,
            'idempotency_key' => 'gerizim_imported:' . $brief_id . ':' . $workflow_id,
            'evidence_payload' => ['contract_version' => self::contract_version()],
            'actor' => 'ideasdi-redaccion-gerizim',
        ];
    }

    private static function radar_import_identity(int $brief_id, string $workflow_id): string {
        return hash('sha256', 'radar-editorial-ideasdi|' . $brief_id . '|' . $workflow_id);
    }

    private static function normalize_utc_iso(string $value): string {
        try {
            return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\\TH:i:s\\Z');
        } catch (Throwable $e) {
            return '';
        }
    }

    private static function post_created_at_utc(WP_Post $post): string {
        $date = get_post_datetime($post, 'date', 'gmt');
        if ($date instanceof DateTimeInterface && $date->getTimestamp() > 0) {
            return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
        }

        $local = trim((string) $post->post_date);
        if ($local === '' || str_starts_with($local, '0000-00-00')) {
            return '';
        }
        try {
            $fallback = new DateTimeImmutable($local, wp_timezone());
            if ($fallback->getTimestamp() <= 0) {
                return '';
            }
            return $fallback->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
        } catch (Throwable $e) {
            return '';
        }
    }

    private static function safe_log_capture_error(string $event, Throwable $error, array $context = []): void {
        if (!class_exists('IDG_Logger')) {
            return;
        }
        $context['traceability_event'] = $event;
        IDG_Logger::log('traceability_capture_error', 'La captura de trazabilidad falló sin afectar el flujo editorial: ' . $error->getMessage(), $context);
    }

    private static function utc_now_iso(): string {
        return gmdate('Y-m-d\TH:i:s\Z');
    }
}
