<?php
define('ABSPATH', __DIR__ . '/');
define('IDG_TRACEABILITY_DB_VERSION', '1.2.0');
define('ARRAY_A', 'ARRAY_A');

final class RC157_Prepared_Query {
    public function __construct(public string $sql, public array $args) {}
}

final class RC157_DB {
    public string $prefix = 'wp_';
    public string $last_error = '';
    public array $rows = [];
    public bool $table_exists = true;
    public string $selection_error = '';
    public array $claim_update_fail_ids = [];
    public array $hide_after_claim_ids = [];
    public array $mismatch_after_claim_ids = [];
    private array $hide_next_get = [];

    public function prepare($query, ...$args): RC157_Prepared_Query {
        return new RC157_Prepared_Query($query, $args);
    }

    public function get_charset_collate(): string {
        return '';
    }

    private function sql($query): string {
        $sql = $query instanceof RC157_Prepared_Query ? $query->sql : (string) $query;
        return preg_replace('/\s+/', ' ', trim($sql));
    }

    private function args($query): array {
        return $query instanceof RC157_Prepared_Query ? $query->args : [];
    }

    private function columns(): array {
        return [
            'id' => ['bigint(20) unsigned', 'NO'],
            'idempotency_key' => ['varchar(191)', 'NO'],
            'event_type' => ['varchar(64)', 'NO'],
            'payload_json' => ['longtext', 'NO'],
            'status' => ['varchar(20)', 'NO'],
            'attempts' => ['smallint(5) unsigned', 'NO'],
            'next_attempt_at' => ['datetime', 'YES'],
            'last_http_status' => ['smallint(5) unsigned', 'YES'],
            'last_error' => ['varchar(500)', 'NO'],
            'last_transport_error' => ['varchar(500)', 'NO'],
            'created_at' => ['datetime', 'NO'],
            'updated_at' => ['datetime', 'NO'],
            'sent_at' => ['datetime', 'YES'],
            'dependency_key' => ['varchar(191)', 'NO'],
            'lock_token' => ['varchar(64)', 'NO'],
            'locked_at' => ['datetime', 'YES'],
            'reflection_synced_at' => ['datetime', 'YES'],
            'reflection_last_error' => ['varchar(500)', 'NO'],
            'reflection_attempts' => ['smallint(5) unsigned', 'NO'],
            'reflection_last_attempt_at' => ['datetime', 'YES'],
        ];
    }

    public function get_var($query) {
        $sql = $this->sql($query);
        if (str_contains($sql, 'SHOW TABLES LIKE')) {
            return $this->table_exists ? 'wp_idg_traceability_outbox' : null;
        }
        return null;
    }

    public function get_row($query, $output = null): ?array {
        $sql = $this->sql($query);
        $args = $this->args($query);
        if (str_contains($sql, 'WHERE idempotency_key = %s')) {
            foreach ($this->rows as $row) {
                if ($row['idempotency_key'] === (string) $args[0]) {
                    return $row;
                }
            }
        }
        if (str_contains($sql, 'WHERE id = %d')) {
            $id = (int) $args[0];
            if (!empty($this->hide_next_get[$id])) {
                unset($this->hide_next_get[$id]);
                return null;
            }
            return $this->rows[$id] ?? null;
        }
        return null;
    }

    public function get_col($query): array {
        $sql = $this->sql($query);
        $args = $this->args($query);
        if (!str_contains($sql, 'SELECT child.id')) {
            return [];
        }
        if ($this->selection_error !== '') {
            $this->last_error = $this->selection_error;
            return [];
        }
        $now = (string) $args[0];
        $limit = (int) $args[1];
        $ids = [];
        foreach ($this->rows as $id => $row) {
            if (!in_array($row['status'], ['queued', 'retry'], true)) {
                continue;
            }
            if ($row['next_attempt_at'] !== null && $row['next_attempt_at'] > $now) {
                continue;
            }
            if ($row['lock_token'] !== '' || $row['locked_at'] !== null) {
                continue;
            }
            if ($row['dependency_key'] !== '') {
                $parent = $this->find_by_key($row['dependency_key']);
                if (!$parent || $parent['status'] !== 'sent') {
                    continue;
                }
            }
            $ids[] = (int) $id;
        }
        sort($ids);
        return array_slice($ids, 0, $limit);
    }

    private function find_by_key(string $key): ?array {
        foreach ($this->rows as $row) {
            if ($row['idempotency_key'] === $key) {
                return $row;
            }
        }
        return null;
    }

    public function get_results($query, $output = null): array {
        $sql = $this->sql($query);
        $args = $this->args($query);
        if (str_contains($sql, 'SHOW COLUMNS')) {
            $result = [];
            foreach ($this->columns() as $name => $meta) {
                $result[] = ['Field' => $name, 'Type' => $meta[0], 'Null' => $meta[1]];
            }
            return $result;
        }
        if (str_contains($sql, 'SHOW INDEX')) {
            $definitions = [
                ['PRIMARY', 0, ['id']],
                ['idempotency_key', 0, ['idempotency_key']],
                ['status_next_attempt', 1, ['status', 'next_attempt_at', 'id']],
                ['dependency_key', 1, ['dependency_key']],
                ['locked_at', 1, ['locked_at']],
                ['reflection_sync', 1, ['reflection_synced_at', 'updated_at', 'id']],
            ];
            $result = [];
            foreach ($definitions as [$name, $non_unique, $columns]) {
                foreach ($columns as $index => $column) {
                    $result[] = [
                        'Key_name' => $name,
                        'Non_unique' => $non_unique,
                        'Seq_in_index' => $index + 1,
                        'Column_name' => $column,
                    ];
                }
            }
            return $result;
        }
        if (str_contains($sql, "dependency_key<>''") || str_contains($sql, "dependency_key <> ''")) {
            return [];
        }
        if (str_contains($sql, "status='blocked' AND last_error=%s")) {
            return [];
        }
        if (str_contains($sql, 'reflection_synced_at IS NULL OR reflection_synced_at < updated_at')) {
            $after = (int) $args[0];
            $limit = (int) $args[1];
            $rows = array_filter($this->rows, static function (array $row) use ($after): bool {
                return $row['id'] > $after
                    && ($row['reflection_synced_at'] === null || $row['reflection_synced_at'] < $row['updated_at'])
                    && !($row['status'] === 'sending' && ($row['lock_token'] !== '' || $row['locked_at'] !== null));
            });
            return array_slice(array_values($rows), 0, $limit);
        }
        return [];
    }

    public function query($query) {
        if (!$query instanceof RC157_Prepared_Query) {
            return 0;
        }
        $sql = $this->sql($query);
        $args = $query->args;
        if (str_contains($sql, "SET status='sending'")) {
            [$token, $locked_at, $updated_at, $id, $now] = $args;
            $id = (int) $id;
            if (in_array($id, $this->claim_update_fail_ids, true) || !isset($this->rows[$id])) {
                return 0;
            }
            $row = &$this->rows[$id];
            if (!in_array($row['status'], ['queued', 'retry'], true)
                || $row['lock_token'] !== ''
                || $row['locked_at'] !== null
                || ($row['next_attempt_at'] !== null && $row['next_attempt_at'] > $now)) {
                return 0;
            }
            $row['status'] = 'sending';
            $row['lock_token'] = (string) $token;
            $row['locked_at'] = (string) $locked_at;
            $row['updated_at'] = (string) $updated_at;
            $row['reflection_synced_at'] = null;
            if (in_array($id, $this->hide_after_claim_ids, true)) {
                $this->hide_next_get[$id] = true;
            }
            if (in_array($id, $this->mismatch_after_claim_ids, true)) {
                $row['lock_token'] = 'different-lock-token';
            }
            return 1;
        }
        if (str_contains($sql, "WHERE id=%d AND status='sending' AND lock_token=%s")) {
            $id = (int) $args[count($args) - 2];
            $token = (string) $args[count($args) - 1];
            if (!isset($this->rows[$id]) || $this->rows[$id]['status'] !== 'sending' || $this->rows[$id]['lock_token'] !== $token) {
                return 0;
            }
            $row = &$this->rows[$id];
            if (str_contains($sql, "status='sent'")) {
                [$attempts, $http_status, $sent_at, $updated_at] = array_slice($args, 0, 4);
                $row['status'] = 'sent';
                $row['attempts'] = (int) $attempts;
                $row['last_http_status'] = (int) $http_status;
                $row['sent_at'] = $sent_at;
                $row['last_error'] = '';
                $row['last_transport_error'] = '';
                $row['next_attempt_at'] = null;
                $row['updated_at'] = $updated_at;
            }
            $row['lock_token'] = '';
            $row['locked_at'] = null;
            $row['reflection_synced_at'] = null;
            return 1;
        }
        if (str_contains($sql, 'SET reflection_synced_at=%s')) {
            [$sync_at, $attempted_at, $id, $status, $updated_at] = $args;
            $id = (int) $id;
            if (!isset($this->rows[$id]) || $this->rows[$id]['status'] !== $status || $this->rows[$id]['updated_at'] !== $updated_at) {
                return 0;
            }
            $this->rows[$id]['reflection_synced_at'] = $sync_at;
            $this->rows[$id]['reflection_last_error'] = '';
            $this->rows[$id]['reflection_attempts'] = 0;
            $this->rows[$id]['reflection_last_attempt_at'] = $attempted_at;
            return 1;
        }
        if (str_contains($sql, 'SET reflection_last_error=%s')) {
            return 1;
        }
        if (str_contains($sql, "WHERE status='sending' AND locked_at IS NOT NULL")) {
            return 0;
        }
        return 0;
    }
}

$GLOBALS['options'] = [];
$GLOBALS['wpdb'] = new RC157_DB();

class IDG_Traceability {
    public static bool $delivery_enabled = true;
    public static bool $delivery_configuration_valid = true;
    public static array $synced = [];

    public static function delivery_enabled(): bool { return self::$delivery_enabled; }
    public static function delivery_configuration_valid(): bool { return self::$delivery_configuration_valid; }
    public static function schedule_processing(): void {}
    public static function schedule_at(int $timestamp): void {}
    public static function schedule_reconciliation(): void {}
    public static function sync_row_reflection(array $row): bool {
        self::$synced[$row['idempotency_key']] = $row['status'];
        return true;
    }
    public static function live_capture_cutoff(): array { return ['valid' => true, 'timestamp' => 0]; }
    public static function iso_to_timestamp(string $value): int { return strtotime($value) ?: 0; }
    public static function config_string(string $name, string $default = ''): string {
        return $name === 'IDG_RADAR_TRACEABILITY_TOKEN' ? 'SYNTHETIC_REDACTION_FIXTURE' : $default;
    }
    public static function validate_event_payload(array $payload, string $status = 'queued', string $error = ''): array {
        return ['valid' => true, 'reason' => ''];
    }
}

class IDG_Traceability_Client {
    public function send(array $payload): array {
        return [
            'success' => true,
            'retry' => false,
            'http_status' => 201,
            'code' => 'traceability_event_recorded',
            'message' => 'ok',
        ];
    }
}

function absint($value) { return abs((int) $value); }
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function update_option($key, $value, $autoload = false) { $GLOBALS['options'][$key] = $value; return true; }
function sanitize_text_field($value) { return trim(preg_replace('/\s+/', ' ', (string) $value)); }
function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $value)); }
function wp_strip_all_tags($value) { return strip_tags((string) $value); }
function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }
function wp_generate_password($length = 12, $special = true, $extra = false) {
    static $counter = 0;
    return str_pad('lock' . ++$counter, $length, 'x');
}

require_once dirname(__DIR__) . '/includes/class-traceability-outbox.php';

function rc157_ok(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

function rc157_reset(): RC157_DB {
    $db = new RC157_DB();
    $GLOBALS['wpdb'] = $db;
    $GLOBALS['options'] = [];
    IDG_Traceability::$delivery_enabled = true;
    IDG_Traceability::$delivery_configuration_valid = true;
    IDG_Traceability::$synced = [];
    return $db;
}

function rc157_candidate(RC157_DB $db, int $id = 1): void {
    $key = 'gerizim_imported:12012:idg_observability_' . $id;
    $payload = [
        'event_type' => 'gerizim_imported',
        'brief_id' => 12012,
        'occurred_at' => '2026-07-05T10:00:00Z',
        'observed_at' => '2026-07-05T10:00:01Z',
        'source_system' => 'gerizim-wordpress',
        'source_record_id' => 'idg_observability_' . $id,
        'workflow_id' => 'idg_observability_' . $id,
        'wordpress_post_id' => null,
        'wordpress_status' => null,
        'idempotency_key' => $key,
        'evidence_payload' => ['contract_version' => '1.1'],
        'actor' => 'ideasdi-redaccion-gerizim',
    ];
    $db->rows[$id] = [
        'id' => $id,
        'idempotency_key' => $key,
        'event_type' => 'gerizim_imported',
        'payload_json' => json_encode($payload),
        'status' => 'queued',
        'attempts' => 0,
        'next_attempt_at' => '2000-01-01 00:00:00',
        'last_http_status' => null,
        'last_error' => '',
        'last_transport_error' => '',
        'created_at' => '2026-07-05 10:00:00',
        'updated_at' => '2026-07-05 10:00:00',
        'sent_at' => null,
        'dependency_key' => '',
        'lock_token' => '',
        'locked_at' => null,
        'reflection_synced_at' => '2026-07-05 10:00:00',
        'reflection_last_error' => '',
        'reflection_attempts' => 0,
        'reflection_last_attempt_at' => null,
    ];
}

$db = rc157_reset();
$db->table_exists = false;
$result = IDG_Traceability_Outbox::process_queue(20);
rc157_ok($result['early_exit_reason'] === 'schema_not_ready', 'schema_not_ready identificado');

$db = rc157_reset();
IDG_Traceability::$delivery_enabled = false;
$result = IDG_Traceability_Outbox::process_queue(20);
rc157_ok($result['early_exit_reason'] === 'delivery_disabled', 'delivery_disabled identificado');

$db = rc157_reset();
IDG_Traceability::$delivery_configuration_valid = false;
$result = IDG_Traceability_Outbox::process_queue(20);
rc157_ok($result['early_exit_reason'] === 'delivery_configuration_invalid', 'delivery_configuration_invalid identificado');

$db = rc157_reset();
$db->selection_error = '<b>Selection failed</b> SYNTHETIC_REDACTION_FIXTURE';
$result = IDG_Traceability_Outbox::process_queue(20);
rc157_ok($result['early_exit_reason'] === 'sql_selection_error', 'sql_selection_error identificado');
rc157_ok($result['sql_error'] === 'Selection failed [redacted]', 'sql_error se sanitiza y redacta');
rc157_ok(!str_contains(json_encode($result), 'SYNTHETIC_REDACTION_FIXTURE'), 'resultado no expone el token');
rc157_ok(!str_contains(json_encode($result), 'payload_json'), 'resultado no incluye payload_json');

$db = rc157_reset();
$result = IDG_Traceability_Outbox::process_queue(20);
rc157_ok($result['early_exit_reason'] === 'no_candidates', 'no_candidates identificado');
rc157_ok($result['candidate_ids'] === [] && $result['candidate_count'] === 0, 'no_candidates conserva lista y conteo vacíos');

$db = rc157_reset();
rc157_candidate($db, 11);
$result = IDG_Traceability_Outbox::process_queue(20);
rc157_ok($result['candidate_ids'] === [11] && $result['candidate_count'] === 1, 'candidato seleccionado queda visible');
rc157_ok($result['claim_success_ids'] === [11] && $result['claim_failed_ids'] === [], 'claim UPDATE correcto y lock verificado');
rc157_ok($result['early_exit_reason'] === 'completed', 'completed identificado');
rc157_ok($result['processed'] === 1 && $result['sent'] === 1 && $db->rows[11]['status'] === 'sent', 'procesamiento exitoso conserva transición existente');

$db = rc157_reset();
rc157_candidate($db, 12);
$db->claim_update_fail_ids = [12];
$result = IDG_Traceability_Outbox::process_queue(20);
rc157_ok($result['early_exit_reason'] === 'candidates_not_claimed', 'candidates_not_claimed identificado');
rc157_ok($result['claim_failed_ids'] === [12], 'claim UPDATE fallido registra ID');
rc157_ok(($result['claim_failure_reasons'][12] ?? '') === 'claim_update_failed', 'claim UPDATE fallido registra motivo');

$db = rc157_reset();
rc157_candidate($db, 13);
$db->hide_after_claim_ids = [13];
$result = IDG_Traceability_Outbox::process_queue(20);
rc157_ok($result['early_exit_reason'] === 'claim_verification_failed', 'fila reclamada no recuperable usa claim_verification_failed');
rc157_ok($result['claim_success_ids'] === [] && $result['claim_failed_ids'] === [13], 'fila no recuperable no se cuenta como claim correcto');
rc157_ok(($result['claim_failure_reasons'][13] ?? '') === 'claimed_row_not_found', 'fila reclamada no recuperable registra motivo');

$db = rc157_reset();
rc157_candidate($db, 14);
$db->mismatch_after_claim_ids = [14];
$result = IDG_Traceability_Outbox::process_queue(20);
rc157_ok($result['early_exit_reason'] === 'claim_verification_failed', 'lock diferente usa claim_verification_failed');
rc157_ok($result['claim_success_ids'] === [] && $result['claim_failed_ids'] === [14], 'lock diferente no se cuenta como claim correcto');
rc157_ok(($result['claim_failure_reasons'][14] ?? '') === 'lock_token_mismatch', 'lock diferente registra motivo');

echo "PASS traceability observability mock\n";
