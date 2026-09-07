<?php
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}
$projection = require dirname(__DIR__) . '/includes/data/editorial-canonical.php';
require_once dirname(__DIR__) . '/includes/class-canonical-adapter.php';

function canonical_same($actual, $expected, string $label): void {
    if ($actual !== $expected) {
        fwrite(STDERR, "FAIL: {$label}\n" . var_export($actual, true) . "\n");
        exit(1);
    }
}
function canonical_path(array $projection, string $path) {
    $value = $projection;
    foreach (explode('.', $path) as $key) {
        if (!is_array($value) || !array_key_exists($key, $value)) {
            canonical_same(false, true, 'missing ' . $path);
        }
        $value = $value[$key];
    }
    return $value;
}
canonical_same(IDG_Canonical_Adapter::projection(), $projection, 'projection repeat');
canonical_same(array_keys($projection), ['knowledge_id', 'schema_version', 'canonical_version', 'canonical_sha256', 'global', 'surfaces', 'categories', 'lenses', 'derived_formats', 'resolution'], 'roots');
$contracts = [
    'knowledge_id' => 'editorial.canonical', 'schema_version' => 1, 'canonical_version' => '1.0.0',
    'canonical_sha256' => '4329520966d28417fb2570c1be2208975358cfef22161657d38016f6c50f84ca',
    'global.identity' => ['thesis' => 'leer el presente a través del diseño', 'voice' => 'Diseñador Traductor', 'depth' => 'profundidad sin densidad'],
    'global.evidence.levels' => ['fact', 'editorial_interpretation', 'limited_inference'],
    'global.evidence.principles' => ['source_verifies_not_narrates' => true, 'evidence_witness' => true, 'observable_evidence_preferred' => true, 'unsupported_claims_forbidden' => true],
    'global.evidence.precedence' => ['evidence_over_taxonomy' => true, 'evidence_over_recipe' => true, 'evidence_over_lens' => true],
    'global.writing_principles' => ['show_before_explaining_importance' => true, 'non_promotional' => true, 'open_disciplinary_language' => true, 'avoid_press_release_voice' => true, 'avoid_catalogue_voice' => true, 'thesis_should_be_shown_not_announced' => true],
    'surfaces.article.title' => ['scope' => 'editorial_h1_not_seo_title', 'preferred_max_chars' => 60, 'hard_max_chars' => 68],
    'surfaces.article.introduction' => ['standard_paragraphs' => 2, 'enforcement' => 'preferred', 'purpose' => ['identify_subject', 'establish_relevance', 'establish_editorial_angle']],
    'surfaces.article.editorial_box' => ['required' => true, 'min_words' => 40, 'max_words' => 55, 'must_answer' => ['what_it_is', 'who_is_responsible', 'what_it_contributes'], 'forbidden' => ['links', 'bold', 'promotional_language'], 'position' => 'after_introduction'],
    'surfaces.article.headings' => ['hierarchy_required' => true, 'style' => 'descriptive_and_concise', 'avoid' => 'single_underdeveloped_paragraph', 'merge_or_expand_when_needed' => true],
    'surfaces.article.links.responsible_external' => ['required' => true, 'count' => 1, 'target' => 'responsible_official_url', 'contextual' => true, 'standalone' => false, 'responsible_entity' => ['designer', 'studio', 'brand', 'organization', 'project_owner']],
    'surfaces.article.links.taxonomy_internal' => ['required' => true, 'count' => 1, 'target' => 'resolved_editorial_tag', 'contextual' => true, 'standalone' => false, 'resolution' => ['first' => 'primary_lens_tag', 'second' => 'approved_secondary_lens_tag'], 'invented_tag' => false, 'arbitrary_post' => false, 'category_landing_fallback' => false, 'unresolved_behavior' => 'require_editorial_resolution'],
    'surfaces.calendar_event' => [
        'distinct_from' => 'article', 'implementation_surface' => 'wordpress_custom_plugin', 'editorial_behavior' => 'event_record',
        'required_facts' => ['event_name', 'start_date', 'end_date', 'city', 'country', 'venue_when_available', 'official_source'],
        'taxonomies' => ['country' => ['type' => 'country'], 'event_category' => ['values' => ['Arquitectura e interiores', 'Diseño digital y 3D', 'Diseño interdisciplinar', 'Moda', 'Movilidad y transporte', 'Semana de diseño']]],
        'editorial_purpose' => ['what', 'when', 'where', 'why_it_matters'],
    ],
    'derived_formats.reel' => ['scenes' => 6, 'voiceover' => ['scenes_1_to_5' => ['words' => 14], 'scene_6' => ['fixed_cta_required' => true]], 'overlays' => ['per_scene' => 3, 'max_chars' => 40], 'fixed_cta' => 'Conoce más de este proyecto en ideasDi.com'],
    'resolution' => [
        'sequence' => ['global', 'surface', 'category', 'primary_lens', 'secondary_lenses', 'piece_context'],
        'piece_context' => ['canonical_policy' => false, 'source' => 'runtime_input', 'allowed_fields' => ['sources', 'verified_facts', 'brief', 'responsible_entity', 'evidence', 'angle', 'article_specific_constraints']],
        'hard_global_guardrails_overridable' => false, 'evidence_over_suggestions' => ['surface', 'category', 'lens'], 'taxonomy_can_create_facts' => false, 'taxonomy_can_force_angle' => false,
    ],
    'lenses.definitions.generative_ai' => ['focus' => 'Papel verificable de la IA generativa en el proceso y resultado de diseño.', 'axes' => ['workflow', 'intervención humana', 'representación', 'límites observables']],
    'lenses.definitions.sustainability.unsupported_environmental_claims_forbidden' => true,
];
foreach ($contracts as $path => $expected) {
    canonical_same(canonical_path($projection, $path), $expected, $path);
}
canonical_same(array_keys($projection['categories']), ['product', 'architecture_interiors', 'fashion', 'mobility', 'digital_3d', 'contests_calls'], 'category IDs');
canonical_same(array_keys($projection['lenses']['definitions']), ['furniture', 'lighting', 'materiality', 'automotive', 'generative_ai', 'sustainability'], 'lens IDs');
foreach (['model' => 'semantic', 'seo_authority' => false, 'primary_lens' => 'explicit_when_present', 'secondaries' => 'additive', 'wordpress_tag_order_authoritative' => false, 'unknown_tags' => 'allowed', 'evidence_precedence' => true] as $key => $value) {
    canonical_same($projection['lenses'][$key], $value, 'lens policy ' . $key);
}
$categories = [
    'product' => ['Diseño de producto', 'Diseño de Producto', ' DISENO DE PRODUCTO '],
    'architecture_interiors' => ['Arquitectura e interiores', 'Arquitectura y diseño interior', 'Interior & Arquitectura'],
    'fashion' => ['Moda'], 'mobility' => ['Movilidad y transporte', 'Movilidad', 'Transporte'],
    'digital_3d' => ['Diseño digital y 3D', 'Diseño digital', 'Diseño Digital'],
    'contests_calls' => ['Concursos y convocatorias', 'Concursos de diseño'],
];
foreach ($categories as $id => $aliases) {
    foreach ($aliases as $alias) {
        foreach (['category_name', 'editorial_context_name'] as $field) {
            canonical_same(IDG_Canonical_Adapter::resolve([$field => $alias])['category'], $id, 'category ' . $alias);
        }
    }
    if ($id !== 'contests_calls') {
        canonical_same($projection['categories'][$id]['identity'], ['required' => false, 'use_when_supported_by_evidence' => true], 'optional identity');
    }
}
canonical_same(IDG_Canonical_Adapter::resolve(['category_name' => 'Moda', 'editorial_context_name' => 'Movilidad'])['category'], 'fashion', 'category precedence');
canonical_same(IDG_Canonical_Adapter::resolve(['category_name' => 'unknown', 'editorial_context_name' => 'Movilidad'])['category'], 'mobility', 'recognized fallback');
canonical_same(IDG_Canonical_Adapter::resolve(['category_id' => 12])['category'], null, 'without WordPress');
foreach ([['editorial_context' => 'event_calendar'], ['recurring_target_post_type' => 'evento'], ['editorial_context' => 'contest_call', 'recurring_target_post_type' => 'evento']] as $input) {
    $result = IDG_Canonical_Adapter::resolve($input + ['category_name' => 'Moda']);
    canonical_same([$result['surface'], $result['category']], ['calendar_event', null], 'event surface');
}
$result = IDG_Canonical_Adapter::resolve(['editorial_context' => 'contest_call', 'category_name' => 'Moda']);
canonical_same([$result['surface'], $result['category']], ['article', 'contests_calls'], 'contest routing');
$lenses = [
    'automotive' => ['Diseño automotriz', 'Automotriz', 'Automóvil', 'AUTOMOVIL'],
    'furniture' => ['Mobiliario'], 'lighting' => ['Iluminación', 'Iluminación natural', "Iluminacio\u{0301}n"],
    'materiality' => ['Materialidad', 'Materiales'], 'sustainability' => ['Diseño sostenible', 'Sostenibilidad'], 'generative_ai' => ['IA generativa'],
];
foreach ($lenses as $id => $aliases) {
    foreach (array_merge([$id], $aliases) as $alias) {
        canonical_same(IDG_Canonical_Adapter::resolve(['primary_lens' => $alias])['primary_lens'], $id, 'lens ' . $alias);
    }
}
$input = ['primary_lens' => 'automotive', 'radar_lente_sugerida' => 'furniture', 'lens_suggested' => 'lighting', 'radar_tag_principal' => 'materiality', 'tag_names' => ['unknown', 'sustainability', 'generative_ai']];
foreach (['primary_lens' => 'automotive', 'radar_lente_sugerida' => 'furniture', 'lens_suggested' => 'lighting', 'radar_tag_principal' => 'materiality', 'tag_names' => 'sustainability'] as $field => $expected) {
    canonical_same(IDG_Canonical_Adapter::resolve($input)['primary_lens'], $expected, 'primary precedence ' . $field);
    unset($input[$field]);
}
canonical_same(IDG_Canonical_Adapter::resolve(['editorial_lens' => 'Mobiliario'])['primary_lens'], null, 'editorial_lens ignored');
$input = [
    'primary_lens' => 'unknown', 'radar_lente_sugerida' => 'Mobiliario',
    'secondary_lenses' => ['Materiales', 'Mobiliario', 'materiality', 'unknown'],
    'radar_tags_secundarios' => ['IA generativa', 'Materialidad'],
    'tag_names' => ['Iluminación natural', 'generative_ai', 'Sostenibilidad'],
    'responsible_official_url' => ' ', 'official_source' => 'https://example.invalid/official',
    'brief_fact' => 'legacy brief', 'editorial_angle' => 'angle', 'entity' => 'studio', 'radar_restricciones_editoriales' => ['constraint'],
    'piece_context' => ['brief' => 'canonical brief', 'unexpected' => 'drop', 'evidence' => [], 'sources' => '', 'verified_facts' => null],
];
$before = $input;
$result = IDG_Canonical_Adapter::resolve($input);
canonical_same($result['secondary_lenses'], ['materiality', 'generative_ai', 'lighting', 'sustainability'], 'secondary order/dedupe');
canonical_same($result['responsible_official_url'], $input['official_source'], 'URL fallback');
canonical_same(IDG_Canonical_Adapter::resolve(['responsible_official_url' => 'unvalidated', 'official_source' => 'fallback'])['responsible_official_url'], 'unvalidated', 'URL precedence without enforcement');
canonical_same($result['piece_context'], ['brief' => 'canonical brief', 'angle' => 'angle', 'responsible_entity' => 'studio', 'article_specific_constraints' => ['constraint']], 'legacy context / no fabricated evidence');
canonical_same(IDG_Canonical_Adapter::resolve(['brief_fact' => 'brief'])['piece_context'], ['brief' => 'brief'], 'legacy brief');
$provided = ['sources' => ['source'], 'verified_facts' => ['fact'], 'brief' => 'brief', 'responsible_entity' => 'entity', 'evidence' => ['witness'], 'angle' => 'angle', 'article_specific_constraints' => ['limit']];
canonical_same(IDG_Canonical_Adapter::resolve(['piece_context' => $provided])['piece_context'], $provided, 'all canonical context fields');
canonical_same(IDG_Canonical_Adapter::resolve(['brief_fact' => '', 'entity' => [], 'editorial_angle' => null, 'radar_restricciones_editoriales' => false])['piece_context'], [], 'empty context');
canonical_same(IDG_Canonical_Adapter::resolve($input), $result, 'deterministic resolution');
canonical_same($input, $before, 'input unchanged');
foreach (['knowledge_id', 'schema_version', 'canonical_version', 'canonical_sha256'] as $field) {
    canonical_same($result[$field], $projection[$field], 'resolved pin ' . $field);
}
canonical_same($result['projection'], $projection, 'resolved projection');
// Define the optional term lookup only after exercising the standalone path.
if (!function_exists('get_term')) {
    function get_term($id, $taxonomy) {
        canonical_same($taxonomy, 'category', 'term taxonomy');
        if ($id === 12) {
            return (object) ['name' => 'Moda'];
        }
        if ($id === 13) {
            return (object) ['errors' => ['invalid']];
        }
        return false;
    }
}
canonical_same(IDG_Canonical_Adapter::resolve(['category_id' => 12])['category'], 'fashion', 'valid term');
foreach ([13, 14, -1, 'invalid', [], null] as $id) {
    canonical_same(IDG_Canonical_Adapter::resolve(['category_id' => $id])['category'], null, 'invalid term');
}
canonical_same(IDG_Canonical_Adapter::resolve(['category_name' => 'Movilidad', 'category_id' => 12])['category'], 'mobility', 'name before term');
foreach (['includes/class-canonical-adapter.php', 'includes/data/editorial-canonical.php'] as $file) {
    $source = file_get_contents(dirname(__DIR__) . '/' . $file);
    canonical_same(strpos($source, 'editorial-recipes.php'), false, 'no recipe dependency');
    canonical_same((bool) preg_match('/\b(?:add_action|add_filter|update_option|wp_insert_post|file_put_contents)\s*\(/', $source), false, 'no runtime wiring/writes');
}
echo "PASS: RC170_CANONICAL_CORE_OK\n";
