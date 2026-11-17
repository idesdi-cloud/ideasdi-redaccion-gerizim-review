<?php
/**
 * GER-EDITORIAL-2026-01: WP-native Gutenberg integration, read-only.
 * Run only on an isolated staging install: wp eval-file tests/p0-wp-native-integration.php
 * No post/option/meta writes, no queued actions and no network calls.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || !defined('ABSPATH') || !function_exists('home_url')) {
    fwrite(STDERR, "STOP: WordPress CLI bootstrap required.\n");
    exit(2);
}
$host = (string) parse_url(home_url(), PHP_URL_HOST);
if ($host === '' || in_array(strtolower($host), ['ideasdi.com', 'www.ideasdi.com'], true)) {
    fwrite(STDERR, "STOP: production WordPress is forbidden.\n");
    exit(2);
}
if (!function_exists('parse_blocks') || !function_exists('serialize_blocks')
    || !class_exists('IDG_Post_Creator') || !class_exists('IDG_Final_Guard')) {
    fwrite(STDERR, "STOP: native WordPress blocks API and active Gerizim required.\n");
    exit(2);
}
$cases = [
    'simple' => "<h2>Diseño industrial</h2>\n<p>Un proyecto de Estudio Alfa en Bogotá.</p>\n<h3>Materiales</h3>\n<p>La pieza utiliza madera.</p>",
    'list' => "<h2>Diseño y producción</h2>\n<p>La firma presenta un sistema.</p>\n<ul>\n<li>Material reciclado</li>\n<li>Ensamblaje local</li>\n</ul>\n<p>Cierre factual.</p>",
    'attrs' => "<h2>Arquitectura</h2>\n<p class=\"featured-snippet-box\">Estudio Alfa presenta un nuevo edificio en Bogotá.</p>\n<p>El enlace <a href=\"https://example.com\">oficial</a> mantiene su texto.</p>",
];
$convert = new ReflectionMethod('IDG_Post_Creator', 'html_to_gutenberg_blocks');
$passed = 0; $total = 0;
$check = static function (string $name, bool $ok) use (&$passed, &$total): void {
    ++$total;
    if ($ok) ++$passed;
    echo ($ok ? 'PASS' : 'FAIL') . ' | ' . $name . "\n";
};
foreach ($cases as $name => $html) {
    $content = $convert->invoke(null, $html);
    $blocks = parse_blocks($content);
    $validation = IDG_Final_Guard::validate_gutenberg_blocks($content);
    $check("roundtrip $name", serialize_blocks($blocks) === $content);
    $check("guard $name", !empty($validation['ok']));
    $check("allowed $name", !array_filter($blocks,
        static fn(array $block): bool => !in_array($block['blockName'] ?? null, ['core/paragraph', 'core/heading', 'core/list'], true)));
}
$unknown = $convert->invoke(null, '<div>Una estructura inválida.</div>');
$check('unrecognized HTML rejected', empty(IDG_Final_Guard::validate_gutenberg_blocks($unknown)['ok']));
$check('freeform HTML rejected', empty(IDG_Final_Guard::validate_gutenberg_blocks('<h2>Sin delimitador</h2>')['ok']));
$check('unclosed Gutenberg block rejected', empty(IDG_Final_Guard::validate_gutenberg_blocks('<!-- wp:paragraph --><p>Texto sin cierre</p>')['ok']));
echo "SUMMARY $passed/$total | ".($passed === $total ? 'PASS' : 'FAIL')."\n";
exit($passed === $total ? 0 : 1);
