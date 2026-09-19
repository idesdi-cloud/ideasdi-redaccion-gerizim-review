<?php
/** Current RC1.7.6 closure and immutable historical provenance, standalone and read-only. */
require_once __DIR__ . '/support/canonical-regression.php';
require_once dirname(__DIR__) . '/includes/class-canonical-adapter.php';
function release_ok(bool $condition, string $label): void {
    if (!$condition) { throw new RuntimeException($label); }
}
$root = dirname(__DIR__);
$main = file_get_contents($root . '/ideasdi-redaccion-gerizim.php');
release_ok(str_contains($main, "define('IDG_TRACEABILITY_DB_VERSION', '1.2.0');"), 'unchanged DB version');
$event = IDG_Canonical_Adapter::resolve(['surface' => 'calendar_event', 'category_name' => 'Diseño de producto']);
release_ok($event['surface'] === 'calendar_event' && $event['category'] === null, 'explicit calendar without legacy signals');
foreach (['IA generativa', 'Inteligencia artificial generativa'] as $alias) {
    release_ok(IDG_Canonical_Adapter::resolve(['primary_lens' => $alias])['primary_lens'] === 'generative_ai', 'generative AI alias');
    release_ok(IDG_Canonical_Adapter::resolve(['secondary_lenses' => [$alias]])['secondary_lenses'] === ['generative_ai'], 'secondary AI alias');
}
$input = ['category_name' => 'Unknown category', 'primary_lens' => 'Unknown lens', 'secondary_lenses' => ['Unknown lens'], 'piece_context' => ['brief' => 'Unknown context']];
$before = $input;
$unknown = IDG_Canonical_Adapter::resolve($input);
release_ok($input === $before && $unknown['category'] === null && $unknown['primary_lens'] === null && $unknown['secondary_lenses'] === [] && $unknown['piece_context']['brief'] === 'Unknown context', 'unknown values are not promoted or mutated');

$legacy_manifest = $root . '/REGRESION-EDITORIAL-RC1.6.5.sha256';
$historical_manifest = $root . '/REGRESION-EDITORIAL-RC1.7.2.sha256';
release_ok(hash_file('sha256', $legacy_manifest) === IDG_Canonical_Regression::LEGACY_MANIFEST_SHA, 'legacy manifest byte identity');
release_ok(hash_file('sha256', $historical_manifest) === IDG_Canonical_Regression::HISTORICAL_MANIFEST_SHA, 'historical manifest byte identity');
$historical_text = file_get_contents($historical_manifest);
$historical = IDG_Canonical_Regression::parse_historical($historical_text);
release_ok(!isset($historical['REGRESION-EDITORIAL-RC1.7.2.sha256']), 'historical manifest has no self reference');

$text = file_get_contents($root . '/REGRESION-EDITORIAL-RC1.7.6.sha256');
$current = IDG_Canonical_Regression::parse_current($text);
release_ok(!isset($current['REGRESION-EDITORIAL-RC1.7.6.sha256']), 'current manifest has no self reference');
release_ok(IDG_Canonical_Regression::current_state_matches($root, $current), 'exact current regression state');
release_ok(array_keys(IDG_Canonical_Regression::RECONCILED) === [
    'includes/class-prompt-library.php',
    'includes/class-validator.php',
    'includes/class-final-guard.php',
    'includes/class-editorial-rules.php',
    'includes/class-editorial-plan.php',
    'includes/class-editorial-recipe-builder.php',
    'includes/class-post-creator.php',
], 'closed seven-path reconciled historical scope');

$bad = str_repeat('0', 64);
foreach (IDG_Canonical_Regression::LEGACY as $path => $legacy) {
    release_ok(IDG_Canonical_Regression::historical_matches($root, $path, $legacy), 'exact legacy provenance ' . $path);
    release_ok(!IDG_Canonical_Regression::historical_hash_matches($path, $bad, $historical), 'reject forged legacy SHA');
    $forged_historical = $historical;
    $forged_historical[$path] = $bad;
    release_ok(!IDG_Canonical_Regression::historical_hash_matches($path, $legacy, $forged_historical), 'reject historical artifact tamper');
    if (isset(IDG_Canonical_Regression::RECONCILED[$path])) {
        $rollback = $historical;
        $rollback[$path] = $legacy;
        release_ok(!IDG_Canonical_Regression::historical_hash_matches($path, $legacy, $rollback), 'reject historical reconciled rollback');
    }
}
release_ok(!IDG_Canonical_Regression::historical_hash_matches('unknown.php', $bad, $historical), 'reject historical scope expansion');

$protected = 'ideasdi-redaccion-gerizim.php';
$actual = hash_file('sha256', $root . '/' . $protected);
release_ok(IDG_Canonical_Regression::current_hash_matches($protected, $actual, $current), 'current protected hash');
release_ok(!IDG_Canonical_Regression::current_hash_matches($protected, $bad, $current), 'reject current source tamper');
$forged_current = $current;
$forged_current[$protected] = $bad;
release_ok(!IDG_Canonical_Regression::current_state_matches($root, $forged_current), 'reject forged current manifest state');
$rolled_back = $current;
$rolled_back[$protected] = $historical[$protected];
release_ok(!IDG_Canonical_Regression::current_state_matches($root, $rolled_back), 'reject current manifest rollback');
$expanded = $current;
$expanded['unknown.php'] = $bad;
release_ok(!IDG_Canonical_Regression::current_state_matches($root, $expanded), 'reject current scope expansion');

$lines = explode("\n", substr($text, 0, -1));
$invalid_manifests = [
    $text . $lines[0] . "\n",
    implode("\n", array_slice($lines, 1)) . "\n",
    implode("\n", array_reverse($lines)) . "\n",
    $text . $bad . "  REGRESION-EDITORIAL-RC1.7.6.sha256\n",
    $text . $bad . "  unexpected.php\n",
    str_replace($lines[0], 'invalid', $text),
    rtrim($text, "\n"),
];
foreach ($invalid_manifests as $invalid) {
    $rejected = false;
    try { IDG_Canonical_Regression::parse_current($invalid); } catch (RuntimeException $error) { $rejected = true; }
    release_ok($rejected, 'reject duplicate, missing, reordered, self-referential, unexpected or malformed current manifest');
}
echo "RC170_RELEASE_REGRESSION_OK\n";
