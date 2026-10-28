<?php

define('ABSPATH', dirname(__DIR__) . '/');
define('IDG_VERSION', '0.4.0-RC1.7.8');
define('IDG_EDITORIAL_RULES_OPTION_KEY', 'idg_editorial_rules');

$GLOBALS['rc178_options'] = [
    'idg_editorial_rules' => [
        'h1_max_chars' => 999,
        'editorial_box_min_words' => 1,
        'editorial_box_max_words' => 999,
        'reel_scenes' => 2,
        'reel_vo_words' => 3,
        'reel_overlays_per_scene' => 1,
        'reel_overlay_max_chars' => 999,
        'reel_cta' => 'CTA legacy relajado',
        'h1_keyword_policy' => 'exact',
    ],
];
$GLOBALS['rc178_meta'] = [];
$GLOBALS['rc178_blocks'] = [];

if (!function_exists('mb_strtolower')) { function mb_strtolower($text) { return strtolower((string) $text); } }
if (!function_exists('mb_strlen')) { function mb_strlen($text) { return strlen((string) $text); } }
if (!function_exists('mb_stripos')) { function mb_stripos($haystack, $needle) { return stripos((string) $haystack, (string) $needle); } }
if (!function_exists('mb_substr')) { function mb_substr($text, $start, $length = null) { return $length === null ? substr((string) $text, $start) : substr((string) $text, $start, $length); } }
if (!function_exists('mb_strrpos')) { function mb_strrpos($haystack, $needle) { return strrpos((string) $haystack, (string) $needle); } }

final class WP_Term {
    public int $term_id;
    public string $taxonomy;
    public string $name;
    public function __construct(int $id, string $taxonomy, string $name) {
        $this->term_id = $id;
        $this->taxonomy = $taxonomy;
        $this->name = $name;
    }
}

function rc178_assert($condition, string $label): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    echo "OK: {$label}\n";
}

if (in_array('--intentional-failure', $argv, true)) {
    rc178_assert(false, 'sonda intencional');
}

function get_option($key, $default = []) { return $GLOBALS['rc178_options'][$key] ?? $default; }
function wp_strip_all_tags($text) { return strip_tags((string) $text); }
function sanitize_text_field($text) { return trim(strip_tags((string) $text)); }
function sanitize_textarea_field($text) { return trim((string) $text); }
function sanitize_key($text) { return strtolower(preg_replace('/[^a-z0-9_-]/i', '', (string) $text)); }
function esc_url_raw($url) { return trim((string) $url); }
function esc_url($url) { return htmlspecialchars((string) $url, ENT_QUOTES | ENT_HTML5, 'UTF-8'); }
function esc_html($text) { return htmlspecialchars((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8'); }
function esc_attr($text) { return htmlspecialchars((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8'); }
function absint($value) { return abs((int) $value); }
function is_wp_error($value) { return false; }
function home_url($path = '') { return 'https://ideasdi.com' . ($path !== '' ? '/' . ltrim((string) $path, '/') : ''); }
function wp_parse_url($url, $part = -1) { return parse_url((string) $url, $part); }
function remove_accents($text) {
    return strtr((string) $text, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','ñ'=>'n','Ñ'=>'N']);
}
function get_term($id, $taxonomy = '') {
    if ((int) $id === 10 && $taxonomy === 'post_tag') return new WP_Term(10, 'post_tag', 'Diseño industrial');
    if ((int) $id === 20 && $taxonomy === 'category') return new WP_Term(20, 'category', 'Producto');
    return null;
}
function get_term_by($field, $value, $taxonomy) {
    return $taxonomy === 'post_tag' && $field === 'name' && $value === 'Diseño industrial'
        ? new WP_Term(10, 'post_tag', 'Diseño industrial')
        : false;
}
function get_term_link($term, $taxonomy = '') {
    $id = $term instanceof WP_Term ? $term->term_id : (int) $term;
    return $id === 10 ? 'https://ideasdi.com/tag/diseno-industrial/' : 'https://ideasdi.com/taxonomia/evento/';
}
function get_category_link($id) { return 'https://ideasdi.com/category/producto/'; }
function get_post_type_archive_link($type) { return $type === 'evento' ? 'https://ideasdi.com/eventos/' : ''; }
function update_post_meta($post_id, $key, $value) { $GLOBALS['rc178_meta'][$key] = $value; return true; }
function serialize_blocks($blocks) { $GLOBALS['rc178_blocks'] = $blocks; return '__BLOCKS__'; }
function parse_blocks($content) { return []; }
function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }
function untrailingslashit($value) { return rtrim((string) $value, '/'); }

final class IDG_Canonical_Context {
    public static function resolve(array $workflow): array {
        return [
            'primary_lens' => (string) ($workflow['primary_lens'] ?? ''),
            'secondary_lenses' => (array) ($workflow['secondary_lenses'] ?? []),
            'responsible_official_url' => '',
        ];
    }
    public static function prompt_block(array $workflow): string { return ''; }
}
final class IDG_Canonical_Adapter {
    public static function resolve(array $workflow): array {
        return ['primary_lens' => (string) ($workflow['primary_lens'] ?? '')];
    }
}
final class IDG_Priority_Readings {
    public static function is_noindex_tag_name(string $name, string $category = ''): bool { return false; }
    public static function tag_status(string $name, string $category = ''): string { return 'indexable'; }
    public static function category_curated_url(string $category): string { return ''; }
}

require_once ABSPATH . 'includes/class-editorial-rules.php';
require_once ABSPATH . 'includes/class-internal-links.php';
require_once ABSPATH . 'includes/class-reel-contract.php';
require_once ABSPATH . 'includes/class-validator.php';
require_once ABSPATH . 'includes/class-final-guard.php';
require_once ABSPATH . 'includes/class-post-creator.php';
require_once ABSPATH . 'includes/class-prompt-library.php';

function rc178_words(int $count, string $prefix = 'palabra'): string {
    $words = [];
    for ($i = 1; $i <= $count; $i++) $words[] = $prefix . $i;
    return implode(' ', $words);
}

function rc178_reel(): string {
    $lines = [];
    for ($scene = 1; $scene <= 5; $scene++) {
        $lines[] = 'VO ' . $scene . ': ' . rc178_words(14, 'voz');
        for ($overlay = 1; $overlay <= 3; $overlay++) {
            $lines[] = "Overlay {$scene}.{$overlay}: Texto {$scene}-{$overlay}";
        }
    }
    $lines[] = 'VO 6: Conoce más de este proyecto en ideasDi.com';
    for ($overlay = 1; $overlay <= 3; $overlay++) $lines[] = "Overlay 6.{$overlay}: Cierre {$overlay}";
    return implode("\n", $lines);
}

function rc178_box(int $words): string { return rc178_words($words, 'dato'); }

function rc178_guard(string $box, string $html_box = '', string $intro = 'Obra de Estudio Uno, a cargo de Ana, firmada por su equipo y presentada como proyecto de investigación.'): array {
    $content = "# Decisiones verificables del diseño\n## Una lectura concreta\n{$intro}\n\nCaja editorial\n{$box}\n\n### Material y uso\nUn párrafo explica la decisión concreta del proyecto.\nOtro párrafo traduce su efecto observable sin inventar hechos.";
    $html_box = $html_box !== '' ? $html_box : '<p class="featured-snippet-box">' . esc_html($box) . '</p>';
    $html = '<h2>Una lectura concreta</h2><p>' . esc_html($intro) . '</p>' . $html_box . '<h3>Material y uso</h3><p>Un párrafo explica la decisión concreta.</p><p>Otro párrafo traduce su efecto.</p>';
    $sections = [
        'meta_description' => str_repeat('m', 120),
        'seo_report' => 'Informe completo',
        'social_copy' => 'Copy completo',
        'feedback_notes' => 'Sin cambios',
        'reel_package' => rc178_reel(),
    ];
    $workflow = [
        'assignment_card' => 'Ficha',
        'editorial_plan_raw' => 'Plan',
        'editorial_recipe_applied' => 'Receta',
        'recipe_base_structure' => ['semantic_library_version' => '1.1.0'],
        'recurring_target_post_type' => 'evento',
        'editorial_context' => 'event_calendar',
        'workflow_origin' => 'recurring_update',
    ];
    return IDG_Final_Guard::validate_before_draft($content, $html, $sections, $workflow);
}

/* La sonda prueba que una aserción fallida termina el proceso con código no cero. */
$probe_output = [];
$probe_code = 0;
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --intentional-failure 2>&1', $probe_output, $probe_code);
rc178_assert($probe_code !== 0, 'las aserciones fallidas devuelven exit distinto de cero');

/* A: H1, post_title, versión y Yoast dinámico. */
$plugin = file_get_contents(ABSPATH . 'ideasdi-redaccion-gerizim.php');
rc178_assert((bool) preg_match('/^ \* Version: 0\.4\.0-RC1\.7\.8$/m', $plugin), 'cabecera exacta RC1.7.8');
rc178_assert(str_contains($plugin, "define('IDG_VERSION', '0.4.0-RC1.7.8');"), 'IDG_VERSION exacta');
rc178_assert(str_contains($plugin, "define('IDG_TRACEABILITY_DB_VERSION', '1.2.0');"), 'versión DB 1.2.0 preservada');
$h1_count = new ReflectionMethod(IDG_Final_Guard::class, 'h1_count');
$h1_count->setAccessible(true);
rc178_assert($h1_count->invoke(null, "# Único\nTexto") === 1, 'acepta un H1 único');
rc178_assert($h1_count->invoke(null, "# Uno\n# Dos") === 2, 'detecta H1 duplicado');
$extract_title = new ReflectionMethod(IDG_Post_Creator::class, 'extract_title');
$extract_title->setAccessible(true);
rc178_assert($extract_title->invoke(null, "# El H1 gobierna WordPress\nTexto", 'Fallback') === 'El H1 gobierna WordPress', 'post_title se obtiene del H1');
$yoast = new ReflectionMethod(IDG_Post_Creator::class, 'update_yoast_meta');
$yoast->setAccessible(true);
$yoast->invoke(null, 77, 'Descripción', 'keyword');
rc178_assert(($GLOBALS['rc178_meta']['_yoast_wpseo_title'] ?? '') === '%%title%%', 'Yoast usa %%title%% dinámico');
$creator_source = file_get_contents(ABSPATH . 'includes/class-post-creator.php');
rc178_assert(str_contains($creator_source, "\$seo_title = '%%title%%';"), 'salida legacy TÍTULO SEO se fija como referencia dinámica');
$admin = file_get_contents(ABSPATH . 'includes/class-admin-page.php');
rc178_assert(str_contains($admin, 'Título SEO (legacy · solo lectura)'), 'Título SEO queda identificado como legacy de solo lectura');

/* Hard contract: las preferencias contradictorias no relajan gates. */
$rules = IDG_Editorial_Rules::get();
foreach (['h1_max_chars'=>68, 'editorial_box_min_words'=>40, 'editorial_box_max_words'=>55, 'reel_scenes'=>6, 'reel_vo_words'=>14, 'reel_overlays_per_scene'=>3, 'reel_overlay_max_chars'=>40] as $key => $value) {
    rc178_assert(($rules[$key] ?? null) === $value, "contrato duro {$key}");
}
rc178_assert($rules['reel_cta'] === 'Conoce más de este proyecto en ideasDi.com', 'CTA duro no se relaja');
$override = new ReflectionMethod(IDG_Post_Creator::class, 'editorial_override_allowed');
$override->setAccessible(true);
foreach (['El H1 supera 68 caracteres.', 'La caja editorial debe tener entre 40 y 55 palabras.', 'P2 exige exactamente un anchor interno real.', 'VO 6 debe ser exactamente el CTA fijo.'] as $hard_error) {
    rc178_assert(!$override->invoke(null, ['errors'=>[$hard_error]]), "override no relaja: {$hard_error}");
}

/* B: caja única, límites inclusivos, estructura y contenido formal. */
foreach ([40, 55] as $limit) {
    $guard = rc178_guard(rc178_box($limit));
    rc178_assert(empty(array_filter($guard['errors'], static fn($error) => str_contains($error, 'caja editorial'))), "caja acepta límite {$limit}");
}
$guard = rc178_guard(rc178_box(39));
rc178_assert((bool) array_filter($guard['errors'], static fn($error) => str_contains($error, 'entre 40 y 55')), 'caja rechaza 39 palabras');
$guard = rc178_guard('**' . rc178_box(40) . '**');
rc178_assert((bool) array_filter($guard['errors'], static fn($error) => str_contains($error, 'negritas')), 'caja rechaza negritas');
$guard = rc178_guard('[enlace factual](https://example.com) ' . rc178_box(38));
rc178_assert((bool) array_filter($guard['errors'], static fn($error) => str_contains($error, 'enlaces')), 'caja rechaza enlaces');
$guard = rc178_guard('Proyecto imperdible ' . rc178_box(38));
rc178_assert((bool) array_filter($guard['errors'], static fn($error) => str_contains($error, 'promocional')), 'caja rechaza promoción explícita');
$guard = rc178_guard(rc178_box(40), '<div class="featured-snippet-box">' . rc178_box(40) . '</div>');
rc178_assert((bool) array_filter($guard['errors'], static fn($error) => str_contains($error, 'párrafo Gutenberg')), 'caja rechaza estructura que no es párrafo Gutenberg');
$guard = rc178_guard(rc178_box(40));
rc178_assert(empty(array_filter($guard['errors'], static fn($error) => str_contains($error, 'frase artificial'))), 'atribuciones obra de/a cargo de/firmado por/proyecto de no causan falsos bloqueos');

$markdown_to_html = new ReflectionMethod(IDG_Post_Creator::class, 'markdownish_to_html');
$markdown_to_html->setAccessible(true);
$box_html = $markdown_to_html->invoke(null, "# Título\n## Bajada\nIntroducción.\nCaja editorial\n" . rc178_box(40) . "\n### Desarrollo", 'Título');
rc178_assert(substr_count($box_html, 'featured-snippet-box') === 1, 'conversión produce una única caja HTML');
$to_blocks = new ReflectionMethod(IDG_Post_Creator::class, 'html_to_gutenberg_blocks');
$to_blocks->setAccessible(true);
rc178_assert($to_blocks->invoke(null, $box_html) === '__BLOCKS__', 'conversión usa bloques Gutenberg reales del mock');
$featured = array_values(array_filter($GLOBALS['rc178_blocks'], static fn($block) => ($block['attrs']['className'] ?? '') === 'featured-snippet-box'));
rc178_assert(count($featured) === 1 && $featured[0]['blockName'] === 'core/paragraph', 'caja es un único core/paragraph');

/* C y D: voz, revisión honesta y keyword flexible/multilínea solo warning. */
$editorial_prompt = IDG_Prompt_Library::editorial_prompt([]);
foreach (['Diseñador Traductor', 'decisiones de diseño verificables', 'sin inventar hechos', 'registra “sin cambios”'] as $needle) {
    rc178_assert(str_contains($editorial_prompt, $needle), "prompt editorial contiene {$needle}");
}
$warnings = IDG_Validator::summarize("# Título natural\nArquitectura de enfoque\nsostenible transforma el recorrido.", 'arquitectura sostenible');
rc178_assert(empty(array_filter($warnings, static fn($warning) => str_contains($warning, 'cerca de la apertura'))), 'keyword variante multilínea se reconoce cerca de apertura');
$warnings = IDG_Validator::summarize("# Título natural\nUna apertura sin términos relacionados.", 'arquitectura sostenible');
rc178_assert((bool) array_filter($warnings, static fn($warning) => str_contains($warning, 'no un bloqueo')), 'ausencia keyword produce solo warning');
rc178_assert(!str_contains(file_get_contents(ABSPATH . 'includes/class-validator.php'), "h1_keyword_policy"), 'detector desligado de h1_keyword_policy legacy');

/* E: P2 audita anchors reales y todas las formas internas. */
$article = ['tag_ids'=>[10], 'primary_lens'=>'Diseño industrial', 'category_id'=>20];
$expected = 'https://ideasdi.com/tag/diseno-industrial/';
$audit = IDG_Internal_Links::audit_html('<p>Lee <a href="' . $expected . '">decisiones materiales del proyecto</a>.</p>', $article);
rc178_assert(empty($audit['errors']) && $audit['expected_count'] === 1 && $audit['internal_count'] === 1, 'P2 positivo con un tag real resuelto');
$audit = IDG_Internal_Links::audit_html('<p><a href="/tag/inventado/">destino interno inventado</a></p>', $article);
rc178_assert(!empty($audit['errors']) && $audit['expected_count'] === 0, 'URL interna inventada bloquea');
$audit = IDG_Internal_Links::audit_html('<a href="' . $expected . '">decisiones materiales del proyecto</a><a href="/otra/">otra URL interna extra</a>', $article);
rc178_assert(count($audit['unexpected_internal_anchors']) === 1 && !empty($audit['errors']), 'URL interna extra bloquea');
$audit = IDG_Internal_Links::audit_html('<a href="' . $expected . '">decisiones materiales del proyecto</a><a href="' . $expected . '?x=1#dos">segunda copia del enlace</a>', $article);
rc178_assert($audit['expected_count'] === 2 && !empty($audit['errors']), 'URL canónica duplicada bloquea');
foreach (['//www.ideasdi.com/tag/diseno-industrial/?utm=1#lectura', '/tag/diseno%2Dindustrial/?x=1', 'https://ideasdi.com/tag/diseno-industrial#fragmento'] as $variant) {
    $audit = IDG_Internal_Links::audit_html('<a href="' . $variant . '">decisiones materiales del proyecto</a>', $article);
    rc178_assert(empty($audit['errors']) && $audit['expected_count'] === 1, "equivalencia segura {$variant}");
}
$audit = IDG_Internal_Links::audit_html('<a href="tag/diseno-industrial/">ruta relativa dependiente del documento</a>', $article);
rc178_assert($audit['expected_count'] === 0 && count($audit['unexpected_internal_anchors']) === 1, 'ruta relativa no se equipara inseguramente al tag raíz');
$audit = IDG_Internal_Links::audit_html('<a href="https://archivo.ideasdi.com/otra/">archivo interno adicional real</a><a href="' . $expected . '">decisiones materiales del proyecto</a>', $article);
rc178_assert(count($audit['unexpected_internal_anchors']) === 1, 'subdominio interno también se audita');
$audit = IDG_Internal_Links::audit_html('<p>Texto visible ' . $expected . ' sin anchor.</p>', $article);
rc178_assert($audit['expected_count'] === 0 && !empty($audit['errors']), 'texto URL sin anchor no cuenta como enlace real');
$audit = IDG_Internal_Links::audit_html('<p>Evento sin enlace.</p>', ['recurring_target_post_type'=>'evento', 'editorial_context'=>'event_calendar']);
rc178_assert(empty($audit['policy_required']) && $audit['exception'] === 'evento_cpt_verificado', 'evento CPT sin tag conserva excepción verificada');
$audit = IDG_Internal_Links::audit_html('<p>Concurso sin enlace.</p>', ['recurring_target_post_type'=>'post', 'workflow_origin'=>'recurring_update']);
rc178_assert(empty($audit['policy_required']) && $audit['exception'] === 'concurso_recurrente_verificado', 'concurso recurrente conserva excepción verificada');
$audit = IDG_Internal_Links::audit_html('<p>Sin enlace.</p>', ['sponsor_client'=>'Marca aislada']);
rc178_assert(!empty($audit['policy_required']) && !empty($audit['errors']), 'clave sponsor aislada no autoriza excepción');

/* Compatibilidad patrocinados: el aviso histórico NO concede excepciones P2. */
$disclosure = new ReflectionMethod(IDG_Post_Creator::class, 'maybe_add_sponsored_disclosure');
$disclosure->setAccessible(true);
$detect_sponsor = new ReflectionMethod(IDG_Post_Creator::class, 'is_sponsored_workflow');
$detect_sponsor->setAccessible(true);
foreach ([
    'sponsor_client' => ['sponsor_client' => 'Marca histórica', 'sponsored_visible_label' => true],
    'sponsored_required_link' => ['sponsored_required_link' => 'https://aliado.test/proyecto/', 'sponsored_visible_label' => true],
] as $name => $legacy_workflow) {
    rc178_assert($detect_sponsor->invoke(null, $legacy_workflow) === true, "reconoce patrocinado histórico {$name}");
    $before = '<p>Contenido editorial documentado.</p>';
    $after = $disclosure->invoke(null, $before, $legacy_workflow);
    rc178_assert(str_contains($after, 'class="idg-sponsored-disclosure"') && str_contains($after, 'Contenido patrocinado'), "aviso visible en patrocinio histórico {$name}");
    rc178_assert($disclosure->invoke(null, $after, $legacy_workflow) === $after, "aviso patrocinado no se duplica {$name}");
    $p2_legacy = IDG_Internal_Links::audit_html('<p>Sin enlace editorial.</p>', $legacy_workflow);
    rc178_assert(!empty($p2_legacy['policy_required']) && !empty($p2_legacy['errors']), "campos heredados no eluden P2 {$name}");
    $p2_extra = IDG_Internal_Links::audit_html('<a href="https://ideasdi.com/inventado/">URL no autorizada</a>', $legacy_workflow);
    rc178_assert(!empty($p2_extra['errors']), "campos heredados no autorizan URLs internas {$name}");
}

$audit = IDG_Internal_Links::audit_html('<p>Patrocinado verificado.</p>', ['piece_type'=>'Artículo patrocinado', 'sponsored_brief'=>'Brief contractual']);
rc178_assert(empty($audit['policy_required']) && $audit['exception'] === 'patrocinado_verificado', 'patrocinado con tipo y brief conserva excepción verificada');

/* B1: excepción a la cuota NO autoriza destinos arbitrarios. */
$unsupported = '<a href="/tag/no-autorizado/">enlace interno no autorizado</a>';
foreach ([
    'evento' => ['recurring_target_post_type'=>'evento', 'editorial_context'=>'event_calendar'],
    'concurso' => ['recurring_target_post_type'=>'post', 'workflow_origin'=>'recurring_update'],
    'patrocinado' => ['piece_type'=>'Artículo patrocinado', 'sponsored_brief'=>'Brief contractual'],
] as $case => $case_workflow) {
    $bad = IDG_Internal_Links::audit_html($unsupported, $case_workflow);
    rc178_assert(empty($bad['policy_required']) && count($bad['unexpected_internal_anchors']) === 1 && !empty($bad['errors']), "excepción {$case} rechaza URL interna inventada");
    $no_link = IDG_Internal_Links::audit_html('<p>Contenido sin enlace.</p>', $case_workflow);
    rc178_assert(empty($no_link['errors']), "excepción {$case} permite ausencia de enlace principal");
}
$good_event = IDG_Internal_Links::audit_html('<a href="https://ideasdi.com/eventos/">agenda oficial de eventos</a>', ['recurring_target_post_type'=>'evento', 'editorial_context'=>'event_calendar']);
rc178_assert(empty($good_event['errors']) && $good_event['expected_count'] === 1, 'evento permite archivo CPT resuelto');
$good_contest = IDG_Internal_Links::audit_html('<a href="' . $expected . '">decisiones materiales del proyecto</a>', array_merge($article, ['recurring_target_post_type'=>'post', 'workflow_origin'=>'recurring_update']));
rc178_assert(empty($good_contest['errors']) && $good_contest['expected_count'] === 1, 'concurso permite tag resuelto cuando existe');
$sponsored_internal = ['piece_type'=>'Artículo patrocinado', 'sponsored_brief'=>'Brief contractual', 'sponsored_required_link'=>'https://ideasdi.com/patrocinado-no-validado/'];
$bad_sponsor = IDG_Internal_Links::audit_html('<a href="https://ideasdi.com/patrocinado-no-validado/">enlace del sponsor</a>', $sponsored_internal);
rc178_assert(!empty($bad_sponsor['errors']), 'brief patrocinado no autoriza por sí solo una URL interna');
foreach ([
    'evento' => ['recurring_target_post_type'=>'evento', 'editorial_context'=>'event_calendar'],
    'concurso' => ['recurring_target_post_type'=>'post', 'workflow_origin'=>'recurring_update'],
    'patrocinado' => ['piece_type'=>'Artículo patrocinado', 'sponsored_brief'=>'Brief contractual'],
] as $case => $workflow_case) {
    $final = IDG_Final_Guard::validate_before_draft(
        '', '<p><a href="/tag/no-autorizado/">link inventado</a></p>',
        ['meta_description'=>'Meta de prueba', 'seo_report'=>'Informe', 'social_copy'=>'Copy', 'feedback_notes'=>'Sin cambios', 'reel_package'=>rc178_reel()],
        $workflow_case
    );
    rc178_assert(str_contains(implode(' | ', $final['errors']), 'P2 prohíbe URL internas no autorizadas'), "Final Guard propaga bloqueo P2 en {$case}");
}

/* B3: writer output omits SEO title; legacy parser still reads it. */
$seo_template = new ReflectionMethod(IDG_Post_Creator::class, 'rebuild_seo_result');
$seo_template->setAccessible(true);
$rebuilt = $seo_template->invoke(null, '# Un H1', '%%title%%', 'Meta', 'Informe', 'Social', rc178_reel(), 'Sin cambios');
rc178_assert(!str_contains($rebuilt, 'TÍTULO SEO'), 'salida nueva no genera TÍTULO SEO');
$parse_legacy = new ReflectionMethod(IDG_Post_Creator::class, 'extract_sections');
$parse_legacy->setAccessible(true);
$legacy = $parse_legacy->invoke(null, "ARTÍCULO FINAL\n# Un H1\nTÍTULO SEO\nTítulo antiguo\nMETA DESCRIPTION\nMeta");
rc178_assert(($legacy['seo_title'] ?? '') === 'Título antiguo', 'parser conserva lectura de TÍTULO SEO legacy');
$writer_prompt = IDG_Prompt_Library::seo_prompt([]);
rc178_assert(!preg_match('/(?m)^TÍTULO SEO\s*$/u', $writer_prompt), 'prompt nuevo no exige un título SEO alternativo');

/* B4: reglas blandas legacy incompatibles no se inyectan al prompt. */
$GLOBALS['rc178_options']['idg_editorial_rules']['reel_rules'] = 'VO 6 debe incluir el CTA fijo; se puede agregar un sufijo.';
$rules_prompt = IDG_Editorial_Rules::prompt_block();
rc178_assert(!str_contains($rules_prompt, 'se puede agregar un sufijo'), 'instrucción legacy contradice CTA y se omite');
rc178_assert(str_contains($rules_prompt, 'Conoce más de este proyecto en ideasDi.com'), 'contrato CTA exacto permanece visible');
rc178_assert(IDG_Editorial_Rules::get()['reel_rules'] === 'VO 6 debe incluir el CTA fijo; se puede agregar un sufijo.', 'preferencia histórica sigue guardada');
$GLOBALS['rc178_options']['idg_editorial_rules']['reel_rules'] = 'La composición del Reel debe reflejar decisiones de diseño verificables.';
rc178_assert(str_contains(IDG_Editorial_Rules::prompt_block(), 'composición del Reel'), 'preferencia compatible se conserva en prompt');
$GLOBALS['rc178_options']['idg_editorial_rules']['reel_rules'] = 'VO 6 puede cerrar con cualquier frase';
rc178_assert(!str_contains(IDG_Editorial_Rules::prompt_block(), 'cualquier frase'), 'instrucción VO6 arbitraria también se excluye');


/* F: contrato exacto del Reel. */
$inspection = IDG_Reel_Contract::inspect(rc178_reel());
rc178_assert($inspection['valid'] && $inspection['status'] === 'válido' && empty($inspection['issues']), 'Reel válido mantiene valid/status/issues consistentes');
foreach (['Conoce más de este proyecto en ideasDi.com.', 'Cierre: Conoce más de este proyecto en ideasDi.com', 'Conoce más de este proyecto en ideasDi.com ahora'] as $bad_cta) {
    $bad = preg_replace('/^VO 6:.*$/m', 'VO 6: ' . $bad_cta, rc178_reel());
    $inspection = IDG_Reel_Contract::inspect((string) $bad);
    rc178_assert(!$inspection['valid'] && $inspection['status'] === 'requiere revisión' && !$inspection['cta_in_final_vo'], "CTA inexacto bloquea: {$bad_cta}");
}
$bad_words = str_replace('VO 3: ' . rc178_words(14, 'voz'), 'VO 3: ' . rc178_words(13, 'voz'), rc178_reel());
rc178_assert(!IDG_Reel_Contract::inspect($bad_words)['valid'], 'VO 1-5 exige exactamente 14 palabras');
$bad_overlay = str_replace("Overlay 2.3: Texto 2-3\n", '', rc178_reel());
rc178_assert(!IDG_Reel_Contract::inspect($bad_overlay)['valid'], 'cada escena exige exactamente tres overlays');
$long_overlay = str_replace('Overlay 4.2: Texto 4-2', 'Overlay 4.2: Este overlay supera deliberadamente cuarenta caracteres exactos', rc178_reel());
rc178_assert(!IDG_Reel_Contract::inspect($long_overlay)['valid'], 'overlay de más de 40 caracteres bloquea');

echo "RC178_TARGETED_REGRESSION_OK\n";
