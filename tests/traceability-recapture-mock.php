<?php
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');
$GLOBALS['idg_test_options'] = [];
$GLOBALS['fail_delete_option'] = false;

class IDG_Traceability_Outbox {
    public static array $rows = [];
    public static bool $ready = true;
    public static function schema_ready(): bool { return self::$ready; }
    public static function get_by_key(string $key): ?array { return self::$rows[$key] ?? null; }
    public static function insert_event(array $payload, string $status = 'queued', string $dependency = '', string $error = ''): array {
        $key = (string) $payload['idempotency_key'];
        if (isset(self::$rows[$key])) {
            return ['success' => true, 'inserted' => false, 'row' => self::$rows[$key]];
        }
        $row = [
            'idempotency_key' => $key,
            'event_type' => $payload['event_type'],
            'payload_json' => json_encode($payload),
            'status' => $status,
            'dependency_key' => $dependency,
            'last_error' => $error,
        ];
        self::$rows[$key] = $row;
        return ['success' => true, 'inserted' => true, 'row' => $row];
    }
}
class IDG_Traceability {
    public static function contract_version(): string { return '1.1'; }
    public static function rebuild_recapture_intent(array $intent): array {
        if (($intent['event_type'] ?? '') !== 'gerizim_imported') {
            return ['success' => false, 'reason' => 'invalid_import_occurred_at'];
        }
        $payload = $intent['payload'];
        $payload['occurred_at'] = '2026-07-05T09:00:00Z';
        return ['success' => true, 'payload' => $payload, 'status' => 'queued', 'error' => '', 'dependency_key' => ''];
    }
}
function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $value)); }
function sanitize_text_field($value) { return trim((string) $value); }
function absint($value) { return abs((int) $value); }
function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }
function maybe_unserialize($value) { return is_string($value) ? unserialize($value) : $value; }
function add_option($key, $value, $deprecated = '', $autoload = false) {
    if (array_key_exists($key, $GLOBALS['idg_test_options'])) return false;
    $GLOBALS['idg_test_options'][$key] = $value;
    return true;
}
function get_option($key, $default = false) { return $GLOBALS['idg_test_options'][$key] ?? $default; }
function update_option($key, $value, $autoload = false) { $GLOBALS['idg_test_options'][$key] = $value; return true; }
function delete_option($key) {
    if ($GLOBALS['fail_delete_option']) return false;
    if (!array_key_exists($key, $GLOBALS['idg_test_options'])) return false;
    unset($GLOBALS['idg_test_options'][$key]);
    return true;
}

require_once dirname(__DIR__) . '/includes/class-traceability-recapture.php';
function ok($condition, string $message): void {
    if (!$condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
    echo "OK: {$message}\n";
}
function payload(string $key, string $occurred = '2026-07-05T10:00:00Z', int $postId = 500): array {
    return [
        'event_type' => 'wordpress_published',
        'brief_id' => 20,
        'occurred_at' => $occurred,
        'observed_at' => '2026-07-05T10:00:01Z',
        'source_system' => 'wordpress',
        'source_record_id' => (string) $postId,
        'workflow_id' => 'idg_aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
        'wordpress_post_id' => $postId,
        'wordpress_status' => 'publish',
        'idempotency_key' => $key,
        'evidence_payload' => ['contract_version' => '1.1', 'previous_status' => 'pending', 'new_status' => 'publish'],
        'actor' => 'wordpress',
    ];
}

$GLOBALS['idg_test_options']['idg_traceability_recapture_cursor'] = 77;
ok(IDG_Traceability_Recapture::count() === 0, 'el cursor aislado no cuenta como intención pendiente');
ok(IDG_Traceability_Recapture::problem_rows() === [], 'el cursor aislado no aparece como problema');

$key1 = 'wordpress_published:idg_a:500';
$key2 = 'wordpress_published:idg_b:501';
ok(IDG_Traceability_Recapture::record(payload($key1), 'queued', 'wordpress_post_created:idg_a:500', 'outbox_insert_failed'), 'primera intención se guarda atómicamente');
ok(IDG_Traceability_Recapture::record(payload($key2, '2026-07-05T10:00:00Z', 501), 'queued', 'wordpress_post_created:idg_b:501', 'outbox_insert_failed'), 'segunda intención concurrente no sobrescribe la primera');
ok(IDG_Traceability_Recapture::count() === 2, 'existen dos opciones independientes');

$same = payload($key1);
$same['observed_at'] = '2026-07-05T12:00:00Z';
ok(IDG_Traceability_Recapture::record($same, 'queued', 'wordpress_post_created:idg_a:500', 'outbox_insert_failed'), 'observed_at diferente sigue compatible');
$different = payload($key1, '2026-07-05T11:00:00Z');
ok(!IDG_Traceability_Recapture::record($different, 'queued', 'wordpress_post_created:idg_a:500', 'outbox_insert_failed'), 'occurred_at diferente produce conflicto');
$option1 = IDG_Traceability_Recapture::option_name_for_key($key1);
ok(($GLOBALS['idg_test_options'][$option1]['last_error'] ?? '') === 'idempotency_payload_conflict', 'conflicto queda durable');
ok(IDG_Traceability_Recapture::conflict_count() === 1, 'conflicto se contabiliza para diagnóstico');

$recovered = IDG_Traceability_Recapture::recover_batch(10, 0);
ok(($recovered['recovered'] ?? 0) >= 1, 'publicación perdida se reconstruye en outbox');
ok(isset(IDG_Traceability_Outbox::$rows[$key2]), 'fila publicada perdida recuperada');
ok(isset($GLOBALS['idg_test_options'][$option1]), 'intención conflictiva no se elimina');

$key3 = 'wordpress_published:idg_c:502';
$payload3 = payload($key3, '2026-07-05T10:00:00Z', 502);
ok(IDG_Traceability_Recapture::record($payload3, 'queued', 'wordpress_post_created:idg_c:502', 'outbox_insert_failed'), 'intención compatible adicional se conserva');
IDG_Traceability_Outbox::insert_event($payload3, 'queued', 'wordpress_post_created:idg_c:502', '');
$option3 = IDG_Traceability_Recapture::option_name_for_key($key3);
$GLOBALS['fail_delete_option'] = true;
IDG_Traceability_Recapture::recover_batch(20, 0);
ok(isset($GLOBALS['idg_test_options'][$option3]), 'fallo de limpieza no elimina intención durable');
ok(($GLOBALS['idg_test_options'][$option3]['last_error'] ?? '') === 'recapture_cleanup_failed', 'fallo de limpieza queda diagnosticado');
$GLOBALS['fail_delete_option'] = false;
IDG_Traceability_Recapture::recover_batch(20, 0);
ok(!isset($GLOBALS['idg_test_options'][$option3]), 'intención se elimina solo después de limpieza verificada');

$workflowId = 'idg_cccccccc-cccc-4ccc-8ccc-cccccccccccc';
$workflow = ['workflow_id' => $workflowId, 'radar_brief_id' => 33];
ok(IDG_Traceability_Recapture::record_unresolved_import($workflow), 'importación sin fecha conserva intención bloqueada');
IDG_Traceability_Recapture::recover_batch(20, 0);
$importKey = 'gerizim_imported:33:' . $workflowId;
ok(isset(IDG_Traceability_Outbox::$rows[$importKey]), 'reconciliador reconstruye importación cuando obtiene fecha original');

echo "PASS recapture mock\n";
