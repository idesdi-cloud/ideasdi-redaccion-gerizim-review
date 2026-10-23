<?php
declare(strict_types=1);

/* P1 CLI acceptance: real Final Guard and Reel Contract. */
define('ABSPATH', __DIR__ . '/../');

if (!function_exists('mb_strtolower')) { function mb_strtolower($value, $encoding = null): string { return strtolower((string) $value); } }
if (!function_exists('mb_strlen')) { function mb_strlen($value, $encoding = null): int { return strlen((string) $value); } }

function wp_strip_all_tags($value): string { return trim(strip_tags((string) $value)); }
function esc_url_raw($value): string { return trim((string) $value); }
function wp_parse_url($url, $component = -1) { return parse_url((string) $url, $component); }
function remove_accents($value): string {
    return strtr((string) $value, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','ñ'=>'n','Ñ'=>'N']);
}
function get_term($id, $taxonomy) { return null; }
function is_wp_error($value): bool { return false; }

final class IDG_Internal_Links { public static function normalize(array $workflow): array { return []; } }
final class IDG_Editorial_Rules {
    public static function get(): array {
        return [
            'h1_max_chars' => 68,
            'h1_keyword_policy' => 'flexible',
            'editorial_box_min_words' => 40,
            'editorial_box_max_words' => 55,
            'reel_scenes' => 6,
            'reel_vo_words' => 14,
            'reel_overlays_per_scene' => 3,
            'reel_overlay_max_chars' => 40,
            'reel_cta' => 'Conoce más de este proyecto en ideasDi.com',
        ];
    }
}

require_once __DIR__ . '/../includes/class-final-guard.php';
require_once __DIR__ . '/../includes/class-reel-contract.php';

function p1e_assert(bool $condition, string $scenario, string $fault, int &$assertions): void {
    $assertions++;
    if (!$condition) { fwrite(STDOUT, "P1_ASSERT:$scenario:$fault\n"); exit(42); }
}
function p1e_pass(string $scenario, int $assertions): never {
    fwrite(STDOUT, "P1_RESULT:$scenario:PASS:ASSERTIONS=$assertions\n");
    exit(0);
}
function p1e_box(): string {
    return 'La propuesta organiza materiales, recorridos y encuentros para explicar cómo una decisión espacial concreta transforma la experiencia cotidiana. El análisis contrasta escala, textura, luz y uso sin convertir datos aislados en una lista, y mantiene cada afirmación ligada a evidencia verificable del proyecto presentado.';
}
function p1e_words(int $count): string {
    $words = ['uno','dos','tres','cuatro','cinco','seis','siete','ocho','nueve','diez','once','doce','trece','catorce','quince','dieciseis','diecisiete','dieciocho','diecinueve','veinte','veintiuno','veintidos','veintitres','veinticuatro','veinticinco','veintiseis','veintisiete','veintiocho','veintinueve','treinta','treintayuno','treintaydos','treintaytres','treintaycuatro','treintaycinco','treintayseis','treintaysiete','treintayocho','treintaynueve','cuarenta','cuarentayuno','cuarentaydos','cuarentaytres','cuarentaycuatro','cuarentaycinco','cuarentayseis','cuarentaysiete','cuarentayocho','cuarentaynueve','cincuenta','cincuentayuno','cincuentaydos','cincuentaytres','cincuentaycuatro','cincuentaycinco','cincuentayseis'];
    return implode(' ', array_slice($words, 0, $count));
}
function p1e_workflow(): array {
    return [
        'keyword' => 'Arquitectura circular',
        'assignment_card' => 'Ficha sintética P1',
        'editorial_plan_raw' => 'Plan editorial P1',
        'editorial_recipe_applied' => 'Receta P1',
        'recipe_base_structure' => ['semantic_library_version' => '1.0', 'primary_tag_role' => 'disciplina'],
    ];
}
function p1e_sections(string $reel = ''): array {
    return [
        'meta_description' => 'Arquitectura circular analiza materiales, recorridos y decisiones espaciales con evidencia concreta para comprender su impacto cotidiano y cultural.',
        'seo_report' => 'Informe SEO sintético.',
        'social_copy' => 'Copy social sintético.',
        'feedback_notes' => 'Retroalimentación sintética.',
        'reel_package' => $reel,
    ];
}
function p1e_content(string $box, string $placement = 'after'): array {
    $head = "# Arquitectura circular\n\n";
    $intro = "## Introducción\n\nPrimer párrafo factual sobre materia, espacio y uso cotidiano.\n\nSegundo párrafo factual que conecta decisiones técnicas y experiencia.";
    $marker = "Caja editorial:\n" . $box;
    $tail = "## Desarrollo\n\nEl proyecto articula decisiones verificables sin lenguaje documental artificial.";
    if ($placement === 'before') { $content = $head . $marker . "\n\n" . $intro . "\n\n" . $tail; }
    elseif ($placement === 'absent') { $content = $head . $intro . "\n\n" . $tail; }
    else { $content = $head . $intro . "\n\n" . $marker . "\n\n" . $tail; }
    $plain_box = wp_strip_all_tags((string) preg_replace('/\[([^\]]+)\]\([^\)]+\)/', '$1', $box));
    $html = '<h2>Introducción</h2><p>Primer párrafo factual sobre materia, espacio y uso cotidiano.</p><p>Segundo párrafo factual que conecta decisiones técnicas y experiencia.</p>';
    if ($placement !== 'absent') { $html .= '<p class="featured-snippet-box">' . htmlspecialchars($plain_box, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</p>'; }
    $html .= '<h3>Desarrollo</h3><p>El proyecto articula decisiones verificables sin lenguaje documental artificial.</p>';
    return [$content, $html];
}
function p1e_guard(string $box, string $placement = 'after', bool $html_box = true): array {
    [$content, $html] = p1e_content($box, $placement);
    if (!$html_box) { $html = str_replace(' class="featured-snippet-box"', '', $html); }
    return [$content, IDG_Final_Guard::validate_before_draft($content, $html, p1e_sections(), p1e_workflow())];
}
function p1e_errors(array $result): string { return implode(' | ', (array) ($result['errors'] ?? [])); }
function p1e_reel(array $vo_overrides = [], array $omit_overlays = [], bool $extra = false, bool $long = false): string {
    $lines = [];
    for ($scene = 1; $scene <= 6; $scene++) {
        $vo = $scene === 6 ? 'Conoce más de este proyecto en ideasDi.com' : 'Diseño material conecta luz escala textura uso ritmo contexto memoria detalle oficio forma espacio';
        if (array_key_exists($scene, $vo_overrides)) { $vo = (string) $vo_overrides[$scene]; }
        $lines[] = "VO $scene: $vo";
        for ($position = 1; $position <= 3; $position++) {
            if (in_array("$scene.$position", $omit_overlays, true)) { continue; }
            $text = $long && $scene === 2 && $position === 2 ? str_repeat('x', 41) : "Detalle $scene.$position";
            $lines[] = "Overlay $scene.$position: $text";
        }
    }
    if ($extra) { $lines[] = 'Overlay 6.4: Extra'; }
    return implode("\n", $lines);
}

$scenario = $argv[1] ?? '';
$assertions = 0;

if ($scenario === '') { exit(0); }

if ($scenario === 'box_valid_preserved') {
    [$content, $result] = p1e_guard(p1e_box());
    p1e_assert($result['ok'] === true, $scenario, 'valid_box_rejected:' . p1e_errors($result), $assertions);
    p1e_assert(str_contains($content, "Caja editorial:\n" . p1e_box()), $scenario, 'valid_box_text_changed', $assertions);
    p1e_assert(!str_contains(p1e_errors($result), 'caja editorial'), $scenario, 'unexpected_box_diagnostic', $assertions);
    p1e_pass($scenario, $assertions);
}
if (str_starts_with($scenario, 'box_')) {
    $box = p1e_box();
    $placement = 'after';
    $html_box = true;
    $needle = '';
    if ($scenario === 'box_absent') { $placement = 'absent'; $needle = 'No se detectó caja editorial'; }
    if ($scenario === 'box_short') { $box = p1e_words(39); $needle = 'Detectadas: 39'; }
    if ($scenario === 'box_long') { $box = p1e_words(56); $needle = 'Detectadas: 56'; }
    if ($scenario === 'box_bold') { $box = '**' . p1e_box() . '**'; $html_box = false; $needle = 'no puede contener negritas'; }
    if ($scenario === 'box_html_link') { $box = '<a href="https://example.test">Fuente</a> ' . p1e_words(40); $html_box = false; $needle = 'no puede contener enlaces'; }
    if ($scenario === 'box_markdown_link') { $box = '[Fuente](https://example.test) ' . p1e_words(40); $html_box = false; $needle = 'no puede contener enlaces'; }
    if ($scenario === 'box_misplaced') { $placement = 'before'; $needle = 'después de la introducción'; }
    if ($needle !== '') {
        [, $result] = p1e_guard($box, $placement, $html_box);
        p1e_assert($result['ok'] === false, $scenario, 'invalid_box_was_accepted', $assertions);
        p1e_assert(str_contains(p1e_errors($result), $needle), $scenario, 'missing_specific_diagnostic', $assertions);
        p1e_pass($scenario, $assertions);
    }
}
if ($scenario === 'reel_valid_contract') {
    $input = p1e_reel();
    $normalized = IDG_Reel_Contract::normalize($input);
    $result = IDG_Reel_Contract::inspect($input);
    p1e_assert($result['valid'] === true, $scenario, 'valid_reel_rejected:' . implode('|', $result['issues']), $assertions);
    p1e_assert(array_intersect_key($result['vo_counts'], array_flip([1,2,3,4,5])) === [1=>14,2=>14,3=>14,4=>14,5=>14], $scenario, 'vo1_to_vo5_counts_not_exact', $assertions);
    p1e_assert($result['overlay_counts'] === [1=>3,2=>3,3=>3,4=>3,5=>3,6=>3] && $result['total_overlays'] === 18, $scenario, 'overlay_counts_not_exact', $assertions);
    p1e_assert($result['cta_in_final_vo'] === true && ($result['vo'][6] ?? '') === 'Conoce más de este proyecto en ideasDi.com' && $normalized === $input, $scenario, 'cta_or_normalization_changed', $assertions);
    p1e_pass($scenario, $assertions);
}
if (str_starts_with($scenario, 'reel_invalid_')) {
    $input = p1e_reel();
    $needle = '';
    if ($scenario === 'reel_invalid_13_words') { $input = p1e_reel([1 => 'Diseño material conecta luz escala textura uso ritmo contexto memoria detalle oficio forma']); $needle = 'Detectadas: 13'; }
    if ($scenario === 'reel_invalid_15_words') { $input = p1e_reel([1 => 'Diseño material conecta luz escala textura uso ritmo contexto memoria detalle oficio forma espacio público']); $needle = 'Detectadas: 15'; }
    if ($scenario === 'reel_invalid_missing_cta') { $input = p1e_reel([6 => 'Cierre editorial sin llamada obligatoria']); $needle = 'CTA fijo obligatorio'; }
    if ($scenario === 'reel_invalid_missing_overlay') { $input = p1e_reel([], ['3.2']); $needle = 'Escena 3 debe incluir exactamente 3 overlays'; }
    if ($scenario === 'reel_invalid_missing_scene') { $input = preg_replace('/^VO 4:.*\n(?:Overlay 4\.[123]:.*\n?){3}/m', '', p1e_reel()); $needle = 'Falta VO 4'; }
    if ($scenario === 'reel_invalid_extra_overlay') { $input = p1e_reel([], [], true); $needle = 'Escena 6 debe incluir exactamente 3 overlays'; }
    if ($scenario === 'reel_invalid_long_overlay') { $input = p1e_reel([], [], false, true); $needle = 'supera 40 caracteres'; }
    if ($needle !== '') {
        $result = IDG_Reel_Contract::inspect((string) $input);
        p1e_assert($result['valid'] === false, $scenario, 'invalid_reel_was_accepted', $assertions);
        p1e_assert(str_contains(implode(' | ', $result['issues']), $needle), $scenario, 'missing_specific_diagnostic', $assertions);
        p1e_pass($scenario, $assertions);
    }
}
if ($scenario === 'reel_multiline_continuation') {
    $input = "VO 1: Diseño material conecta luz escala textura\nuso ritmo contexto memoria detalle oficio forma espacio\nOverlay 1: Materia y luz\ncontinúan juntas\nOverlay 2: Escala humana\nOverlay 3: Uso cotidiano";
    $expected = "VO 1: Diseño material conecta luz escala textura uso ritmo contexto memoria detalle oficio forma espacio\nOverlay 1.1: Materia y luz continúan juntas\nOverlay 1.2: Escala humana\nOverlay 1.3: Uso cotidiano";
    $normalized = IDG_Reel_Contract::normalize($input);
    p1e_assert($normalized === $expected, $scenario, 'continuation_not_bound_to_own_vo_or_overlay', $assertions);
    $inspection = IDG_Reel_Contract::inspect($input);
    p1e_assert(($inspection['vo'][1] ?? '') === 'Diseño material conecta luz escala textura uso ritmo contexto memoria detalle oficio forma espacio', $scenario, 'vo_literal_words_not_preserved', $assertions);
    p1e_assert(($inspection['overlays'][1][0]['text'] ?? '') === 'Materia y luz continúan juntas', $scenario, 'overlay_literal_words_not_preserved', $assertions);
    p1e_assert(IDG_Reel_Contract::normalize($normalized) === $normalized, $scenario, 'multiline_normalization_not_idempotent', $assertions);

    $boundaries = "Texto sin VO previo\nVO 1: Campo reconocido\n\nseparado por blanco\nVO roto sin dos puntos\nno debe unirse al VO roto\nScene 2:\ntampoco cruza escena\nOverlay 1: Sin VO tras marcador\ncontinuación sin asociación";
    $boundary_normalized = IDG_Reel_Contract::normalize($boundaries);
    p1e_assert(str_contains($boundary_normalized, "VO 1: Campo reconocido\nseparado por blanco"), $scenario, 'blank_separator_was_crossed', $assertions);
    p1e_assert(str_contains($boundary_normalized, "VO roto sin dos puntos\nno debe unirse al VO roto"), $scenario, 'malformed_label_was_treated_as_continuation', $assertions);
    p1e_assert(str_contains($boundary_normalized, "tampoco cruza escena\nOverlay 1: Sin VO tras marcador\ncontinuación sin asociación"), $scenario, 'scene_or_missing_vo_boundary_was_crossed', $assertions);
    p1e_pass($scenario, $assertions);
}

fwrite(STDERR, "HARNESS_ERROR: unknown editorial scenario: $scenario\n");
exit(70);
