<?php
define('ABSPATH', __DIR__ . '/');

$GLOBALS['transients'] = [];
$GLOBALS['current_user_id'] = 42;

class IDG_Traceability_Outbox {
    public static function counts(): array { return ['queued' => 0, 'retry' => 0, 'failed' => 0, 'blocked' => 0, 'sent' => 0]; }
    public static function latest_summary(): array { return ['last_sent_at' => '', 'last_error' => '']; }
    public static function problematic_rows(int $limit = 50): array { return []; }
}

class IDG_Traceability_Recapture {
    public static function problem_rows(int $limit = 30): array { return []; }
}

class IDG_Traceability {
    public static function recoverable_failure_marker_rows(int $limit = 30): array { return []; }
    public static function diagnostics(): array {
        return [
            'capture_enabled' => true,
            'delivery_enabled' => true,
            'url_configured' => true,
            'url_valid' => true,
            'token_configured' => true,
            'cutoff_valid' => true,
            'table_available' => true,
            'schema_ready' => true,
            'schema_errors' => [],
            'scheduler_available' => true,
            'contract_compatible' => true,
            'recapture_pending' => 0,
            'recapture_conflicts' => 0,
            'recoverable_failure_markers' => 0,
        ];
    }
    public static function live_capture_cutoff(): array { return ['raw' => '2026-07-04T14:53:06.000Z', 'minimum' => '2026-07-04T14:53:06.000Z']; }
    public static function contract_version(): string { return '1.1'; }
}

function get_current_user_id(): int { return (int) $GLOBALS['current_user_id']; }
function set_transient(string $key, $value, int $expiration): bool {
    $GLOBALS['transients'][$key] = ['value' => $value, 'expiration' => $expiration];
    return true;
}
function get_transient(string $key) { return $GLOBALS['transients'][$key]['value'] ?? false; }
function delete_transient(string $key): bool { unset($GLOBALS['transients'][$key]); return true; }
function sanitize_key($value): string { return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $value)); }
function sanitize_text_field($value): string { return trim(preg_replace('/\s+/', ' ', (string) $value)); }
function wp_strip_all_tags($value): string { return strip_tags((string) $value); }
function absint($value): int { return abs((int) $value); }
function esc_html($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_url($value): string { return (string) $value; }
function admin_url(string $path = ''): string { return 'https://example.test/wp-admin/' . ltrim($path, '/'); }
function wp_nonce_field(string $action): void { echo '<input type="hidden" name="_wpnonce" value="nonce">'; }
function submit_button(string $text, string $type = 'primary', string $name = 'submit', bool $wrap = true): void {
    echo '<button name="' . esc_attr($name) . '">' . esc_html($text) . '</button>';
}

require_once dirname(__DIR__) . '/includes/class-traceability-admin.php';

function rc157_admin_ok(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

$result = [
    'processed' => 0,
    'sent' => 0,
    'retry' => 0,
    'failed' => 0,
    'blocked' => 0,
    'candidate_ids' => [77001],
    'candidate_count' => 1,
    'claim_success_ids' => [],
    'claim_failed_ids' => [77001],
    'claim_failure_reasons' => [77001 => 'lock_token_mismatch'],
    'early_exit_reason' => 'claim_verification_failed',
    'sql_error' => 'internal detail that must not be rendered',
];

$store = new ReflectionMethod(IDG_Traceability_Admin::class, 'store_queue_result');
$store->setAccessible(true);
rc157_admin_ok($store->invoke(null, 42, $result) === true, 'resultado se guarda mediante transient');
$key = 'idg_traceability_process_queue_result_42';
rc157_admin_ok(isset($GLOBALS['transients'][$key]), 'transient está asociado al usuario actual');
rc157_admin_ok(!isset($GLOBALS['transients']['idg_traceability_process_queue_result_7']), 'resultado no se comparte con otro usuario');
rc157_admin_ok(($GLOBALS['transients'][$key]['expiration'] ?? 0) === 300, 'transient usa expiración breve');

$_GET['traceability_action'] = 'processed';
ob_start();
IDG_Traceability_Admin::render_section();
$output = ob_get_clean();

$expected = 'Procesados: 0 · Enviados: 0 · Reintentos: 0 · Fallidos: 0 · Bloqueados: 0 · Candidatos: 1 · Reclamos correctos: 0 · Reclamos fallidos: 1 · Motivo: claim_verification_failed';
rc157_admin_ok(str_contains($output, $expected), 'aviso muestra el resultado real y los nueve campos');
foreach (['Procesados:', 'Enviados:', 'Reintentos:', 'Fallidos:', 'Bloqueados:', 'Candidatos:', 'Reclamos correctos:', 'Reclamos fallidos:', 'Motivo:'] as $label) {
    rc157_admin_ok(substr_count($output, $label) === 1, "campo {$label} aparece siempre una vez");
}
rc157_admin_ok(!isset($GLOBALS['transients'][$key]), 'transient se elimina después de mostrar el resultado');
rc157_admin_ok(!str_contains($output, 'internal detail that must not be rendered'), 'sql_error no se expone en el aviso');
rc157_admin_ok(!str_contains($output, 'payload_json') && !str_contains($output, 'Authorization'), 'aviso no expone payloads ni cabeceras');

echo "PASS traceability admin observability mock\n";
