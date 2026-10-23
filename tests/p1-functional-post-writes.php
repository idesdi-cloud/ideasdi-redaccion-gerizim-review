<?php
declare(strict_types=1);

/* P1 CLI acceptance: real IDG_Post_Creator with in-memory WordPress boundaries. */
define('ABSPATH', __DIR__ . '/../');

if (!function_exists('mb_strtolower')) { function mb_strtolower($value, $encoding = null): string { return strtolower((string) $value); } }
if (!function_exists('mb_strlen')) { function mb_strlen($value, $encoding = null): int { return strlen((string) $value); } }
if (!function_exists('mb_substr')) { function mb_substr($value, $start, $length = null, $encoding = null): string { return $length === null ? substr((string) $value, (int) $start) : substr((string) $value, (int) $start, (int) $length); } }
if (!function_exists('mb_strrpos')) { function mb_strrpos($haystack, $needle, $offset = 0, $encoding = null) { return strrpos((string) $haystack, (string) $needle, (int) $offset); } }

final class WP_Error {
    public function __construct(private string $message) {}
    public function get_error_message(): string { return $this->message; }
}
class WP_Post {
    public int $ID;
    public string $post_type;
    public string $post_title;
    public string $post_name;
    public string $post_status;
    public int $post_author;
    public string $post_content;
    public string $post_excerpt;
    public function __construct(array $row) {
        foreach ($row as $key => $value) { $this->{$key} = $value; }
    }
}

$GLOBALS['p1_log'] = ['insert' => [], 'update' => [], 'meta' => [], 'terms' => []];
$GLOBALS['p1_posts'] = [];
$GLOBALS['p1_can_edit'] = true;
$GLOBALS['p1_terms'] = ['category' => [34], 'post_tag' => [8, 9]];
$GLOBALS['p1_thumbnail'] = 77;

function wp_strip_all_tags($value): string { return trim(strip_tags((string) $value)); }
function sanitize_title($value): string { return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower((string) $value)), '-'); }
function sanitize_text_field($value): string { return trim(wp_strip_all_tags((string) $value)); }
function sanitize_key($value): string { return (string) preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)); }
function wp_kses_post($value): string { return (string) $value; }
function esc_url_raw($value): string { return trim((string) $value); }
function esc_url($value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8'); }
function esc_html($value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8'); }
function esc_attr($value): string { return esc_html($value); }
function wp_json_encode($value): string { return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); }
function absint($value): int { return abs((int) $value); }
function current_time($type): string { return '2026-10-22 10:00:00'; }
function get_current_user_id(): int { return 501; }
function current_user_can($capability, $post_id = 0): bool { return (bool) $GLOBALS['p1_can_edit']; }
function is_wp_error($value): bool { return $value instanceof WP_Error; }
function get_edit_post_link($post_id, $context = ''): string { return 'https://example.test/wp-admin/post.php?post=' . (int) $post_id; }
function wp_parse_url($url, $component = -1) { return parse_url((string) $url, $component); }
function remove_accents($value): string {
    return strtr((string) $value, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','ñ'=>'n','Ñ'=>'N']);
}
function untrailingslashit($value): string { return rtrim((string) $value, '/'); }
function get_term($id, $taxonomy) { return null; }
function has_category($category, $post): bool { return (int) $category === 34; }
function get_post_thumbnail_id($post_id): int { return (int) $GLOBALS['p1_thumbnail']; }
function get_object_taxonomies($post_type): array { return ['category', 'post_tag']; }
function wp_get_object_terms($post_id, $taxonomy, $args = []): array { return $GLOBALS['p1_terms'][$taxonomy] ?? []; }
function clean_post_cache($post_id): void {}

function wp_insert_post($postarr, $return_error = false) {
    $GLOBALS['p1_log']['insert'][] = $postarr;
    $id = 9101;
    $GLOBALS['p1_posts'][$id] = new WP_Post([
        'ID' => $id,
        'post_type' => (string) $postarr['post_type'],
        'post_title' => (string) $postarr['post_title'],
        'post_name' => (string) $postarr['post_name'],
        'post_status' => (string) $postarr['post_status'],
        'post_author' => (int) $postarr['post_author'],
        'post_content' => (string) $postarr['post_content'],
        'post_excerpt' => (string) $postarr['post_excerpt'],
    ]);
    return $id;
}
function wp_update_post($postarr, $return_error = false) {
    $GLOBALS['p1_log']['update'][] = $postarr;
    $id = (int) ($postarr['ID'] ?? 0);
    if (!isset($GLOBALS['p1_posts'][$id])) { return new WP_Error('missing post'); }
    foreach (['post_title', 'post_content', 'post_excerpt'] as $field) {
        if (array_key_exists($field, $postarr)) { $GLOBALS['p1_posts'][$id]->{$field} = (string) $postarr[$field]; }
    }
    return $id;
}
function get_post($post_id) { return isset($GLOBALS['p1_posts'][(int) $post_id]) ? clone $GLOBALS['p1_posts'][(int) $post_id] : null; }
function update_post_meta($post_id, $key, $value): bool {
    $GLOBALS['p1_log']['meta'][] = ['post_id' => (int) $post_id, 'key' => (string) $key, 'value' => $value];
    return true;
}
function wp_set_post_terms($post_id, $terms, $taxonomy, $append = false): array {
    $GLOBALS['p1_log']['terms'][] = ['post_id' => (int) $post_id, 'terms' => array_values($terms), 'taxonomy' => (string) $taxonomy];
    return array_values($terms);
}

function serialize_blocks($blocks): string {
    return 'P1BLOCKS:' . base64_encode((string) json_encode($blocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}
function parse_blocks($content): array {
    if (!str_starts_with((string) $content, 'P1BLOCKS:')) { return []; }
    $decoded = json_decode((string) base64_decode(substr((string) $content, 9), true), true);
    return is_array($decoded) ? $decoded : [];
}

final class IDG_Internal_Links {
    public static function normalize(array $workflow): array { return []; }
    public static function summary(array $workflow): string { return ''; }
}
final class IDG_Prompt_Library { public static function version(string $name): string { return 'p1-test'; } }
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
final class IDG_Recurring_Updates {
    public static function target_post_fingerprint(int $post_id, string $content_type = ''): string {
        $post = get_post($post_id);
        return $post instanceof WP_Post ? hash('sha256', implode('|', [$post->ID, $post->post_type, $content_type, $post->post_name])) : '';
    }
    public static function editorial_target_signature(int $post_id, string $target_post_type = ''): string {
        $post = get_post($post_id);
        return $post instanceof WP_Post ? hash('sha256', implode('|', [$post->ID, $target_post_type, $post->post_title, $post->post_status, $post->post_author])) : '';
    }
    public static function validate_event_title_years(string $title, string $start = '', string $end = ''): array {
        preg_match_all('/\b20\d{2}\b/', $title, $matches);
        $expected = [];
        foreach ([$start, $end] as $date) { if (preg_match('/\b(20\d{2})\b/', $date, $m)) { $expected[] = $m[1]; } }
        $expected = array_values(array_unique($expected));
        if ($expected && array_diff($expected, $matches[0] ?? [])) {
            return ['errors' => ['El año del título no coincide con la temporada editorial protegida.']];
        }
        return ['errors' => []];
    }
    public static function validate_editorial_identity(int $post_id, array $workflow, string $generated_title): array {
        $identity = trim((string) ($workflow['recurring_expected_identity'] ?? ''));
        if ($identity !== '' && stripos(remove_accents($generated_title), remove_accents($identity)) === false) {
            return ['ok' => false, 'message' => 'La identidad editorial protegida no coincide con el título generado.', 'warnings' => []];
        }
        return ['ok' => true, 'message' => '', 'warnings' => []];
    }
}

require_once __DIR__ . '/../includes/class-reel-contract.php';
require_once __DIR__ . '/../includes/class-final-guard.php';
require_once __DIR__ . '/../includes/class-post-creator.php';

function p1_meta_values(string $key): array {
    return array_values(array_map(static fn(array $row) => $row['value'], array_filter(
        $GLOBALS['p1_log']['meta'], static fn(array $row) => $row['key'] === $key
    )));
}
function p1_reset_log(): void { $GLOBALS['p1_log'] = ['insert' => [], 'update' => [], 'meta' => [], 'terms' => []]; }
function p1_assert(bool $condition, string $scenario, string $fault, int &$assertions): void {
    $assertions++;
    if (!$condition) { fwrite(STDOUT, "P1_ASSERT:$scenario:$fault\n"); exit(42); }
}
function p1_pass(string $scenario, int $assertions): never {
    fwrite(STDOUT, "P1_RESULT:$scenario:PASS:ASSERTIONS=$assertions\n");
    exit(0);
}
function p1_box(): string {
    return 'La propuesta organiza materiales, recorridos y encuentros para explicar cómo una decisión espacial concreta transforma la experiencia cotidiana. El análisis contrasta escala, textura, luz y uso sin convertir datos aislados en una lista, y mantiene cada afirmación ligada a evidencia verificable del proyecto presentado.';
}
function p1_seo_result(string $title = 'Arquitectura circular 2027', bool $box = true, string $report = '- Enlaces aplicados: 9 internos; 9 externos'): string {
    $article = "# $title\n\n## Introducción\n\nEl proyecto presenta una estrategia material precisa y verificable para su contexto inmediato.\n\nLa organización espacial conecta decisiones técnicas con una experiencia cotidiana legible.";
    if ($box) { $article .= "\n\nCaja editorial:\n" . p1_box(); }
    $article .= "\n\n## Desarrollo\n\nLa estructura coordina luz, circulación y materia con criterios observables.";
    $meta = $title . ' analiza materiales, recorridos y decisiones espaciales con evidencia concreta para comprender su impacto cotidiano.';
    return "ARTÍCULO FINAL\n$article\n\nTÍTULO SEO\n$title | ideasDi\n\nMETA DESCRIPTION\n$meta\n\nINFORME SEO INTERNO\n$report\n\nCOPY PARA REDES\nLectura editorial del proyecto.\n\nPAQUETE REEL\nPendiente de revisión humana.\n\nRETROALIMENTACIÓN GERIZIM\nContenido revisado para prueba P1.";
}
function p1_workflow(bool $box = true, string $title = 'Arquitectura circular 2027'): array {
    return [
        'seo_result' => p1_seo_result($title, $box),
        'keyword' => $title,
        'entity' => 'Estudio P1',
        'piece_type' => 'Artículo',
        'assignment_card' => 'Ficha sintética P1',
        'editorial_plan_raw' => 'Plan editorial aplicado',
        'editorial_recipe_applied' => 'Receta P1',
        'recipe_base_structure' => ['semantic_library_version' => '1.0', 'primary_tag_role' => 'disciplina'],
        'workflow_id' => 'synthetic-p1',
    ];
}
function p1_seed_target(int $id = 700, string $type = 'evento'): void {
    $GLOBALS['p1_posts'][$id] = new WP_Post([
        'ID' => $id,
        'post_type' => $type,
        'post_title' => 'Arquitectura circular 2027',
        'post_name' => 'arquitectura-circular-2027',
        'post_status' => 'publish',
        'post_author' => 41,
        'post_content' => 'contenido anterior',
        'post_excerpt' => 'extracto anterior',
    ]);
}
function p1_recurring_workflow(int $id = 700): array {
    $workflow = p1_workflow(true, 'Arquitectura circular 2027');
    $workflow += [
        'force_validation_override' => true,
        'recurring_target_post_id' => $id,
        'recurring_selected_post_id' => $id,
        'recurring_target_post_type' => 'evento',
        'workflow_origin' => 'recurring_update',
        'editorial_context' => 'event_calendar',
        'event_start_date' => '2027-03-01',
        'event_end_date' => '2027-03-03',
        'recurring_expected_identity' => 'Arquitectura circular',
    ];
    $workflow['recurring_target_identity_fingerprint'] = IDG_Recurring_Updates::target_post_fingerprint($id, 'event');
    $workflow['recurring_target_signature'] = IDG_Recurring_Updates::editorial_target_signature($id, 'evento');
    return $workflow;
}
function p1_zero_writes(string $scenario, int &$assertions): void {
    p1_assert(count($GLOBALS['p1_log']['insert']) === 0, $scenario, 'unexpected_insert', $assertions);
    p1_assert(count($GLOBALS['p1_log']['update']) === 0, $scenario, 'unexpected_update', $assertions);
    p1_assert(count($GLOBALS['p1_log']['meta']) === 0 && count($GLOBALS['p1_log']['terms']) === 0, $scenario, 'unexpected_meta_or_terms', $assertions);
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
$scenario = $argv[1] ?? '';
$assertions = 0;

if ($scenario === '') { exit(0); }

if ($scenario === 'post_valid_pending') {
    $result = IDG_Post_Creator::create_pending_post(p1_workflow());
    p1_assert($result['success'] === true && $result['post_id'] === 9101, $scenario, 'pending_result_or_id', $assertions);
    p1_assert(count($GLOBALS['p1_log']['insert']) === 1 && $GLOBALS['p1_log']['insert'][0]['post_status'] === 'pending', $scenario, 'not_exactly_one_pending_insert', $assertions);
    p1_assert(p1_meta_values('_yoast_wpseo_title') === ['Arquitectura circular 2027 | ideasDi'], $scenario, 'yoast_title_mismatch', $assertions);
    p1_assert(p1_meta_values('_yoast_wpseo_focuskw') === ['Arquitectura circular 2027'], $scenario, 'yoast_focuskw_mismatch', $assertions);
    p1_assert(p1_meta_values('_yoast_wpseo_metadesc') === ['Arquitectura circular 2027 analiza materiales, recorridos y decisiones espaciales con evidencia concreta para comprender su impacto cotidiano'], $scenario, 'yoast_metadesc_mismatch', $assertions);
    p1_pass($scenario, $assertions);
}
if ($scenario === 'post_invalid_rejected') {
    $result = IDG_Post_Creator::create_pending_post(p1_workflow(false));
    p1_assert($result['success'] === false, $scenario, 'invalid_input_was_accepted', $assertions);
    p1_assert(str_contains((string) $result['message'], 'No se detectó caja editorial'), $scenario, 'missing_box_diagnostic', $assertions);
    p1_zero_writes($scenario, $assertions);
    p1_pass($scenario, $assertions);
}
if ($scenario === 'post_invalid_override_pending') {
    $workflow = p1_workflow(false);
    $workflow['force_validation_override'] = true;
    $result = IDG_Post_Creator::create_pending_post($workflow);
    p1_assert($result['success'] === true && count($GLOBALS['p1_log']['insert']) === 1, $scenario, 'manual_override_did_not_write', $assertions);
    p1_assert($GLOBALS['p1_log']['insert'][0]['post_status'] === 'pending', $scenario, 'override_not_pending', $assertions);
    p1_assert(str_contains((string) $result['validation_summary'], 'AVISO') && str_contains((string) $result['validation_summary'], 'ignorada manualmente'), $scenario, 'override_warning_not_visible', $assertions);
    p1_assert(count(p1_meta_values('_idg_final_validation')) === 1 && str_contains((string) p1_meta_values('_idg_final_validation')[0], 'No se detectó caja editorial'), $scenario, 'override_audit_missing', $assertions);
    p1_pass($scenario, $assertions);
}
if (str_starts_with($scenario, 'recurring_')) {
    p1_seed_target();
    $workflow = p1_recurring_workflow();
    if ($scenario === 'recurring_no_permission') { $GLOBALS['p1_can_edit'] = false; }
    if ($scenario === 'recurring_wrong_selected_id') { $workflow['recurring_selected_post_id'] = 701; }
    if ($scenario === 'recurring_stale_fingerprint') { $workflow['recurring_target_identity_fingerprint'] = str_repeat('a', 64); }
    if ($scenario === 'recurring_stale_signature') { $workflow['recurring_target_signature'] = str_repeat('b', 64); }
    if ($scenario === 'recurring_wrong_type') { $workflow['recurring_target_post_type'] = 'post'; }
    if ($scenario === 'recurring_wrong_identity') { $workflow['recurring_expected_identity'] = 'Cordwainers'; }
    if ($scenario === 'recurring_wrong_year') { $workflow['seo_result'] = p1_seo_result('Arquitectura circular 2026', true); }
    $result = IDG_Post_Creator::update_existing_event($workflow);
    if ($scenario === 'recurring_valid_update') {
        p1_assert($result['success'] === true && $result['same_post_id'] === true, $scenario, 'update_result_or_id', $assertions);
        p1_assert(count($GLOBALS['p1_log']['update']) === 1 && (int) $GLOBALS['p1_log']['update'][0]['ID'] === 700, $scenario, 'not_exactly_one_target_update', $assertions);
        p1_assert(count($GLOBALS['p1_log']['insert']) === 0, $scenario, 'insert_used_for_recurring', $assertions);
        $after = get_post(700);
        p1_assert($after->post_name === 'arquitectura-circular-2027' && $after->post_status === 'publish' && $after->post_author === 41, $scenario, 'slug_status_author_changed', $assertions);
        p1_assert(get_post_thumbnail_id(700) === 77 && wp_get_object_terms(700, 'category') === [34] && wp_get_object_terms(700, 'post_tag') === [8, 9], $scenario, 'thumbnail_or_taxonomies_changed', $assertions);
        p1_pass($scenario, $assertions);
    }
    $diagnostics = [
        'recurring_no_permission' => 'No tienes permisos',
        'recurring_wrong_selected_id' => 'ID seleccionado originalmente no coincide',
        'recurring_stale_fingerprint' => 'identidad protegida',
        'recurring_stale_signature' => 'cambió después de preparar',
        'recurring_wrong_type' => 'cambió de tipo',
        'recurring_wrong_identity' => 'identidad editorial protegida',
        'recurring_wrong_year' => 'año del título no coincide',
    ];
    if (isset($diagnostics[$scenario])) {
        p1_assert($result['success'] === false, $scenario, 'unsafe_update_was_accepted', $assertions);
        p1_assert(str_contains((string) $result['message'], $diagnostics[$scenario]), $scenario, 'wrong_guard_diagnostic', $assertions);
        p1_zero_writes($scenario, $assertions);
        p1_pass($scenario, $assertions);
    }
}

fwrite(STDERR, "HARNESS_ERROR: unknown functional scenario: $scenario\n");
exit(70);
}
