<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

if (!class_exists('WP_Error')) {
    final class WP_Error {
        public function __construct(public string $code = 'fixture_error') {}
    }
}

if (!class_exists('WP_Term')) {
    final class WP_Term {
        public int $term_id;
        public string $name;
        public string $slug;
        public string $taxonomy;

        public function __construct(int $term_id, string $name, string $slug, string $taxonomy) {
            $this->term_id = $term_id;
            $this->name = $name;
            $this->slug = $slug;
            $this->taxonomy = $taxonomy;
        }
    }
}

function is_wp_error($value): bool { return $value instanceof WP_Error; }
if (!function_exists('mb_strtolower')) {
    function mb_strtolower(string $value, ?string $encoding = null): string { return strtolower($value); }
}
if (!function_exists('mb_stripos')) {
    function mb_stripos(string $haystack, string $needle, int $offset = 0, ?string $encoding = null) {
        return stripos($haystack, $needle, $offset);
    }
}
function remove_accents(string $value): string {
    return strtr($value, [
        'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U','Ñ'=>'N',
        'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n',
    ]);
}
function sanitize_title(string $value): string {
    $value = strtolower(remove_accents(trim($value)));
    return trim((string) preg_replace('/[^a-z0-9]+/', '-', $value), '-');
}
function esc_url_raw(string $value): string { return trim($value); }
function sanitize_text_field(string $value): string { return trim(strip_tags($value)); }
function absint($value): int { return abs((int) $value); }
function wp_parse_url(string $url, int $part = -1) { return parse_url($url, $part); }
function wp_strip_all_tags(string $value): string { return strip_tags($value); }
function get_option(string $key, $default = false) { return $default; }

function home_url(string $path = '/'): string {
    $overrides = $GLOBALS['rc1781_home_urls'] ?? [];
    return array_key_exists($path, $overrides)
        ? (string) $overrides[$path]
        : 'https://ideasdi.com/' . ltrim($path, '/');
}

function get_term(int $term_id, string $taxonomy = '') {
    $GLOBALS['rc1781_get_term_calls'][] = [$term_id, $taxonomy];
    $term = $GLOBALS['rc1781_terms'][$term_id] ?? null;
    if (!$term instanceof WP_Term || ($taxonomy !== '' && $term->taxonomy !== $taxonomy)) {
        return new WP_Error('term_not_found');
    }
    return $term;
}

function get_term_by(string $field, string $value, string $taxonomy) {
    $GLOBALS['rc1781_get_term_by_calls']++;
    return new WP_Error('forbidden_lookup');
}

function get_term_link($term, string $taxonomy = '') {
    $term_id = $term instanceof WP_Term ? $term->term_id : (int) $term;
    $GLOBALS['rc1781_term_link_calls'][] = [$term_id, $taxonomy];
    return $GLOBALS['rc1781_term_links'][$term_id] ?? new WP_Error('missing_link');
}

function get_category_link(int $category_id) {
    $GLOBALS['rc1781_category_link_calls'][] = $category_id;
    return $GLOBALS['rc1781_category_links'][$category_id] ?? new WP_Error('missing_category_link');
}

function get_post_type_archive_link(string $post_type) {
    return $GLOBALS['rc1781_archive_links'][$post_type] ?? '';
}

function rc1781_term(int $id, string $name, string $slug, string $taxonomy): WP_Term {
    return new WP_Term($id, $name, $slug, $taxonomy);
}

function rc1781_reset_fixtures(): void {
    $GLOBALS['rc1781_terms'] = [
        57 => rc1781_term(57, 'Diseño de Producto', 'diseno-de-productos', 'category'),
        1231 => rc1781_term(1231, 'Arquitectura y diseño interior', 'diseno-interior', 'category'),
        1238 => rc1781_term(1238, 'Moda', 'diseno-de-indumentaria', 'category'),
        1236 => rc1781_term(1236, 'Movilidad', 'diseno-transporte', 'category'),
        1232 => rc1781_term(1232, 'Diseño digital', 'modelado-3d', 'category'),
        34 => rc1781_term(34, 'Concursos y convocatorias', 'concursos-de-diseno', 'category'),
        201 => rc1781_term(201, 'Mobiliario', 'mobiliario', 'post_tag'),
        202 => rc1781_term(202, 'Materialidad', 'materialidad', 'post_tag'),
        203 => rc1781_term(203, 'Desfiles', 'desfiles', 'post_tag'),
        204 => rc1781_term(204, 'Automotriz', 'automovil', 'post_tag'),
        205 => rc1781_term(205, 'Render', 'render', 'post_tag'),
        206 => rc1781_term(206, 'Iluminación natural', 'iluminacion-natural', 'post_tag'),
        207 => rc1781_term(207, 'Tecnología', 'diseno-de-productos-informaticos', 'post_tag'),
        208 => rc1781_term(208, 'Impresión 3D', 'impresora-3d', 'post_tag'),
        209 => rc1781_term(209, 'Render', 'render-equivocado', 'post_tag'),
        210 => rc1781_term(210, 'Tag inventado', 'tag-inventado', 'post_tag'),
    ];
    $GLOBALS['rc1781_term_links'] = [
        201 => 'https://ideasdi.com/tag/mobiliario/',
        202 => 'https://ideasdi.com/tag/materialidad/',
        203 => 'https://ideasdi.com/tag/desfiles/',
        204 => 'https://ideasdi.com/tag/automovil/',
        205 => 'https://ideasdi.com/tag/render/',
        206 => 'https://ideasdi.com/tag/iluminacion-natural/',
        207 => 'https://ideasdi.com/tag/tecnologia/',
        208 => 'https://ideasdi.com/tag/impresora-3d/',
        209 => 'https://ideasdi.com/tag/render-equivocado/',
        210 => 'https://ideasdi.com/tag/tag-inventado/',
    ];
    $GLOBALS['rc1781_category_links'] = [
        57 => 'https://ideasdi.com/category/diseno-de-productos/',
        1231 => 'https://ideasdi.com/category/diseno-interior/',
        1238 => 'https://ideasdi.com/category/diseno-de-indumentaria/',
        1236 => 'https://ideasdi.com/category/diseno-transporte/',
        1232 => 'https://ideasdi.com/category/modelado-3d/',
        34 => 'https://ideasdi.com/category/concursos-de-diseno/',
    ];
    $GLOBALS['rc1781_home_urls'] = [];
    $GLOBALS['rc1781_archive_links'] = ['evento' => 'https://ideasdi.com/eventos/'];
    $GLOBALS['rc1781_get_term_calls'] = [];
    $GLOBALS['rc1781_get_term_by_calls'] = 0;
    $GLOBALS['rc1781_term_link_calls'] = [];
    $GLOBALS['rc1781_category_link_calls'] = [];
}

require_once ABSPATH . 'includes/class-canonical-adapter.php';
require_once ABSPATH . 'includes/class-canonical-context.php';
require_once ABSPATH . 'includes/class-priority-readings.php';
require_once ABSPATH . 'includes/class-internal-links.php';
require_once ABSPATH . 'includes/class-prompt-library.php';

rc1781_reset_fixtures();

function rc1781_workflow(int $category_id, array $tag_ids, array $extra = []): array {
    return array_merge([
        'category_id' => $category_id,
        'tag_ids' => $tag_ids,
        'piece_type' => 'Actualidad',
        'keyword' => 'SkyPaint',
        'entity' => 'Studio Fixture',
        'official_source' => 'https://example.com/skypaint',
    ], $extra);
}

if (!defined('IDG_RC1781_FIXTURES_ONLY') || IDG_RC1781_FIXTURES_ONLY !== true) {
    $assertions = 0;
    $failures = [];
    $check = static function (bool $condition, string $label) use (&$assertions, &$failures): void {
        $assertions++;
        if (!$condition) $failures[] = $label;
    };

    $canonical = IDG_Internal_Links::library(rc1781_workflow(1231, [205, 202], ['primary_lens' => 'Materialidad']));
    $check($canonical['editorial_resolution_status'] === 'resolved', 'canonical resolved');
    $check($canonical['resolved_lens'] === 'materiality', 'canonical lens');
    $check(($canonical['primary']['type'] ?? '') === 'tag_principal', 'canonical type');
    $check($canonical['primary_tag'] === 'Materialidad', 'canonical tag');
    $check($canonical['tag_url'] === 'https://ideasdi.com/tag/materialidad/', 'canonical real URL');
    $check($canonical['category_url'] === 'https://ideasdi.com/category/diseno-interior/', 'WordPress category URL when no curated URL exists');

    $secondary = IDG_Internal_Links::library(rc1781_workflow(1231, [202, 206], [
        'primary_lens' => 'Mobiliario',
        'secondary_lenses' => ['Iluminación'],
    ]));
    $check($secondary['resolved_lens'] === 'lighting', 'secondary lens priority');
    $check($secondary['primary_tag'] === 'Iluminación natural', 'secondary term');
    $check(($secondary['primary']['type'] ?? '') === 'tag_principal', 'secondary canonical type');

    $fallback = IDG_Internal_Links::library(rc1781_workflow(1232, [205], ['primary_lens' => 'SkyPaint']));
    $check($fallback['editorial_resolution_status'] === 'resolved', 'fallback resolved');
    $check($fallback['resolved_lens'] === '', 'fallback has no invented lens');
    $check(($fallback['primary']['type'] ?? '') === 'tag_fallback_seleccionado', 'fallback type');
    $check($fallback['primary_tag'] === 'Render', 'fallback first selected');
    $check($fallback['tag_url'] === 'https://ideasdi.com/tag/render/', 'fallback real URL');

    rc1781_reset_fixtures();
    $dedup = IDG_Internal_Links::library(rc1781_workflow(1232, [205, 205, 208]));
    $check($dedup['primary_tag'] === 'Render', 'tag ids deduplicated in order');
    $check(count(array_filter($GLOBALS['rc1781_get_term_calls'], static fn($call) => $call === [205, 'post_tag'])) === 1, 'dedup lookup only once in current resolution');

    rc1781_reset_fixtures();
    $GLOBALS['rc1781_term_links'][202] = new WP_Error('link_error');
    $next = IDG_Internal_Links::library(rc1781_workflow(1231, [202, 206], [
        'primary_lens' => 'Materialidad',
        'secondary_lenses' => ['Iluminación'],
    ]));
    $check($next['primary_tag'] === 'Iluminación natural', 'next after WP_Error');
    $check($next['resolved_lens'] === 'lighting', 'next keeps causal lens order');
    $GLOBALS['rc1781_term_links'][202] = '';
    $next = IDG_Internal_Links::library(rc1781_workflow(1231, [202, 206], ['secondary_lenses' => ['Iluminación']]));
    $check($next['primary_tag'] === 'Iluminación natural', 'next after empty URL');
    $GLOBALS['rc1781_term_links'][202] = 'javascript:alert(1)';
    $next = IDG_Internal_Links::library(rc1781_workflow(1231, [202, 206], ['secondary_lenses' => ['Iluminación']]));
    $check($next['primary_tag'] === 'Iluminación natural', 'next after javascript URL');

    foreach ([new WP_Error('fallback_error'), '', 'javascript:fallback'] as $invalid_url) {
        rc1781_reset_fixtures();
        $GLOBALS['rc1781_term_links'][205] = $invalid_url;
        $next = IDG_Internal_Links::library(rc1781_workflow(1232, [205, 208], ['primary_lens' => 'SkyPaint']));
        $check($next['primary_tag'] === 'Impresión 3D' && ($next['primary']['type'] ?? '') === 'tag_fallback_seleccionado', 'fallback advances after invalid URL');
    }

    rc1781_reset_fixtures();
    foreach ([
        57 => [201, 'Mobiliario'],
        1231 => [202, 'Materialidad'],
        1238 => [203, 'Desfiles'],
        1236 => [204, 'Automotriz'],
        1232 => [205, 'Render'],
    ] as $category_id => [$tag_id, $tag_name]) {
        $row = IDG_Internal_Links::library(rc1781_workflow($category_id, [$tag_id]));
        $check($row['primary_tag'] === $tag_name, "validated category {$category_id}");
    }
    $contest = IDG_Internal_Links::library(rc1781_workflow(34, [210]));
    $check($contest['category_name'] === 'Concursos y convocatorias', 'contest triple validated');
    $check($contest['editorial_resolution_status'] === 'unresolved', 'contest noindex/fake unresolved');

    foreach (['term_id' => 999, 'name' => 'Diseño de producto', 'slug' => 'producto', 'taxonomy' => 'post_tag'] as $field => $wrong) {
        rc1781_reset_fixtures();
        $GLOBALS['rc1781_terms'][57]->{$field} = $wrong;
        $row = IDG_Internal_Links::library(rc1781_workflow(57, [201]));
        $check($row['editorial_resolution_status'] === 'unresolved', "category rejects {$field}");
    }

    rc1781_reset_fixtures();
    $check(IDG_Internal_Links::library(rc1781_workflow(57, [207]))['editorial_resolution_status'] === 'unresolved', 'No Index rejected');
    $check(IDG_Internal_Links::library(rc1781_workflow(57, [205]))['editorial_resolution_status'] === 'unresolved', 'cross-category tag rejected');
    $check(IDG_Internal_Links::library(rc1781_workflow(1232, [209]))['editorial_resolution_status'] === 'unresolved', 'wrong tag slug rejected');
    $check(IDG_Internal_Links::library(rc1781_workflow(1232, [210]))['editorial_resolution_status'] === 'unresolved', 'fake tag rejected');
    $check(IDG_Internal_Links::library(rc1781_workflow(1232, []))['editorial_resolution_status'] === 'unresolved', 'no selected tag unresolved');
    $check(IDG_Internal_Links::library(rc1781_workflow(1232, [0]))['editorial_resolution_status'] === 'unresolved', 'WP term error unresolved');
    $check($GLOBALS['rc1781_get_term_by_calls'] === 0, 'get_term_by never used');

    rc1781_reset_fixtures();
    $curated = IDG_Internal_Links::library(rc1781_workflow(57, [201]));
    $check($curated['category_url'] === 'https://ideasdi.com/diseno-productos/', 'curated category URL has priority');
    $GLOBALS['rc1781_home_urls']['/arte-3d/'] = 'javascript:category';
    $category_fallback = IDG_Internal_Links::library(rc1781_workflow(1232, [205]));
    $check($category_fallback['category_url'] === 'https://ideasdi.com/category/modelado-3d/', 'WP category URL fallback');
    $check($category_fallback['tag_url'] !== $category_fallback['category_url'], 'category URL never becomes tag URL');
    $GLOBALS['rc1781_category_links'][1232] = new WP_Error('category_error');
    $category_missing = IDG_Internal_Links::library(rc1781_workflow(1232, [205]));
    $check($category_missing['category_url'] === '', 'invalid category URLs stay empty');
    $check(($category_missing['primary']['type'] ?? '') === 'tag_fallback_seleccionado', 'category URL absence does not alter P2 tag');

    rc1781_reset_fixtures();
    $workflow = rc1781_workflow(1232, [205]);
    $normalized = IDG_Internal_Links::normalize($workflow);
    $check(count($normalized) === 1, 'normalize yields one link');
    $check(($normalized[0]['url'] ?? '') === 'https://ideasdi.com/tag/render/', 'normalize uses resolver URL');
    $audit = IDG_Internal_Links::audit_html('<p>Explora <a href="https://ideasdi.com/tag/render/">atmósferas digitales precisas</a>.</p>', $workflow);
    $check(empty($audit['errors']), 'single expected P2 anchor accepted');
    $check($audit['internal_count'] === 1 && $audit['expected_count'] === 1, 'P2 exact counts');
    $audit = IDG_Internal_Links::audit_html('<p><a href="/tag/render/">atmósferas digitales precisas</a> y <a href="https://ideasdi.com/tag/render/?x=1">otra lectura contextual</a>.</p>', $workflow);
    $check(!empty($audit['errors']) && $audit['expected_count'] === 2, 'duplicate expected P2 blocked');
    $audit = IDG_Internal_Links::audit_html('<p><a href="https://ideasdi.com/tag/render/">atmósferas digitales precisas</a> <a href="/otra/">extra</a>.</p>', $workflow);
    $check(count($audit['unexpected_internal_anchors']) === 1, 'unexpected internal destination blocked');
    $audit = IDG_Internal_Links::audit_html('<p><a href="/inventada/">URL inventada</a>.</p>', rc1781_workflow(1232, []));
    $check($audit['resolution_status'] === 'unresolved' && count($audit['errors']) >= 2, 'unresolved never accepts fabricated URL');

    $event = [
        'recurring_target_post_type' => 'evento',
        'editorial_context' => 'event_calendar',
        'workflow_origin' => 'recurring_update',
    ];
    $event_library = IDG_Internal_Links::library($event);
    $check(($event_library['primary']['type'] ?? '') === 'archivo_eventos', 'event archive policy remains separate');
    $event_audit = IDG_Internal_Links::audit_html('<p><a href="https://ideasdi.com/eventos/">agenda real</a> <a href="/extra/">extra</a></p>', $event);
    $check($event_audit['policy_required'] === false, 'verified event quota exception');
    $check(count($event_audit['unexpected_internal_anchors']) === 1 && !empty($event_audit['errors']), 'event extra URL still blocked');

    $summary = IDG_Internal_Links::library_summary(rc1781_workflow(1232, [205]));
    $check(str_contains($summary, 'primer post_tag explícitamente seleccionado'), 'summary states fallback phase');
    $check(str_contains($summary, 'nunca fallback P2'), 'summary excludes category fallback');
    $check(str_contains($summary, 'https://ideasdi.com/tag/render/'), 'summary carries real resolver URL');

    $system = IDG_Prompt_Library::system_prompt();
    $seo = IDG_Prompt_Library::seo_prompt(rc1781_workflow(1232, [205]));
    $check(str_contains($system, 'primer post_tag explícitamente seleccionado'), 'system prompt states both phases');
    $check(str_contains($seo, 'https://ideasdi.com/tag/render/'), 'SEO prompt consumes real resolver URL');
    $check(str_contains($seo, 'Tipo interno: tag_fallback_seleccionado'), 'SEO prompt carries real fallback type');
    $check(str_contains($seo, '3 a 8 palabras'), 'SEO prompt anchor length');
    $check(str_contains($seo, 'no uses como anchor el nombre literal del tag'), 'SEO prompt rejects literal tag anchor');
    $check(str_contains($seo, 'exactamente un anchor Markdown'), 'SEO prompt requires one internal URL');
    $check(str_contains($seo, 'Concursos y convocatorias') && str_contains($seo, 'ambas fases'), 'contest instructions aligned');

    $unresolved_prompt = IDG_Prompt_Library::seo_prompt(rc1781_workflow(1232, []));
    $check(str_contains($unresolved_prompt, 'ninguna lente canónica ni ningún post_tag'), 'unresolved names both phases');
    $check(str_contains($unresolved_prompt, 'Requiere revisión editorial'), 'unresolved requires editorial review');
    $check(!str_contains($unresolved_prompt, 'no existe un tag canónico real con URL resuelta'), 'obsolete unresolved message absent');

    if ($failures) {
        fwrite(STDERR, "FAIL " . count($failures) . "/{$assertions}: " . implode(' | ', $failures) . "\n");
        exit(1);
    }
    echo "PASS {$assertions}/{$assertions} rc1781-skypaint-regression\n";
}
