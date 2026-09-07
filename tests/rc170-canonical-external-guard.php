<?php
/** D2-D1b2b1: real production consumers, with and without canonical context. */
define('ABSPATH', dirname(__DIR__) . '/');
function esc_url_raw($url) { return filter_var($url, FILTER_VALIDATE_URL) && preg_match('~^https?://~', $url) ? $url : ''; }
function esc_url($url) { return htmlspecialchars(esc_url_raw($url), ENT_QUOTES, 'UTF-8'); }
function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }
function wp_strip_all_tags($text) { return strip_tags($text); }
function remove_accents($text) { return strtr($text, ['á'=>'a', 'é'=>'e', 'í'=>'i', 'ó'=>'o', 'ú'=>'u']); }
// ASCII case/length fixtures; allow standalone execution without mbstring.
if (!function_exists('mb_strtolower')) { function mb_strtolower($text) { return strtolower($text); } }
if (!function_exists('mb_strlen')) { function mb_strlen($text) { return preg_match_all('/./us', $text); } }
function check($condition, $label) { if (!$condition) throw new RuntimeException($label); }
function invoke($class, $method, ...$args) { return (new ReflectionMethod($class, $method))->invoke(null, ...$args); }
require_once ABSPATH . 'includes/class-post-creator.php';
require_once ABSPATH . 'includes/class-final-guard.php';
$explicit = 'https://studio.test/project/';
$legacy = 'https://legacy.test/project/';
$information = 'https://news.test/story/';
$workflow = ['entity' => 'Studio', 'responsible_official_url' => $explicit, 'official_source' => $legacy, 'source_information_url' => $information];
foreach (['compatibility', 'canonical'] as $mode) {
    if ($mode === 'canonical') {
        require_once ABSPATH . 'includes/class-canonical-adapter.php';
        require_once ABSPATH . 'includes/class-canonical-context.php';
    }
    foreach (['IDG_Post_Creator', 'IDG_Final_Guard'] as $class) {
        foreach ([[$workflow, $explicit], [['official_source' => $legacy], $legacy], [['source_information_url' => $information], ''], [['responsible_official_url' => '', 'official_source' => $legacy], $legacy], [['responsible_official_url' => 'javascript:alert(1)'], '']] as [$input, $expected]) {
            check(invoke($class, 'resolved_official_source_url', $input) === $expected, "$mode $class URL precedence/safety");
        }
        foreach (['editorial_context' => 'event_calendar', 'recurring_target_post_type' => 'evento', 'wordpress_content_type' => 'Evento'] as $key => $value) {
            check(invoke($class, 'resolved_official_source_url', $workflow + [$key => $value]) === $legacy, "$mode $class separate event surface");
        }
    }
    $html = '<p>El proyecto de Studio presenta sus materiales.</p>';
    $linked = invoke('IDG_Post_Creator', 'ensure_official_source_link', $html, $workflow);
    check(str_contains($linked, 'href="' . $explicit . '"'), "$mode canonical insertion");
    check(!str_contains($linked, $legacy) && !str_contains($linked, $information), "$mode no substituted URL");
    check(strip_tags($linked) === strip_tags($html), "$mode unchanged factual prose");
    check(invoke('IDG_Post_Creator', 'ensure_official_source_link', $linked, $workflow) === $linked, "$mode no duplicate");
    $query = $workflow; $query['responsible_official_url'] .= '?a=1&b=2';
    $existing = '<p>El proyecto de <a href="' . esc_url($query['responsible_official_url']) . '">Studio</a>. Studio continúa.</p>';
    check(invoke('IDG_Post_Creator', 'ensure_official_source_link', $existing, $query) === $existing, "$mode escaped URL no duplicate");
    check(invoke('IDG_Post_Creator', 'ensure_official_source_link', '<p>Texto sin anchor contextual.</p>', $workflow) === '<p>Texto sin anchor contextual.</p>', "$mode no loose source line");
    $missing = 'Falta el enlace externo obligatorio hacia la URL del responsable.';
    $good = IDG_Final_Guard::validate_before_draft('', $linked, [], $workflow);
    $bad = IDG_Final_Guard::validate_before_draft('', str_replace($explicit, $legacy, $linked), [], $workflow);
    check(in_array($missing, $bad['errors'], true) && !in_array($missing, $good['errors'], true), "$mode guard validates canonical URL, rejects legacy conflict");
}
$event = $workflow + ['editorial_context' => 'event_calendar', 'recurring_target_post_type' => 'evento'];
$event_html = '<p>Consulta la <a href="' . $information . '">página oficial del evento</a>.</p>';
check(invoke('IDG_Final_Guard', 'event_presentation_status', $event_html, $event)['ok'], 'event official page retains information source');
foreach (['includes/class-post-creator.php', 'includes/class-final-guard.php'] as $file) {
    check(!str_contains(file_get_contents(ABSPATH . $file), 'https://' . 'ideasdi.com/eventos/'), 'no fixed event archive');
}
check(str_contains(file_get_contents(ABSPATH . 'ideasdi-redaccion-gerizim.php'), 'Version: 0.4.0-RC1.7.0'), 'final RC1.7.0 version');
// Exact final RC1.7.0 fingerprints; behavioral assertions remain unchanged.
foreach ([
    'ideasdi-redaccion-gerizim.php' => 'c024aa06149541dce9fd53fc5853e64017d5cdde86b82805aff82e6e2c751f64',
    'includes/class-canonical-adapter.php' => '9b8485e6e4a75df578fbc21e0d8a8717942f0c2326e69559847373ddf2b538db',
    'includes/class-canonical-context.php' => '89e97607417945f6386549e735433e78ac2b68e0635c9cdaef3ebe41012e85d0',
    'includes/data/editorial-canonical.php' => '95512585c4f7f17bf506f1b1b7734411e898f80b4bdf2e3c6995f203335b0034',
    'includes/class-internal-links.php' => '054f6f84a62c600dcf9b5c156c60bfed1fbef31072d8ae4df58258360c186970',
] as $file => $sha) {
    check(hash_file('sha256', ABSPATH . $file) === $sha, 'protected source ' . $file);
}
echo "CANONICAL_EXTERNAL_GUARD_OK\n";
