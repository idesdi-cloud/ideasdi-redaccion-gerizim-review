<?php
// Standalone prompt/copy regression; no WordPress, network or Git mutations.
define('ABSPATH', dirname(__DIR__) . '/');
function get_option($key, $default = []) { return $default; }
if (!function_exists('mb_stripos')) {
    function mb_stripos($haystack, $needle) { return stripos($haystack, $needle); }
}
final class IDG_Internal_Links {
    public static function normalize(array $data): array { return []; }
}
require ABSPATH . 'includes/class-prompt-library.php';
function rc170_ok(bool $condition, string $message): void {
    if (!$condition) { fwrite(STDERR, "FAIL: $message\n"); exit(1); }
    echo "OK: $message\n";
}
$helper = new ReflectionMethod(IDG_Prompt_Library::class, 'responsible_url');
$helper->setAccessible(true);
$canonical = 'https://canonical.example/official';
$explicit = 'https://responsible.example/official';
$legacy = 'https://legacy.example/official';
$document = 'https://document.example/source';
$cases = [
    [['canonical_context' => ['responsible_official_url' => $canonical], 'responsible_official_url' => $explicit, 'official_source' => $legacy], $canonical],
    [['canonical_context' => ['responsible_official_url' => ' '], 'responsible_official_url' => $explicit, 'official_source' => $legacy], $explicit],
    [['canonical_context' => ['responsible_official_url' => 'invalid'], 'responsible_official_url' => 'ftp://invalid.example/file', 'official_source' => $legacy], $legacy],
    [['official_source' => $legacy], $legacy],
    [['source_information_url' => $document], ''],
    [['canonical_context' => 'invalid', 'responsible_official_url' => [], 'official_source' => 'invalid'], ''],
    [['responsible_official_url' => " $explicit "], $explicit],
];
foreach ($cases as $index => [$data, $expected]) {
    $data['source_information_url'] = $document;
    rc170_ok($helper->invoke(null, $data) === $expected, "responsible URL precedence/validation $index");
    $seo = IDG_Prompt_Library::seo_prompt($data);
    $research = IDG_Prompt_Library::web_research_prompt($data, []);
    rc170_ok(str_contains($seo, "URL Diseñador / estudio / marca responsable para enlace externo: $expected\n") && str_contains($research, "URL responsable para enlace externo: $expected\n"), "resolved URL reaches article and research $index");
    rc170_ok(str_contains($seo, "URL documental / fuente complementaria: $document\n") && str_contains($research, "URL documental / fuente complementaria: $document\n"), "documentary source remains separate $index");
}
$prompt = file_get_contents(ABSPATH . 'includes/class-prompt-library.php');
$admin = file_get_contents(ABSPATH . 'includes/class-admin-page.php');
foreach (['https://ideasdi.com/eventos/', 'https://ideasdi.com/concursos-y-convocatorias-diseno/'] as $fixed) {
    rc170_ok(!str_contains($prompt, $fixed), "no fixed prompt URL $fixed");
}
rc170_ok(!preg_match('/(?:no\s*index|operativo)[^\n]*(?:enlaza|apuntar|página principal)[^\n]*categoría/iu', $prompt . $admin), 'no NoIndex to category fallback');
rc170_ok(!str_contains($prompt . $admin, 'Si el enlace disponible es una página de categoría, úsalo como enlace interno principal'), 'no category main link instruction');
foreach (['primero primary_lens', 'después secondary_lenses canónicas aprobadas en su orden', 'existente e indexable con URL real', 'marca unresolved', 'Los tags desconocidos o ajenos son solo contexto', 'consume como máximo la única URL interna', 'sin duplicar la URL', 'sin usar la keyword principal como anchor', 'No inventes hechos para el anchor'] as $text) {
    rc170_ok(str_contains(IDG_Prompt_Library::system_prompt(), $text), "system contract: $text");
}
$event = IDG_Prompt_Library::seo_prompt(['editorial_context' => 'event_calendar']);
rc170_ok(str_contains($event, 'si los datos suministran una URL real de archivo CPT o taxonomía propia') && str_contains($event, 'no fabriques una URL ni apliques el contrato de tags de article'), 'event navigation supplied, real and separate');
foreach (['Para consultar las bases completas y participar en [Nombre del concurso]', 'Para consultar la programación y la información actualizada de [Nombre del evento]'] as $closing) {
    rc170_ok(str_contains($event, $closing), 'official closing preserved');
}
require ABSPATH . 'includes/class-admin-page.php';
$render = new ReflectionMethod(IDG_Workflow_Admin_Controller::class, 'render_internal_links_fields');
$render->setAccessible(true);
ob_start();
$render->invoke(null, []);
$html = ob_get_clean();
foreach (['primary_lens', 'secondary_lenses', 'existentes e indexables con URL real', 'La categoría es contexto, no fallback', 'unresolved y requiere resolución editorial'] as $text) {
    rc170_ok(str_contains($html, $text), "admin rendered copy: $text");
}
rc170_ok(!str_contains($admin, 'Categoría detectada para fallback de enlaces'), 'report category is context only');
$main = file_get_contents(ABSPATH . 'ideasdi-redaccion-gerizim.php');
rc170_ok((bool) preg_match('/Version:\s*0\.4\.0-RC1\.7\.0\s*$/m', $main), 'plugin final RC1.7.0 version');
// Exact final RC1.7.0 fingerprints; behavioral assertions remain unchanged.
$protected = [
    'ideasdi-redaccion-gerizim.php' => 'c024aa06149541dce9fd53fc5853e64017d5cdde86b82805aff82e6e2c751f64',
    'includes/class-canonical-adapter.php' => '9b8485e6e4a75df578fbc21e0d8a8717942f0c2326e69559847373ddf2b538db',
    'includes/class-canonical-context.php' => '89e97607417945f6386549e735433e78ac2b68e0635c9cdaef3ebe41012e85d0',
    'includes/class-editorial-recipe-builder.php' => 'fe00f7b3524c11d631266e4bffba4a0bb83a57c097bebdcc4b212d027820c04c',
    'includes/class-final-guard.php' => 'ad97dc6d190f487d90b21061bbf5a824d505ac22e49182a3b9f4486f4590bdc4',
    'includes/class-internal-links.php' => '054f6f84a62c600dcf9b5c156c60bfed1fbef31072d8ae4df58258360c186970',
    'includes/class-post-creator.php' => 'bd69c626507539968ba4685695fa320cbee39d9a5f4bee1280f6fe72b47b3380',
    'includes/class-workflow-prompt-data.php' => '215fc18bec9fb4f513e774d6f2bd21b10a84420050d7fd5547b8cf5ffe3d688b',
    'includes/data/editorial-canonical.php' => '95512585c4f7f17bf506f1b1b7734411e898f80b4bdf2e3c6995f203335b0034',
    'includes/data/editorial-recipes.php' => '2cd8b3b28e9fde338528f47e3ef690812fb4cc0c310002688dbe579e36e2840a',
];
foreach ($protected as $path => $sha256) {
    rc170_ok(hash_file('sha256', ABSPATH . $path) === $sha256, "protected baseline: $path");
}
echo "RC170_CANONICAL_PROMPTS_ADMIN_OK\n";
