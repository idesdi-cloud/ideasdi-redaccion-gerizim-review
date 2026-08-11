<?php
$root = dirname(__DIR__);
$read = static fn(string $path): string => file_get_contents($root . '/' . $path) ?: '';
$main = $read('ideasdi-redaccion-gerizim.php');
$runner = $read('includes/class-job-runner.php');
$planning = $read('includes/class-workflow-planning-pipeline.php');
$redaction = $read('includes/class-workflow-redaction-pipeline.php');
$orchestrator = $read('includes/class-workflow-stage-orchestrator.php');
$prompt_data = $read('includes/class-workflow-prompt-data.php');
$output_parser = $read('includes/class-workflow-output-parser.php');
$contract = $read('CONTRATO-RADAR-DIRECTUS-1.1.md');

function rc163_ok(bool $condition, string $message): void {
    static $number = 0;
    $number++;
    if (!$condition) {
        fwrite(STDERR, sprintf("FAIL D%02d: %s\n", $number, $message));
        exit(1);
    }
    echo sprintf("OK D%02d: %s\n", $number, $message);
}

rc163_ok(str_contains($main, 'Version: 0.4.0-RC1.6.3.2') && str_contains($main, "define('IDG_VERSION', '0.4.0-RC1.6.3.2')"), 'versión RC1.6.3.2 consistente');
rc163_ok(str_contains($main, "define('IDG_TRACEABILITY_DB_VERSION', '1.2.0')"), 'sin migración de base de datos');
rc163_ok(str_contains($contract, 'trazabilidad 1.1') && str_contains($contract, '0.4.0-RC1.6.3'), 'contrato Radar/Directus 1.1 preservado');
rc163_ok(str_contains($read('includes/class-workflow-contract.php'), "public const FORMAT = 'legacy-array-v1'"), 'formato legacy-array-v1 preservado');
rc163_ok(str_contains($read('includes/contracts/interface-workflow-planning-pipeline.php'), 'IDG_Workflow_Planning_Pipeline_Contract'), 'contrato de planificación disponible');
rc163_ok(str_contains($read('includes/contracts/interface-workflow-redaction-pipeline.php'), 'IDG_Workflow_Redaction_Pipeline_Contract'), 'contrato de redacción disponible');
rc163_ok(str_contains($read('includes/contracts/interface-workflow-stage-orchestrator.php'), 'IDG_Workflow_Stage_Orchestrator_Contract'), 'contrato de orquestación de etapas disponible');
rc163_ok(str_contains($read('includes/adapters/class-workflow-stage-input-adapters.php'), 'IDG_Planning_Workflow_Stage_Input_Adapter') && str_contains($read('includes/adapters/class-workflow-stage-input-adapters.php'), 'IDG_Redaction_Workflow_Stage_Input_Adapter'), 'adaptadores internos disponibles');
rc163_ok(str_contains($orchestrator, 'self::adapt') && str_contains($orchestrator, 'IDG_Workflow_Planning_Pipeline') && str_contains($orchestrator, 'IDG_Workflow_Redaction_Pipeline'), 'orquestador de etapas permanece delgado');
rc163_ok(substr_count($runner, 'IDG_Workflow_Stage_Orchestrator::redact') === 3, 'runner delega las tres fases de texto');
rc163_ok(!str_contains($runner, 'IDG_Prompt_Library::') && !str_contains($runner, '->complete('), 'runner desacoplado de prompts y modelo');
rc163_ok(!str_contains($runner, 'ensure_editorial_plan') && !str_contains($runner, 'ensure_document_card'), 'runner desacoplado de planificación');
rc163_ok(str_contains($planning, 'editorial_plan_prompt') && str_contains($planning, 'material_card_prompt'), 'planificación conserva prompts existentes');
rc163_ok(str_contains($redaction, 'generate_prompt') && str_contains($redaction, 'editorial_prompt') && str_contains($redaction, 'seo_prompt'), 'redacción conserva fases existentes');
rc163_ok(!str_contains($planning . $redaction, 'IDG_Post_Creator::'), 'pipelines no publican');
rc163_ok(str_contains($runner, 'IDG_Post_Creator::create_pending_post') && str_contains($runner, 'IDG_Post_Creator::update_existing_event'), 'publicación histórica permanece en runner');
rc163_ok(str_contains($prompt_data, "'keyword'") && str_contains($prompt_data, "'temporary_material_mode'") && str_contains($prompt_data, "'article'"), 'payload de prompt conservado');
rc163_ok(str_contains($output_parser, 'parse_editorial') && str_contains($output_parser, 'extract_feedback_notes'), 'postprocesamiento determinista separado');

$expected_hashes = [
    'assets/admin.js' => 'ff0c3c0ba0cccb38103be0452aff75b21b799a14cbb7dede0351b96670a6a350',
    'assets/admin.css' => '257b481a61fd6b031fec33dfa14327b7d1e64f8341cc3454ddca797fd2cf4acd',
    'includes/class-prompt-library.php' => '5535975f1e289e5354dc18bdb374f031ceaeedef99e0ab91234b71dc4d9e022b',
    'includes/class-validator.php' => '7b0e4cca2b089c0a85c98df4d9ab70eae1a5b03a3560cb6050b400933379b485',
    'includes/class-final-guard.php' => '8e4e06c7a4ce47e608ee230b50e36513357aa0682662e79365bce8e8612cca82',
    'includes/class-editorial-rules.php' => 'c77e4a3cb8d73a66377c2ff7f234f38faff5d1122e3df7fd333edeadacf55e1f',
    'includes/class-editorial-plan.php' => '041451207efc560142395ecce51e10d3357155afa6cfb4d5f3382e55c0196ebd',
    'includes/class-editorial-recipe-builder.php' => 'fee28b83033825666a5d4a4c803d226d3f7768c72fafb61f86218d8b26da5e6c',
    'includes/class-post-creator.php' => 'c3c40b9b8ac57d3cd1dbdba184b61f602049602e061faf4f7b0fcb3e1ff15b3d',
];
foreach ($expected_hashes as $path => $hash) {
    rc163_ok(hash_file('sha256', $root . '/' . $path) === $hash, "equivalencia SHA-256 de {$path}");
}

$complete_calls = 0;
foreach (glob($root . '/includes/*.php') ?: [] as $path) {
    $complete_calls += substr_count(file_get_contents($path) ?: '', '->complete(');
}
rc163_ok($complete_calls === 8, 'número de llamadas OpenAI permanece en ocho');
rc163_ok(substr_count($planning . $redaction, 'IDG_Prompt_Library::') === 6, 'seis puntos de construcción de prompts preservados');
rc163_ok(str_contains($read('tests/workflow-planning-redaction-equivalence.php'), 'PASS workflow planning redaction equivalence'), 'prueba de equivalencia incluida');
rc163_ok(str_contains($read('tests/workflow-planning-redaction-routing-static.php'), 'PASS workflow planning redaction routing static'), 'prueba estática incluida');
rc163_ok(str_contains($read('tests/workflow-policies-equivalence.php'), 'PASS workflow policies equivalence'), 'regresión de políticas incluida');
rc163_ok(str_contains($read('tests/workflow-action-strategy-center-equivalence.php'), 'PASS workflow action strategy center equivalence'), 'regresión de estrategias incluida');
rc163_ok(str_contains($read('tests/recurring-contest-structural-apply-mock.php'), 'PASS recurring contest structural apply mock'), 'regresión de concursos incluida');

 echo "PASS RC1.6.3 planning redaction decoupling acceptance\n";
