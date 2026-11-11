<?php
declare(strict_types=1);
// Synthetic, local-only POST gate regression. No live WordPress.
require __DIR__ . '/rc1782-targeted-regression.php';
require_once ABSPATH . 'includes/class-sanitizer.php';
if (!function_exists('wp_unslash')) {
    function wp_unslash($value) { return $value; }
}
if (!function_exists('admin_url')) {
    function admin_url($path = '') { return 'https://example.invalid/wp-admin/' . ltrim($path, '/'); }
}
if (!function_exists('wp_safe_redirect')) {
    function wp_safe_redirect($url) { $GLOBALS['probe_redirect'] = $url; return true; }
}
if (!function_exists('wp_die')) {
    function wp_die($message) { throw new RuntimeException((string) $message); }
}
if (!function_exists('check_admin_referer')) {
    function check_admin_referer($action) {
        if (!wp_verify_nonce((string) ($_POST['_wpnonce'] ?? ''), (string) $action)) {
            wp_die('Invalid nonce');
        }
        return 1;
    }
}
require_once ABSPATH . 'includes/class-temporary-material.php';
require_once ABSPATH . 'includes/contracts/interface-workflow-orchestrator.php';
if (!class_exists('IDG_Workflow_Input_Adapter_Registry')) {
    final class IDG_Workflow_Input_Adapter_Registry {
        public static function for_source(string $source): object {
            return new class {
                public function adapt(array $workflow): array { return $workflow; }
            };
        }
    }
}
require_once ABSPATH . 'includes/class-workflow-orchestrator.php';
require_once ABSPATH . 'includes/class-admin-page.php';

$case = (string) ($argv[2] ?? '');
if (($argv[1] ?? '') === '--probe') {
    $id = 'rc17857-post-' . $case;
    $wf = rc1782_workflow($id);
    $wf['status'] = 'failed'; // Avoid masking independent guards with fixture's default processing state.
    if ($case === 'foreign-owner') $wf['user_id'] = 8;
    if ($case === 'draft') $wf['draft_post_id'] = 91;
    if ($case === 'recurring') $wf['workflow_origin'] = 'recurring';
    if ($case === 'processing') $wf['status'] = 'processing';
    if ($case === 'no-capability') $GLOBALS['rc1782_can_edit'] = false;
    if ($case !== 'missing-workflow') $GLOBALS['rc1782_options'][$id] = $wf;
    $before = get_option($id, []);
    $meta_before = $GLOBALS['rc1782_user_meta'];
    $http_before = count($GLOBALS['rc1782_http_calls']);
    $posts_before = count($GLOBALS['rc1782_posts']);
    $_POST = [
        'workflow_id' => $id,
        'step' => 'revalidate_without_ai',
        '_wpnonce' => $case === 'invalid-nonce' ? 'bad' : wp_create_nonce('idg_submit_workflow'),
        'keyword' => 'MUTACION INDEBIDA',
        'base_article' => 'MUTACION INDEBIDA',
        'temp_material_text' => 'MUTACION INDEBIDA',
    ];
    register_shutdown_function(static function () use ($id, $case, $before, $meta_before, $http_before, $posts_before) {
        $last = error_get_last();
        $fatal = is_array($last) && in_array((int) ($last['type'] ?? 0), [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)
            ? (string) ($last['message'] ?? '') : '';
        echo "\nPOST_GATE_RESULT=" . json_encode([
            'case' => $case,
            'unchanged' => get_option($id, []) === $before,
            'meta_unchanged' => $GLOBALS['rc1782_user_meta'] === $meta_before,
            'http_unchanged' => count($GLOBALS['rc1782_http_calls']) === $http_before,
            'posts_unchanged' => count($GLOBALS['rc1782_posts']) === $posts_before,
            'fatal' => $fatal,
            'redirect' => $GLOBALS['probe_redirect'] ?? '',
            'error' => $GLOBALS['probe_error'] ?? '',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    });
    try {
        IDG_Workflow_Admin_Controller::handle_submit_workflow();
    } catch (Throwable $e) {
        $GLOBALS['probe_error'] = $e->getMessage();
    }
    exit;
}
foreach (['foreign-owner', 'draft', 'recurring', 'processing', 'invalid-nonce', 'no-capability', 'missing-workflow'] as $name) {
    $cmd = [PHP_BINARY, __FILE__, '--probe', $name];
    $p = proc_open($cmd, [1=>['pipe','w'], 2=>['pipe','w']], $pipes);
    rc1782_ok(is_resource($p), "POST $name: child started");
    $out = (string) stream_get_contents($pipes[1]);
    $err = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $exit = proc_close($p);
    $marker = 'POST_GATE_RESULT=';
    $pos = strrpos($out, $marker);
    $data = $pos === false ? null : json_decode(trim(substr($out, $pos + strlen($marker))), true);
    rc1782_ok($exit === 0 && is_array($data) && empty($data['fatal']), "POST $name: controller reached shutdown, no PHP fatal (" . substr($err,0,100) . ")");
    if (in_array($name, ['invalid-nonce', 'no-capability'], true)) {
        rc1782_ok(!empty($data['error']), "POST $name: permission/nonce check actually rejected");
    } else {
        rc1782_ok(str_contains((string) ($data['redirect'] ?? ''), 'message=revalidation_blocked'),
            "POST $name: reserved action explicitly blocked instead of falling through to SAVE");
    }
    rc1782_ok(!empty($data['unchanged']) && !empty($data['meta_unchanged'])
        && !empty($data['http_unchanged']) && !empty($data['posts_unchanged']),
        "POST $name: no workflow, session, HTTP or post writes");
}
echo "RC17857_POST_GATE_SAFE\n";
