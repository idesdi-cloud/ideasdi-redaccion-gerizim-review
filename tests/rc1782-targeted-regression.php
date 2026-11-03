<?php
declare(strict_types=1);

define('ABSPATH', dirname(__DIR__) . '/');
define('IDG_VERSION', '0.4.0-RC1.7.8.2');
define('IDG_OPTION_KEY', 'idg_settings');
define('IDG_PROMPTS_OPTION_KEY', 'idg_prompt_settings');
define('IDG_SESSION_KEY_PREFIX', 'idg_workflow_user_');

$GLOBALS['rc1782_options'] = [IDG_OPTION_KEY => ['api_key' => 'local-only', 'model' => 'local-mock']];
$GLOBALS['rc1782_user_meta'] = [];
$GLOBALS['rc1782_post_meta'] = [];
$GLOBALS['rc1782_posts'] = [];
$GLOBALS['rc1782_terms'] = [];
$GLOBALS['rc1782_http_queue'] = [];
$GLOBALS['rc1782_http_calls'] = [];
$GLOBALS['rc1782_current_user'] = 7;
$GLOBALS['rc1782_can_edit'] = true;

if (!function_exists('mb_strtolower')) { function mb_strtolower($text, $encoding = null) { return strtolower((string) $text); } }
if (!function_exists('mb_strtoupper')) { function mb_strtoupper($text, $encoding = null) { return strtoupper((string) $text); } }
if (!function_exists('mb_strlen')) { function mb_strlen($text, $encoding = null) { return strlen((string) $text); } }
if (!function_exists('mb_stripos')) { function mb_stripos($haystack, $needle, $offset = 0, $encoding = null) { return stripos((string) $haystack, (string) $needle, (int) $offset); } }
if (!function_exists('mb_strpos')) { function mb_strpos($haystack, $needle, $offset = 0, $encoding = null) { return strpos((string) $haystack, (string) $needle, (int) $offset); } }
if (!function_exists('mb_strrpos')) { function mb_strrpos($haystack, $needle, $offset = 0, $encoding = null) { return strrpos((string) $haystack, (string) $needle, (int) $offset); } }
if (!function_exists('mb_substr')) { function mb_substr($text, $start, $length = null, $encoding = null) { return $length === null ? substr((string) $text, (int) $start) : substr((string) $text, (int) $start, (int) $length); } }

final class WP_Term {
    public function __construct(public int $term_id, public string $taxonomy, public string $name, public string $slug) {}
}
final class WP_Error {
    public function __construct(private string $message) {}
    public function get_error_message(): string { return $this->message; }
}

function rc1782_ok(bool $condition, string $label): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL {$label}\n");
        exit(1);
    }
    echo "OK {$label}\n";
}
function get_option($key, $default = []) { return $GLOBALS['rc1782_options'][$key] ?? $default; }
function update_option($key, $value, $autoload = null) { $GLOBALS['rc1782_options'][$key] = $value; return true; }
function add_option($key, $value, $deprecated = '', $autoload = null) { if (array_key_exists($key, $GLOBALS['rc1782_options'])) return false; $GLOBALS['rc1782_options'][$key] = $value; return true; }
function delete_option($key) { unset($GLOBALS['rc1782_options'][$key]); return true; }
function update_user_meta($user, $key, $value) { $GLOBALS['rc1782_user_meta'][$user][$key] = $value; return true; }
function get_user_meta($user, $key, $single = false) { return $GLOBALS['rc1782_user_meta'][$user][$key] ?? ''; }
function delete_user_meta($user, $key) { unset($GLOBALS['rc1782_user_meta'][$user][$key]); return true; }
function get_current_user_id() { return (int) $GLOBALS['rc1782_current_user']; }
function current_user_can($capability, ...$args) { return !empty($GLOBALS['rc1782_can_edit']); }
function current_time($format) { return $format === 'Y-m' ? '2026-11' : '2026-11-03 10:00:00'; }
function sanitize_key($value) { return strtolower((string) preg_replace('/[^a-z0-9_\-]/i', '', (string) $value)); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function sanitize_textarea_field($value) { return trim((string) $value); }
function sanitize_title($value) { return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower(remove_accents((string) $value))), '-'); }
function wp_strip_all_tags($value) { return strip_tags((string) $value); }
function wp_kses_post($value) { return (string) $value; }
function esc_url_raw($value) { return filter_var(trim((string) $value), FILTER_VALIDATE_URL) ? trim((string) $value) : ''; }
function esc_url($value) { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8'); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8'); }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8'); }
function absint($value) { return abs((int) $value); }
function is_wp_error($value) { return $value instanceof WP_Error; }
function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }
function wp_parse_url($url, $part = -1) { return parse_url((string) $url, $part); }
function home_url($path = '') { return 'https://ideasdi.com/' . ltrim((string) $path, '/'); }
function untrailingslashit($value) { return rtrim((string) $value, '/'); }
function remove_accents($value) { return strtr((string) $value, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','ñ'=>'n','Ñ'=>'N']); }
function get_term($id, $taxonomy = '') { $term = $GLOBALS['rc1782_terms'][(int) $id] ?? null; return $term instanceof WP_Term && ($taxonomy === '' || $term->taxonomy === $taxonomy) ? $term : null; }
function get_term_link($term, $taxonomy = '') { $id = $term instanceof WP_Term ? $term->term_id : (int) $term; return match ($id) { 3 => 'https://ideasdi.com/tag/animacion/', 4 => 'https://ideasdi.com/tag/software-3d/', default => new WP_Error('term missing') }; }
function get_category_link($id) { return (int) $id === 1232 ? 'https://ideasdi.com/category/modelado-3d/' : new WP_Error('category missing'); }
function wp_create_nonce($action) { return hash('sha256', 'nonce|' . (string) $action); }
function wp_verify_nonce($nonce, $action) { return hash_equals(wp_create_nonce($action), (string) $nonce); }
function wp_insert_post($postarr, $wp_error = false) { $id = 900 + count($GLOBALS['rc1782_posts']); $GLOBALS['rc1782_posts'][$id] = $postarr; return $id; }
function wp_set_post_terms($post_id, $terms, $taxonomy, $append = false) { return true; }
function update_post_meta($post_id, $key, $value) { $GLOBALS['rc1782_post_meta'][$post_id][$key] = $value; return true; }
function get_edit_post_link($post_id, $context = 'display') { return 'https://ideasdi.com/wp-admin/post.php?post=' . (int) $post_id; }
function serialize_blocks($blocks) { return json_encode($blocks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); }
function parse_blocks($content) { $decoded = json_decode((string) $content, true); return is_array($decoded) ? $decoded : []; }
function wp_remote_post($url, $args) { $GLOBALS['rc1782_http_calls'][] = ['url' => $url, 'args' => $args]; return array_shift($GLOBALS['rc1782_http_queue']) ?? new WP_Error('unexpected local call'); }
function wp_remote_retrieve_response_code($response) { return (int) ($response['status'] ?? 0); }
function wp_remote_retrieve_body($response) { return (string) ($response['body'] ?? ''); }
function wp_remote_retrieve_header($response, $name) { return (string) ($response['headers'][strtolower((string) $name)] ?? ''); }

final class IDG_Logger { public static array $rows = []; public static function log($event, $message, $context = []) { self::$rows[] = compact('event', 'message', 'context'); } }
final class IDG_Canonical_Context {
    public static function resolve(array $workflow): array { return ['primary_lens' => (string) ($workflow['primary_lens'] ?? ''), 'secondary_lenses' => (array) ($workflow['secondary_lenses'] ?? []), 'responsible_official_url' => (string) ($workflow['responsible_official_url'] ?? $workflow['official_source'] ?? '')]; }
    public static function prompt_block(array $workflow): string { return 'Contexto canónico local: ' . (string) ($workflow['primary_lens'] ?? ''); }
}
final class IDG_Canonical_Adapter {
    public static function resolve(array $workflow): array { $lens = trim((string) ($workflow['primary_lens'] ?? '')); return ['primary_lens' => $lens]; }
}
final class IDG_Priority_Readings {
    public static function tag_matrix(): array { return [
        ['tag'=>'Animación','tag_slug'=>'animacion','category_slug'=>'modelado-3d','status'=>'Index'],
        ['tag'=>'Software 3D','tag_slug'=>'software-3d','category_slug'=>'modelado-3d','status'=>'Index'],
    ]; }
    public static function is_noindex_tag_name(string $name, string $category = ''): bool { return false; }
    public static function tag_status(string $name, string $category = ''): string { return 'Index'; }
    public static function category_curated_url(string $category): string { return ''; }
    public static function build_suggestion(int $category, array $tags): string { return 'Animación y Software 3D'; }
}
final class IDG_Editorial_Rules {
    public static function get(): array { return ['h1_max_chars'=>68,'editorial_box_min_words'=>40,'editorial_box_max_words'=>55,'reel_scenes'=>6,'reel_vo_words'=>14,'reel_overlays_per_scene'=>3,'reel_overlay_max_chars'=>40,'reel_cta'=>'Conoce más de este proyecto en ideasDi.com']; }
    public static function prompt_block(string $scope = ''): string { return '\nReglas editoriales activas locales.'; }
}

require_once ABSPATH . 'includes/class-workflow-policies.php';
require_once ABSPATH . 'includes/contracts/interface-workflow-redaction-pipeline.php';
require_once ABSPATH . 'includes/contracts/interface-workflow-action-strategy.php';
require_once ABSPATH . 'includes/class-reel-contract.php';
require_once ABSPATH . 'includes/class-internal-links.php';
require_once ABSPATH . 'includes/class-validator.php';
require_once ABSPATH . 'includes/class-final-guard.php';
require_once ABSPATH . 'includes/class-post-creator.php';
require_once ABSPATH . 'includes/class-prompt-library.php';
require_once ABSPATH . 'includes/class-openai-client.php';
require_once ABSPATH . 'includes/class-usage-estimator.php';
require_once ABSPATH . 'includes/class-workflow-prompt-data.php';
require_once ABSPATH . 'includes/class-workflow-output-parser.php';
require_once ABSPATH . 'includes/class-workflow-redaction-pipeline.php';
require_once ABSPATH . 'includes/strategies/class-workflow-action-strategies.php';
require_once ABSPATH . 'includes/class-job-runner.php';

$GLOBALS['rc1782_terms'] = [
    1232 => new WP_Term(1232, 'category', 'Diseño digital', 'modelado-3d'),
    3 => new WP_Term(3, 'post_tag', 'Animación', 'animacion'),
    4 => new WP_Term(4, 'post_tag', 'Software 3D', 'software-3d'),
];

function rc1782_response(string $text, array $usage = ['input_tokens'=>10,'output_tokens'=>5,'total_tokens'=>15]): array {
    return ['status'=>200,'headers'=>['x-request-id'=>'local'],'body'=>json_encode(['object'=>'response','id'=>'resp_local','status'=>'completed','output_text'=>$text,'usage'=>$usage])];
}
function rc1782_box(string $name, int $count): string { $words = [$name]; for ($i = 2; $i <= $count; $i++) $words[] = 'dato' . $i; return implode(' ', $words); }
function rc1782_reel(bool $bad = false): string {
    $vo = [
        'ActionForge concentra animación, capas y exportación en navegador para pruebas rápidas de personajes y movimiento.',
        'La línea temporal en segundos permite seguir poses, pausar cambios y revisar el timing con claridad.',
        'Orbitar la cámara ayuda a comprobar el gesto desde distintos ángulos sin perder continuidad.',
        'FBX y GLB llevan el trabajo hacia Unreal, Unity, Godot y Blender sin cerrar el flujo.',
        'ActionForge funciona como estación ligera para preproducción y pruebas de movimiento con acceso sencillo.',
        'Conoce más de este proyecto en ideasDi.com',
    ];
    if (!$bad) {
        $vo[0] = 'ActionForge concentra animación, capas y exportación web para pruebas rápidas de personajes y movimiento.';
        $vo[1] = 'La línea temporal permite seguir poses, pausar cambios y revisar el timing con claridad.';
        $vo[3] = 'FBX y GLB llevan el trabajo a Unreal, Unity, Godot y Blender sin cerrarlo.';
    }
    $lines = [];
    foreach ($vo as $index => $text) {
        $scene = $index + 1;
        $lines[] = "VO {$scene}: {$text}";
        for ($overlay = 1; $overlay <= 3; $overlay++) $lines[] = "Overlay {$scene}.{$overlay}: Escena {$scene} texto {$overlay}";
    }
    return implode("\n", $lines);
}
function rc1782_article(string $box): string {
    return "# ActionForge lleva animación al navegador\n## Movimiento y preproducción en una interfaz ligera\nCGstuff Interactive ofrece acceso a ActionForge desde el [sitio oficial de la herramienta](https://actionforge.app/), donde puede utilizarse directamente en el navegador. La interfaz concentra capas, poses y exportación para ensayar personajes sin instalar una estación compleja.\n\nLa línea temporal permite revisar el timing y orbitar la cámara antes de llevar el trabajo hacia otros programas. Ese recorrido mantiene visibles decisiones concretas durante pruebas rápidas de movimiento.\n\nCaja editorial\n{$box}\n\n### Capas, poses y continuidad\nLa organización por capas separa partes del personaje y facilita cambios localizados durante una prueba. La cámara orbital permite comprobar el gesto desde varios ángulos sin perder la secuencia.\n\nLa línea temporal hace legible el orden de poses y pausas. Estas [pruebas de animación tridimensional](https://ideasdi.com/tag/animacion/) conectan observación, ajuste y continuidad dentro del navegador.\n\n### Exportación sin cerrar el flujo\nLos formatos FBX y GLB trasladan el resultado hacia Unreal, Unity, Godot o Blender. La exportación conserva la prueba como punto intermedio, no como destino rígido.\n\nActionForge funciona así como una estación ligera de preproducción. Su valor aparece en la rapidez para observar movimiento, corregir una pose y continuar el trabajo en otra herramienta.";
}
function rc1782_seo(string $article, string $reel): string {
    $meta = 'ActionForge reúne animación, capas, cámara y exportación en navegador para probar personajes y preparar flujos de movimiento.';
    return "ARTÍCULO FINAL\n{$article}\n\nMETA DESCRIPTION\n{$meta}\n\nINFORME SEO INTERNO\n- Keyword principal: ActionForge\n- 5 keywords utilizadas: animación, navegador, capas, exportación, personajes\n- Enlaces aplicados: 1 interno; 1 externo\n- Validaciones: pendientes del guard\n- 3 mejoras sugeridas: revisar ritmo, precisión y accesibilidad\n\nCOPY PARA REDES\nActionForge lleva pruebas de animación al navegador. #Animación #DiseñoDigital\n\nPAQUETE REEL\n{$reel}\n\nRETROALIMENTACIÓN GERIZIM\n- Se conservaron la caja, la estructura y las atribuciones verificables.";
}
function rc1782_workflow(string $id, string $seo = ''): array {
    return [
        'workflow_id'=>$id,'user_id'=>7,'status'=>'processing','keyword'=>'ActionForge','entity'=>'CGstuff Interactive',
        'official_source'=>'https://actionforge.app/','responsible_official_url'=>'https://actionforge.app/','category_id'=>1232,
        'tag_ids'=>[3,4],'tag_names'=>['Animación','Software 3D'],'primary_lens'=>'Animación','secondary_lenses'=>['Software 3D'],
        'piece_type'=>'Actualidad','brief_fact'=>'ActionForge permite animar y exportar desde el navegador.',
        'assignment_card'=>'Ficha ActionForge217','document_card'=>'Ficha documental ActionForge217','editorial_plan_raw'=>'Plan ActionForge217',
        'editorial_recipe_applied'=>'Leer decisiones de animación, continuidad y exportación.','recipe_base'=>'Animación y Software 3D',
        'recipe_base_structure'=>['semantic_library_version'=>'1.1.0'],'editorial_result'=>rc1782_article(rc1782_box('ActionForge', 40)),
        'base_article'=>rc1782_article(rc1782_box('ActionForge', 40)),'seo_result'=>$seo,
    ];
}
function rc1782_set_confirmation(array $workflow): array {
    $workflow['force_validation_override'] = true;
    $workflow['force_confirmation'] = true;
    $workflow['force_confirmation_user_id'] = get_current_user_id();
    $workflow['force_confirmation_workflow_id'] = (string) $workflow['workflow_id'];
    $workflow['force_confirmation_content_hash'] = IDG_Post_Creator::override_content_hash($workflow);
    $workflow['force_confirmation_nonce'] = wp_create_nonce(IDG_Post_Creator::force_nonce_action($workflow, get_current_user_id()));
    return $workflow;
}
function rc1782_post_html(int $post_id): string {
    $blocks = json_decode((string) ($GLOBALS['rc1782_posts'][$post_id]['post_content'] ?? ''), true);
    $html = '';
    $walk = function ($rows) use (&$walk, &$html): void { foreach ((array) $rows as $row) { $html .= (string) ($row['innerHTML'] ?? ''); $walk($row['innerBlocks'] ?? []); } };
    $walk($blocks);
    return $html;
}

/* SkyPaint199: P2 unresolved + caja de 39 palabras y force fail closed. */
$sky_box = 'SkyPaint es un complemento de Blender creado por Casey_Sheep para generar cielos estilizados de forma procedimental. Permite cambiar entre día y noche mediante la rotación solar e incluye nubes, sol, luna y estrellas, con compatibilidad para Cycles y EEVEE.';
$sky_article = "# SkyPaint genera cielos estilizados en Blender\n## Rotación solar y atmósfera procedural\nCasey_Sheep desarrolla SkyPaint para generar cielos estilizados en Blender. El complemento reúne nubes, sol, luna y estrellas sin recurrir a texturas ni HDRI.\n\nLa rotación de la luz solar permite pasar del día a la noche dentro de la escena. Ese control vincula el cielo con la iluminación del proyecto.\n\nCaja editorial\n{$sky_box}\n\n### Control de la luz solar\nLa rotación solar modifica el paso entre día y noche. El ajuste permite trabajar la atmósfera de escenas de animación, concepto y cartoon.\n\nLa configuración inicial se realiza con un clic. Después se puede observar el cambio del cielo al rotar la luz solar.\n\n### Compatibilidad en Blender\nSkyPaint funciona con Cycles y EEVEE. Su construcción procedural evita la dependencia de texturas y HDRI para generar estos cielos estilizados.\n\nEl complemento está disponible en el [perfil oficial del creador](https://superhivemarket.com/creators/caseysheep), que identifica a Casey_Sheep como responsable.";
$sky_voices = [
    'SkyPaint genera cielos estilizados para Blender mediante procedimientos que prescinden de texturas y HDRI.',
    'La rotación de luz solar permite cambiar entre día y noche durante la escena.',
    'Nubes, sol, luna y estrellas forman parte del sistema para construir cielos de animación.',
    'Casey_Sheep desarrolla este complemento compatible con Cycles y EEVEE para flujos dentro de Blender.',
    'Un clic configura el sistema antes de ajustar la iluminación y observar cambios atmosféricos.',
    'Conoce más de este proyecto en ideasDi.com',
];
$sky_lines = [];
foreach ($sky_voices as $index => $voice) {
    $scene = $index + 1;
    if ($scene === 2) $voice = str_replace('permite cambiar', "permite\ncambiar", $voice);
    $sky_lines[] = "VO {$scene}: {$voice}";
    for ($overlay = 1; $overlay <= 3; $overlay++) $sky_lines[] = "Overlay {$scene}.{$overlay}: Cielo {$scene} detalle {$overlay}";
}
$sky_reel = implode("\n", $sky_lines);
$sky_seo = rc1782_seo($sky_article, $sky_reel);
$sky_seo = str_replace('ActionForge reúne animación, capas, cámara y exportación en navegador para probar personajes y preparar flujos de movimiento.', 'SkyPaint genera cielos estilizados en Blender sin texturas ni HDRI, con rotación solar y compatibilidad con Cycles y EEVEE.', $sky_seo);
$sky_seo = str_replace('ActionForge lleva pruebas de animación al navegador.', 'SkyPaint genera cielos estilizados en Blender.', $sky_seo);
$sky_seo = str_replace('ActionForge', 'SkyPaint', $sky_seo);
$sky = rc1782_workflow('SkyPaint199', $sky_seo);
$sky['keyword'] = 'SkyPaint'; $sky['entity'] = 'Casey_Sheep';
$sky['official_source'] = 'https://superhivemarket.com/creators/caseysheep';
$sky['responsible_official_url'] = $sky['official_source'];
$sky['brief_fact'] = 'SkyPaint 1.0 es un complemento para Blender de Casey_Sheep que genera cielos estilizados procedimentales sin texturas ni HDRI, con rotación solar y compatibilidad con Cycles y EEVEE.';
$sky['document_card'] = $sky['brief_fact']; $sky['assignment_card'] = 'Brief Radar 199 / hallazgo 3366: cielos estilizados en Blender.';
$sky['editorial_plan_raw'] = 'Leer la relación entre rotación solar y atmósfera procedural.';
$sky['editorial_recipe_applied'] = $sky['editorial_plan_raw'];
$sky['base_article'] = $sky_article; $sky['editorial_result'] = $sky_article;
// Snapshot de regresión: resolución editorial P2 unresolved, sin destino autorizado.
$sky['tag_ids'] = []; $sky['primary_lens'] = ''; $sky['secondary_lenses'] = [];
$normal = IDG_Post_Creator::preflight_public($sky);
rc1782_ok(empty($normal['ok']) && IDG_Post_Creator::override_eligible($normal['validation']), 'SkyPaint199 preflight normal inválido pero solo con errores omitibles clasificados');

$before = count($GLOBALS['rc1782_posts']);
$no_confirm = $sky; $no_confirm['force_validation_override'] = true;
rc1782_ok(empty(IDG_Post_Creator::create_pending_post($no_confirm)['success']) && count($GLOBALS['rc1782_posts']) === $before, 'force sin confirmación no escribe');
$confirmed = rc1782_set_confirmation($sky);
$forced = IDG_Post_Creator::create_pending_post($confirmed);
rc1782_ok(!empty($forced['success']) && empty($forced['validation']['ok']) && !empty($forced['validation']['overridden']), 'force válido escribe pending sin convertir validation.ok en true');
$forced_id = (int) $forced['post_id'];
$override_meta = json_decode((string) ($GLOBALS['rc1782_post_meta'][$forced_id]['_idg_validation_override'] ?? ''), true);
rc1782_ok(!empty($override_meta['manual_review_required']) && !empty($override_meta['ignored_errors']) && !empty($override_meta['seal']), 'force registra errores, usuario, hashes, sello y revisión manual');
rc1782_ok(($GLOBALS['rc1782_posts'][$forced_id]['post_status'] ?? '') === 'pending' && ($GLOBALS['rc1782_posts'][$forced_id]['post_title'] ?? '') !== '' && ($GLOBALS['rc1782_post_meta'][$forced_id]['_yoast_wpseo_title'] ?? '') === '%%title%%', 'force conserva pending, título H1 y Yoast dinámico');
rc1782_ok(str_contains(rc1782_post_html($forced_id), 'SkyPaint') && !str_contains(rc1782_post_html($forced_id), 'tag/animacion'), 'force conserva caja y no inventa URL P2');

foreach (['stale','user','permission','target','invented'] as $negative) {
    $case = rc1782_set_confirmation($sky);
    $GLOBALS['rc1782_can_edit'] = true; $GLOBALS['rc1782_current_user'] = 7;
    if ($negative === 'stale') $case['force_confirmation_content_hash'] = str_repeat('0', 64);
    if ($negative === 'user') $GLOBALS['rc1782_current_user'] = 8;
    if ($negative === 'permission') $GLOBALS['rc1782_can_edit'] = false;
    if ($negative === 'target') $case['draft_post_id'] = 44;
    if ($negative === 'invented') {
        $case['seo_result'] = str_replace('La rotación solar modifica', '[destino interno inventado](https://ideasdi.com/inventado/) modifica', $case['seo_result']);
        $case = rc1782_set_confirmation($case);
    }
    $writes = count($GLOBALS['rc1782_posts']);
    $result = IDG_Post_Creator::create_pending_post($case);
    rc1782_ok(empty($result['success']) && count($GLOBALS['rc1782_posts']) === $writes, "SkyPaint199 negativo {$negative} produce cero escrituras");
}
$GLOBALS['rc1782_can_edit'] = true; $GLOBALS['rc1782_current_user'] = 7;
rc1782_ok(!IDG_Post_Creator::override_eligible(['ok'=>false,'errors'=>['error desconocido'],'error_codes'=>['unknown']]), 'error desconocido bloquea aun con force');

/* La estrategia real consume la confirmación y deja historial explícito. */
$strategy_workflow = rc1782_set_confirmation(array_merge($sky, ['workflow_id'=>'SkyPaint199-strategy']));
$GLOBALS['rc1782_options']['SkyPaint199-strategy'] = $strategy_workflow;
(new IDG_Draft_Workflow_Action_Strategy())->execute('SkyPaint199-strategy', 'draft_force', $strategy_workflow);
$strategy_saved = get_option('SkyPaint199-strategy', []);
rc1782_ok(!empty($strategy_saved['draft_post_id']) && empty($strategy_saved['force_confirmation_nonce']), 'estrategia real crea pending y consume confirmación');

/* ActionForge217: pipeline SEO, reparación única VO 1/2/4 y payload idéntico. */
$bad_reel = rc1782_reel(true);
$good_reel = rc1782_reel(false);
$seo_bad = rc1782_seo(rc1782_article(rc1782_box('ActionForge', 40)), $bad_reel);
$repair_json = json_encode(['patches'=>[
    ['segment'=>'VO:1','replacement'=>'ActionForge concentra animación, capas y exportación web para pruebas rápidas de personajes y movimiento.'],
    ['segment'=>'VO:2','replacement'=>'La línea temporal permite seguir poses, pausar cambios y revisar el timing con claridad.'],
    ['segment'=>'VO:4','replacement'=>'FBX y GLB llevan el trabajo a Unreal, Unity, Godot y Blender sin cerrarlo.'],
]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$GLOBALS['rc1782_http_queue'] = [rc1782_response($seo_bad), rc1782_response((string) $repair_json, ['input_tokens'=>7,'output_tokens'=>4,'total_tokens'=>11])];
$GLOBALS['rc1782_http_calls'] = [];
$action = rc1782_workflow('ActionForge217');
$GLOBALS['rc1782_options']['ActionForge217'] = $action;
(new IDG_Workflow_Redaction_Pipeline())->execute('ActionForge217', 'seo', $action, new IDG_OpenAI_Client('local-only', 'local-mock'));
$action_done = get_option('ActionForge217', []);
rc1782_ok(count($GLOBALS['rc1782_http_calls']) === 2 && !empty($action_done['seo_repair']['attempted']) && !empty($action_done['seo_repair']['applied']), 'SEO usa una sola llamada de reparación y no regenera el artículo');
rc1782_ok(($action_done['status'] ?? '') === 'completed' && ($action_done['last_action'] ?? '') === 'seo' && !empty($action_done['seo_preflight_payload_hash']), 'seo_completed ocurre solo después del preflight aprobado');
$inspection = $action_done['seo_reel_validation'];
rc1782_ok(array_slice($inspection['vo_counts'], 0, 5, true) === [1=>14,2=>14,3=>14,4=>14,5=>14], 'VO 1–5 quedan en 14 palabras por conteo real');
$bad_inspection = IDG_Reel_Contract::inspect($bad_reel);
rc1782_ok($inspection['vo'][3] === $bad_inspection['vo'][3] && $inspection['vo'][5] === $bad_inspection['vo'][5] && $inspection['vo'][6] === $bad_inspection['vo'][6] && $inspection['overlays'] === $bad_inspection['overlays'], 'VO 3/5/6 y todos los overlays quedan byte idénticos');
rc1782_ok(str_contains((string) $action_done['seo_result'], rc1782_article(rc1782_box('ActionForge', 40))), 'la reparación Reel no regenera ni altera el artículo');
rc1782_ok(str_contains($action_done['seo_result'], 'sitio oficial de la herramienta') && !str_contains($action_done['seo_result'], '[ActionForge](https://actionforge.app/'), 'enlace externo contextual usa anchor no keyword');
rc1782_ok(str_contains((string) $action_done['seo_result'], 'Validaciones verificadas por plugin: preflight Gutenberg aprobado; Reel validado.'), 'informe SEO sustituye el claim del modelo por resultado real de preflight y Reel');

/* La decisión P2 se fija en el workflow completo antes de formar el payload reducido. */
$prompt_data = IDG_Workflow_Prompt_Data::prepare($action, $action['editorial_result'], 'seo');
rc1782_ok(($prompt_data['primary_lens'] ?? '') === 'Animación' && ($prompt_data['internal_links_resolved'] ?? []) === IDG_Internal_Links::normalize($action), 'Prompt_Data conserva primary_lens y transmite exactamente la resolución P2 canónica');

/* Recorrido preventivo completo: una llamada válida por fase, sin usar reparación. */
$journey = rc1782_workflow('ActionForge217-prevention');
$journey['base_article'] = ''; $journey['editorial_result'] = ''; $journey['seo_result'] = '';
$journey_editorial = "ARTÍCULO REVISADO\n" . rc1782_article(rc1782_box('ActionForge', 40)) . "\n\nDIAGNÓSTICO EDITORIAL INTERNO\n- Sin cambios.\n\nNOTAS EDITORIALES INTERNAS\nSin notas.";
$GLOBALS['rc1782_http_queue'] = [
    rc1782_response(rc1782_article(rc1782_box('ActionForge', 40))),
    rc1782_response($journey_editorial),
    rc1782_response(rc1782_seo(rc1782_article(rc1782_box('ActionForge', 40)), rc1782_reel())),
];
$GLOBALS['rc1782_http_calls'] = [];
$GLOBALS['rc1782_options']['ActionForge217-prevention'] = $journey;
$pipeline = new IDG_Workflow_Redaction_Pipeline();
$pipeline->execute('ActionForge217-prevention', 'generate', $journey, new IDG_OpenAI_Client('local-only', 'local-mock'));
$journey = get_option('ActionForge217-prevention', []);
$pipeline->execute('ActionForge217-prevention', 'editorial', $journey, new IDG_OpenAI_Client('local-only', 'local-mock'));
$journey = get_option('ActionForge217-prevention', []);
$pipeline->execute('ActionForge217-prevention', 'seo', $journey, new IDG_OpenAI_Client('local-only', 'local-mock'));
$journey_done = get_option('ActionForge217-prevention', []);
rc1782_ok(count($GLOBALS['rc1782_http_calls']) === 3 && empty($journey_done['generate_repair']['attempted']) && empty($journey_done['editorial_repair']['attempted']) && empty($journey_done['seo_repair']['attempted']), 'salida válida desde origen completa generate/editorial/SEO con una llamada por fase y sin reparación');
rc1782_ok(!empty($journey_done['generate_validation']['ok']) && !empty($journey_done['editorial_validation']['ok']) && !empty($journey_done['seo_validation']['ok']) && str_contains((string) $journey_done['seo_result'], 'Caja editorial'), 'recorrido preventivo conserva caja, estructura, tesis y validación final');

$created = IDG_Post_Creator::create_pending_post($action_done);
rc1782_ok(!empty($created['success']), 'create_pending_post escribe ActionForge217 después de aprobación');
$created_id = (int) $created['post_id'];
$saved_content = (string) ($GLOBALS['rc1782_posts'][$created_id]['post_content'] ?? '');
rc1782_ok(hash_equals((string) $action_done['seo_preflight_post_content'], $saved_content), 'payload Gutenberg validado coincide exactamente con el guardado');
$saved_html = rc1782_post_html($created_id);
rc1782_ok(substr_count($saved_html, 'https://actionforge.app/') === 1 && substr_count($saved_html, 'https://ideasdi.com/tag/animacion/') === 1, 'post guarda exactamente un externo oficial y un P2 Animación autorizado');
rc1782_ok(($GLOBALS['rc1782_posts'][$created_id]['post_title'] ?? '') === 'ActionForge lleva animación al navegador' && ($GLOBALS['rc1782_post_meta'][$created_id]['_yoast_wpseo_title'] ?? '') === '%%title%%' && str_contains($saved_html, 'featured-snippet-box'), 'post conserva H1 como título, Yoast y caja Gutenberg');

/* Negativos reales del enlace externo, derivados de ActionForge217. */
$missing_external_article = str_replace('[sitio oficial de la herramienta](https://actionforge.app/)', 'sitio oficial de la herramienta', rc1782_article(rc1782_box('ActionForge', 40)));
$missing_external = IDG_Post_Creator::preflight_public(rc1782_workflow('ActionForge217-missing-link', rc1782_seo($missing_external_article, rc1782_reel())));
rc1782_ok(empty($missing_external['ok']) && !str_contains($missing_external['prepared']['html_content'], 'href="https://actionforge.app/"'), 'postprocessing no inventa el enlace ni enlaza automáticamente ActionForge');
$extra_external_article = str_replace('La organización por capas', '[enlace adicional no autorizado](https://invented.invalid/tool) La organización por capas', rc1782_article(rc1782_box('ActionForge', 40)));
$extra_external_workflow = rc1782_workflow('ActionForge217-extra-link', rc1782_seo($extra_external_article, rc1782_reel()));
$extra_external = IDG_Post_Creator::preflight_public($extra_external_workflow);
rc1782_ok(empty($extra_external['ok']) && !IDG_Post_Creator::override_eligible($extra_external['validation']), 'enlace externo adicional bloquea preflight y excepción editorial');
$writes = count($GLOBALS['rc1782_posts']);
rc1782_ok(empty(IDG_Post_Creator::create_pending_post(rc1782_set_confirmation($extra_external_workflow))['success']) && count($GLOBALS['rc1782_posts']) === $writes, 'force con enlace externo no autorizado produce cero escrituras');
$tampered = $action_done;
$tampered['seo_result'] = str_replace('La organización por capas', 'El trabajo por capas', $tampered['seo_result']);
$writes = count($GLOBALS['rc1782_posts']);
rc1782_ok(empty(IDG_Post_Creator::create_pending_post($tampered)['success']) && count($GLOBALS['rc1782_posts']) === $writes, 'sello SEO rechaza contenido modificado antes de escritura');

/* Reparación inválida: intervención, sin processing, seo_completed ni bucle. */
$GLOBALS['rc1782_http_queue'] = [rc1782_response($seo_bad), rc1782_response(json_encode(['patches'=>[['segment'=>'VO:1','replacement'=>'sigue teniendo quince palabras deliberadamente para fallar esta reparación local ahora mismo completa']]]))];
$GLOBALS['rc1782_http_calls'] = [];
$invalid = rc1782_workflow('ActionForge217-invalid');
$GLOBALS['rc1782_options']['ActionForge217-invalid'] = $invalid;
(new IDG_Workflow_Redaction_Pipeline())->execute('ActionForge217-invalid', 'seo', $invalid, new IDG_OpenAI_Client('local-only', 'local-mock'));
$invalid_done = get_option('ActionForge217-invalid', []);
$events = array_column((array) ($invalid_done['history'] ?? []), 'event');
rc1782_ok(count($GLOBALS['rc1782_http_calls']) === 2 && ($invalid_done['status'] ?? '') === 'failed' && !in_array('seo_completed', $events, true), 'reparación SEO inválida queda en intervención sin bucle ni seo_completed');

rc1782_ok(!empty($invalid_done['seo_article_validation']['ok']) && empty($invalid_done['seo_reel_validation']['valid']), 'artículo válido y reel inválido conservan estados independientes');

/* Generación/editorial inválidas no se completan; editorial unchanged es honesta. */
$phase = rc1782_workflow('SkyPaint199-generate');
$phase['base_article'] = ''; $phase['editorial_result'] = '';
$GLOBALS['rc1782_http_queue'] = [rc1782_response('# SkyPaint'), rc1782_response('{"patches":[]}')];
$GLOBALS['rc1782_options']['SkyPaint199-generate'] = $phase;
(new IDG_Workflow_Redaction_Pipeline())->execute('SkyPaint199-generate', 'generate', $phase, new IDG_OpenAI_Client('local-only', 'local-mock'));
$generate_failed = get_option('SkyPaint199-generate', []);
rc1782_ok(($generate_failed['status'] ?? '') === 'failed' && ($generate_failed['last_action'] ?? '') !== 'generate', 'generación inválida no se declara completa');

$editorial_invalid = rc1782_workflow('ActionForge217-editorial-invalid');
$GLOBALS['rc1782_http_queue'] = [rc1782_response("ARTÍCULO REVISADO\n# ActionForge\n\nDIAGNÓSTICO EDITORIAL INTERNO\nCorregido"), rc1782_response('{"patches":[]}')];
$GLOBALS['rc1782_options']['ActionForge217-editorial-invalid'] = $editorial_invalid;
(new IDG_Workflow_Redaction_Pipeline())->execute('ActionForge217-editorial-invalid', 'editorial', $editorial_invalid, new IDG_OpenAI_Client('local-only', 'local-mock'));
$editorial_failed = get_option('ActionForge217-editorial-invalid', []);
rc1782_ok(($editorial_failed['status'] ?? '') === 'failed' && ($editorial_failed['last_action'] ?? '') !== 'editorial', 'editorial inválida no se declara completa');

$unchanged = rc1782_workflow('ActionForge217-editorial-unchanged');
$unchanged_output = "ARTÍCULO REVISADO\n" . $unchanged['base_article'] . "\n\nDIAGNÓSTICO EDITORIAL INTERNO\n- Se corrigieron varios pasajes.\n\nNOTAS EDITORIALES INTERNAS\nSin notas.";
$GLOBALS['rc1782_http_queue'] = [rc1782_response($unchanged_output)];
$GLOBALS['rc1782_options']['ActionForge217-editorial-unchanged'] = $unchanged;
(new IDG_Workflow_Redaction_Pipeline())->execute('ActionForge217-editorial-unchanged', 'editorial', $unchanged, new IDG_OpenAI_Client('local-only', 'local-mock'));
$unchanged_done = get_option('ActionForge217-editorial-unchanged', []);
rc1782_ok(array_key_exists('editorial_changed', $unchanged_done) && $unchanged_done['editorial_changed'] === false && str_contains((string) $unchanged_done['editorial_diagnosis'], 'unchanged; no se atribuyen correcciones ni mejoras no realizadas'), 'editorial unchanged se registra por comparación real');


/* Correcciones acotadas de la revisión RC1.7.8.3. */
$ui = file_get_contents(ABSPATH . 'includes/class-admin-page.php');
rc1782_ok(
    str_contains($ui, '$seo_available && self::should_show_force_draft($workflow)')
    && !str_contains($ui, '$seo_done && self::should_show_force_draft($workflow)'),
    'la excepción depende de salida SEO disponible, no de validación aprobada'
);
$public_line = '- Validaciones: frase pública preservada.';
$sample = "ARTÍCULO FINAL\n{$public_line}\n\nINFORME SEO INTERNO\n- Validaciones: cumple\n- Caja editorial: cumple\n\nCOPY PARA REDES\nCopy.";
$method = new ReflectionMethod(IDG_Workflow_Redaction_Pipeline::class, 'verified_seo_result');
$verified = $method->invoke(new IDG_Workflow_Redaction_Pipeline(), $sample, ['ok'=>false], ['valid'=>false]);
rc1782_ok(str_starts_with($verified, "ARTÍCULO FINAL\n{$public_line}\n"), 'el informe verificado no modifica una línea pública homónima');
rc1782_ok(str_contains($verified, 'Declaraciones del modelo (no equivalen a verificación del plugin):') && str_contains($verified, 'preflight Gutenberg requiere intervención; Reel requiere intervención.'), 'el informe distingue afirmaciones del modelo de resultados rechazados');
rc1782_ok($method->invoke(new IDG_Workflow_Redaction_Pipeline(), $verified, ['ok'=>false], ['valid'=>false]) === $verified, 'la verificación del informe es idempotente');

echo "RC1782_TARGETED_FUNCTIONAL_OK\n";
