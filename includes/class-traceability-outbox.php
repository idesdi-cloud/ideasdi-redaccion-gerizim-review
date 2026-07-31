<?php
if (!defined('ABSPATH')) {
    exit;
}

final class IDG_Traceability_Outbox {
    private const MAX_ATTEMPTS = 8;
    private const STALE_LOCK_SECONDS = 900;
    private const DEFAULT_BATCH = 50;

    public static function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'idg_traceability_outbox';
    }

    public static function install_or_upgrade(): array {
        global $wpdb;
        $installed = (string) get_option('idg_traceability_db_version', '');
        $current = self::schema_status();
        if ($installed === IDG_TRACEABILITY_DB_VERSION && !empty($current['valid'])) {
            return $current;
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table_name();
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            idempotency_key varchar(191) NOT NULL,
            event_type varchar(64) NOT NULL,
            payload_json longtext NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'queued',
            attempts smallint(5) unsigned NOT NULL DEFAULT 0,
            next_attempt_at datetime NULL,
            last_http_status smallint(5) unsigned NULL,
            last_error varchar(500) NOT NULL DEFAULT '',
            last_transport_error varchar(500) NOT NULL DEFAULT '',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            sent_at datetime NULL,
            dependency_key varchar(191) NOT NULL DEFAULT '',
            lock_token varchar(64) NOT NULL DEFAULT '',
            locked_at datetime NULL,
            reflection_synced_at datetime NULL,
            reflection_last_error varchar(500) NOT NULL DEFAULT '',
            reflection_attempts smallint(5) unsigned NOT NULL DEFAULT 0,
            reflection_last_attempt_at datetime NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY idempotency_key (idempotency_key),
            KEY status_next_attempt (status,next_attempt_at,id),
            KEY dependency_key (dependency_key),
            KEY locked_at (locked_at),
            KEY reflection_sync (reflection_synced_at,updated_at,id)
        ) {$charset};";
        if (property_exists($wpdb, 'last_error')) {
            $wpdb->last_error = '';
        }
        $dbdelta_result = dbDelta($sql);
        $dbdelta_error = property_exists($wpdb, 'last_error') ? trim((string) $wpdb->last_error) : '';
        $status = self::schema_status($dbdelta_error);
        $status['dbdelta_result'] = is_array($dbdelta_result) ? $dbdelta_result : [];
        if (!empty($status['valid'])) {
            $write_test = self::verify_write_read_delete();
            if (empty($write_test['valid'])) {
                $status['valid'] = false;
                $status['errors'][] = (string) ($write_test['error'] ?? 'schema_write_test_failed');
            }
        }
        if (!empty($status['valid'])) {
            update_option('idg_traceability_db_version', IDG_TRACEABILITY_DB_VERSION, false);
            if ((string) get_option('idg_traceability_db_version', '') !== IDG_TRACEABILITY_DB_VERSION) {
                $status['valid'] = false;
                $status['errors'][] = 'schema_version_persistence_failed';
            }
        }
        $status['errors'] = array_values(array_unique((array) ($status['errors'] ?? [])));
        return $status;
    }

    public static function schema_ready(): bool {
        $status = self::schema_status();
        return !empty($status['valid']);
    }

    public static function schema_status(string $migration_error = ''): array {
        global $wpdb;
        $errors = [];
        if ($migration_error !== '') {
            $errors[] = 'dbdelta_error';
        }
        if (!self::table_exists()) {
            return ['valid' => false, 'errors' => array_merge($errors, ['table_missing']), 'columns' => [], 'unique_index' => false, 'db_error' => self::clean_error($migration_error)];
        }
        $table = self::table_name();
        if (property_exists($wpdb, 'last_error')) {
            $wpdb->last_error = '';
        }
        $column_rows = $wpdb->get_results("SHOW COLUMNS FROM {$table}", ARRAY_A);
        $column_error = property_exists($wpdb, 'last_error') ? trim((string) $wpdb->last_error) : '';
        $columns = [];
        $column_meta = [];
        foreach ((array) $column_rows as $row) {
            $field = (string) ($row['Field'] ?? $row['field'] ?? '');
            if ($field !== '') {
                $columns[] = $field;
                $column_meta[$field] = [
                    'type' => strtolower((string) ($row['Type'] ?? $row['type'] ?? '')),
                    'null' => strtoupper((string) ($row['Null'] ?? $row['null'] ?? 'YES')),
                ];
            }
        }
        $required_types = [
            'id' => 'bigint', 'idempotency_key' => 'varchar', 'event_type' => 'varchar',
            'payload_json' => 'longtext', 'status' => 'varchar', 'attempts' => 'smallint',
            'next_attempt_at' => 'datetime', 'last_http_status' => 'smallint',
            'last_error' => 'varchar', 'last_transport_error' => 'varchar',
            'created_at' => 'datetime', 'updated_at' => 'datetime', 'sent_at' => 'datetime',
            'dependency_key' => 'varchar', 'lock_token' => 'varchar', 'locked_at' => 'datetime',
            'reflection_synced_at' => 'datetime', 'reflection_last_error' => 'varchar',
            'reflection_attempts' => 'smallint', 'reflection_last_attempt_at' => 'datetime',
        ];
        $missing = array_values(array_diff(array_keys($required_types), $columns));
        if (!empty($missing)) {
            $errors[] = 'missing_columns:' . implode(',', $missing);
        }
        foreach ($required_types as $column => $expected_type) {
            if (!isset($column_meta[$column])) {
                continue;
            }
            if (!str_starts_with((string) $column_meta[$column]['type'], $expected_type)) {
                $errors[] = 'incompatible_column_type:' . $column;
            }
        }
        foreach (['id', 'idempotency_key', 'event_type', 'payload_json', 'status', 'attempts', 'created_at', 'updated_at', 'dependency_key', 'lock_token'] as $not_null) {
            if (isset($column_meta[$not_null]) && $column_meta[$not_null]['null'] !== 'NO') {
                $errors[] = 'incompatible_column_nullability:' . $not_null;
            }
        }
        if ($column_error !== '') {
            $errors[] = 'column_inspection_error';
        }

        if (property_exists($wpdb, 'last_error')) {
            $wpdb->last_error = '';
        }
        $index_rows = $wpdb->get_results("SHOW INDEX FROM {$table}", ARRAY_A);
        $index_error = property_exists($wpdb, 'last_error') ? trim((string) $wpdb->last_error) : '';
        $indexes = [];
        foreach ((array) $index_rows as $row) {
            $name = (string) ($row['Key_name'] ?? $row['key_name'] ?? '');
            if ($name === '') {
                continue;
            }
            $seq = (int) ($row['Seq_in_index'] ?? $row['seq_in_index'] ?? 1);
            $indexes[$name]['unique'] = (int) ($row['Non_unique'] ?? $row['non_unique'] ?? 1) === 0;
            $indexes[$name]['columns'][$seq] = (string) ($row['Column_name'] ?? $row['column_name'] ?? '');
        }
        foreach ($indexes as &$index) {
            ksort($index['columns']);
            $index['columns'] = array_values($index['columns']);
        }
        unset($index);
        $unique = false;
        foreach ($indexes as $index) {
            if (!empty($index['unique']) && ($index['columns'] ?? []) === ['idempotency_key']) {
                $unique = true;
                break;
            }
        }
        if (!$unique) {
            $errors[] = 'missing_unique_idempotency_index';
        }
        $required_indexes = [
            ['status', 'next_attempt_at', 'id'],
            ['dependency_key'],
            ['locked_at'],
            ['reflection_synced_at', 'updated_at', 'id'],
        ];
        foreach ($required_indexes as $required) {
            $found = false;
            foreach ($indexes as $index) {
                if (array_slice((array) ($index['columns'] ?? []), 0, count($required)) === $required) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $errors[] = 'missing_operational_index:' . implode('_', $required);
            }
        }
        if ($index_error !== '') {
            $errors[] = 'index_inspection_error';
        }
        return [
            'valid' => empty($errors),
            'errors' => array_values(array_unique($errors)),
            'columns' => $columns,
            'column_meta' => $column_meta,
            'unique_index' => $unique,
            'indexes' => $indexes,
            'db_error' => self::clean_error($migration_error !== '' ? $migration_error : ($column_error !== '' ? $column_error : $index_error)),
        ];
    }

    private static function verify_write_read_delete(): array {
        global $wpdb;
        $table = self::table_name();
        $key = '__idg_schema_test__:' . substr(hash('sha256', uniqid('', true)), 0, 32);
        $now = self::db_now();
        if (property_exists($wpdb, 'last_error')) {
            $wpdb->last_error = '';
        }
        $inserted = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (idempotency_key,event_type,payload_json,status,attempts,created_at,updated_at,dependency_key,lock_token,reflection_last_error,reflection_attempts) VALUES (%s,'schema_test','{}','blocked',0,%s,%s,'','', '',0)",
            $key,
            $now,
            $now
        ));
        $insert_error = property_exists($wpdb, 'last_error') ? trim((string) $wpdb->last_error) : '';
        if ($inserted !== 1 || $insert_error !== '') {
            return ['valid' => false, 'error' => 'schema_write_test_failed'];
        }
        if (property_exists($wpdb, 'last_error')) {
            $wpdb->last_error = '';
        }
        $found = (string) $wpdb->get_var($wpdb->prepare("SELECT idempotency_key FROM {$table} WHERE idempotency_key=%s LIMIT 1", $key));
        $read_error = property_exists($wpdb, 'last_error') ? trim((string) $wpdb->last_error) : '';
        if (property_exists($wpdb, 'last_error')) {
            $wpdb->last_error = '';
        }
        $deleted = $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE idempotency_key=%s", $key));
        $delete_error = property_exists($wpdb, 'last_error') ? trim((string) $wpdb->last_error) : '';
        return [
            'valid' => $found === $key && $deleted === 1 && $read_error === '' && $delete_error === '',
            'error' => $found !== $key ? 'schema_read_test_failed' : (($deleted !== 1 || $delete_error !== '') ? 'schema_cleanup_test_failed' : ''),
        ];
    }

    public static function table_exists(): bool {
        global $wpdb;
        $table = self::table_name();
        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        return (string) $found === $table;
    }

    public static function insert_event(array $payload, string $status = 'queued', string $dependency_key = '', string $error = ''): array {
        global $wpdb;
        if (!self::schema_ready()) {
            return ['success' => false, 'message' => 'traceability_schema_incomplete'];
        }

        $key = sanitize_text_field((string) ($payload['idempotency_key'] ?? ''));
        $event_type = sanitize_key((string) ($payload['event_type'] ?? ''));
        if ($key === '' || $event_type === '') {
            return ['success' => false, 'message' => 'invalid_event_identity'];
        }
        if (class_exists('IDG_Traceability') && method_exists('IDG_Traceability', 'validate_event_payload')) {
            $validation = IDG_Traceability::validate_event_payload($payload, $status, $error);
            if (empty($validation['valid'])) {
                return ['success' => false, 'message' => sanitize_key((string) ($validation['reason'] ?? 'invalid_event_payload'))];
            }
        }

        $allowed = ['queued', 'sending', 'retry', 'failed', 'sent', 'blocked'];
        $status = in_array($status, $allowed, true) ? $status : 'blocked';
        $json = wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($json) || $json === '') {
            return ['success' => false, 'message' => 'payload_encoding_failed'];
        }
        $now = self::db_now();
        $sql = $wpdb->prepare(
            "INSERT INTO " . self::table_name() . "
            (idempotency_key,event_type,payload_json,status,attempts,next_attempt_at,last_http_status,last_error,last_transport_error,created_at,updated_at,sent_at,dependency_key,lock_token,locked_at,reflection_synced_at,reflection_last_error,reflection_attempts,reflection_last_attempt_at)
            VALUES (%s,%s,%s,%s,0,%s,NULL,%s,'',%s,%s,NULL,%s,'',NULL,NULL,'',0,NULL)
            ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)",
            $key,
            $event_type,
            $json,
            $status,
            $now,
            self::clean_error($error),
            $now,
            $now,
            sanitize_text_field($dependency_key)
        );
        $result = $wpdb->query($sql);
        if ($result === false) {
            return ['success' => false, 'message' => 'outbox_insert_failed'];
        }

        $row = self::get_by_key($key);
        if ($row && !self::same_event_identity((string) ($row['payload_json'] ?? ''), $payload)) {
            self::sync_and_mark($row);
            return ['success' => false, 'message' => 'idempotency_payload_conflict', 'row' => $row, 'inserted' => false];
        }
        if ($row) {
            self::sync_and_mark($row);
        }
        return ['success' => true, 'row' => $row, 'inserted' => $result === 1];
    }

    public static function get_by_key(string $key): ?array {
        global $wpdb;
        if (!self::table_exists()) {
            return null;
        }
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table_name() . ' WHERE idempotency_key = %s LIMIT 1', $key), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    public static function get_by_id(int $id): ?array {
        global $wpdb;
        if (!self::table_exists()) {
            return null;
        }
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table_name() . ' WHERE id = %d LIMIT 1', $id), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    public static function counts(): array {
        global $wpdb;
        $counts = array_fill_keys(['queued', 'retry', 'failed', 'blocked', 'sent', 'sending'], 0);
        if (!self::table_exists()) {
            return $counts;
        }
        $rows = $wpdb->get_results('SELECT status, COUNT(*) AS total FROM ' . self::table_name() . ' GROUP BY status', ARRAY_A);
        foreach ((array) $rows as $row) {
            $status = (string) ($row['status'] ?? '');
            if (array_key_exists($status, $counts)) {
                $counts[$status] = (int) ($row['total'] ?? 0);
            }
        }
        return $counts;
    }

    public static function latest_summary(): array {
        global $wpdb;
        if (!self::table_exists()) {
            return ['last_sent_at' => '', 'last_error' => ''];
        }
        $sent = (string) $wpdb->get_var('SELECT MAX(sent_at) FROM ' . self::table_name());
        $error = $wpdb->get_var("SELECT last_error FROM " . self::table_name() . " WHERE last_error <> '' ORDER BY updated_at DESC LIMIT 1");
        return ['last_sent_at' => $sent, 'last_error' => self::clean_error((string) $error)];
    }

    public static function problematic_rows(int $limit = 50): array {
        global $wpdb;
        if (!self::schema_ready()) {
            return [];
        }
        return (array) $wpdb->get_results($wpdb->prepare(
            "SELECT id,idempotency_key,event_type,status,attempts,last_http_status,last_error,last_transport_error,reflection_last_error,reflection_attempts,updated_at
             FROM " . self::table_name() . " WHERE status IN ('failed','blocked') ORDER BY updated_at DESC,id DESC LIMIT %d",
            max(1, $limit)
        ), ARRAY_A);
    }

    public static function process_queue(int $limit = 10): array {
        $summary = [
            'processed' => 0,
            'sent' => 0,
            'retry' => 0,
            'failed' => 0,
            'blocked' => 0,
            'candidate_ids' => [],
            'candidate_count' => 0,
            'claim_success_ids' => [],
            'claim_failed_ids' => [],
            'claim_failure_reasons' => [],
            'early_exit_reason' => '',
            'sql_error' => '',
        ];
        if (!self::schema_ready()) {
            $summary['early_exit_reason'] = 'schema_not_ready';
            return $summary;
        }
        self::recover_stale_sending();
        $pre_reconcile = self::reconcile(self::DEFAULT_BATCH);
        if (!empty($pre_reconcile['has_more'])) {
            IDG_Traceability::schedule_reconciliation();
        }
        if (!IDG_Traceability::delivery_enabled()) {
            $summary['early_exit_reason'] = 'delivery_disabled';
            return $summary;
        }
        if (!IDG_Traceability::delivery_configuration_valid()) {
            $summary['early_exit_reason'] = 'delivery_configuration_invalid';
            return $summary;
        }

        global $wpdb;
        $now = self::db_now();
        $table = self::table_name();
        if (property_exists($wpdb, 'last_error')) {
            $wpdb->last_error = '';
        }
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT child.id FROM {$table} child
             LEFT JOIN {$table} parent ON parent.idempotency_key = child.dependency_key
             WHERE child.status IN ('queued','retry')
               AND (child.next_attempt_at IS NULL OR child.next_attempt_at <= %s)
               AND (child.lock_token='' OR child.lock_token IS NULL)
               AND child.locked_at IS NULL
               AND (child.dependency_key='' OR parent.status='sent')
             ORDER BY child.id ASC LIMIT %d",
            $now,
            max(1, $limit)
        ));
        $summary['sql_error'] = property_exists($wpdb, 'last_error')
            ? self::clean_error((string) $wpdb->last_error)
            : '';
        $summary['candidate_ids'] = array_values(array_map('intval', (array) $ids));
        $summary['candidate_count'] = count($summary['candidate_ids']);
        if ($summary['sql_error'] !== '') {
            $summary['early_exit_reason'] = 'sql_selection_error';
            return $summary;
        }
        if ($summary['candidate_count'] === 0) {
            $summary['early_exit_reason'] = 'no_candidates';
            return $summary;
        }

        $claim_verification_failed = false;
        foreach ($summary['candidate_ids'] as $id) {
            $row = self::get_by_id($id);
            if (!$row) {
                $summary['claim_failed_ids'][] = $id;
                $summary['claim_failure_reasons'][$id] = 'claimed_row_not_found';
                continue;
            }
            $dependency = self::dependency_state($row);
            if ($dependency === 'wait') {
                continue;
            }
            if ($dependency === 'missing') {
                if (self::set_blocked($row, 'missing_dependency')) {
                    $summary['blocked']++;
                }
                continue;
            }
            if ($dependency === 'not_sent') {
                if (self::set_blocked($row, 'dependency_not_sent')) {
                    $summary['blocked']++;
                }
                continue;
            }

            $token = wp_generate_password(48, false, false);
            if (!self::claim($id, $token)) {
                $summary['claim_failed_ids'][] = $id;
                $summary['claim_failure_reasons'][$id] = 'claim_update_failed';
                continue;
            }
            $claimed = self::get_by_id($id);
            if (!$claimed) {
                $summary['claim_failed_ids'][] = $id;
                $summary['claim_failure_reasons'][$id] = 'claimed_row_not_found';
                $claim_verification_failed = true;
                continue;
            }
            if (!hash_equals((string) ($claimed['lock_token'] ?? ''), $token)) {
                $summary['claim_failed_ids'][] = $id;
                $summary['claim_failure_reasons'][$id] = 'lock_token_mismatch';
                $claim_verification_failed = true;
                continue;
            }
            $summary['claim_success_ids'][] = $id;
            $payload = json_decode((string) ($claimed['payload_json'] ?? ''), true);
            if (!is_array($payload)) {
                if (self::finish_blocked($claimed, $token, 'invalid_payload_json')) {
                    $summary['blocked']++;
                }
                continue;
            }

            try {
                $result = (new IDG_Traceability_Client())->send($payload);
            } catch (Throwable $e) {
                $result = ['success' => false, 'retry' => true, 'http_status' => 0, 'code' => 'transport_exception', 'message' => 'Excepción temporal durante la entrega de trazabilidad.'];
            }
            $attempt = (int) ($claimed['attempts'] ?? 0) + 1;
            $summary['processed']++;
            if (!empty($result['success'])) {
                if (self::finish_sent($claimed, $token, $attempt, (int) ($result['http_status'] ?? 0))) {
                    $summary['sent']++;
                }
                continue;
            }

            $code = sanitize_key((string) ($result['code'] ?? 'delivery_failed'));
            $transport_error = (string) ($result['message'] ?? '');
            if (!empty($result['retry']) && $attempt < self::MAX_ATTEMPTS) {
                $delay = self::retry_delay_after_attempt($attempt);
                if (self::finish_retry($claimed, $token, $attempt, (int) ($result['http_status'] ?? 0), $code, $transport_error, $delay)) {
                    $summary['retry']++;
                }
                continue;
            }
            $final_error = !empty($result['retry']) ? 'retry_limit_reached' : ($code !== '' ? $code : 'delivery_failed');
            if (self::finish_failed($claimed, $token, (int) ($result['http_status'] ?? 0), $final_error, $transport_error, $attempt)) {
                $summary['failed']++;
            }
        }

        $reconciled = self::reconcile(self::DEFAULT_BATCH);
        if ((int) ($reconciled['dependencies_released'] ?? 0) > 0 || (int) ($reconciled['cutoff_released'] ?? 0) > 0) {
            IDG_Traceability::schedule_processing();
        }
        if (!empty($reconciled['has_more'])) {
            IDG_Traceability::schedule_reconciliation();
        }
        if ($claim_verification_failed) {
            $summary['early_exit_reason'] = 'claim_verification_failed';
        } elseif ($summary['candidate_count'] > 0 && count($summary['claim_success_ids']) === 0) {
            $summary['early_exit_reason'] = 'candidates_not_claimed';
        } else {
            $summary['early_exit_reason'] = 'completed';
        }
        return $summary;
    }

    public static function retry_temporary_events(): int {
        self::recover_stale_sending();
        global $wpdb;
        if (!self::schema_ready()) {
            return 0;
        }
        $now = self::db_now();
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE " . self::table_name() . " SET status='retry', next_attempt_at=%s, updated_at=%s, lock_token='', locked_at=NULL, reflection_synced_at=NULL WHERE status='retry' AND (lock_token='' OR lock_token IS NULL) AND locked_at IS NULL",
            $now,
            $now
        ));
        return max(0, (int) $updated);
    }

    public static function reactivate_reviewed_event(int $id): array {
        $row = self::get_by_id($id);
        if (!$row || !in_array((string) ($row['status'] ?? ''), ['failed', 'blocked'], true)) {
            return ['success' => false, 'reason' => 'event_not_reactivatable'];
        }
        if ((string) ($row['lock_token'] ?? '') !== '' || !empty($row['locked_at'])) {
            return ['success' => false, 'reason' => 'event_locked'];
        }
        $payload = json_decode((string) ($row['payload_json'] ?? ''), true);
        if (!is_array($payload)) {
            return ['success' => false, 'reason' => 'invalid_payload_json'];
        }
        $validation = IDG_Traceability::validate_event_payload($payload, 'queued', '');
        if (empty($validation['valid'])) {
            return ['success' => false, 'reason' => (string) ($validation['reason'] ?? 'invalid_event_payload')];
        }
        if (IDG_Traceability::delivery_enabled() && !IDG_Traceability::delivery_configuration_valid()) {
            return ['success' => false, 'reason' => 'delivery_configuration_invalid'];
        }
        $previous_error = (string) ($row['last_error'] ?? '');
        $occurred = (string) ($payload['occurred_at'] ?? '');
        $cutoff_state = self::releasable_cutoff_state($occurred);
        if ($cutoff_state !== 'ready') {
            self::set_blocked($row, $cutoff_state === 'historical' ? 'historical_before_live_capture_cutoff' : 'invalid_live_capture_cutoff');
            return ['success' => false, 'reason' => $cutoff_state];
        }
        $dependency = self::dependency_state($row);
        if ($dependency !== 'ready') {
            self::set_blocked($row, $dependency === 'missing' ? 'missing_dependency' : 'dependency_not_sent');
            return ['success' => false, 'reason' => $dependency];
        }
        global $wpdb;
        $now = self::db_now();
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE " . self::table_name() . " SET status='queued', attempts=0, next_attempt_at=%s, last_error='', last_transport_error='', updated_at=%s, reflection_synced_at=NULL
             WHERE id=%d AND status IN ('failed','blocked') AND (lock_token='' OR lock_token IS NULL) AND locked_at IS NULL",
            $now,
            $now,
            $id
        ));
        $fresh = self::get_by_id($id);
        if ($fresh) {
            self::sync_and_mark($fresh);
        }
        return ['success' => (int) $updated === 1, 'reason' => (int) $updated === 1 ? '' : 'reactivation_race', 'previous_error' => $previous_error];
    }

    public static function reconcile(int $limit = self::DEFAULT_BATCH): array {
        $result = [
            'dependencies_blocked' => 0,
            'dependencies_released' => 0,
            'cutoff_released' => 0,
            'reflections' => 0,
            'reflection_failures' => 0,
            'has_more' => false,
        ];
        if (!self::schema_ready()) {
            return $result;
        }
        global $wpdb;
        $limit = max(1, $limit);
        $table = self::table_name();

        $dependency_cursor = absint(get_option('idg_traceability_reconcile_dependency_cursor', 0));
        $children = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id>%d AND dependency_key<>'' AND status IN ('queued','retry','blocked') ORDER BY id ASC LIMIT %d",
            $dependency_cursor,
            $limit
        ), ARRAY_A);
        if (empty($children) && $dependency_cursor > 0) {
            update_option('idg_traceability_reconcile_dependency_cursor', 0, false);
        } else {
            foreach ((array) $children as $row) {
                $dependency_cursor = max($dependency_cursor, (int) ($row['id'] ?? 0));
                $parent = self::get_by_key((string) ($row['dependency_key'] ?? ''));
                $status = (string) ($row['status'] ?? '');
                $error = (string) ($row['last_error'] ?? '');
                if (!$parent) {
                    if (($status !== 'blocked' || $error !== 'missing_dependency') && self::set_blocked($row, 'missing_dependency')) {
                        $result['dependencies_blocked']++;
                    }
                    continue;
                }
                $parent_status = (string) ($parent['status'] ?? '');
                if (in_array($parent_status, ['failed', 'blocked'], true)) {
                    if (($status !== 'blocked' || $error !== 'dependency_not_sent') && self::set_blocked($row, 'dependency_not_sent')) {
                        $result['dependencies_blocked']++;
                    }
                    continue;
                }
                if ($parent_status === 'sent' && $status === 'blocked' && in_array($error, ['dependency_not_sent', 'missing_dependency'], true)) {
                    $cutoff_state = self::releasable_cutoff_state(self::payload_occurred_at($row));
                    if ($cutoff_state === 'ready' && self::set_queued($row)) {
                        $result['dependencies_released']++;
                    } elseif ($cutoff_state === 'invalid') {
                        self::set_blocked($row, 'invalid_live_capture_cutoff');
                    } elseif ($cutoff_state === 'historical') {
                        self::set_blocked($row, 'historical_before_live_capture_cutoff');
                    }
                }
            }
            update_option('idg_traceability_reconcile_dependency_cursor', count($children) >= $limit ? $dependency_cursor : 0, false);
            $result['has_more'] = $result['has_more'] || count($children) >= $limit;
        }

        $cutoff = IDG_Traceability::live_capture_cutoff();
        if (!empty($cutoff['valid'])) {
            $cutoff_cursor = absint(get_option('idg_traceability_reconcile_cutoff_cursor', 0));
            $blocked = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table} WHERE id>%d AND status='blocked' AND last_error=%s AND (lock_token='' OR lock_token IS NULL) AND locked_at IS NULL ORDER BY id ASC LIMIT %d",
                $cutoff_cursor,
                'invalid_live_capture_cutoff',
                $limit
            ), ARRAY_A);
            if (empty($blocked) && $cutoff_cursor > 0) {
                update_option('idg_traceability_reconcile_cutoff_cursor', 0, false);
            } else {
                foreach ((array) $blocked as $row) {
                    $cutoff_cursor = max($cutoff_cursor, (int) ($row['id'] ?? 0));
                    $occurred_timestamp = IDG_Traceability::iso_to_timestamp(self::payload_occurred_at($row));
                    if ($occurred_timestamp >= (int) $cutoff['timestamp'] && self::dependency_state($row) === 'ready') {
                        if (self::set_queued($row)) {
                            $result['cutoff_released']++;
                        }
                    } elseif ($occurred_timestamp > 0 && $occurred_timestamp < (int) $cutoff['timestamp']) {
                        self::set_blocked($row, 'historical_before_live_capture_cutoff');
                    }
                }
                update_option('idg_traceability_reconcile_cutoff_cursor', count($blocked) >= $limit ? $cutoff_cursor : 0, false);
                $result['has_more'] = $result['has_more'] || count($blocked) >= $limit;
            }
        }

        $reflection_cursor = absint(get_option('idg_traceability_reconcile_reflection_cursor', 0));
        $reflection_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id>%d
             AND (reflection_synced_at IS NULL OR reflection_synced_at < updated_at)
             AND NOT (status='sending' AND (lock_token<>'' OR locked_at IS NOT NULL))
             ORDER BY id ASC LIMIT %d",
            $reflection_cursor,
            $limit
        ), ARRAY_A);
        if (empty($reflection_rows) && $reflection_cursor > 0) {
            update_option('idg_traceability_reconcile_reflection_cursor', 0, false);
        } else {
            foreach ((array) $reflection_rows as $row) {
                $reflection_cursor = max($reflection_cursor, (int) ($row['id'] ?? 0));
                if (self::sync_and_mark($row)) {
                    $result['reflections']++;
                } else {
                    $result['reflection_failures']++;
                }
            }
            update_option('idg_traceability_reconcile_reflection_cursor', count($reflection_rows) >= $limit ? $reflection_cursor : 0, false);
            $result['has_more'] = $result['has_more'] || count($reflection_rows) >= $limit;
        }
        return $result;
    }

    private static function payload_occurred_at(array $row): string {
        $payload = json_decode((string) ($row['payload_json'] ?? ''), true);
        return is_array($payload) ? (string) ($payload['occurred_at'] ?? '') : '';
    }

    public static function recover_stale_sending(): int {
        global $wpdb;
        if (!self::schema_ready()) {
            return 0;
        }
        $threshold = gmdate('Y-m-d H:i:s', time() - self::STALE_LOCK_SECONDS);
        $now = self::db_now();
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE " . self::table_name() . " SET status='retry', next_attempt_at=%s, updated_at=%s, lock_token='', locked_at=NULL, last_error=%s, reflection_synced_at=NULL
             WHERE status='sending' AND locked_at IS NOT NULL AND locked_at < %s",
            $now,
            $now,
            'stale_sending_recovered',
            $threshold
        ));
        return max(0, (int) $updated);
    }

    public static function retry_delay_after_attempt(int $attempt): int {
        $delays = [1 => 60, 2 => 300, 3 => 900, 4 => 3600, 5 => 10800, 6 => 21600, 7 => 43200];
        return $delays[$attempt] ?? 0;
    }

    private static function dependency_state(array $row): string {
        $dependency_key = (string) ($row['dependency_key'] ?? '');
        if ($dependency_key === '') {
            return 'ready';
        }
        $parent = self::get_by_key($dependency_key);
        if (!$parent) {
            return 'missing';
        }
        $status = (string) ($parent['status'] ?? '');
        if ($status === 'sent') {
            return 'ready';
        }
        if (in_array($status, ['failed', 'blocked'], true)) {
            return 'not_sent';
        }
        return 'wait';
    }

    private static function claim(int $id, string $token): bool {
        global $wpdb;
        $now = self::db_now();
        $affected = $wpdb->query($wpdb->prepare(
            "UPDATE " . self::table_name() . " SET status='sending', lock_token=%s, locked_at=%s, updated_at=%s, reflection_synced_at=NULL
             WHERE id=%d AND status IN ('queued','retry') AND (lock_token='' OR lock_token IS NULL) AND locked_at IS NULL AND (next_attempt_at IS NULL OR next_attempt_at <= %s)",
            $token,
            $now,
            $now,
            $id,
            $now
        ));
        return (int) $affected === 1;
    }

    private static function finish_sent(array $row, string $token, int $attempts, int $http_status): bool {
        return self::finish_locked($row, $token, "status='sent', attempts=%d, next_attempt_at=NULL, last_http_status=%d, last_error='', last_transport_error='', sent_at=%s", [$attempts, $http_status, self::db_now()]);
    }

    private static function finish_retry(array $row, string $token, int $attempts, int $http_status, string $error, string $transport_error, int $delay): bool {
        $next = gmdate('Y-m-d H:i:s', time() + max(0, $delay));
        $success = self::finish_locked($row, $token, "status='retry', attempts=%d, next_attempt_at=%s, last_http_status=%d, last_error=%s, last_transport_error=%s", [$attempts, $next, $http_status, self::clean_error($error), self::clean_error($transport_error)]);
        if ($success) {
            IDG_Traceability::schedule_at(time() + max(1, $delay));
        }
        return $success;
    }

    private static function finish_failed(array $row, string $token, int $http_status, string $error, string $transport_error, int $attempts): bool {
        return self::finish_locked($row, $token, "status='failed', attempts=%d, next_attempt_at=NULL, last_http_status=%d, last_error=%s, last_transport_error=%s", [$attempts, $http_status, self::clean_error($error), self::clean_error($transport_error)]);
    }

    private static function finish_blocked(array $row, string $token, string $reason): bool {
        return self::finish_locked($row, $token, "status='blocked', next_attempt_at=NULL, last_error=%s", [self::clean_error($reason)]);
    }

    private static function finish_locked(array $row, string $token, string $set_sql, array $args): bool {
        global $wpdb;
        $now = self::db_now();
        $args[] = $now;
        $args[] = (int) ($row['id'] ?? 0);
        $args[] = $token;
        $sql = "UPDATE " . self::table_name() . " SET {$set_sql}, updated_at=%s, lock_token='', locked_at=NULL, reflection_synced_at=NULL WHERE id=%d AND status='sending' AND lock_token=%s";
        $affected = $wpdb->query($wpdb->prepare($sql, ...$args));
        if ((int) $affected !== 1) {
            return false;
        }
        $fresh = self::get_by_id((int) ($row['id'] ?? 0));
        if ($fresh) {
            self::sync_and_mark($fresh);
        }
        return true;
    }

    private static function set_blocked(array $row, string $reason): bool {
        global $wpdb;
        $now = self::db_now();
        $affected = $wpdb->query($wpdb->prepare(
            "UPDATE " . self::table_name() . " SET status='blocked', next_attempt_at=NULL, last_error=%s, updated_at=%s, reflection_synced_at=NULL
             WHERE id=%d AND status IN ('queued','retry','blocked') AND (lock_token='' OR lock_token IS NULL) AND locked_at IS NULL",
            self::clean_error($reason),
            $now,
            (int) ($row['id'] ?? 0)
        ));
        if ((int) $affected !== 1) {
            return false;
        }
        $fresh = self::get_by_id((int) ($row['id'] ?? 0));
        if ($fresh) {
            self::sync_and_mark($fresh);
        }
        return true;
    }

    private static function set_queued(array $row): bool {
        global $wpdb;
        $now = self::db_now();
        $affected = $wpdb->query($wpdb->prepare(
            "UPDATE " . self::table_name() . " SET status='queued', next_attempt_at=%s, last_error='', updated_at=%s, reflection_synced_at=NULL
             WHERE id=%d AND status='blocked' AND (lock_token='' OR lock_token IS NULL) AND locked_at IS NULL",
            $now,
            $now,
            (int) ($row['id'] ?? 0)
        ));
        if ((int) $affected !== 1) {
            return false;
        }
        $fresh = self::get_by_id((int) ($row['id'] ?? 0));
        if ($fresh) {
            self::sync_and_mark($fresh);
        }
        return true;
    }

    private static function releasable_cutoff_state(string $occurred_at): string {
        $cutoff = IDG_Traceability::live_capture_cutoff();
        if (empty($cutoff['valid'])) {
            return 'invalid';
        }
        $occurred = IDG_Traceability::iso_to_timestamp($occurred_at);
        if ($occurred <= 0) {
            return 'invalid';
        }
        return $occurred >= (int) $cutoff['timestamp'] ? 'ready' : 'historical';
    }

    private static function same_event_identity(string $stored_json, array $candidate): bool {
        $stored = json_decode($stored_json, true);
        if (!is_array($stored)) {
            return false;
        }
        unset($stored['observed_at'], $candidate['observed_at']);
        return wp_json_encode($stored, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            === wp_json_encode($candidate, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private static function sync_and_mark(array $row): bool {
        global $wpdb;
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            return false;
        }
        $expected_status = (string) ($row['status'] ?? '');
        $expected_updated_at = (string) ($row['updated_at'] ?? '');
        $expected_lock_token = (string) ($row['lock_token'] ?? '');
        $expected_locked_at = (string) ($row['locked_at'] ?? '');
        if ($expected_status === 'sending'
            && ($expected_lock_token !== '' || $expected_locked_at !== '')) {
            return false;
        }
        $attempted_at = self::db_now();
        $synced = IDG_Traceability::sync_row_reflection($row);
        if ($synced === false) {
            $wpdb->query($wpdb->prepare(
                "UPDATE " . self::table_name() . " SET reflection_last_error=%s, reflection_attempts=reflection_attempts+1, reflection_last_attempt_at=%s WHERE id=%d",
                'reflection_verification_failed',
                $attempted_at,
                $id
            ));
            return false;
        }
        $fresh = self::get_by_id($id);
        if (!$fresh
            || (string) ($fresh['status'] ?? '') !== $expected_status
            || (string) ($fresh['updated_at'] ?? '') !== $expected_updated_at
            || (string) ($fresh['lock_token'] ?? '') !== $expected_lock_token
            || (string) ($fresh['locked_at'] ?? '') !== $expected_locked_at) {
            return false;
        }
        $affected = $wpdb->query($wpdb->prepare(
            "UPDATE " . self::table_name() . " SET reflection_synced_at=%s, reflection_last_error='', reflection_attempts=0, reflection_last_attempt_at=%s
             WHERE id=%d AND status=%s AND updated_at=%s
             AND (lock_token='' OR lock_token IS NULL) AND locked_at IS NULL
             AND (reflection_synced_at IS NULL OR reflection_synced_at < updated_at)",
            $expected_updated_at,
            $attempted_at,
            $id,
            $expected_status,
            $expected_updated_at
        ));
        return (int) $affected === 1;
    }

    private static function db_now(): string {
        return gmdate('Y-m-d H:i:s');
    }

    private static function clean_error(string $error): string {
        $error = sanitize_text_field(wp_strip_all_tags($error));
        $token = class_exists('IDG_Traceability') ? IDG_Traceability::config_string('IDG_RADAR_TRACEABILITY_TOKEN') : '';
        if ($token !== '') {
            $error = str_replace($token, '[redacted]', $error);
        }
        return function_exists('mb_substr') ? mb_substr($error, 0, 500) : substr($error, 0, 500);
    }
}
