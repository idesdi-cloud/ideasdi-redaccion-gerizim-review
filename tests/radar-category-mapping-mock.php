<?php

define('ABSPATH', __DIR__ . '/');

class Term {
    public function __construct(
        public int $term_id,
        public string $name,
        public string $slug = ''
    ) {}
}

if (!function_exists('wp_check_invalid_utf8')) {
    function wp_check_invalid_utf8($value) { return $value; }
}

if (!function_exists('mb_strtolower')) {
    function mb_strtolower($value) { return strtolower($value); }
}

if (!function_exists('current_time')) {
    function current_time($type) { return '2026-09-04 12:00:00'; }
}

if (!function_exists('absint')) {
    function absint($value) { return abs((int) $value); }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($value) { return trim((string) $value); }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($value) { return trim((string) $value); }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw($value) { return trim((string) $value); }
}

if (!function_exists('remove_accents')) {
    function remove_accents($value) {
        return iconv('UTF-8', 'ASCII//TRANSLIT', $value) ?: $value;
    }
}

function is_wp_error($value) {
    return false;
}

function get_terms($args) {
    if (($args['taxonomy'] ?? '') === 'category') {
        return [
            new Term(1231, 'Arquitectura y diseño interior'),
            new Term(57, 'Diseño de Producto'),
            new Term(1232, 'Diseño digital'),
            new Term(1236, 'Movilidad'),
            new Term(34, 'Concursos y convocatorias'),
            new Term(1238, 'Moda'),
        ];
    }

    if (($args['taxonomy'] ?? '') === 'post_tag') {
        return [
            new Term(2001, 'Residencial', 'residencial'),
        ];
    }

    return [];
}

require_once dirname(__DIR__) . '/includes/class-radar-importer.php';

function ok($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message\n");
        exit(1);
    }

    echo "OK: $message\n";
}

function radar_json(string $category, int $id): string {
    return json_encode([
        'sistema' => 'radar-editorial-ideasdi',
        'destino' => 'gerizim-wp',
        'version_exportacion' => '1.1',
        'fecha_exportacion' => '2026-09-04T12:00:00Z',
        'brief' => [
            'id' => $id,
            'keyword_principal' => 'Prueba',
            'responsable' => 'Responsable',
            'responsable_url' => 'https://example.com/',
            'fuente_oficial' => 'https://example.com/news',
            'tipo_pieza' => 'Actualidad',
            'categoria_wp' => $category,
            'etiquetas_wp' => ['Residencial'],
            'tag_principal' => 'Residencial',
        ],
        'contenido_editorial' => [
            'hecho_base' => 'Hecho base suficiente para la prueba.',
            'angulo_editorial' => 'Ángulo editorial suficiente para la prueba.',
        ],
        'hallazgo' => [
            'id' => $id + 1000,
            'url_hallazgo' => 'https://example.com/news',
        ],
    ], JSON_UNESCAPED_UNICODE);
}

$cases = [
    'Arquitectura e interiores' => 1231,
    'Diseño de producto' => 57,
    'Diseño digital y 3D' => 1232,
    'Movilidad y transporte' => 1236,
    'Concursos y convocatorias' => 34,
    'Moda' => 1238,
];

$id = 5000;

foreach ($cases as $category => $expected_id) {
    $result = IDG_Radar_Importer::import_from_json_string(
        radar_json($category, $id++),
        []
    );

    ok(
        !empty($result['success']),
        "$category importa correctamente"
    );

    ok(
        (int) ($result['workflow']['category_id'] ?? 0) === $expected_id,
        "$category => category_id $expected_id"
    );
}

echo "PASS radar category mapping\n";
