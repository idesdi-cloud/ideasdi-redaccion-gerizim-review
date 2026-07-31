<?php
$root = dirname(__DIR__);
$read = static fn(string $path): string => file_get_contents($root . '/' . $path) ?: '';
$main = $read('ideasdi-redaccion-gerizim.php');
$policy = $read('includes/class-workflow-policies.php');
$contract = $read('includes/class-workflow-contract.php');
$runner = $read('includes/class-job-runner.php');
$admin = $read('includes/class-admin-page.php');
$strategies = $read('includes/strategies/class-workflow-action-strategies.php');
$orchestrator = $read('includes/class-workflow-orchestrator.php');

function policy_static_ok(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

policy_static_ok(str_contains($main, "class-workflow-policies.php") && strpos($main, 'class-workflow-policies.php') < strpos($main, 'class-workflow-contract.php'), 'plugin carga políticas antes del contrato');
policy_static_ok(str_contains($policy, "STATUS_DRAFT = 'draft'") && str_contains($policy, "STATUS_PROCESSING = 'processing'") && str_contains($policy, "STATUS_COMPLETED = 'completed'") && str_contains($policy, "STATUS_FAILED = 'failed'"), 'estados centralizados');
policy_static_ok(str_contains($policy, 'ALLOWED_TRANSITIONS') && str_contains($policy, 'transition_allowed'), 'transiciones centralizadas');
policy_static_ok(str_contains($policy, 'VALIDATION_STEPS') && str_contains($policy, 'advance_block_reason'), 'elegibilidad y avance centralizados');
policy_static_ok(str_contains($policy, 'MAX_AUTOMATIC_RETRIES = 0') && str_contains($policy, "return 'manual_only'"), 'reintentos mantienen política manual');
policy_static_ok(str_contains($policy, 'blocks_interactive_mutation'), 'bloqueo por processing centralizado');
policy_static_ok(str_contains($contract, 'IDG_Workflow_Policies::is_known_action') && !str_contains($contract, 'KNOWN_ACTIONS'), 'contrato consume catálogo central');
policy_static_ok(str_contains($strategies, 'IDG_Workflow_Policies::ACTION_GENERATE') && str_contains($strategies, 'IDG_Workflow_Policies::is_force_action'), 'estrategias consumen acciones y overrides centrales');
policy_static_ok(str_contains($runner, 'IDG_Workflow_Policies::mark_processing') && str_contains($runner, 'IDG_Workflow_Policies::mark_failed') && str_contains($runner, 'IDG_Workflow_Policies::mark_completed'), 'runner consume transiciones centrales');
policy_static_ok(str_contains($runner, 'IDG_Workflow_Policies::history_limit') && !str_contains($runner, 'MAX_WORKFLOW_HISTORY'), 'runner consume límite histórico central');
policy_static_ok(str_contains($runner, 'IDG_Workflow_Policies::should_retry'), 'excepciones consultan política de reintentos');
policy_static_ok(str_contains($admin, 'IDG_Workflow_Policies::is_known_action') && str_contains($admin, 'IDG_Workflow_Policies::advance_block_reason'), 'panel consume acciones y condiciones de avance');
policy_static_ok(substr_count($admin, 'IDG_Workflow_Policies::blocks_interactive_mutation') >= 4, 'bloqueos interactivos del panel usan una fuente única');
policy_static_ok(str_contains($orchestrator, 'IDG_Workflow_Policies::automatic_retry_limit'), 'orquestador declara política de cola sin alterar delegación');
policy_static_ok(!str_contains($runner, "['status'] = 'processing'") && !str_contains($runner, "['status'] = 'failed'") && !str_contains($runner, "['status'] = 'completed'"), 'runner ya no define estados operativos en línea');
policy_static_ok(!str_contains($admin, "in_array(\$step, ['generate', 'editorial', 'seo'"), 'panel ya no duplica catálogo de acciones');

echo "PASS workflow policies routing static\n";
