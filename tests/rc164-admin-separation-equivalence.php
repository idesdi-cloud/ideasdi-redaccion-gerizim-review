<?php
$root = dirname(__DIR__);
$admin = file_get_contents($root . '/includes/class-admin-page.php') ?: '';
$facade = file_get_contents($root . '/includes/class-admin-page-facade.php') ?: '';
$view = file_get_contents($root . '/includes/class-workflow-admin-view.php') ?: '';
$support = file_get_contents($root . '/includes/class-workflow-admin-support.php') ?: '';
function rc164_ok(bool $ok, string $message): void { if (!$ok) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); } echo "OK: {$message}\n"; }
rc164_ok(str_contains($admin, 'final class IDG_Workflow_Admin_Controller'), 'implementación histórica trasladada al controlador');
foreach (['handle_submit_workflow', 'handle_download_report', 'render_workflow_page', 'store_radar_partial_reset_snapshot', 'validate_step_before_run'] as $method) {
    rc164_ok(str_contains($admin, 'function ' . $method), "método {$method} preservado");
}
rc164_ok(str_contains($facade, 'IDG_Workflow_Admin_Controller::handle_submit_workflow') && str_contains($facade, 'IDG_Workflow_Admin_View::render_workflow_page'), 'fachada conserva delegaciones públicas');
rc164_ok(str_contains($view, 'IDG_Workflow_Admin_Controller::render_workflow_page'), 'vista conserva renderizado del flujo');
rc164_ok(str_contains($support, 'has_generated_workflow_content'), 'soporte administrativo expone helper compartido');
rc164_ok(hash_file('sha256', $root . '/assets/admin.js') === 'ff0c3c0ba0cccb38103be0452aff75b21b799a14cbb7dede0351b96670a6a350', 'admin.js idéntico');
rc164_ok(hash_file('sha256', $root . '/assets/admin.css') === '257b481a61fd6b031fec33dfa14327b7d1e64f8341cc3454ddca797fd2cf4acd', 'admin.css idéntico');
echo "PASS RC1.6.4 admin separation equivalence\n";
