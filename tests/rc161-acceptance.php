<?php
$root = dirname(__DIR__);
$read = static fn(string $path): string => file_get_contents($root . '/' . $path) ?: '';
$main = $read('ideasdi-redaccion-gerizim.php');
$runner = $read('includes/class-job-runner.php');
$contract = $read('includes/contracts/interface-workflow-action-strategy.php');
$strategies = $read('includes/strategies/class-workflow-action-strategies.php');
$orchestrator = $read('includes/class-workflow-orchestrator.php');

function rc161_ok(bool $condition, string $message): void {
    static $number = 0;
    $number++;
    if (!$condition) {
        fwrite(STDERR, sprintf("FAIL S%02d: %s\n", $number, $message));
        exit(1);
    }
    echo sprintf("OK S%02d: %s\n", $number, $message);
}

rc161_ok(str_contains($main, 'Version: 0.4.0-RC1.6.2') && str_contains($main, "define('IDG_VERSION', '0.4.0-RC1.6.2')"), 'versión RC1.6.1 consistente');
rc161_ok(str_contains($main, "define('IDG_TRACEABILITY_DB_VERSION', '1.2.0')"), 'sin migración de base de datos');
rc161_ok(str_contains($contract, 'interface IDG_Workflow_Action_Strategy_Contract'), 'contrato de estrategia disponible');
rc161_ok(str_contains($strategies, 'final class IDG_Workflow_Action_Strategy_Center'), 'centro de estrategias disponible');
rc161_ok(str_contains($runner, 'IDG_Workflow_Action_Strategy_Center::execute'), 'runner delega selección y ejecución');
rc161_ok(!str_contains(substr($runner, strpos($runner, 'public static function process_scheduled_action'), 2600), "if (\$action === 'generate')"), 'selección legacy retirada del dispatcher');
rc161_ok(str_contains($orchestrator, 'IDG_Job_Runner::process_scheduled_action'), 'orquestador continúa delgado');

foreach ([
    'IDG_Generate_Workflow_Action_Strategy',
    'IDG_Editorial_Workflow_Action_Strategy',
    'IDG_Seo_Workflow_Action_Strategy',
    'IDG_Draft_Workflow_Action_Strategy',
    'IDG_Recurring_Content_Workflow_Action_Strategy',
] as $class) {
    rc161_ok(str_contains($strategies, 'class ' . $class), "{$class} registrada");
}
foreach ([
    "IDG_Workflow_Policies::ACTION_GENERATE",
    "IDG_Workflow_Policies::ACTION_EDITORIAL",
    "IDG_Workflow_Policies::ACTION_SEO",
    "IDG_Workflow_Policies::ACTION_DRAFT_FORCE",
    "IDG_Workflow_Policies::ACTION_RECURRING_CONTENT_FORCE",
] as $mapping) {
    rc161_ok(str_contains($strategies, $mapping), "mapeo {$mapping} preservado");
}
rc161_ok(str_contains($strategies, 'draft_force_requested') && str_contains($strategies, 'recurring_content_force_requested'), 'eventos de override preservados');
rc161_ok(str_contains($strategies, 'return false;') && str_contains($strategies, 'return null;'), 'acción desconocida conserva no-op');

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
    rc161_ok(hash_file('sha256', $root . '/' . $path) === $hash, "equivalencia SHA-256 de {$path}");
}

$complete_calls = 0;
foreach (glob($root . '/includes/*.php') ?: [] as $path) {
    $complete_calls += substr_count(file_get_contents($path) ?: '', '->complete(');
}
rc161_ok($complete_calls === 8, 'número de llamadas OpenAI permanece en ocho');
rc161_ok(substr_count($runner, 'IDG_Prompt_Library::') === 6, 'construcción de prompts del runner sin cambios');
rc161_ok(str_contains($read('tests/workflow-action-strategy-center-equivalence.php'), 'PASS workflow action strategy center equivalence'), 'prueba de equivalencia del centro incluida');
rc161_ok(str_contains($read('tests/workflow-strategy-runner-routing-static.php'), 'PASS workflow strategy runner routing static'), 'prueba de enrutamiento incluida');

echo "PASS RC1.6.1 strategy center acceptance\n";
