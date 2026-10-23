<?php
declare(strict_types=1);

/* Synthetic fixtures only: these are not exports or claims about Radar data. */
require_once __DIR__ . '/p1-functional-post-writes.php';

function p1c_assert(bool $condition, string $scenario, string $fault, int &$assertions): void {
    $assertions++;
    if (!$condition) { fwrite(STDOUT, "P1_ASSERT:$scenario:$fault\n"); exit(42); }
}
function p1c_pass(string $scenario, int $assertions): never {
    fwrite(STDOUT, "P1_RESULT:$scenario:PASS:ASSERTIONS=$assertions\n");
    exit(0);
}
function p1c_sections(string $reel = ''): array {
    return [
        'meta_description' => 'Arquitectura circular 2027 analiza materiales, recorridos y decisiones espaciales con evidencia concreta para comprender su impacto cotidiano.',
        'seo_report' => 'Informe SEO de fixture sintético.',
        'social_copy' => 'Copy sintético.',
        'feedback_notes' => 'Retroalimentación sintética.',
        'reel_package' => $reel,
    ];
}
function p1c_guard_workflow(string $keyword): array {
    return [
        'keyword' => $keyword,
        'assignment_card' => 'Ficha sintética, no Radar',
        'editorial_plan_raw' => 'Plan sintético',
        'editorial_recipe_applied' => 'Receta sintética',
        'recipe_base_structure' => ['semantic_library_version' => '1.0', 'primary_tag_role' => 'disciplina'],
    ];
}
function p1c_report_counts(string $seo_result): array {
    if (!preg_match('/Enlaces aplicados:\s*(\d+)\s+internos?;\s*(\d+)\s+externos?/iu', $seo_result, $m)) { return [-1, -1]; }
    return [(int) $m[1], (int) $m[2]];
}

$scenario = $argv[1] ?? '';
$assertions = 0;

if ($scenario === '') { exit(0); }

if ($scenario === 'case_192_spline_cleaner') {
    p1_reset_log();
    $box = p1_box();
    $workflow = p1_workflow(true, 'Spline Cleaner');
    $workflow['entity'] = 'Spline Cleaner';
    $workflow['workflow_id'] = 'synthetic-192';
    $result = IDG_Post_Creator::create_pending_post($workflow);
    p1c_assert($result['success'] === true, $scenario, 'synthetic_192_valid_box_rejected', $assertions);
    p1c_assert(str_contains((string) $result['postprocessed_html'], '<p class="featured-snippet-box">' . $box . '</p>'), $scenario, 'synthetic_192_box_not_preserved_exactly', $assertions);
    p1c_assert(count(preg_split('/\s+/u', trim($box))) >= 40 && count(preg_split('/\s+/u', trim($box))) <= 55, $scenario, 'synthetic_192_fixture_box_out_of_range', $assertions);
    p1c_pass($scenario, $assertions);
}

if ($scenario === 'case_191_cordwainers') {
    $content = "# Cordwainers Footwear Awards 2027\n\n## Introducción\n\nPrimer párrafo factual del concurso sintético.\n\nSegundo párrafo factual del concurso sintético.\n\n## Desarrollo\n\nLa convocatoria articula una lectura editorial verificable.";
    $html = '<h2>Introducción</h2><p>Primer párrafo factual del concurso sintético.</p><p>Segundo párrafo factual del concurso sintético.</p><h3>Desarrollo</h3><p>La convocatoria articula una lectura editorial verificable.</p>';
    $reel = "VO 1: Calzado experimental reúne oficio material y nuevas formas\nde pensar el movimiento cotidiano\nOverlay 1: Oficio y materia\ncontinúan en diálogo\nOverlay 2: Movimiento cotidiano\nOverlay 3: Diseño emergente";
    $guard = IDG_Final_Guard::validate_before_draft($content, $html, p1c_sections($reel), p1c_guard_workflow('Cordwainers Footwear Awards 2027'));
    p1c_assert($guard['ok'] === false && str_contains(implode(' | ', $guard['errors']), 'No se detectó caja editorial'), $scenario, 'synthetic_191_absent_box_not_diagnosed', $assertions);
    $expected = "VO 1: Calzado experimental reúne oficio material y nuevas formas de pensar el movimiento cotidiano\nOverlay 1.1: Oficio y materia continúan en diálogo\nOverlay 1.2: Movimiento cotidiano\nOverlay 1.3: Diseño emergente";
    $normalized = IDG_Reel_Contract::normalize($reel);
    p1c_assert($normalized === $expected, $scenario, 'synthetic_191_multiline_not_kept_in_same_scene', $assertions);
    $inspection = IDG_Reel_Contract::inspect($reel);
    p1c_assert(($inspection['vo'][1] ?? '') === 'Calzado experimental reúne oficio material y nuevas formas de pensar el movimiento cotidiano', $scenario, 'synthetic_191_vo_literal_content_changed', $assertions);
    p1c_assert(($inspection['overlays'][1][0]['text'] ?? '') === 'Oficio y materia continúan en diálogo', $scenario, 'synthetic_191_overlay_literal_content_changed', $assertions);
    p1c_pass($scenario, $assertions);
}

if ($scenario === 'case_189_delorean_airspeeder') {
    p1_reset_log();
    $article = "# DeLorean Airspeeder 2027\n\n## Introducción\n\nEl prototipo sintético relaciona movilidad, escala y experiencia.\n\nLa lectura compara decisiones técnicas mediante evidencia observable.\n\n## Desarrollo\n\nConsulta DeLorean para verificar los datos oficiales del proyecto.";
    $meta = 'DeLorean Airspeeder 2027 analiza movilidad, escala y decisiones técnicas mediante evidencia concreta para comprender su impacto cotidiano.';
    $reel = "VO 1: Movilidad escala técnica materia velocidad espacio futuro detalle forma uso ciudad\nOverlay 1.1: Movilidad futura";
    $seo = "ARTÍCULO FINAL\n$article\n\nTÍTULO SEO\nDeLorean Airspeeder 2027 | ideasDi\n\nMETA DESCRIPTION\n$meta\n\nINFORME SEO INTERNO\n- Enlaces aplicados: 8 internos; 8 externos\n\nCOPY PARA REDES\nCaso sintético 189.\n\nPAQUETE REEL\n$reel\n\nRETROALIMENTACIÓN GERIZIM\nFixture sintético, no dato Radar.";
    $workflow = p1_workflow(false, 'DeLorean Airspeeder 2027');
    $workflow['seo_result'] = $seo;
    $workflow['keyword'] = 'DeLorean Airspeeder 2027';
    $workflow['entity'] = 'DeLorean';
    $workflow['responsible_official_url'] = 'https://delorean.test/proyecto';
    $workflow['force_validation_override'] = true;
    $workflow['workflow_id'] = 'synthetic-189';
    $result = IDG_Post_Creator::create_pending_post($workflow);
    p1c_assert($result['success'] === true, $scenario, 'synthetic_189_public_output_unavailable', $assertions);
    p1c_assert(str_contains((string) $result['validation_summary'], 'No se detectó caja editorial'), $scenario, 'synthetic_189_absent_box_not_diagnosed', $assertions);
    $inspection = IDG_Reel_Contract::inspect($reel);
    $issues = implode(' | ', $inspection['issues']);
    p1c_assert(str_contains($issues, 'VO 1 debe tener exactamente 14 palabras') && str_contains($issues, 'Falta VO 2'), $scenario, 'synthetic_189_vo_count_diagnostics_missing', $assertions);
    $actual_html = (string) ($result['postprocessed_html'] ?? '');
    $actual_links = substr_count($actual_html, '<a ');
    [$reported_internal, $reported_external] = p1c_report_counts((string) ($result['seo_result'] ?? ''));
    p1c_assert($actual_links > 0, $scenario, 'synthetic_189_actual_final_html_has_no_link_evidence', $assertions);
    p1c_assert($reported_internal === 0 && $reported_external === 1, $scenario, 'synthetic_189_report_fields_not_repaired', $assertions);
    p1c_assert($reported_internal + $reported_external === $actual_links, $scenario, 'synthetic_189_report_does_not_match_actual_final_html', $assertions);
    p1c_pass($scenario, $assertions);
}

fwrite(STDERR, "HARNESS_ERROR: unknown synthetic fixture scenario: $scenario\n");
exit(70);
