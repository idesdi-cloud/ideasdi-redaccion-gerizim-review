<?php
/** Standalone production-class coverage for D2-D1b1; no WordPress runtime. */
define('ABSPATH', dirname(__DIR__) . '/');
define('IDG_PLUGIN_DIR', ABSPATH);
// Minimal UTF-8 helpers for this fixture corpus when CLI lacks mbstring.
if (!function_exists('mb_strtolower')) {
    function mb_strtolower($text) { return strtolower(strtr($text, ['Á'=>'á', 'É'=>'é', 'Í'=>'í', 'Ó'=>'ó', 'Ú'=>'ú', 'Ü'=>'ü', 'Ñ'=>'ñ'])); }
    function mb_strlen($text) { return preg_match_all('/./us', $text); }
    function mb_stripos($text, $needle) { return stripos(mb_strtolower($text), mb_strtolower($needle)); }
}
function wp_strip_all_tags($text) { return strip_tags($text); }
function get_option($key, $default = false) { return $default; }
function get_term($id, $taxonomy) { return null; }
function is_wp_error($value) { return false; }
function check($condition, string $label): void {
    if (!$condition) { throw new RuntimeException($label); }
}
$bootstrap = file_get_contents(ABSPATH . 'ideasdi-redaccion-gerizim.php');
$previous = -1;
foreach (['canonical-adapter', 'canonical-context', 'disciplinary-library', 'editorial-recipe-builder', 'editorial-plan'] as $class) {
    $position = strpos($bootstrap, "require_once IDG_PLUGIN_DIR . 'includes/class-" . $class . ".php';");
    check($position !== false && $position > $previous, 'bootstrap order ' . $class);
    $previous = $position;
    require_once ABSPATH . 'includes/class-' . $class . '.php';
}
require_once ABSPATH . 'includes/class-workflow-prompt-data.php';
require_once ABSPATH . 'includes/class-prompt-library.php';
check(str_contains($bootstrap, 'Version: 0.4.0-RC1.7.0') && str_contains($bootstrap, "'0.4.0-RC1.7.0'"), 'plugin version');
// Exact final RC1.7.0 fingerprints; behavioral assertions remain unchanged.
foreach ([
    'includes/class-internal-links.php' => '054f6f84a62c600dcf9b5c156c60bfed1fbef31072d8ae4df58258360c186970',
    'includes/class-final-guard.php' => 'ad97dc6d190f487d90b21061bbf5a824d505ac22e49182a3b9f4486f4590bdc4',
    'includes/class-post-creator.php' => 'bd69c626507539968ba4685695fa320cbee39d9a5f4bee1280f6fe72b47b3380',
    'includes/class-canonical-adapter.php' => '9b8485e6e4a75df578fbc21e0d8a8717942f0c2326e69559847373ddf2b538db',
    'includes/data/editorial-canonical.php' => '95512585c4f7f17bf506f1b1b7734411e898f80b4bdf2e3c6995f203335b0034',
] as $file => $sha) {
    check(hash_file('sha256', ABSPATH . $file) === $sha, 'protected baseline ' . $file);
}
$projection = IDG_Canonical_Adapter::projection();
check($projection['canonical_sha256'] === '4329520966d28417fb2570c1be2208975358cfef22161657d38016f6c50f84ca', 'canonical pin');
$lenses = ['furniture', 'lighting', 'materiality', 'automotive', 'generative_ai', 'sustainability'];
check(array_keys($projection['lenses']['definitions']) === $lenses, 'exact six lenses');
foreach ($lenses as $id) {
    $ctx = IDG_Canonical_Context::resolve(['primary_lens' => $id]);
    check($ctx['primary_lens'] === $id && $ctx['layers']['primary_lens'] === $projection['lenses']['definitions'][$id], 'lens ' . $id);
}
$workflow = ['category_name' => 'Diseño digital y 3D', 'tag_names' => ['Unrelated tag'], 'brief_fact' => 'Unverified brief', 'primary_lens' => 'generative_ai', 'secondary_lenses' => ['lighting', 'generative_ai', 'lighting'], 'piece_context' => ['global' => ['evidence' => false], 'article_specific_constraints' => ['hard_global_guardrails_overridable' => true]]];
$before = serialize($workflow);
$ctx = IDG_Canonical_Context::resolve($workflow);
check(serialize($workflow) === $before && $ctx === IDG_Canonical_Context::resolve($workflow), 'deterministic and non-mutating');
check($ctx['resolution'] === $projection['resolution'] && array_keys($ctx['layers']) === $projection['resolution']['sequence'], 'ordered canonical resolution');
check($ctx['layers']['global'] === $projection['global'] && !$ctx['resolution']['hard_global_guardrails_overridable'], 'global guardrails cannot be overridden');
check($ctx['layers']['global']['evidence']['precedence'] === ['evidence_over_taxonomy' => true, 'evidence_over_recipe' => true, 'evidence_over_lens' => true], 'evidence precedence');
foreach (['verified_facts', 'evidence', 'sources', 'global'] as $field) {
    check(!array_key_exists($field, $ctx['piece_context']), 'no fabricated ' . $field);
}
$provided = ['sources' => ['document'], 'verified_facts' => ['provided fact'], 'evidence' => ['provided observation']];
check(IDG_Canonical_Context::resolve(['piece_context' => $provided])['piece_context'] === $provided, 'preserve supplied evidence');
check($ctx['secondary_lenses'] === ['lighting'], 'ordered deduplicated secondaries');
check(IDG_Canonical_Context::resolve(['editorial_lens' => 'generative_ai'])['primary_lens'] === null, 'plan lens is not canonical primary');
check(IDG_Canonical_Context::resolve(['tag_names' => ['Unrelated tag']])['primary_lens'] === null, 'unknown tags not coerced');
$title = $ctx['layers']['surface']['title'];
check($title['preferred_max_chars'] === 60 && $title['hard_max_chars'] === 68, 'preferred/hard title limits');
$guidance = IDG_Canonical_Context::prompt_block($workflow);
check(str_contains($guidance, 'De 61 a 68 no es fallo de validación'), '61–68 quality only');
foreach (['fact, editorial_interpretation, limited_inference', 'La fuente verifica, no narra', 'no son hechos verificados', 'no promocional', 'Mostrar antes'] as $text) {
    check(str_contains($guidance, $text), 'evidence/writing prompt ' . $text);
}
$calendar = ['editorial_context' => 'event_calendar', 'category_name' => 'Diseño de producto'];
$event = IDG_Canonical_Context::resolve($calendar);
check($event['surface'] === 'calendar_event' && $event['category'] === null && !isset($event['layers']['surface']['title']), 'distinct calendar');
check(!str_contains(IDG_Canonical_Context::prompt_block($calendar), 'H1:'), 'calendar guidance has no article title rule');
foreach (['product', 'architecture_interiors', 'fashion', 'mobility', 'digital_3d'] as $id) {
    $name = ['product' => 'Diseño de producto', 'architecture_interiors' => 'Arquitectura e interiores', 'fashion' => 'Moda', 'mobility' => 'Movilidad y transporte', 'digital_3d' => 'Diseño digital y 3D'][$id];
    $input = ['category_name' => $name, 'entity' => 'Example'];
    check(IDG_Canonical_Context::resolve($input)['layers']['category']['identity'] === ['required' => false, 'use_when_supported_by_evidence' => true], 'canonical optional identity ' . $id);
    $recipe = IDG_Editorial_Recipe_Builder::build($input);
    check(!$recipe['identity_required'] && str_contains($recipe['identity_prompt'], 'evidencia verificable'), 'recipe optional identity ' . $id);
    check(str_contains(IDG_Editorial_Plan::fallback($input, $recipe)['identity'], 'opcional'), 'plan optional identity ' . $id);
}
$contest = IDG_Canonical_Context::resolve(['editorial_context' => 'contest_call']);
check($contest['layers']['category']['editorial_mode'] === 'practical_opportunity' && !isset($contest['layers']['category']['identity']), 'contest practical mode without invented identity');
check(!IDG_Editorial_Recipe_Builder::build(['category_name' => 'Concursos y convocatorias'])['identity_required'], 'contest recipe');
foreach ([['primary_lens' => 'generative_ai'], ['primary_lens' => 'lighting', 'secondary_lenses' => ['generative_ai']]] as $selection) {
    $input = array_replace($workflow, ['secondary_lenses' => []], $selection);
    $semantic = IDG_Disciplinary_Library::resolve($input);
    $recipe = IDG_Editorial_Recipe_Builder::build($input);
    foreach ($projection['lenses']['definitions']['generative_ai']['axes'] as $axis) {
        check(in_array($axis, $semantic['decisions'], true) && in_array($axis, $recipe['available_axes'], true), 'AI axes reach consumers ' . $axis);
        check(str_contains(IDG_Editorial_Recipe_Builder::prompt_structure($input), $axis), 'AI axes reach prompt ' . $axis);
    }
    unset($input['primary_lens'], $input['secondary_lenses']);
    check($semantic['tag_classifications'] === IDG_Disciplinary_Library::resolve($input)['tag_classifications'], 'canonical lenses do not change classification');
}
require_once ABSPATH . 'includes/class-internal-links.php';
$data = IDG_Workflow_Prompt_Data::prepare($workflow, '');
check($data['canonical_context'] === $ctx, 'prompt payload canonical context');
check(str_contains(IDG_Prompt_Library::generate_prompt($data), $guidance), 'production prompt canonical guidance');
check(str_contains(IDG_Prompt_Library::editorial_plan_prompt($data), $guidance), 'plan prompt canonical guidance');
$plan = IDG_Editorial_Plan::fallback([], ['discipline' => 'generative_ai']);
$applied = IDG_Editorial_Plan::apply_to_workflow([], $plan, 'local-test');
check(IDG_Canonical_Context::resolve($applied)['primary_lens'] === null, 'production plan does not promote editorial_lens');
echo "PASS: rc170 canonical consumption\n";
