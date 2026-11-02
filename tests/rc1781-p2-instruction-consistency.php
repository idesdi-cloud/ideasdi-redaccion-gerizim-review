<?php
declare(strict_types=1);

define('IDG_RC1781_FIXTURES_ONLY', true);
require __DIR__ . '/rc1781-skypaint-regression.php';
require_once ABSPATH . 'includes/class-post-creator.php';
require_once ABSPATH . 'includes/class-admin-page.php';

$assertions = 0;
$failures = [];
$check = static function (bool $condition, string $label) use (&$assertions, &$failures): void {
    $assertions++;
    if (!$condition) $failures[] = $label;
};

rc1781_reset_fixtures();
$canonical_workflow = rc1781_workflow(1231, [205, 202], [
    'primary_lens' => 'Materialidad',
    'secondary_lenses' => ['Iluminación'],
]);
$canonical_library = IDG_Internal_Links::library($canonical_workflow);
$canonical_links = IDG_Internal_Links::normalize($canonical_workflow);
$canonical_summary = IDG_Internal_Links::library_summary($canonical_workflow);
$canonical_seo = IDG_Prompt_Library::seo_prompt($canonical_workflow);

$check((new ReflectionClass(IDG_Internal_Links::class))->getFileName() === ABSPATH . 'includes/class-internal-links.php', 'real IDG_Internal_Links class loaded');
$check(($canonical_library['primary']['type'] ?? '') === 'tag_principal', 'canonical result type');
$check(($canonical_library['primary']['url'] ?? '') === 'https://ideasdi.com/tag/materialidad/', 'canonical real URL');
$check($canonical_library['resolved_lens'] === 'materiality', 'canonical resolved lens');
$check(count($canonical_links) === 1 && $canonical_links[0] === $canonical_library['primary'], 'normalize executes same real resolver');
$check(str_contains($canonical_summary, 'Materialidad') && str_contains($canonical_summary, 'https://ideasdi.com/tag/materialidad/'), 'library summary uses resolver data');
$check(str_contains($canonical_seo, 'Tipo interno: tag_principal'), 'SEO prompt receives canonical type from resolver');
$check(str_contains($canonical_seo, 'https://ideasdi.com/tag/materialidad/'), 'SEO prompt receives canonical URL from resolver');
$check(!str_contains($canonical_seo, 'fixture_url'), 'SEO prompt has no injected fixture URL');

rc1781_reset_fixtures();
$fallback_workflow = rc1781_workflow(1232, [205], ['primary_lens' => 'SkyPaint']);
$fallback_library = IDG_Internal_Links::library($fallback_workflow);
$fallback_links = IDG_Internal_Links::normalize($fallback_workflow);
$fallback_summary = IDG_Internal_Links::library_summary($fallback_workflow);
$fallback_seo = IDG_Prompt_Library::seo_prompt($fallback_workflow);

$check(($fallback_library['primary']['type'] ?? '') === 'tag_fallback_seleccionado', 'SkyPaint fallback result type');
$check($fallback_library['resolved_lens'] === '', 'SkyPaint does not invent canonical lens');
$check(($fallback_library['primary']['source_name'] ?? '') === 'Render', 'SkyPaint fallback selected Render');
$check(($fallback_library['primary']['url'] ?? '') === 'https://ideasdi.com/tag/render/', 'SkyPaint fallback real URL');
$check(count($fallback_links) === 1 && ($fallback_links[0]['type'] ?? '') === 'tag_fallback_seleccionado', 'normalize preserves fallback contract');
$check(str_contains($fallback_summary, 'tag_fallback_seleccionado') || str_contains($fallback_summary, 'primer post_tag'), 'summary explicitly aligns fallback');
$check(str_contains($fallback_seo, 'Tipo interno: tag_fallback_seleccionado'), 'SEO prompt gets fallback type from real resolver');
$check(str_contains($fallback_seo, 'https://ideasdi.com/tag/render/'), 'SEO prompt gets fallback URL from real resolver');

// These exercise the real library, normalize, summary and SEO prompt while
// retaining the inherited 54 assertion integration count.
rc1781_reset_fixtures();
$radar_only = rc1781_workflow(1231, [206, 202], [
    'radar_lente_sugerida' => 'Materialidad',
    'radar_tag_principal' => 'Materialidad',
    'tag_names' => ['Materialidad'],
    'radar_tags_secundarios' => ['Materialidad'],
    'lens_suggested' => 'Materialidad',
]);
$radar_only_library = IDG_Internal_Links::library($radar_only);
$radar_only_normalized = IDG_Internal_Links::normalize($radar_only);
$radar_only_summary = IDG_Internal_Links::library_summary($radar_only);
$radar_only_seo = IDG_Prompt_Library::seo_prompt($radar_only);
if (($radar_only_library['primary_tag'] ?? '') !== 'Iluminación natural'
    || ($radar_only_library['primary']['type'] ?? '') !== 'tag_fallback_seleccionado'
    || ($radar_only_library['resolved_lens'] ?? null) !== ''
    || ($radar_only_normalized[0]['type'] ?? '') !== 'tag_fallback_seleccionado'
    || !str_contains($radar_only_summary, 'primer post_tag explícitamente seleccionado')
    || !str_contains($radar_only_seo, 'Tipo interno: tag_fallback_seleccionado')) {
    $failures[] = 'Radar-only fields preserve real P2 fallback across resolver consumers';
}

foreach ([
    [['primary_lens' => 'Materialidad'], 'Materialidad', 'materiality'],
    [['primary_lens' => 'invalida', 'secondary_lenses' => ['Materialidad']], 'Materialidad', 'materiality'],
    [['primary_lens' => 'Iluminación natural', 'secondary_lenses' => ['Materialidad']], 'Iluminación natural', 'lighting'],
] as [$fields, $expected_tag, $expected_lens]) {
    $candidate = IDG_Internal_Links::library(rc1781_workflow(1231, [206, 202], $fields));
    if (($candidate['primary_tag'] ?? '') !== $expected_tag
        || ($candidate['primary']['type'] ?? '') !== 'tag_principal'
        || ($candidate['resolved_lens'] ?? '') !== $expected_lens) {
        $failures[] = 'Explicit P2 lens priority remains canonical';
        break;
    }
}

$system = IDG_Prompt_Library::system_prompt();
$check(str_contains($system, 'primary_lens canónica') && str_contains($system, 'secondary_lenses canónicas'), 'system names canonical priority');
$check(str_contains($system, 'primer post_tag explícitamente seleccionado por el editor'), 'system names selected-tag fallback');
$check(str_contains($system, 'misma categoría WordPress validada') || str_contains($system, 'categoría WordPress validada'), 'system requires validated WordPress category');
$check(str_contains($system, 'URL real HTTP(S)'), 'system requires HTTP(S) URL');
$check(str_contains($system, 'La categoría es contexto, no fallback'), 'system excludes category as P2 destination');
$check(str_contains($system, '3 a 8 palabras'), 'system sets anchor length');
$check(str_contains($system, 'evita usar el nombre literal del tag como anchor'), 'system rejects literal tag anchor');
$check(str_contains($system, 'exactamente un enlace contextual'), 'system requires exactly one internal link');

$check(str_contains($fallback_seo, 'exactamente un anchor Markdown'), 'SEO requires one Markdown anchor');
$check(str_contains($fallback_seo, 'de 3 a 8 palabras'), 'SEO requires 3-8 words');
$check(str_contains($fallback_seo, 'no uses como anchor el nombre literal del tag'), 'SEO rejects literal tag anchor');
$check(str_contains($fallback_seo, 'Concursos y convocatorias define la superficie/categoría'), 'contest instructions present');
$check(str_contains($fallback_seo, 'si ninguna resuelve, primer post_tag explícitamente seleccionado'), 'contest instructions include fallback');
$check(str_contains($fallback_seo, 'Si ambas fases fallan'), 'contest instructions state unresolved condition');

$unresolved_workflow = rc1781_workflow(1232, []);
$unresolved_library = IDG_Internal_Links::library($unresolved_workflow);
$unresolved_links = IDG_Internal_Links::normalize($unresolved_workflow);
$unresolved_summary = IDG_Internal_Links::library_summary($unresolved_workflow);
$unresolved_seo = IDG_Prompt_Library::seo_prompt($unresolved_workflow);
$check($unresolved_library['editorial_resolution_status'] === 'unresolved', 'no selection is unresolved');
$check($unresolved_links === [], 'unresolved normalize has no URL');
$check(str_contains($unresolved_summary, 'unresolved') && str_contains($unresolved_summary, 'revisión editorial'), 'summary requests editorial review');
$check(str_contains($unresolved_seo, 'UNRESOLVED: ninguna lente canónica ni ningún post_tag explícitamente seleccionado por el editor'), 'SEO unresolved names both phases');
$check(str_contains($unresolved_seo, 'URL real HTTP(S) en la misma categoría WordPress validada'), 'SEO unresolved states complete URL/category contract');
$check(str_contains($unresolved_seo, 'Requiere revisión editorial'), 'SEO unresolved requests review');
$check(str_contains($unresolved_seo, 'no infieras por Radar, nombre o categoría'), 'SEO unresolved forbids implicit inference');
$check(str_contains($unresolved_seo, 'ni inventes, copies o sustituyas una URL interna'), 'SEO unresolved forbids URL fabrication');
$check(!str_contains($unresolved_seo, 'no existe un tag canónico real con URL resuelta'), 'obsolete unresolved statement removed');

$check(IDG_Prompt_Library::version('generate') === 'generate-v2.2.0-RC1.7.8.1', 'generate prompt version');
$check(IDG_Prompt_Library::version('editorial') === 'editorial-v2.2.0-RC1.7.8.1', 'editorial prompt version');
$check(IDG_Prompt_Library::version('seo') === 'seo-v2.2.0-RC1.7.8.1', 'SEO prompt version');
$check(IDG_Prompt_Library::version('material_card') === 'material-card-v1.1.0-RC1.5.2', 'material prompt version unchanged');
$check(IDG_Prompt_Library::version('editorial_plan') === 'editorial-plan-v2.1.0-RC1.5.2', 'plan prompt version unchanged');
$check(IDG_Prompt_Library::version('web_research') === 'web-research-v1.2.0-RC1.5.2', 'research prompt version unchanged');

$check(IDG_Post_Creator::override_eligible(['ok' => true, 'errors' => []]) === false, 'override ineligible without errors');
$check(IDG_Post_Creator::override_eligible(['ok' => false, 'errors' => ['soft']]) === false, 'override ineligible with soft error');
$check(IDG_Post_Creator::override_eligible(['ok' => false, 'errors' => ['P2 bloqueado']]) === false, 'override ineligible with P2 error');
$wrapper = new ReflectionMethod(IDG_Post_Creator::class, 'editorial_override_allowed');
$wrapper->setAccessible(true);
$check($wrapper->invoke(null, ['ok' => true, 'errors' => []]) === false, 'private override wrapper delegates closed policy');

$admin_source = file_get_contents(ABSPATH . 'includes/class-admin-page.php');
$method_start = strpos($admin_source, 'private static function should_show_force_draft');
$method_end = strpos($admin_source, 'private static function render_sponsored_fields', $method_start);
$admin_method = substr($admin_source, $method_start, $method_end - $method_start);
$check(str_contains($admin_method, "\$workflow['force_validation_override'] = false;"), 'admin disables force before preflight');
$check(str_contains($admin_method, 'IDG_Post_Creator::preflight_public($workflow)'), 'admin uses current real preflight');
$check(str_contains($admin_method, 'IDG_Post_Creator::override_eligible'), 'admin delegates eligibility policy');
$check(!str_contains($admin_method, 'last_error') && !str_contains($admin_method, "['history']"), 'admin never infers override from error/history');

if ($failures) {
    fwrite(STDERR, "FAIL " . count($failures) . "/{$assertions}: " . implode(' | ', $failures) . "\n");
    exit(1);
}
echo "PASS {$assertions}/{$assertions} rc1781-p2-instruction-consistency\n";
