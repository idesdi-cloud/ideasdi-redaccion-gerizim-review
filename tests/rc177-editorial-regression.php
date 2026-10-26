<?php
// RC1.7.7 focused regression: final WordPress tags and editorial prompts.
define('ABSPATH', dirname(__DIR__) . '/');
define('IDG_PLUGIN_DIR', ABSPATH);

function wp_strip_all_tags($text) { return strip_tags($text); }
function is_wp_error($value) { return false; }
function get_term($id, $taxonomy) { return null; }
function get_option($key, $default = []) { return $default; }
final class IDG_Internal_Links {
    public static function normalize(array $data): array { return []; }
}
if (!function_exists('mb_strtolower')) {
    function mb_strtolower($text) { return strtolower($text); }
}
if (!function_exists('mb_strlen')) {
    function mb_strlen($text) { return strlen($text); }
}
if (!function_exists('mb_stripos')) {
    function mb_stripos($haystack, $needle) { return stripos($haystack, $needle); }
}

require_once ABSPATH . 'includes/class-disciplinary-library.php';
require_once ABSPATH . 'includes/class-editorial-recipe-builder.php';
require_once ABSPATH . 'includes/class-prompt-library.php';

function rc177_ok(bool $condition, string $label): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    echo "OK: {$label}\n";
}

$workflow = [
    'category_name' => 'Moda',
    'tag_names' => ['Prêt-à-porter'],
    'radar_tag_principal' => 'Desfiles',
    'radar_tags_secundarios' => ['Desfiles'],
    'radar_lente_sugerida' => 'Desfiles',
    'editorial_tags_final' => true,
];

$library = IDG_Disciplinary_Library::resolve($workflow);
rc177_ok($library['primary_tag'] === 'Prêt-à-porter', 'la etiqueta final conserva Prêt-à-porter como lente principal');
rc177_ok(!str_contains(implode(' ', $library['secondary_tags']), 'Desfiles'), 'la biblioteca no reintroduce Desfiles heredado');
rc177_ok(!str_contains(IDG_Disciplinary_Library::classification_text($library['tag_classifications']), 'Desfiles'), 'la clasificación activa excluye lentes Radar retiradas');

$recipe_tags = new ReflectionMethod(IDG_Editorial_Recipe_Builder::class, 'tag_names');
$recipe_tags->setAccessible(true);
$recipe_primary = new ReflectionMethod(IDG_Editorial_Recipe_Builder::class, 'primary_tag_name');
$recipe_primary->setAccessible(true);
$recipe_secondary = new ReflectionMethod(IDG_Editorial_Recipe_Builder::class, 'secondary_tag_names');
$recipe_secondary->setAccessible(true);
$active_tags = $recipe_tags->invoke(null, $workflow);
rc177_ok($active_tags === ['Prêt-à-porter'], 'la receta no reintroduce la etiqueta Radar retirada');
rc177_ok($recipe_primary->invoke(null, $workflow, $active_tags) === 'Prêt-à-porter', 'la receta prioriza la etiqueta final del editor');
rc177_ok($recipe_secondary->invoke(null, $workflow, $active_tags, 'Prêt-à-porter') === [], 'la receta no usa secundarias Radar retiradas');

$editorial = IDG_Prompt_Library::editorial_prompt([]);
$seo = IDG_Prompt_Library::seo_prompt([]);
foreach ([
    'si el artículo ya cumple y no hay un problema sustantivo, devuélvelo idéntico',
    'H1 o H2 vagos, ornamentales o repetitivos',
    'problema sustantivo si existe',
] as $text) {
    rc177_ok(str_contains($editorial, $text), "la revisión editorial exige {$text}");
}
foreach ([
    'párrafo factual de 40 a 55 palabras',
    'rellena con adjetivos',
    'muletillas abstractas como “en ese sentido”, “a la vez”, “desde ahí” o “más allá de”',
] as $text) {
    rc177_ok(str_contains($seo, $text), "SEO conserva {$text}");
}

$admin = file_get_contents(ABSPATH . 'includes/class-admin-page.php');
rc177_ok(str_contains($admin, 'if (!$is_recurring_workflow && array_key_exists(\'tag_ids\', $_POST))') && str_contains($admin, '$data[\'editorial_tags_final\'] = true;'), 'el submit marca etiquetas finales solo para edición no recurrente');
echo "RC177_EDITORIAL_REGRESSION_OK\n";
