<?php
$root = dirname(__DIR__);
$read = static fn(string $path): string => file_get_contents($root . '/' . $path) ?: '';
$main = $read('ideasdi-redaccion-gerizim.php');
$contract = $read('includes/class-workflow-contract.php');
$adapter_contract = $read('includes/contracts/interface-workflow-input-adapter.php');
$orchestrator_contract = $read('includes/contracts/interface-workflow-orchestrator.php');
$adapters = $read('includes/adapters/class-workflow-input-adapters.php');
$orchestrator = $read('includes/class-workflow-orchestrator.php');
$admin = $read('includes/class-admin-page.php');
$runner = $read('includes/class-job-runner.php');
$recurring = $read('includes/class-recurring-updates.php');

function rc160_ok(bool $condition, string $message): void {
    static $number = 0;
    $number++;
    if (!$condition) {
        fwrite(STDERR, sprintf("FAIL C%02d: %s\n", $number, $message));
        exit(1);
    }
    echo sprintf("OK C%02d: %s\n", $number, $message);
}

rc160_ok(str_contains($main, 'Version: 0.4.0-RC1.6.2') && str_contains($main, "define('IDG_VERSION', '0.4.0-RC1.6.2')"), 'versión RC1.6.1 consistente');
rc160_ok(str_contains($main, "define('IDG_TRACEABILITY_DB_VERSION', '1.2.0')"), 'esquema de trazabilidad sin migración');
rc160_ok(str_contains($adapter_contract, 'interface IDG_Workflow_Input_Adapter_Contract'), 'contrato de adaptador disponible');
rc160_ok(str_contains($orchestrator_contract, 'interface IDG_Workflow_Orchestrator_Contract'), 'contrato de orquestador disponible');
rc160_ok(str_contains($contract, "public const FORMAT = 'legacy-array-v1'") && str_contains($contract, 'return $workflow;'), 'contrato preserva formato de workflow');
foreach (['IDG_Admin_Workflow_Input_Adapter', 'IDG_Radar_Workflow_Input_Adapter', 'IDG_Recurring_Workflow_Input_Adapter', 'IDG_Traceability_Workflow_Input_Adapter'] as $class) {
    rc160_ok(str_contains($adapters, 'class ' . $class), "adaptador {$class} disponible");
}
rc160_ok(str_contains($orchestrator, 'IDG_Job_Runner::new_workflow') && str_contains($orchestrator, 'IDG_Job_Runner::save_workflow'), 'orquestador delega persistencia al runner legado');
rc160_ok(str_contains($orchestrator, 'IDG_Job_Runner::schedule') && str_contains($orchestrator, 'IDG_Job_Runner::process_scheduled_action'), 'orquestador delega cola y ejecución');
rc160_ok(str_contains($main, "['IDG_Workflow_Orchestrator', 'process_scheduled_action']"), 'hook principal usa orquestador');
rc160_ok(str_contains($admin, "adapt(\$data, 'radar')") && str_contains($admin, "create(\$data, 'radar')"), 'entrada Radar pasa por adaptador');
rc160_ok(str_contains($admin, "? 'recurring' : 'admin'") && str_contains($admin, 'IDG_Workflow_Orchestrator::process_scheduled_action'), 'entrada administrativa pasa por orquestador');
rc160_ok(str_contains($recurring, "IDG_Workflow_Orchestrator::create(\$workflow_data, 'recurring')"), 'actualizaciones recurrentes usan adaptador dedicado');
rc160_ok(str_contains($runner, "class_exists('IDG_Workflow_Orchestrator')") && str_contains($runner, 'self::process_scheduled_action'), 'fallback mantiene compatibilidad sin dependencia dura');
rc160_ok(str_contains($runner, 'public static function new_workflow') && str_contains($runner, 'public static function get_workflow') && str_contains($runner, 'public static function save_workflow'), 'API legacy de workflows permanece disponible');

$expected_hashes = [
    'assets/admin.js' => 'ff0c3c0ba0cccb38103be0452aff75b21b799a14cbb7dede0351b96670a6a350',
    'assets/admin.css' => '257b481a61fd6b031fec33dfa14327b7d1e64f8341cc3454ddca797fd2cf4acd',
    'includes/class-prompt-library.php' => '5535975f1e289e5354dc18bdb374f031ceaeedef99e0ab91234b71dc4d9e022b',
    'includes/class-openai-client.php' => '9d78583fe37059da513edb1b4af84eb12add32245b7dd495b97e5c40196fc072',
    'includes/class-validator.php' => '7b0e4cca2b089c0a85c98df4d9ab70eae1a5b03a3560cb6050b400933379b485',
    'includes/class-final-guard.php' => '8e4e06c7a4ce47e608ee230b50e36513357aa0682662e79365bce8e8612cca82',
    'includes/class-editorial-rules.php' => 'c77e4a3cb8d73a66377c2ff7f234f38faff5d1122e3df7fd333edeadacf55e1f',
    'includes/class-editorial-plan.php' => '041451207efc560142395ecce51e10d3357155afa6cfb4d5f3382e55c0196ebd',
    'includes/class-editorial-recipe-builder.php' => 'fee28b83033825666a5d4a4c803d226d3f7768c72fafb61f86218d8b26da5e6c',
];
foreach ($expected_hashes as $path => $hash) {
    rc160_ok(hash_file('sha256', $root . '/' . $path) === $hash, "equivalencia SHA-256 de {$path}");
}

$complete_calls = 0;
foreach (glob($root . '/includes/*.php') ?: [] as $path) {
    $complete_calls += substr_count(file_get_contents($path) ?: '', '->complete(');
}
rc160_ok($complete_calls === 8, 'número de llamadas OpenAI permanece en ocho');
rc160_ok(substr_count($runner, 'IDG_Prompt_Library::') === 6, 'puntos de construcción de prompts del runner sin cambios');
rc160_ok(str_contains($read('includes/class-post-creator.php'), 'has_category(34, $post_before)') && str_contains($read('includes/class-post-creator.php'), 'target_post_fingerprint($post_id, $content_type)'), 'escritor recurrente amplía destino a concursos con identidad protegida');
rc160_ok(str_contains($recurring, 'build_contest_editorial_workflow_data') && !str_contains($recurring, 'La escritura de concursos o convocatorias permanece desactivada'), 'concursos pueden aplicar datos y preparar workflow editorial');
rc160_ok(str_contains($read('tests/workflow-adapters-equivalence.php'), 'PASS workflow adapters equivalence'), 'prueba de adaptadores incluida');
rc160_ok(str_contains($read('tests/workflow-orchestrator-equivalence.php'), 'PASS workflow orchestrator equivalence'), 'prueba de orquestación incluida');

echo "PASS RC1.6.1 preserves RC1.6.0 contracts, orchestration and recurring contest write acceptance\n";
