<?php
$root = dirname(__DIR__);
$main = file_get_contents($root . '/ideasdi-redaccion-gerizim.php') ?: '';
$admin = file_get_contents($root . '/includes/class-admin-page.php') ?: '';
$facade = file_get_contents($root . '/includes/class-admin-page-facade.php') ?: '';
$view = file_get_contents($root . '/includes/class-workflow-admin-view.php') ?: '';

function rc164_ok(bool $ok, string $message): void {
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

rc164_ok(
    str_contains($admin, 'final class IDG_Workflow_Admin_Controller'),
    'implementación histórica permanece en controlador'
);

foreach ([
    'handle_submit_workflow',
    'handle_download_report',
    'render_workflow_page',
    'store_radar_partial_reset_snapshot',
    'validate_step_before_run',
] as $method) {
    rc164_ok(
        str_contains($admin, 'function ' . $method),
        "método {$method} preservado"
    );
}

rc164_ok(
    str_contains($facade, 'IDG_Workflow_Admin_Controller::handle_submit_workflow')
    && str_contains($facade, 'IDG_Workflow_Admin_View::render_workflow_page'),
    'fachada conserva delegaciones públicas'
);

rc164_ok(
    str_contains($view, 'IDG_Workflow_Admin_Controller::render_workflow_page'),
    'vista conserva renderizado del flujo'
);

rc164_ok(
    str_contains($admin, "require_once __DIR__ . '/class-workflow-admin-view.php'")
    && str_contains($admin, "require_once __DIR__ . '/class-admin-page-facade.php'"),
    'módulo administrativo conserva carga autónoma de vista y fachada'
);

rc164_ok(
    !file_exists($root . '/includes/class-workflow-admin-support.php'),
    'wrapper administrativo sin consumidores retirado'
);

rc164_ok(
    !str_contains($main, 'class-workflow-admin-support.php')
    && !str_contains($main, 'class-workflow-admin-view.php')
    && !str_contains($main, 'class-admin-page-facade.php'),
    'bootstrap duplicado retirado del plugin principal'
);

rc164_ok(
    str_contains($facade, 'store_radar_partial_reset_snapshot')
    && str_contains($facade, 'restore_radar_partial_reset_snapshot')
    && str_contains($facade, 'radar_partial_reset_snapshot_key'),
    'compatibilidad privada de snapshots Radar preservada'
);

rc164_ok(
    hash_file('sha256', $root . '/assets/admin.js')
        === 'ff0c3c0ba0cccb38103be0452aff75b21b799a14cbb7dede0351b96670a6a350',
    'admin.js idéntico'
);

rc164_ok(
    hash_file('sha256', $root . '/assets/admin.css')
        === '257b481a61fd6b031fec33dfa14327b7d1e64f8341cc3454ddca797fd2cf4acd',
    'admin.css idéntico'
);

echo "PASS RC1.6.4 admin separation equivalence\n";
