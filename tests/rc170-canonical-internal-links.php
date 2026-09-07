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
// Exact final RC1.7.0 fingerprints; behavioral assertions remain unchanged.
foreach ([
    'ideasdi-redaccion-gerizim.php' => 'c024aa06149541dce9fd53fc5853e64017d5cdde86b82805aff82e6e2c751f64',
    'includes/class-canonical-adapter.php' => '9b8485e6e4a75df578fbc21e0d8a8717942f0c2326e69559847373ddf2b538db',
    'includes/class-canonical-context.php' => '89e97607417945f6386549e735433e78ac2b68e0635c9cdaef3ebe41012e85d0',
    'includes/data/editorial-canonical.php' => '95512585c4f7f17bf506f1b1b7734411e898f80b4bdf2e3c6995f203335b0034',
    'includes/class-final-guard.php' => 'ad97dc6d190f487d90b21061bbf5a824d505ac22e49182a3b9f4486f4590bdc4',
    'includes/class-post-creator.php' => 'bd69c626507539968ba4685695fa320cbee39d9a5f4bee1280f6fe72b47b3380',
    'tests/rc170-canonical-core.php' => '3625b9b3e3a1d1e3e053bb00be102de92106c1d3fc9cdd984408a92951b317d3',
    'tests/rc170-canonical-consumption.php' => 'b3e3e5617b2f5d2312ec413871bf30c19c4df83a934ee093da534bc798ebc070',
] as $file => $sha) {
    check(hash_file('sha256', ABSPATH . $file) === $sha, 'protected source ' . $file);
}
echo "CANONICAL_INTERNAL_LINKS_OK\n";
