<?php
$root = dirname(__DIR__);
$read = static fn(string $path): string => file_get_contents($root . '/' . $path) ?: '';
$main = $read('ideasdi-redaccion-gerizim.php');
$runner = $read('includes/class-job-runner.php');
$planning = $read('includes/class-workflow-planning-pipeline.php');
$redaction = $read('includes/class-workflow-redaction-pipeline.php');
$orchestrator = $read('includes/class-workflow-stage-orchestrator.php');
$prompt_data = $read('includes/class-workflow-prompt-data.php');
$adapters = $read('includes/adapters/class-workflow-stage-input-adapters.php');

function split_static_ok(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

split_static_ok(str_contains($main, 'interface-workflow-planning-pipeline.php') && str_contains($main, 'interface-workflow-redaction-pipeline.php'), 'plugin carga contratos de planificación y redacción');
split_static_ok(str_contains($main, 'class-workflow-stage-input-adapters.php') && str_contains($main, 'class-workflow-stage-orchestrator.php'), 'plugin carga adaptadores y orquestador de etapas');
split_static_ok(str_contains($adapters, 'IDG_Planning_Workflow_Stage_Input_Adapter') && str_contains($adapters, 'IDG_Redaction_Workflow_Stage_Input_Adapter'), 'fronteras internas tienen adaptadores transparentes');
split_static_ok(str_contains($orchestrator, 'IDG_Workflow_Planning_Pipeline') && str_contains($orchestrator, 'IDG_Workflow_Redaction_Pipeline'), 'orquestador delega en pipelines separados');
split_static_ok(substr_count($orchestrator, 'new IDG_OpenAI_Client()') === 1, 'una sola instancia de cliente por fase conserva secuencia');
split_static_ok(substr_count($runner, 'IDG_Workflow_Stage_Orchestrator::redact') === 3, 'runner delega generate, editorial y seo');
split_static_ok(!str_contains($runner, 'IDG_Prompt_Library::') && !str_contains($runner, '->complete('), 'runner ya no contiene prompts ni llamadas de redacción');
split_static_ok(!str_contains($runner, 'ensure_document_card') && !str_contains($runner, 'ensure_editorial_plan') && !str_contains($runner, 'prepare_prompt_data'), 'runner ya no contiene planificación');
split_static_ok(str_contains($planning, 'material_card_prompt') && str_contains($planning, 'editorial_plan_prompt'), 'pipeline de planificación conserva ficha y plan');
split_static_ok(str_contains($redaction, 'generate_prompt') && str_contains($redaction, 'editorial_prompt') && str_contains($redaction, 'seo_prompt'), 'pipeline de redacción conserva las tres fases');
split_static_ok(str_contains($prompt_data, "'temporary_material_excerpt'") && str_contains($prompt_data, "'editorial_plan'"), 'payload histórico de prompts está encapsulado');
split_static_ok(!str_contains($redaction, 'IDG_Post_Creator::') && !str_contains($planning, 'IDG_Post_Creator::'), 'planificación y redacción no publican');
split_static_ok(str_contains($runner, 'IDG_Post_Creator::create_pending_post') && str_contains($runner, 'IDG_Post_Creator::update_existing_event'), 'publicación permanece en el runner histórico');

$complete_calls = 0;
foreach (glob($root . '/includes/*.php') ?: [] as $path) {
    $complete_calls += substr_count(file_get_contents($path) ?: '', '->complete(');
}
split_static_ok($complete_calls === 8, 'número total de llamadas OpenAI permanece en ocho');
split_static_ok(substr_count($planning . $redaction, 'IDG_Prompt_Library::') === 6, 'seis puntos de construcción de prompts preservados');

echo "PASS workflow planning redaction routing static\n";
