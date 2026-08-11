<?php
$root = dirname(__DIR__);
$read = static fn(string $path): string => file_get_contents($root . '/' . $path) ?: '';
$main = $read('ideasdi-redaccion-gerizim.php');
$outbox = $read('includes/class-traceability-outbox.php');
$admin = $read('includes/class-traceability-admin.php');
$client = $read('includes/class-traceability-client.php');
$contract = $read('CONTRATO-RADAR-DIRECTUS-1.1.md');
$observability_test = $read('tests/traceability-observability-mock.php');
$admin_test = $read('tests/traceability-admin-observability-mock.php');

function rc157_accept(bool $condition, string $label): void {
    static $number = 0;
    $number++;
    if (!$condition) {
        fwrite(STDERR, sprintf("FAIL O%02d: %s\n", $number, $label));
        exit(1);
    }
    echo sprintf("OK O%02d: %s\n", $number, $label);
}

rc157_accept(str_contains($main, 'Version: 0.4.0-RC1.6.3.2') && str_contains($main, "define('IDG_VERSION', '0.4.0-RC1.6.3.2')"), 'Observabilidad RC1.5.7 preservada en RC1.6.3.2');
rc157_accept(str_contains($main, "define('IDG_TRACEABILITY_DB_VERSION', '1.2.0')"), 'Esquema permanece 1.2.0');
rc157_accept(str_contains($contract, 'trazabilidad 1.1') && str_contains($contract, '0.4.0-RC1.6.3'), 'Contrato 1.1 preservado');
foreach (['candidate_ids', 'candidate_count', 'claim_success_ids', 'claim_failed_ids', 'claim_failure_reasons', 'early_exit_reason', 'sql_error'] as $field) {
    rc157_accept(str_contains($outbox, "'{$field}'"), "Resultado incluye {$field}");
}
foreach (['schema_not_ready','delivery_disabled','delivery_configuration_invalid','sql_selection_error','no_candidates','candidates_not_claimed','claim_verification_failed','completed'] as $reason) {
    rc157_accept(str_contains($outbox, "'{$reason}'"), "Salida {$reason} implementada");
}
rc157_accept(str_contains($outbox, '$summary[\'claim_success_ids\'][] = $id;') && strpos($outbox, '$summary[\'claim_success_ids\'][] = $id;') > strpos($outbox, 'hash_equals'), 'Claim correcto se registra solo después de verificar lock');
foreach (['claim_update_failed', 'claimed_row_not_found', 'lock_token_mismatch'] as $reason) {
    rc157_accept(str_contains($outbox, "'{$reason}'"), "Motivo de claim {$reason} implementado");
}
$eligibility = "WHERE child.status IN ('queued','retry')\n               AND (child.next_attempt_at IS NULL OR child.next_attempt_at <= %s)\n               AND (child.lock_token='' OR child.lock_token IS NULL)\n               AND child.locked_at IS NULL\n               AND (child.dependency_key='' OR parent.status='sent')";
rc157_accept(str_contains($outbox, $eligibility), 'Consulta de elegibilidad preservada');
rc157_accept(str_contains($admin, 'set_transient(') && str_contains($admin, 'get_transient(') && str_contains($admin, 'delete_transient('), 'Resultado usa transient de ciclo único');
rc157_accept(str_contains($admin, 'QUEUE_RESULT_TRANSIENT_PREFIX . max(0, $user_id)'), 'Transient queda asociado al usuario');
foreach (['Procesados:', 'Enviados:', 'Reintentos:', 'Fallidos:', 'Bloqueados:', 'Candidatos:', 'Reclamos correctos:', 'Reclamos fallidos:', 'Motivo:'] as $label) {
    rc157_accept(str_contains($admin, $label), "Aviso incluye {$label}");
}
rc157_accept(!str_contains($admin, 'payload_json completo') && !str_contains($admin, 'Authorization:'), 'Aviso no incorpora payload ni cabecera Authorization');
rc157_accept(str_contains($observability_test, 'PASS traceability observability mock'), 'Prueba del worker incluida');
rc157_accept(str_contains($admin_test, 'PASS traceability admin observability mock'), 'Prueba del aviso administrativo incluida');
rc157_accept(str_contains($client, 'final class IDG_Traceability_Client'), 'Cliente HTTP permanece disponible sin sustitución');

echo "PASS RC1.5.7 observability preserved in RC1.6.1\n";
