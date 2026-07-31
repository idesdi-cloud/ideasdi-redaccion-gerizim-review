<?php
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}
if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}

if (!function_exists('mb_strtolower')) {
    function mb_strtolower(string $value, ?string $encoding = null): string { return strtolower($value); }
}
final class WP_Post {
    public int $ID;
    public string $post_type;
    public string $post_title;
    public string $post_name;
    public string $post_status;
    public int $post_author;
    public string $post_content;
    public string $post_excerpt;
    public string $post_date_gmt;
    public string $post_modified_gmt;
    public string $guid;

    public function __construct(int $id, string $type, string $title, string $slug) {
        $this->ID = $id;
        $this->post_type = $type;
        $this->post_title = $title;
        $this->post_name = $slug;
        $this->post_status = 'publish';
        $this->post_author = 7;
        $this->post_content = '<p>Contenido 2023 protegido.</p>';
        $this->post_excerpt = 'Extracto protegido.';
        $this->post_date_gmt = '2023-07-18 11:47:00';
        $this->post_modified_gmt = '2026-07-15 17:00:00';
        $this->guid = 'https://ideasdi.com/?p=' . $id;
    }
}
final class WP_Error {}

$GLOBALS['idg_post'] = new WP_Post(11902, 'post', 'Concurso Internacional de Diseño de Iluminación LAMP 2023', 'concurso-diseno-iluminacion');
$GLOBALS['idg_meta'] = [11902 => [
    'fecha_inicio_convocatoria' => '',
    'fecha_cierre_convocatoria' => '',
    'fecha_premiacion_convocatoria' => '',
    'enlace_oficial_convocatoria' => '',
]];
$GLOBALS['idg_terms'] = [11902 => ['category' => [34], 'post_tag' => [101, 202]]];
$GLOBALS['idg_thumb'] = [11902 => 555];

function get_post(int $id) { return $id === 11902 ? $GLOBALS['idg_post'] : null; }
function current_user_can(string $cap, int $id = 0): bool { return true; }
function has_category($category, $post = null): bool { return (int) $category === 34 && $post instanceof WP_Post && $post->ID === 11902; }
function get_post_meta(int $id, string $key, bool $single = true) { return $GLOBALS['idg_meta'][$id][$key] ?? ''; }
function update_post_meta(int $id, string $key, $value): bool { $GLOBALS['idg_meta'][$id][$key] = $value; return true; }
function get_object_taxonomies(string $type, string $output = 'names'): array { return $type === 'post' ? ['category', 'post_tag'] : []; }
function wp_get_object_terms(int $id, string $taxonomy, array $args = []) { return $GLOBALS['idg_terms'][$id][$taxonomy] ?? []; }
function wp_get_post_tags(int $id, array $args = []) { return ($args['fields'] ?? '') === 'names' ? ['Producto', 'Convocatoria abierta'] : ($GLOBALS['idg_terms'][$id]['post_tag'] ?? []); }
function get_post_thumbnail_id(int $id): int { return $GLOBALS['idg_thumb'][$id] ?? 0; }
function wp_update_post(array $data, bool $wp_error = false) {
    if ((int) ($data['ID'] ?? 0) !== 11902) return new WP_Error();
    foreach (['post_title', 'post_name', 'post_content', 'post_excerpt'] as $field) {
        if (array_key_exists($field, $data)) $GLOBALS['idg_post']->{$field} = (string) $data[$field];
    }
    $GLOBALS['idg_post']->post_modified_gmt = '2026-07-15 17:20:00';
    return 11902;
}
function is_wp_error($value): bool { return $value instanceof WP_Error; }
function clean_post_cache(int $id): void {}
function current_time(string $type): string { return '2026-07-15 17:20:00'; }
function get_edit_post_link(int $id, string $context = 'display'): string { return 'https://ideasdi.com/wp-admin/post.php?post=' . $id . '&action=edit'; }
function get_permalink(int $id): string { return 'https://ideasdi.com/concursos-de-diseno/concurso-diseno-iluminacion/'; }
function get_post_status_object(string $status): object { return (object) ['label' => $status === 'publish' ? 'Publicada' : $status]; }
function wp_json_encode($value): string { return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); }
function sanitize_key(string $value): string { return strtolower(preg_replace('/[^a-z0-9_\-]/i', '', $value)); }
function sanitize_title(string $value): string { $value = strtolower(trim($value)); $value = preg_replace('/[^a-z0-9]+/i', '-', $value); return trim((string) $value, '-'); }
function sanitize_text_field(string $value): string { return trim(strip_tags($value)); }
function untrailingslashit(string $value): string { return rtrim($value, '/\\'); }
function wp_strip_all_tags(string $value): string { return strip_tags($value); }

require_once dirname(__DIR__) . '/includes/class-recurring-updates.php';

function contest_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

$reflect = static function(string $method, ...$args) {
    $r = new ReflectionMethod(IDG_Recurring_Updates::class, $method);
    $r->setAccessible(true);
    return $r->invoke(null, ...$args);
};

$source = $reflect('load_source', 'contest', 11902);
contest_assert(is_array($source), 'Concurso de categoría 34 se carga como destino válido.');
$submitted = [
    'update_mode' => 'update_existing',
    'proposed_title' => 'Concurso Internacional de Diseño de Iluminación LAMP 2026',
    'proposed_slug' => 'concurso-diseno-iluminacion-2026',
    'fecha_inicio_convocatoria' => '2026-07-01',
    'fecha_cierre_convocatoria' => '2026-10-01',
    'fecha_premiacion_convocatoria' => '2026-12-10',
    'enlace_oficial_convocatoria' => 'https://lampthecompetition.com/',
];
$comparison = array_merge(
    $reflect('build_identity_comparison', $source, $submitted),
    $reflect('build_comparison', 'contest', $source['raw_fields'], $submitted)
);
$analysis = [
    'content_type' => 'contest',
    'source_post_id' => 11902,
    'selected_post_id' => 11902,
    'source' => $reflect('source_snapshot', $source),
    'source_signature' => $reflect('source_signature', $source),
    'source_target_fingerprint' => IDG_Recurring_Updates::target_post_fingerprint(11902, 'contest'),
    'submitted' => $submitted,
    'comparison' => $comparison,
    'errors' => [],
];
$content_before = $GLOBALS['idg_post']->post_content;
$excerpt_before = $GLOBALS['idg_post']->post_excerpt;
$status_before = $GLOBALS['idg_post']->post_status;
$author_before = $GLOBALS['idg_post']->post_author;
$terms_before = $GLOBALS['idg_terms'][11902];
$thumb_before = $GLOBALS['idg_thumb'][11902];

$result = $reflect('apply_structural_analysis', $analysis);
contest_assert(($result['status'] ?? '') === 'success', 'Aplicación estructural del concurso finaliza correctamente.');
contest_assert(!empty($result['publication_updated']), 'La misma publicación queda marcada como actualizada.');
contest_assert((int) ($result['actual_updated_post_id'] ?? 0) === 11902 && !empty($result['same_post_id']), 'Se conserva exactamente el ID 11902.');
contest_assert($GLOBALS['idg_post']->post_title === $submitted['proposed_title'], 'Título se actualiza a la edición 2026.');
contest_assert($GLOBALS['idg_post']->post_name === $submitted['proposed_slug'], 'Slug confirmado se actualiza.');
contest_assert($GLOBALS['idg_meta'][11902]['fecha_inicio_convocatoria'] === '20260701', 'Fecha de inicio se guarda en formato ACF compatible.');
contest_assert($GLOBALS['idg_meta'][11902]['fecha_cierre_convocatoria'] === '20261001', 'Fecha de cierre se guarda en formato ACF compatible.');
contest_assert($GLOBALS['idg_meta'][11902]['fecha_premiacion_convocatoria'] === '20261210', 'Fecha de premiación se guarda en formato ACF compatible.');
contest_assert($GLOBALS['idg_meta'][11902]['enlace_oficial_convocatoria'] === 'https://lampthecompetition.com/', 'Enlace oficial se guarda y verifica.');
contest_assert($GLOBALS['idg_post']->post_content === $content_before && $GLOBALS['idg_post']->post_excerpt === $excerpt_before, 'Contenido y extracto permanecen intactos en fase estructural.');
contest_assert($GLOBALS['idg_post']->post_status === $status_before && $GLOBALS['idg_post']->post_author === $author_before, 'Estado y autor permanecen intactos.');
contest_assert($GLOBALS['idg_terms'][11902] === $terms_before && $GLOBALS['idg_thumb'][11902] === $thumb_before, 'Categoría, etiquetas e imagen permanecen intactas.');
contest_assert(IDG_Recurring_Updates::target_post_fingerprint(11902, 'contest') !== '', 'Huella protegida del concurso permanece válida tras la actualización.');

echo "PASS recurring contest structural apply mock\n";
