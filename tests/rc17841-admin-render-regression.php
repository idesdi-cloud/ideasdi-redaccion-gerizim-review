<?php
declare(strict_types=1);

define('ABSPATH', dirname(__DIR__) . '/');
$GLOBALS['rc17841_options'] = ['sentinel' => true];
$GLOBALS['rc17841_posts'] = [];
$GLOBALS['rc17841_meta'] = [];
$GLOBALS['rc17841_http'] = [];
require_once ABSPATH . 'includes/class-admin-page.php';

$method = new ReflectionMethod('IDG_Workflow_Admin_Controller', 'should_show_force_draft');
$method->setAccessible(true);
$decide = static fn (array $workflow): bool => $method->invoke(null, $workflow);
$base = ['workflow_id' => 'RC17841-' . uniqid('', true)];
$before = [
    'options' => $GLOBALS['rc17841_options'],
    'posts' => $GLOBALS['rc17841_posts'],
    'meta' => $GLOBALS['rc17841_meta'],
    'http' => $GLOBALS['rc17841_http'],
];

function rc17841_ok(bool $condition, string $label): void { if (!$condition) { fwrite(STDERR, "FAIL {$label}\n"); exit(1); } echo "OK {$label}\n"; }
rc17841_ok(!$decide($base), 'render vacío no muestra excepción');
rc17841_ok($decide($base + ['base_article' => 'Artículo base']), 'render base-only muestra acción diagnóstica');
rc17841_ok($decide($base + ['editorial_result' => 'Salida editorial']), 'render editorial-only muestra acción diagnóstica');
rc17841_ok(!$decide($base + ['article_source' => 'desconocido']), 'fuente inválida no habilita acción');
rc17841_ok($decide($base + ['seo_result' => 'SEO', 'seo_validation' => ['ok' => false]]), 'SEO inválido mantiene acción diagnóstica');
rc17841_ok(!$decide($base + ['seo_result' => 'SEO', 'seo_validation' => ['ok' => true]]), 'SEO validado no muestra excepción');
rc17841_ok(!$decide($base + ['base_article' => 'Artículo', 'draft_post_id' => 12]), 'workflow con draft existente queda cerrado');
rc17841_ok(!$decide($base + ['base_article' => 'Artículo', 'workflow_origin' => 'recurring_update', 'recurring_target_post_id' => 12]), 'workflow recurrente queda cerrado');
rc17841_ok($before === [
    'options' => $GLOBALS['rc17841_options'],
    'posts' => $GLOBALS['rc17841_posts'],
    'meta' => $GLOBALS['rc17841_meta'],
    'http' => $GLOBALS['rc17841_http'],
], 'decisión de render no escribe, no llama remoto ni ejecuta preflight/Gutenberg');

echo "RC17841_ADMIN_RENDER_OK\n";
