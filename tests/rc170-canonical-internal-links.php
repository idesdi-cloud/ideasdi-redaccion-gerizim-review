<?php
/** Focused D2-D1b2a test: production resolution, in-memory WordPress terms. */
define('ABSPATH', dirname(__DIR__) . '/');
class WP_Error {}
class WP_Term {
    public $term_id, $name, $taxonomy;
    public function __construct($id, $name, $taxonomy = 'post_tag') {
        $this->term_id = $id; $this->name = $name; $this->taxonomy = $taxonomy;
    }
}
function is_wp_error($value) { return $value instanceof WP_Error; }
function esc_url_raw($value) { return filter_var($value, FILTER_VALIDATE_URL) && preg_match('~^https?://~', $value) ? $value : ''; }
function sanitize_text_field($value) { return trim(strip_tags($value)); }
function absint($value) { return abs((int) $value); }
function get_term($id, $taxonomy) { return $GLOBALS['terms'][$taxonomy][$id] ?? null; }
function get_term_by($field, $name, $taxonomy) {
    foreach ($GLOBALS['terms'][$taxonomy] ?? [] as $term) {
        if ($term instanceof WP_Term && $term->name === $name) return $term;
    }
    return false;
}
function get_term_link($term, $taxonomy) {
    $id = $term instanceof WP_Term ? $term->term_id : $term;
    return $GLOBALS['urls'][$taxonomy][$id] ?? new WP_Error;
}
function get_category_link($id) { return 'https://fixture.test/category/product/'; }
function get_post_type_archive_link($type) { return $GLOBALS['archive']; }
// Compatibility seam: SEO status is controlled independently of canonical identity.
class IDG_Priority_Readings {
    public static function is_noindex_tag_name($name, $category = '') { return in_array($name, $GLOBALS['noindex'], true); }
    public static function tag_status($name, $category = '') { return self::is_noindex_tag_name($name) ? 'NoIndex' : 'Index'; }
    public static function category_curated_url($name) { return 'https://fixture.test/curated/'; }
}
require_once ABSPATH . 'includes/class-canonical-adapter.php';
require_once ABSPATH . 'includes/class-canonical-context.php';
require_once ABSPATH . 'includes/class-internal-links.php';
function check($condition, $label) { if (!$condition) throw new RuntimeException($label); }
function selected($workflow, $id) {
    $expected = $GLOBALS['urls']['post_tag'][$id];
    foreach (['automatic', 'normalize'] as $method) {
        $links = IDG_Internal_Links::$method($workflow);
        check(count($links) === 1 && $links[0]['url'] === $expected && $links[0]['source_type'] === 'tag', $method . ' selects ' . $id);
    }
    check(IDG_Internal_Links::library($workflow)['editorial_resolution_status'] === 'resolved', 'resolved metadata');
}
function unresolved($workflow) {
    $library = IDG_Internal_Links::library($workflow);
    check($library['primary'] === [] && $library['editorial_resolution_status'] === 'unresolved', 'unresolved metadata');
    check(IDG_Internal_Links::automatic($workflow) === [] && IDG_Internal_Links::normalize($workflow) === [], 'no fallback links');
    check(str_contains(IDG_Internal_Links::library_summary($workflow), 'unresolved'), 'unresolved summary');
}
$terms = ['post_tag' => [1 => new WP_Term(1, 'Mobiliario'), 2 => new WP_Term(2, 'Iluminación natural'), 3 => new WP_Term(3, 'Materiales'), 4 => new WP_Term(4, 'Unrelated')], 'category' => [9 => new WP_Term(9, 'Diseño de producto', 'category')]];
$urls = ['post_tag' => [1 => 'https://fixture.test/real-furniture/', 2 => 'https://fixture.test/real-light/', 3 => 'https://fixture.test/real-materials/', 4 => 'https://fixture.test/unrelated/']];
$noindex = [];
$workflow = ['category_id' => 9, 'primary_lens' => 'furniture', 'secondary_lenses' => ['lighting', 'materiality'], 'tag_ids' => [4, 3, 2, 1]];
selected($workflow, 1);
$noindex = ['Mobiliario']; selected($workflow, 2);
$noindex = []; unset($terms['post_tag'][1]); selected($workflow, 2);
$terms['post_tag'][1] = new WP_Error; selected($workflow, 2);
$terms['post_tag'][1] = new WP_Term(1, 'Mobiliario');
$urls['post_tag'][1] = new WP_Error; selected($workflow, 2);
$urls['post_tag'][1] = 'javascript:alert(1)'; selected($workflow, 2);
$reverse = $workflow; $reverse['secondary_lenses'] = ['materiality', 'lighting']; selected($reverse, 3);
$noindex = ['Mobiliario', 'Iluminación natural', 'Materiales']; unresolved($workflow);
check(IDG_Internal_Links::library($workflow)['category_url'] !== '', 'category exists but is never selected');
$noindex = [];
unresolved(['tag_ids' => [4], 'tag_names' => ['Unrelated'], 'category_id' => 9]);
unresolved(['primary_lens' => 'unknown', 'tag_ids' => [1, 2, 3]]);
$urls['post_tag'][1] = 'https://fixture.test/current-furniture/';
foreach (['post', 'category', 'tag'] as $type) {
    $structured = [['url' => 'https://fixture.test/arbitrary/', 'source_type' => $type, 'post_id' => 123], ['url' => $urls['post_tag'][3], 'source_type' => 'tag']];
    selected($workflow + ['internal_links_structured' => $structured], 1);
    unresolved(['category_id' => 9, 'internal_links_structured' => $structured]);
}
selected(['primary_lens' => 'Mobiliario'], 1); // Exact-name WP lookup, no tag_ids.
$archive = 'https://fixture.test/calendar-archive/';
$urls['event_topic'][20] = 'https://fixture.test/event-topic/';
$event = $workflow + ['editorial_context' => 'event_calendar', 'event_taxonomy_context' => [['taxonomy' => 'event_topic', 'terms' => [['term_id' => 20, 'name' => 'Design week']]]]];
check(IDG_Internal_Links::automatic($event)[0]['url'] === $archive, 'event archive');
check(IDG_Internal_Links::normalize($event)[0]['source_type'] === 'post_type_archive', 'event normalize');
$archive = false;
check(IDG_Internal_Links::automatic($event)[0]['url'] === $urls['event_topic'][20], 'event taxonomy');
unset($urls['event_topic'][20]);
check(IDG_Internal_Links::automatic($event) === [], 'event never falls back to article tags');
$archive = 'https://fixture.test/calendar-archive/';
check(IDG_Internal_Links::automatic($workflow + ['recurring_target_post_type' => 'evento'])[0]['url'] === $archive, 'CPT surface separate');
foreach (['includes/class-internal-links.php', 'tests/rc170-canonical-internal-links.php'] as $file) {
    check(!str_contains(file_get_contents(ABSPATH . $file), 'https://' . 'ideasdi.com/' . 'eventos/'), 'no hard-coded event URL');
}
// Protected source fingerprints at supplied baseline 46e15bc2.
foreach ([
    'ideasdi-redaccion-gerizim.php' => '19db7d8495c53f959f7b68ea69e8c51f13c87ede0a0eb6560fcbf1a4334066cd',
    'includes/class-canonical-adapter.php' => 'd1d0135d9618c998c4b3dcb59c5ab4fde864b882c399a125cdb6f9ef973d45fa',
    'includes/class-canonical-context.php' => '89e97607417945f6386549e735433e78ac2b68e0635c9cdaef3ebe41012e85d0',
    'includes/data/editorial-canonical.php' => '95512585c4f7f17bf506f1b1b7734411e898f80b4bdf2e3c6995f203335b0034',
    'includes/class-final-guard.php' => '8e4e06c7a4ce47e608ee230b50e36513357aa0682662e79365bce8e8612cca82',
    'includes/class-post-creator.php' => 'c3c40b9b8ac57d3cd1dbdba184b61f602049602e061faf4f7b0fcb3e1ff15b3d',
    'tests/rc170-canonical-core.php' => '3625b9b3e3a1d1e3e053bb00be102de92106c1d3fc9cdd984408a92951b317d3',
    'tests/rc170-canonical-consumption.php' => '27626f1b12098a5ee004b6fb561fbfbe63501d3d6858124f63fe2140842949a4',
] as $file => $sha) {
    check(hash_file('sha256', ABSPATH . $file) === $sha, 'protected source ' . $file);
}
echo "CANONICAL_INTERNAL_LINKS_OK\n";
