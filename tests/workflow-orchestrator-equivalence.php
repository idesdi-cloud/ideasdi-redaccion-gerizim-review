<?php
define('ABSPATH', __DIR__ . '/');
define('IDG_SESSION_KEY_PREFIX', 'idg_workflow_user_');
define('IDG_ACTION_HOOK', 'idg_process_workflow_action');

$GLOBALS['idg_test_options'] = [];
$GLOBALS['idg_test_user_meta'] = [];
$GLOBALS['idg_test_logs'] = [];

function wp_generate_uuid4(): string { return '11111111-2222-4333-8444-555555555555'; }
function current_time(string $type): string { return '2026-07-15 12:34:56'; }
function get_current_user_id(): int { return 77; }
function sanitize_key(string $value): string { return strtolower((string) preg_replace('/[^a-z0-9_\-]/i', '', $value)); }
function sanitize_text_field(string $value): string { return trim($value); }
function get_option(string $key, $default = false) { return $GLOBALS['idg_test_options'][$key] ?? $default; }
function update_option(string $key, $value, bool $autoload = false): bool { $GLOBALS['idg_test_options'][$key] = $value; return true; }
function delete_option(string $key): bool { unset($GLOBALS['idg_test_options'][$key]); return true; }
function update_user_meta(int $user_id, string $key, $value): bool { $GLOBALS['idg_test_user_meta'][$user_id][$key] = $value; return true; }
function get_user_meta(int $user_id, string $key, bool $single = false) { return $GLOBALS['idg_test_user_meta'][$user_id][$key] ?? ''; }
function delete_user_meta(int $user_id, string $key): bool { unset($GLOBALS['idg_test_user_meta'][$user_id][$key]); return true; }

final class IDG_Logger {
    public static function log(string $event, string $message, array $context = []): void {
        $GLOBALS['idg_test_logs'][] = [$event, $message, $context];
    }
}

require_once dirname(__DIR__) . '/includes/contracts/interface-workflow-input-adapter.php';
require_once dirname(__DIR__) . '/includes/contracts/interface-workflow-orchestrator.php';
require_once dirname(__DIR__) . '/includes/contracts/interface-workflow-action-strategy.php';
require_once dirname(__DIR__) . '/includes/class-workflow-policies.php';
require_once dirname(__DIR__) . '/includes/class-workflow-contract.php';
require_once dirname(__DIR__) . '/includes/adapters/class-workflow-input-adapters.php';
require_once dirname(__DIR__) . '/includes/strategies/class-workflow-action-strategies.php';
require_once dirname(__DIR__) . '/includes/class-job-runner.php';
require_once dirname(__DIR__) . '/includes/class-workflow-orchestrator.php';

function orchestrator_ok(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

$fixture = [
    'keyword' => 'Workflow equivalente',
    'category_id' => 12,
    'tag_ids' => [4, 8],
    'piece_type' => 'Actualidad',
    'nested' => ['keep' => true],
];

$GLOBALS['idg_test_options'] = [];
$GLOBALS['idg_test_user_meta'] = [];
$direct_id = IDG_Job_Runner::new_workflow($fixture);
$direct_options = $GLOBALS['idg_test_options'];
$direct_user_meta = $GLOBALS['idg_test_user_meta'];

$GLOBALS['idg_test_options'] = [];
$GLOBALS['idg_test_user_meta'] = [];
$orchestrated_id = IDG_Workflow_Orchestrator::create($fixture, 'admin');
$orchestrated_options = $GLOBALS['idg_test_options'];
$orchestrated_user_meta = $GLOBALS['idg_test_user_meta'];

orchestrator_ok($direct_id === $orchestrated_id, 'create conserva identificador');
orchestrator_ok($direct_options === $orchestrated_options, 'create conserva workflow persistido exacto');
orchestrator_ok($direct_user_meta === $orchestrated_user_meta, 'create conserva sesión activa');

$existing_id = 'idg_existing';
$existing = [
    'workflow_id' => $existing_id,
    'status' => 'completed',
    'keyword' => 'Persistencia compatible',
    'history' => [],
];
$GLOBALS['idg_test_options'] = [];
$GLOBALS['idg_test_user_meta'] = [];
IDG_Job_Runner::save_workflow($existing_id, $existing);
$direct_options = $GLOBALS['idg_test_options'];
$direct_user_meta = $GLOBALS['idg_test_user_meta'];

$GLOBALS['idg_test_options'] = [];
$GLOBALS['idg_test_user_meta'] = [];
IDG_Workflow_Orchestrator::save($existing_id, $existing, 'radar');
orchestrator_ok($GLOBALS['idg_test_options'] === $direct_options, 'save conserva wp_options exacto');
orchestrator_ok($GLOBALS['idg_test_user_meta'] === $direct_user_meta, 'save conserva user meta exacto');

$seed = [
    'workflow_id' => $existing_id,
    'status' => 'draft',
    'history' => [],
];
$GLOBALS['idg_test_options'] = [$existing_id => $seed];
IDG_Job_Runner::process_scheduled_action($existing_id, 'future_action');
$direct_unknown = $GLOBALS['idg_test_options'][$existing_id];

$GLOBALS['idg_test_options'] = [$existing_id => $seed];
IDG_Workflow_Orchestrator::process_scheduled_action($existing_id, 'future_action');
$orchestrated_unknown = $GLOBALS['idg_test_options'][$existing_id];
orchestrator_ok($orchestrated_unknown === $direct_unknown, 'acción no conocida conserva semántica legacy');

$GLOBALS['idg_test_options'] = [];
$GLOBALS['idg_test_logs'] = [];
IDG_Job_Runner::process_scheduled_action('missing', 'generate');
$direct_logs = $GLOBALS['idg_test_logs'];
$GLOBALS['idg_test_logs'] = [];
IDG_Workflow_Orchestrator::process_scheduled_action('missing', 'generate');
orchestrator_ok($GLOBALS['idg_test_logs'] === $direct_logs, 'workflow ausente conserva diagnóstico');

echo "PASS workflow orchestrator equivalence\n";
