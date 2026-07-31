<?php
$root = dirname(__DIR__);
$runner = file_get_contents($root . '/includes/class-job-runner.php') ?: '';
$strategies = file_get_contents($root . '/includes/strategies/class-workflow-action-strategies.php') ?: '';
$main = file_get_contents($root . '/ideasdi-redaccion-gerizim.php') ?: '';

function routing_ok(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

$start = strpos($runner, 'public static function process_scheduled_action');
$end = strpos($runner, 'public static function new_workflow', $start === false ? 0 : $start);
$dispatcher = ($start !== false && $end !== false) ? substr($runner, $start, $end - $start) : '';

routing_ok($dispatcher !== '', 'dispatcher del runner localizado');
routing_ok(str_contains($dispatcher, 'IDG_Workflow_Action_Strategy_Center::execute'), 'runner delega selección al centro de estrategias');
foreach ([
    "if (\$action === 'generate')",
    "elseif (\$action === 'editorial')",
    "elseif (\$action === 'seo')",
    "\$action === 'draft_force'",
    "\$action === 'recurring_event_content_force'",
] as $legacy_fragment) {
    routing_ok(!str_contains($dispatcher, $legacy_fragment), "dispatcher ya no selecciona mediante {$legacy_fragment}");
}

foreach ([
    'IDG_Generate_Workflow_Action_Strategy',
    'IDG_Editorial_Workflow_Action_Strategy',
    'IDG_Seo_Workflow_Action_Strategy',
    'IDG_Draft_Workflow_Action_Strategy',
    'IDG_Recurring_Content_Workflow_Action_Strategy',
] as $class) {
    routing_ok(str_contains($strategies, 'class ' . $class), "estrategia {$class} disponible");
}
foreach ([
    'execute_generate_stage',
    'execute_editorial_stage',
    'execute_seo_stage',
    'execute_draft_stage',
    'execute_recurring_content_stage',
] as $entrypoint) {
    routing_ok(str_contains($runner, 'public static function ' . $entrypoint), "entrada {$entrypoint} disponible");
}
routing_ok(str_contains($main, "interface-workflow-action-strategy.php") && str_contains($main, "class-workflow-action-strategies.php"), 'plugin carga contrato y centro de estrategias');
routing_ok(substr_count($strategies, 'IDG_Job_Runner::execute_') === 5, 'cada familia delega una vez al motor histórico');

echo "PASS workflow strategy runner routing static\n";
