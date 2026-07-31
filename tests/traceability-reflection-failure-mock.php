<?php
define('ABSPATH', __DIR__ . '/');
define('IDG_SESSION_KEY_PREFIX', 'idg_session_');
define('IDG_TRACEABILITY_CAPTURE_ENABLED', true);
define('IDG_TRACEABILITY_DELIVERY_ENABLED', false);
define('IDG_TRACEABILITY_CONTRACT_VERSION', '1.1');
define('IDG_TRACEABILITY_ACTION_HOOK', 'idg_traceability_process_outbox');
define('IDG_TRACEABILITY_RECONCILE_HOOK', 'idg_traceability_reconcile');

$GLOBALS['options'] = [];
$GLOBALS['post_meta'] = [];
$GLOBALS['fail_option_keys'] = [];
$GLOBALS['fail_post_meta_keys'] = [];

function add_action(...$args) {}
function get_current_user_id() { return 0; }
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function update_option($key, $value, $autoload = false) {
    if (!empty($GLOBALS['fail_option_keys'][$key])) { return false; }
    $GLOBALS['options'][$key] = $value;
    return true;
}
function update_user_meta(...$args) { return true; }
function get_post_meta($post_id, $key, $single = true) { return $GLOBALS['post_meta'][$post_id][$key] ?? ''; }
function update_post_meta($post_id, $key, $value) {
    if (!empty($GLOBALS['fail_post_meta_keys'][$key])) { return false; }
    $GLOBALS['post_meta'][$post_id][$key] = $value;
    return true;
}
function delete_post_meta($post_id, $key) { unset($GLOBALS['post_meta'][$post_id][$key]); return true; }
function sanitize_text_field($value) { return trim((string) $value); }
function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $value)); }
function absint($value) { return abs((int) $value); }
function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }
function wp_timezone() { return new DateTimeZone('America/Bogota'); }
function wp_next_scheduled($hook) { return false; }
function wp_schedule_single_event(...$args) { return true; }
function wp_schedule_event(...$args) { return true; }

require_once dirname(__DIR__) . '/includes/class-job-runner.php';
require_once dirname(__DIR__) . '/includes/class-traceability.php';

function ok($condition, $message) {
    if (!$condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
    echo "OK: {$message}\n";
}

$workflow_id = 'idg_88888888-8888-4888-8888-888888888888';
$GLOBALS['options'][$workflow_id] = [
    'workflow_id' => $workflow_id,
    'radar_source' => 'radar-editorial-ideasdi',
    'radar_brief_id' => 888,
];
$workflow_payload = [
    'event_type' => 'gerizim_imported',
    'workflow_id' => $workflow_id,
    'brief_id' => 888,
    'occurred_at' => '2026-07-05T12:00:00Z',
    'idempotency_key' => "gerizim_imported:888:{$workflow_id}",
];
$workflow_row = [
    'event_type' => 'gerizim_imported',
    'status' => 'queued',
    'idempotency_key' => $workflow_payload['idempotency_key'],
    'payload_json' => json_encode($workflow_payload),
];
$GLOBALS['fail_option_keys'][$workflow_id] = true;
ok(IDG_Traceability::sync_row_reflection($workflow_row) === false, 'fallo de update_option deja pendiente el reflejo del workflow');
ok(empty($GLOBALS['options'][$workflow_id]['traceability_gerizim_imported_status']), 'workflow no aparenta sincronización cuando update_option falla');
unset($GLOBALS['fail_option_keys'][$workflow_id]);
ok(IDG_Traceability::sync_row_reflection($workflow_row) === true, 'reflejo del workflow se confirma después de persistencia real');

$post_id = 48888;
$post_payload = [
    'event_type' => 'wordpress_post_created',
    'workflow_id' => $workflow_id,
    'brief_id' => 888,
    'wordpress_post_id' => $post_id,
    'wordpress_status' => 'pending',
    'occurred_at' => '2026-07-05T12:05:00Z',
    'idempotency_key' => "wordpress_post_created:{$workflow_id}:{$post_id}",
];
$post_row = [
    'event_type' => 'wordpress_post_created',
    'status' => 'queued',
    'idempotency_key' => $post_payload['idempotency_key'],
    'payload_json' => json_encode($post_payload),
];
$GLOBALS['fail_post_meta_keys']['_idg_traceability_post_created_synced_at_utc'] = true;
ok(IDG_Traceability::sync_row_reflection($post_row) === false, 'fallo de update_post_meta deja pendiente el reflejo del post');
ok(($GLOBALS['post_meta'][$post_id]['_idg_traceability_post_created_status'] ?? '') === 'queued', 'estado parcial se relee pero no se considera sincronizado');
unset($GLOBALS['fail_post_meta_keys']['_idg_traceability_post_created_synced_at_utc']);
ok(IDG_Traceability::sync_row_reflection($post_row) === true, 'reflejo del post se confirma cuando todos los metadatos persisten');

echo "PASS reflection failure mock\n";
