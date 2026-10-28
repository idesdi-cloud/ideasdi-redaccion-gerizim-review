<?php
declare(strict_types=1);

// Contract-only coverage for P3 editorial composition; no WordPress or network required.
define('ABSPATH', dirname(__DIR__) . '/');
function get_option($key, $default = []) { return $default; }
if (!function_exists('mb_stripos')) {
    function mb_stripos($haystack, $needle) { return stripos((string) $haystack, (string) $needle); }
}
final class IDG_Internal_Links {
    public static function normalize(array $data): array { return []; }
}
require ABSPATH . 'includes/class-prompt-library.php';

function p3c_require(bool $condition, string $label): void {
    if (!$condition) {
        fwrite(STDERR, "P3_COMPOSITION_FAIL:$label\n");
        exit(1);
    }
}

$data = [
    'keyword' => 'Concurso Diseño Circular 2027',
    'category_name' => 'Concursos y convocatorias',
    'entity' => 'Organización sintética',
    'document_card' => 'Fechas, roles y proceso sintéticos confirmados.',
];
$generate = IDG_Prompt_Library::generate_prompt($data);
$editorial = IDG_Prompt_Library::editorial_prompt($data);
$seo = IDG_Prompt_Library::seo_prompt($data);
$plan = IDG_Prompt_Library::editorial_plan_prompt($data);

foreach ([
    'H1 de máximo 68 caracteres, preferiblemente hasta 60',
    'primera frase debe entrar de forma narrativa y directa',
    'keyword principal de forma natural',
    'progresión informativa',
    'sin cuota fija',
    'Reduce la reiteración de tesis y los mini cierres',
    'Distingue el rol del organizador del de la plataforma',
    'lista cronológica de fechas verificadas cuando aclare el calendario; no es obligatoria',
    'herramientas o workflow solo si están verificados',
    'Distingue fabricante y alianzas solo cuando estén confirmados',
] as $needle) {
    p3c_require(str_contains($generate, $needle), 'generate:' . $needle);
}

foreach ([
    'No inventes requisitos detallados, rúbricas, ponderaciones, cifras ni hechos para SEO',
    'criterios generales confirmados del jurado o proceso creativo',
    'lista cronológica de fechas verificadas es preferible cuando aclare el calendario, pero no obligatoria',
    'El H1 editorial es el único título y gobierna WordPress; Yoast usa %%title%% de forma dinámica.',
    'No generes ningún rótulo ni campo TÍTULO SEO nuevo',
] as $needle) {
    p3c_require(str_contains($seo, $needle), 'seo:' . $needle);
}

foreach ([
    'reiteración de la tesis, mini conclusiones',
    'Conserva datos útiles, fechas, relaciones, atribuciones y decisiones documentadas',
    'no impongas cuotas de secciones ni de párrafos',
    'distingue organizador y plataforma cuando ambos roles estén confirmados',
] as $needle) {
    p3c_require(str_contains($editorial, $needle), 'editorial:' . $needle);
}

foreach ([
    'progresión informativa, no reproducir mecánicamente',
    'organizador y plataforma cuando ambos roles estén confirmados',
    'rúbricas ni ponderaciones',
    'Diseño digital y 3D',
    'Movilidad',
] as $needle) {
    p3c_require(str_contains($plan, $needle), 'plan:' . $needle);
}

p3c_require(!str_contains($generate . $seo, 'No uses más de dos bloques de lista'), 'no-rigid-list-quota');
p3c_require(!str_contains($plan, 'entre 6 y 7 H3'), 'no-rigid-h3-quota');
echo "P3_EDITORIAL_COMPOSITION_OK\n";
