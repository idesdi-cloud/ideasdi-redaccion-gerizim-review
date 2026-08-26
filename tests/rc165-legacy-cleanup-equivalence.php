<?php
$root = dirname(__DIR__);
$read = static fn(string $path): string =>
    file_get_contents($root . '/' . $path) ?: '';

$main = $read('ideasdi-redaccion-gerizim.php');
$admin = $read('includes/class-admin-page.php');
$facade = $read('includes/class-admin-page-facade.php');
$orchestrator = $read('includes/class-workflow-orchestrator.php');
$contract = $read('includes/class-workflow-contract.php');
$adapters = $read('includes/adapters/class-workflow-input-adapters.php');
$planningRedaction =
    $read('includes/class-workflow-planning-pipeline.php')
    . $read('includes/class-workflow-redaction-pipeline.php');

function rc165_ok(bool $ok, string $message): void {
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

rc165_ok(
    str_contains($main, 'Version: 0.4.0-RC1.6.5')
    && str_contains($main, "define('IDG_VERSION', '0.4.0-RC1.6.5')"),
    'versión RC1.6.5 consistente'
);

rc165_ok(
    !file_exists($root . '/includes/class-workflow-admin-support.php'),
    'wrapper Admin Support sin consumidor eliminado'
);

rc165_ok(
    !str_contains($main, 'class-workflow-admin-support.php')
    && !str_contains($main, 'class-workflow-admin-view.php')
    && !str_contains($main, 'class-admin-page-facade.php'),
    'bootstrap principal ya no duplica módulos administrativos'
);

rc165_ok(
    str_contains($admin, "require_once __DIR__ . '/class-workflow-admin-view.php'")
    && str_contains($admin, "require_once __DIR__ . '/class-admin-page-facade.php'"),
    'class-admin-page permanece autocontenido'
);

rc165_ok(
    str_contains($facade, 'final class IDG_Admin_Page')
    && str_contains($facade, 'store_radar_partial_reset_snapshot')
    && str_contains($facade, 'restore_radar_partial_reset_snapshot'),
    'fachada histórica y snapshots Radar preservados'
);

rc165_ok(
    !str_contains($orchestrator, 'IDG_Workflow_Contract::is_known_action')
    && !str_contains($orchestrator, 'IDG_Workflow_Policies::automatic_retry_limit')
    && str_contains($orchestrator, 'IDG_Job_Runner::schedule')
    && str_contains($orchestrator, 'IDG_Job_Runner::process_scheduled_action'),
    'consultas sin efecto retiradas y delegación preservada'
);

rc165_ok(
    str_contains($contract, "public const FORMAT = 'legacy-array-v1'")
    && str_contains($contract, 'public static function preserve'),
    'workflow legacy-array-v1 preservado'
);

foreach ([
    'IDG_Admin_Workflow_Input_Adapter',
    'IDG_Radar_Workflow_Input_Adapter',
    'IDG_Recurring_Workflow_Input_Adapter',
    'IDG_Traceability_Workflow_Input_Adapter',
] as $class) {
    rc165_ok(
        str_contains($adapters, 'class ' . $class),
        "adaptador {$class} preservado"
    );
}

$completeCalls = 0;
foreach (glob($root . '/includes/*.php') ?: [] as $path) {
    $completeCalls += substr_count(
        file_get_contents($path) ?: '',
        '->complete('
    );
}

rc165_ok(
    $completeCalls === 8,
    'ocho llamadas OpenAI preservadas'
);

rc165_ok(
    substr_count($planningRedaction, 'IDG_Prompt_Library::') === 6,
    'seis puntos de construcción de prompts preservados'
);

foreach ([
    'assets/admin.js'
        => 'ff0c3c0ba0cccb38103be0452aff75b21b799a14cbb7dede0351b96670a6a350',
    'assets/admin.css'
        => '257b481a61fd6b031fec33dfa14327b7d1e64f8341cc3454ddca797fd2cf4acd',
    'includes/class-prompt-library.php'
        => '5535975f1e289e5354dc18bdb374f031ceaeedef99e0ab91234b71dc4d9e022b',
    'includes/class-validator.php'
        => '7b0e4cca2b089c0a85c98df4d9ab70eae1a5b03a3560cb6050b400933379b485',
    'includes/class-final-guard.php'
        => '8e4e06c7a4ce47e608ee230b50e36513357aa0682662e79365bce8e8612cca82',
    'includes/class-editorial-rules.php'
        => 'c77e4a3cb8d73a66377c2ff7f234f38faff5d1122e3df7fd333edeadacf55e1f',
    'includes/class-editorial-plan.php'
        => '041451207efc560142395ecce51e10d3357155afa6cfb4d5f3382e55c0196ebd',
    'includes/class-editorial-recipe-builder.php'
        => 'fee28b83033825666a5d4a4c803d226d3f7768c72fafb61f86218d8b26da5e6c',
    'includes/class-post-creator.php'
        => 'c3c40b9b8ac57d3cd1dbdba184b61f602049602e061faf4f7b0fcb3e1ff15b3d',
    'includes/class-recurring-updates.php'
        => 'f3bdb6742c07726eae92772e45575439c3c6e06d503c2d1e64e0bff5158b7db4',
] as $path => $hash) {
    rc165_ok(
        hash_file('sha256', $root . '/' . $path) === $hash,
        "equivalencia SHA-256 de {$path}"
    );
}

echo "PASS RC1.6.5 controlled legacy cleanup equivalence\n";
