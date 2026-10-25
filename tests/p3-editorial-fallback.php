<?php
declare(strict_types=1);

// Contract-only coverage for the contest fallback parser; no WordPress or network required.
define('ABSPATH', dirname(__DIR__) . '/');
function wp_json_encode($value) { return json_encode($value); }
function wp_strip_all_tags($text) { return strip_tags((string) $text); }
if (!function_exists('mb_strtolower')) {
    function mb_strtolower($text) { return strtolower((string) $text); }
}
require ABSPATH . 'includes/class-editorial-plan.php';

function p3f_require(bool $condition, string $label): void {
    if (!$condition) {
        fwrite(STDERR, "P3_FALLBACK_FAIL:$label\n");
        exit(1);
    }
}

$contestWorkflow = [
    'keyword' => 'Premio Circular 2027',
    'category_name' => 'Concursos y convocatorias',
    'piece_type' => 'Artículo',
];
$contestBase = [
    'discipline' => 'concursos',
    'available_axes' => ['propósito documentado', 'calendario confirmado'],
    'experience_dimensions' => ['experiencia inventable'],
    'risks' => ['fecha por verificar'],
];
$contest = IDG_Editorial_Plan::fallback($contestWorkflow, $contestBase);
p3f_require($contest['perceptual_translations'] === [], 'contest-has-no-forced-perceptual-translations');
foreach ([
    'organizador y plataforma',
    'elegibilidad esencial y proceso creativo solo confirmados',
    'fechas verificadas cronológicamente cuando aclaren el calendario',
    'No inventar requisitos detallados, rúbricas, ponderaciones',
] as $needle) {
    p3f_require(str_contains($contest['applied_recipe'], $needle), 'contest-recipe:' . $needle);
}
p3f_require(str_contains(implode(' ', $contest['risks']), 'No confundir organizador y plataforma'), 'contest-role-risk');
p3f_require(!str_contains($contest['thesis'], 'efecto perceptivo'), 'contest-thesis-not-perceptual');

$parsed = IDG_Editorial_Plan::parse("TESIS EDITORIAL\nPremio Circular 2027 abre una oportunidad confirmada.\n\nLENTE DISCIPLINAR\nconcursos", $contestWorkflow, $contestBase);
p3f_require($parsed['source'] === 'model+fallback', 'incomplete-model-uses-fallback');
p3f_require($parsed['perceptual_translations'] === [], 'parsed-contest-keeps-no-forced-perceptual-translations');

$design = IDG_Editorial_Plan::fallback(
    ['keyword' => 'Objeto sintético', 'category_name' => 'Diseño de producto'],
    ['discipline' => 'diseño de producto', 'experience_dimensions' => ['uso'], 'available_axes' => ['material']]
);
p3f_require(count($design['perceptual_translations']) === 1, 'non-contest-fallback-preserved');
echo "P3_EDITORIAL_FALLBACK_OK\n";
