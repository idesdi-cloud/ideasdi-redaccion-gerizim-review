<?php
/** Final RC1.7 closure, standalone and read-only (tampering is simulated in memory). */
require_once __DIR__ . '/support/canonical-regression.php';
require_once dirname(__DIR__) . '/includes/class-canonical-adapter.php';
function release_ok(bool $condition, string $label): void {
    if (!$condition) { throw new RuntimeException($label); }
}
$root = dirname(__DIR__);
$main = file_get_contents($root . '/ideasdi-redaccion-gerizim.php');
release_ok(preg_match('/^ \* Version: 0\.4\.0-RC1\.7\.0$/m', $main) === 1, 'exact header');
release_ok(str_contains($main, "define('IDG_VERSION', '0.4.0-RC1.7.0');"), 'exact runtime version');
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
release_ok(hash_file('sha256', $root . '/REGRESION-EDITORIAL-RC1.6.5.sha256') === IDG_Canonical_Regression::LEGACY_MANIFEST_SHA, 'legacy manifest byte identity');
$text = file_get_contents($root . '/REGRESION-EDITORIAL-RC1.7.0.sha256');
$current = IDG_Canonical_Regression::parse($text);
release_ok(!isset($current['REGRESION-EDITORIAL-RC1.7.0.sha256']), 'no self reference');
foreach ($current as $path => $hash) {
    release_ok(hash_file('sha256', $root . '/' . $path) === $hash, 'exact current SHA ' . $path);
}
release_ok(array_keys(IDG_Canonical_Regression::RECONCILED) === [
    'includes/class-prompt-library.php',
    'includes/class-final-guard.php',
    'includes/class-editorial-plan.php',
    'includes/class-editorial-recipe-builder.php',
    'includes/class-post-creator.php',
], 'closed five-path D2-D1 historical scope');
$bad = str_repeat('0', 64);
foreach (IDG_Canonical_Regression::LEGACY as $path => $legacy) {
    $actual = hash_file('sha256', $root . '/' . $path);
    release_ok(IDG_Canonical_Regression::historical_matches($root, $path, $legacy), 'exact legacy provenance ' . $path);
    release_ok(!IDG_Canonical_Regression::historical_hash_matches($path, $bad, $actual, $current), 'reject forged legacy SHA');
    release_ok(!IDG_Canonical_Regression::historical_hash_matches($path, $legacy, $bad, $current), 'reject source tamper');
    $forged = $current; $forged[$path] = $bad;
    release_ok(!IDG_Canonical_Regression::historical_hash_matches($path, $legacy, $bad, $forged), 'reject source plus manifest tamper');
    if (isset(IDG_Canonical_Regression::RECONCILED[$path])) {
        release_ok(!IDG_Canonical_Regression::historical_hash_matches($path, $legacy, $actual, $forged), 'reject reconciled manifest tamper');
        release_ok(!IDG_Canonical_Regression::historical_hash_matches($path, $legacy, $legacy, $current), 'reject rollback of reconciled source');
    }
}
release_ok(!IDG_Canonical_Regression::historical_hash_matches('unknown.php', $bad, $bad, ['unknown.php' => $bad]), 'reject scope expansion');
$lines = explode("\n", rtrim($text, "\n"));
foreach ([$text . $lines[0] . "\n", implode("\n", array_slice($lines, 1)) . "\n", implode("\n", array_reverse($lines)) . "\n", $text . $bad . "  REGRESION-EDITORIAL-RC1.7.0.sha256\n", str_replace($lines[0], 'invalid', $text)] as $invalid) {
    $rejected = false;
    try { IDG_Canonical_Regression::parse($invalid); } catch (RuntimeException $e) { $rejected = true; }
    release_ok($rejected, 'reject duplicate, missing, reordered, self-referential or malformed manifest');
}
echo "RC170_RELEASE_REGRESSION_OK\n";
