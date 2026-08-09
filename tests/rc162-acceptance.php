<?php
$root = dirname(__DIR__);
$read = static fn(string $path): string => file_get_contents($root . '/' . $path) ?: '';
$main = $read('ideasdi-redaccion-gerizim.php');
$policy = $read('includes/class-workflow-policies.php');
$contract = $read('includes/class-workflow-contract.php');
$runner = $read('includes/class-job-runner.php');
$planning_redaction = $read('includes/class-workflow-planning-pipeline.php') . $read('includes/class-workflow-redaction-pipeline.php');
$admin = $read('includes/class-admin-page.php');
$strategies = $read('includes/strategies/class-workflow-action-strategies.php');
$orchestrator = $read('includes/class-workflow-orchestrator.php');

function rc162_ok(bool $condition, string $message): void {
    static $number = 0;
    $number++;
    if (!$condition) {
        fwrite(STDERR, sprintf("FAIL P%02d: %s\n", $number, $message));
        exit(1);
    }
    echo sprintf("OK P%02d: %s\n", $number, $message);
}

rc162_ok(str_contains($main, 'Version: 0.4.0-RC1.6.3') && str_contains($main, "define('IDG_VERSION', '0.4.0-RC1.6.3')"), 'versión RC1.6.2 consistente');
rc162_ok(str_contains($main, "define('IDG_TRACEABILITY_DB_VERSION', '1.2.0')"), 'sin migración de base de datos');
rc162_ok(str_contains($main, "includes/class-workflow-policies.php"), 'centro de políticas cargado');
rc162_ok(str_contains($policy, 'final class IDG_Workflow_Policies'), 'clase de políticas disponible');
rc162_ok(str_contains($policy, "public const VERSION = '1.0'"), 'versión de políticas explícita');
foreach (['STATUS_DRAFT', 'STATUS_PROCESSING', 'STATUS_COMPLETED', 'STATUS_FAILED'] as $constant) {
    rc162_ok(str_contains($policy, $constant), "estado {$constant} centralizado");
}
foreach (['ACTION_GENERATE', 'ACTION_EDITORIAL', 'ACTION_SEO', 'ACTION_DRAFT', 'ACTION_DRAFT_FORCE', 'ACTION_RECURRING_CONTENT', 'ACTION_RECURRING_CONTENT_FORCE'] as $constant) {
    rc162_ok(str_contains($policy, $constant), "acción {$constant} centralizada");
}
rc162_ok(str_contains($policy, 'ALLOWED_TRANSITIONS') && str_contains($policy, 'transition_allowed'), 'mapa de transiciones centralizado');
rc162_ok(str_contains($policy, 'advance_block_reason') && str_contains($admin, 'IDG_Workflow_Policies::advance_block_reason'), 'condiciones de avance centralizadas y consumidas');
rc162_ok(str_contains($policy, 'MAX_AUTOMATIC_RETRIES = 0') && str_contains($policy, "return 'manual_only'"), 'reintento editorial continúa manual');
rc162_ok(str_contains($policy, 'blocks_interactive_mutation') && substr_count($admin, 'blocks_interactive_mutation') >= 4, 'bloqueos processing centralizados');
rc162_ok(str_contains($contract, 'IDG_Workflow_Policies::is_known_action'), 'contrato usa catálogo de políticas');
rc162_ok(str_contains($strategies, 'IDG_Workflow_Policies::is_force_action'), 'estrategias usan política de override');
rc162_ok(str_contains($runner, 'IDG_Workflow_Policies::mark_processing') && str_contains($runner, 'IDG_Workflow_Policies::mark_failed') && str_contains($runner, 'IDG_Workflow_Policies::mark_completed'), 'runner usa transiciones centralizadas');
rc162_ok(str_contains($runner, 'IDG_Workflow_Policies::history_limit') && str_contains($runner, 'IDG_Workflow_Policies::should_retry'), 'runner usa historial y reintentos centrales');
rc162_ok(str_contains($orchestrator, 'IDG_Workflow_Policies::automatic_retry_limit') && str_contains($orchestrator, 'IDG_Job_Runner::schedule'), 'orquestador conserva delegación y política de cola');
rc162_ok(!str_contains($runner, "['status'] = 'processing'") && !str_contains($runner, "['status'] = 'failed'") && !str_contains($runner, "['status'] = 'completed'"), 'estados retirados del runner');
rc162_ok(!str_contains($contract, 'private const KNOWN_ACTIONS'), 'catálogo duplicado retirado del contrato');
rc162_ok(!str_contains($admin, "in_array(\$step, ['generate', 'editorial', 'seo'"), 'catálogo duplicado retirado del panel');

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
    rc162_ok(hash_file('sha256', $root . '/' . $path) === $hash, "equivalencia SHA-256 de {$path}");
}

$complete_calls = 0;
foreach (glob($root . '/includes/*.php') ?: [] as $path) {
    $complete_calls += substr_count(file_get_contents($path) ?: '', '->complete(');
}
rc162_ok($complete_calls === 8, 'número de llamadas OpenAI permanece en ocho');
rc162_ok(substr_count($planning_redaction, 'IDG_Prompt_Library::') === 6, 'puntos de construcción de prompts preservados en pipelines');
rc162_ok(str_contains($read('tests/workflow-policies-equivalence.php'), 'PASS workflow policies equivalence'), 'prueba de equivalencia de políticas incluida');
rc162_ok(str_contains($read('tests/workflow-policies-routing-static.php'), 'PASS workflow policies routing static'), 'prueba estática de políticas incluida');
rc162_ok(str_contains($read('tests/workflow-action-strategy-center-equivalence.php'), 'PASS workflow action strategy center equivalence'), 'regresión del centro de estrategias incluida');
rc162_ok(str_contains($read('tests/recurring-contest-structural-apply-mock.php'), 'PASS recurring contest structural apply mock'), 'regresión funcional de concursos incluida');

 echo "PASS RC1.6.2 centralized policies acceptance\n";
