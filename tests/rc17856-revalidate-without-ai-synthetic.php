<?php
declare(strict_types=1);

/**
 * RED regression: persistently rejected SEO, valid canonical document v1,
 * valid CURRENT public preflight, and no previous approval seal.
 * Run from repository root: php tests/rc17856-revalidate-without-ai-synthetic.php
 * No live WordPress calls; the loaded harness uses in-memory WP doubles.
 */
require __DIR__ . '/rc1782-targeted-regression.php';
require_once ABSPATH . 'includes/class-editorial-document.php';

$id = 'idg-synthetic-v1-revalidation-red';
$article = rc1782_article(rc1782_box('ActionForge', 40));
$workflow = rc1782_workflow($id);
$workflow['workflow_origin'] = 'manual';
$workflow['status'] = 'failed';
$workflow['last_error'] = 'SEO requiere intervención anterior.';
$workflow['editorial_validation'] = ['ok' => true, 'errors' => []];
$workflow['canonical_context'] = [
    'responsible_official_url' => $workflow['responsible_official_url'],
];
$document = IDG_Editorial_Document::from_markdown($article, $workflow);
$seo = rc1782_seo($article, rc1782_reel());
$applied = IDG_Editorial_Document::apply_seo_response($document, $seo, $workflow);
rc1782_ok(!empty($applied['ok']), 'RED fixture: SEO components applied to v1 document');
$workflow['editorial_document'] = $applied['document'];
$workflow['seo_result'] = IDG_Editorial_Document::projection($workflow['editorial_document']);

// This is the deliberately historical failure, not today's guard result.
$workflow['seo_validation'] = [
    'ok' => false,
    'errors' => ['Revisión anterior pendiente; revalidar localmente.'],
    'summary' => '- ERROR: Revisión anterior pendiente.',
];
$workflow['seo_validation_summary'] = $workflow['seo_validation']['summary'];
$workflow['seo_requires_intervention'] = true;
$workflow['seo_response_diagnostics'] = [
    'final_preflight' => 'requires_intervention',
    'patch_proposal' => ['received' => false, 'applied' => false],
];
unset(
    $workflow['seo_preflight_payload_hash'],
    $workflow['seo_preflight_post_content'],
    $workflow['seo_preflight_approved_at']
);

$now = IDG_Post_Creator::preflight_public($workflow);
rc1782_ok(!empty($now['ok']) && !empty($now['validation']['ok']),
    'RED fixture: live local preflight is valid (historic state is stale)');
rc1782_ok(!empty($now['prepared']['post_content']),
    'RED fixture: valid prepared Gutenberg payload exists');
rc1782_ok(!isset($workflow['seo_preflight_payload_hash']),
    'RED fixture: there is NO prior approval seal');

$GLOBALS['rc1782_options'][$id] = $workflow;
$http_before = count($GLOBALS['rc1782_http_calls']);
$posts_before = count($GLOBALS['rc1782_posts']);

// On the canonical baseline the method is intentionally absent: RED.
rc1782_ok(method_exists(IDG_Workflow_Redaction_Pipeline::class, 'revalidate_without_ai'),
    'RED expected failure on baseline: local revalidation action exists');

$result = (new IDG_Workflow_Redaction_Pipeline())->revalidate_without_ai(
    $id,
    get_option($id, [])
);
$saved = get_option($id, []);
$final = IDG_Post_Creator::preflight_public($saved);

rc1782_ok(!empty($result['ok']) && !empty($saved['seo_validation']['ok'])
    && empty($saved['seo_requires_intervention'])
    && ($saved['seo_response_diagnostics']['final_preflight'] ?? '') === 'approved'
    && ($saved['status'] ?? '') === 'completed'
    && ($saved['last_error'] ?? null) === '',
    'RED outcome: formerly rejected SEO is approved by current local guard');
rc1782_ok(!empty($final['ok']) && !empty($final['validation']['ok'])
    && !empty($saved['seo_preflight_approved_at'])
    && hash_equals((string) IDG_Post_Creator::prepared_payload_hash($final),
        (string) ($saved['seo_preflight_payload_hash'] ?? ''))
    && (string) ($saved['seo_preflight_post_content'] ?? '') ===
        (string) ($final['prepared']['post_content'] ?? ''),
    'RED outcome: NEW seal matches exactly the FINAL public preflight');
rc1782_ok(
    count($GLOBALS['rc1782_http_calls']) === $http_before &&
    count($GLOBALS['rc1782_posts']) === $posts_before,
    'RED safety: no model HTTP requests and no new WordPress posts'
);
rc1782_ok(
    IDG_Editorial_Document::markdown($saved['editorial_document']) ===
        IDG_Editorial_Document::markdown($workflow['editorial_document']),
    'RED safety: canonical article prose and links unchanged'
);
echo "RC17856_RED_REVALIDATION_CONTRACT_OK\n";
// APPEND ONLY after the 94-line positive RED/GREEN test from Issue #8.
// Assumes $workflow is the original valid v1 fixture and $saved is its approved result.
function rc17856_expect_denied_without_write(string $label, array $source, array $changes): void {
    $case_id = 'idg-rc17856-deny-' . $label;
    $case = $source;
    $case['workflow_id'] = $case_id;
    foreach ($changes as $field => $value) $case[$field] = $value;
    $GLOBALS['rc1782_options'][$case_id] = $case;
    $before = get_option($case_id, []);
    $before_meta = $GLOBALS['rc1782_user_meta'];
    $before_http = count($GLOBALS['rc1782_http_calls']);
    $before_posts = count($GLOBALS['rc1782_posts']);
    $result = (new IDG_Workflow_Redaction_Pipeline())->revalidate_without_ai(
        $case_id, get_option($case_id, [])
    );
    rc1782_ok(empty($result['ok']), 'DENY ' . $label . ': action rejected');
    rc1782_ok(get_option($case_id, []) === $before,
        'DENY ' . $label . ': workflow option unchanged byte-for-byte');
    rc1782_ok($GLOBALS['rc1782_user_meta'] === $before_meta,
        'DENY ' . $label . ': session meta unchanged');
    rc1782_ok(count($GLOBALS['rc1782_http_calls']) === $before_http
        && count($GLOBALS['rc1782_posts']) === $before_posts,
        'DENY ' . $label . ': no HTTP or posts');
}

rc1782_ok((int) get_current_user_id() === 7 && (int) $workflow['user_id'] === 7,
    'DENY fixture: editor identity grounded');
foreach ([
    'foreign-owner' => ['user_id' => 8],
    'already-drafted' => ['draft_post_id' => 941],
    'recurring-origin' => ['workflow_origin' => 'recurring'],
    'recurring-update' => ['workflow_origin' => 'recurring_update'],
    'recurring-target-id' => ['recurring_target_post_id' => 941],
    'recurring-target-type' => ['recurring_target_post_type' => 'evento'],
    'processing-status' => ['status' => 'processing'],
    'processing-flag' => ['processing' => true],
] as $label => $delta) {
    rc17856_expect_denied_without_write($label, $workflow, $delta);
}

// A workflow that passed the positive case later acquires a genuine veto.
// Here failure IS allowed to update diagnostics, but MUST invalidate old stamps.
$blocked_id = 'idg-rc17856-veto-after-approved';
$veto = $saved;
$veto['workflow_id'] = $blocked_id;
$veto['seo_response_diagnostics']['patch_proposal'] = [
    'received' => true, 'applied' => false, 'reason' => 'protected_block'
];
$GLOBALS['rc1782_options'][$blocked_id] = $veto;
$before_http = count($GLOBALS['rc1782_http_calls']);
$before_posts = count($GLOBALS['rc1782_posts']);
$outcome = (new IDG_Workflow_Redaction_Pipeline())->revalidate_without_ai(
    $blocked_id, get_option($blocked_id, [])
);
$after_veto = get_option($blocked_id, []);
rc1782_ok(empty($outcome['ok']), 'VETO: explicitly rejected PATCH prevents approval');
rc1782_ok(($after_veto['status'] ?? '') === 'failed'
    && empty($after_veto['seo_validation']['ok'])
    && !empty($after_veto['seo_requires_intervention'])
    && ($after_veto['seo_response_diagnostics']['final_preflight'] ?? '') === 'requires_intervention'
    && !empty($after_veto['last_error']),
    'VETO: persisted failure state and diagnostic remain coherent');
rc1782_ok(!isset($after_veto['seo_preflight_payload_hash'])
    && !isset($after_veto['seo_preflight_post_content'])
    && !isset($after_veto['seo_preflight_approved_at']),
    'VETO: all three formerly valid approval stamps are removed');
rc1782_ok($after_veto['editorial_document'] === $veto['editorial_document']
    && $after_veto['seo_result'] === $veto['seo_result'],
    'VETO: document and SEO remain identical');
rc1782_ok(count($GLOBALS['rc1782_http_calls']) === $before_http
    && count($GLOBALS['rc1782_posts']) === $before_posts,
    'VETO: no HTTP requests or posts');

function rc17856_case(string $tag, array $source, callable $mutate, bool $expect_ok = false): void {
    $id = 'idg-rc17856-' . strtolower($tag);
    $case = $source; $case['workflow_id'] = $id; $mutate($case);
    $GLOBALS['rc1782_options'][$id] = $case;
    $before = get_option($id, []); $meta = $GLOBALS['rc1782_user_meta'];
    $http = count($GLOBALS['rc1782_http_calls']); $posts = count($GLOBALS['rc1782_posts']);
    $out = (new IDG_Workflow_Redaction_Pipeline())->revalidate_without_ai($id, $before);
    rc1782_ok((bool) $out['ok'] === $expect_ok, $tag . ': causal outcome');
    if (!$expect_ok) {
        rc1782_ok(get_option($id, []) === $before && $GLOBALS['rc1782_user_meta'] === $meta
            && count($GLOBALS['rc1782_http_calls']) === $http && count($GLOBALS['rc1782_posts']) === $posts,
            $tag . ': no writes, HTTP or posts');
    }
}

$sentinel = 'sentinel-before-revalidation';
$GLOBALS['rc1782_user_meta'][7][IDG_SESSION_KEY_PREFIX . 'current'] = $sentinel;
$n01_id = 'idg-rc17856-n01'; $n01_case = $saved; $n01_case['workflow_id'] = $n01_id;
$n01_case['seo_preflight_approved_at'] = '2020-01-01 00:00:00'; $GLOBALS['rc1782_options'][$n01_id] = $n01_case;
$n01_http = count($GLOBALS['rc1782_http_calls']); $n01_posts = count($GLOBALS['rc1782_posts']);
$n01 = (new IDG_Workflow_Redaction_Pipeline())->revalidate_without_ai($n01_id, $n01_case);
rc1782_ok(!empty($n01['ok']) && $GLOBALS['rc1782_user_meta'][7][IDG_SESSION_KEY_PREFIX . 'current'] === $sentinel
    && get_option($n01_id, []) === $n01_case && count($GLOBALS['rc1782_http_calls']) === $n01_http
    && count($GLOBALS['rc1782_posts']) === $n01_posts, 'N01: valid completed seals are idempotent without save');
rc17856_case('N02', $saved, static function (&$w): void { $w['seo_preflight_payload_hash'] = str_repeat('0', 64); });
rc17856_case('N03', $saved, static function (&$w): void { $w['seo_preflight_post_content'] = 'different'; });
foreach (['seo_preflight_payload_hash', 'seo_preflight_post_content', 'seo_preflight_approved_at'] as $stamp) {
    rc17856_case('N04-' . $stamp, $saved, static function (&$w) use ($stamp): void { unset($w[$stamp]); });
}
rc17856_case('N05', $saved, static function (&$w): void { $w['status'] = 'failed'; });
rc17856_case('N06-draft', $saved, static function (&$w): void { $w['status'] = 'draft'; });
rc17856_case('N06-unknown', $saved, static function (&$w): void { $w['status'] = 'mystery'; });
$n07_id = 'idg-rc17856-n07'; $n07_persisted = $saved; $n07_persisted['workflow_id'] = $n07_id;
$n07_argument = $n07_persisted; $n07_argument['keyword'] = 'divergent'; $GLOBALS['rc1782_options'][$n07_id] = $n07_persisted;
$n07_before = get_option($n07_id, []); $n07_meta = $GLOBALS['rc1782_user_meta'];
$n07 = (new IDG_Workflow_Redaction_Pipeline())->revalidate_without_ai($n07_id, $n07_argument);
rc1782_ok(empty($n07['ok']) && get_option($n07_id, []) === $n07_before && $GLOBALS['rc1782_user_meta'] === $n07_meta, 'N07: divergent workflow argument is rejected without write');
rc17856_case('N08', $saved, static function (&$w): void { $w['editorial_document']['blocks'][0]['text'] .= ' editado'; });

$seal = new ReflectionMethod(IDG_Editorial_Document::class, 'seal'); $seal->setAccessible(true);
rc17856_case('N09', $saved, static function (&$w) use ($seal): void {
    $w['editorial_document']['blocks'][1]['id'] = $w['editorial_document']['blocks'][0]['id'];
    $w['editorial_document'] = $seal->invoke(null, $w['editorial_document']);
    $w['seo_result'] = IDG_Editorial_Document::projection($w['editorial_document']);
    rc1782_ok(hash_equals($w['editorial_document']['hash'], IDG_Editorial_Document::hash($w['editorial_document']))
        && $w['seo_result'] === IDG_Editorial_Document::projection($w['editorial_document']), 'N09: congruent hash and projection reach manifest guard');
});
rc17856_case('N10', $saved, static function (&$w): void { $w['seo_result'] .= ' tampered'; });
rc17856_case('N11', $saved, static function (&$w) use ($seal): void {
    $w['editorial_document']['blocks'][0]['text'] .= ' https://unauthorized.example';
    $w['editorial_document'] = $seal->invoke(null, $w['editorial_document']);
    $w['seo_result'] = IDG_Editorial_Document::projection($w['editorial_document']);
    rc1782_ok($w['seo_result'] === IDG_Editorial_Document::projection($w['editorial_document']), 'N11: congruent hash and projection reach link guard');
});
rc17856_case('N12', $workflow, static function (&$w): void { $w['assignment_card'] = ''; });
echo "RC17856_N01_N12_CAUSAL_OK\n";

/* Gerizim 2J.GREEN: cobertura causal independiente N09/N11. */
function rc17856_independent_document_hash(array $document): string {
    $canonical = $document;
    unset($canonical['hash'], $canonical['revision']);
    $sort = static function (&$value) use (&$sort): void {
        if (!is_array($value)) return;
        foreach ($value as &$child) $sort($child);
        unset($child);
        ksort($value);
    };
    $sort($canonical);
    return hash('sha256', (string) wp_json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function rc17856_assert_document_integrity(array $workflow, string $tag): void {
    $doc = $workflow['editorial_document'];
    rc1782_ok(hash_equals((string) $doc['hash'], rc17856_independent_document_hash($doc)), $tag . ': independent document hash');
    rc1782_ok($workflow['seo_result'] === IDG_Editorial_Document::projection($doc), $tag . ': exact SEO projection');
}

$n09strong = $saved;
$n09strong['editorial_document']['blocks'][1]['id'] = $n09strong['editorial_document']['blocks'][0]['id'];
rc1782_ok($n09strong['editorial_document']['blocks'][1]['id'] === $n09strong['editorial_document']['blocks'][0]['id'], 'N09-STRONG: mutation occurred');
$n09strong['editorial_document'] = $seal->invoke(null, $n09strong['editorial_document']);
$n09strong['seo_result'] = IDG_Editorial_Document::projection($n09strong['editorial_document']);
rc17856_assert_document_integrity($n09strong, 'N09-STRONG');
$n09manifest = IDG_Editorial_Document::prompt_manifest($n09strong, IDG_Editorial_Document::markdown($n09strong['editorial_document']), 'seo');
rc1782_ok(empty($n09manifest['ok']) && str_contains((string) ($n09manifest['reason'] ?? ''), 'duplicate'), 'N09-STRONG: independent manifest rejects duplicate block IDs');
rc1782_ok(true, 'N09-STRONG: independent hash + manifest validated');
rc17856_case('N09-STRONG', $saved, static function (&$w) use ($seal): void {
    $w['editorial_document']['blocks'][1]['id'] = $w['editorial_document']['blocks'][0]['id'];
    $w['editorial_document'] = $seal->invoke(null, $w['editorial_document']);
    $w['seo_result'] = IDG_Editorial_Document::projection($w['editorial_document']);
});

$n11external = $saved;
$n11external['editorial_document']['blocks'][0]['text'] .= ' [recurso adicional](https://example.invalid/recurso-adicional)';
rc1782_ok(str_contains($n11external['editorial_document']['blocks'][0]['text'], 'https://example.invalid/recurso-adicional'), 'N11-EXTERNAL: mutation occurred');
$n11external['editorial_document'] = $seal->invoke(null, $n11external['editorial_document']);
$n11external['seo_result'] = IDG_Editorial_Document::projection($n11external['editorial_document']);
rc17856_assert_document_integrity($n11external, 'N11-EXTERNAL');
$n11external_preflight = IDG_Post_Creator::preflight_public($n11external);
rc1782_ok(in_array('El HTML final contiene un enlace externo no autorizado.', (array) ($n11external_preflight['validation']['errors'] ?? []), true), 'N11-EXTERNAL: causal preflight error');
rc1782_ok(true, 'N11-EXTERNAL: guard-specific hash + preflight validated');
rc17856_case('N11-EXTERNAL', $saved, static function (&$w) use ($seal): void {
    $w['editorial_document']['blocks'][0]['text'] .= ' [recurso adicional](https://example.invalid/recurso-adicional)';
    $w['editorial_document'] = $seal->invoke(null, $w['editorial_document']);
    $w['seo_result'] = IDG_Editorial_Document::projection($w['editorial_document']);
});

$n11duplicate = $saved;
$n11duplicate['editorial_document']['blocks'][2]['text'] .= ' [otra referencia](https://actionforge.app/)';
rc1782_ok(str_contains($n11duplicate['editorial_document']['blocks'][2]['text'], 'https://actionforge.app/'), 'N11-DUPLICATE: mutation occurred');
$n11duplicate['editorial_document'] = $seal->invoke(null, $n11duplicate['editorial_document']);
$n11duplicate['seo_result'] = IDG_Editorial_Document::projection($n11duplicate['editorial_document']);
rc17856_assert_document_integrity($n11duplicate, 'N11-DUPLICATE');
$n11duplicate_preflight = IDG_Post_Creator::preflight_public($n11duplicate);
rc1782_ok((bool) array_filter((array) ($n11duplicate_preflight['validation']['errors'] ?? []), static fn($error): bool => str_contains((string) $error, 'El enlace externo obligatorio aparece más de una vez.')), 'N11-DUPLICATE: causal preflight error');
rc1782_ok(true, 'N11-DUPLICATE: guard-specific hash + preflight validated');
rc17856_case('N11-DUPLICATE', $saved, static function (&$w) use ($seal): void {
    $w['editorial_document']['blocks'][2]['text'] .= ' [otra referencia](https://actionforge.app/)';
    $w['editorial_document'] = $seal->invoke(null, $w['editorial_document']);
    $w['seo_result'] = IDG_Editorial_Document::projection($w['editorial_document']);
});

$n11missing = $saved;
$needle = '[pruebas de animación tridimensional](https://ideasdi.com/tag/animacion/)';
$replacement = 'pruebas de animación tridimensional';
$before_missing = $n11missing['editorial_document']['blocks'][7]['text'];
$n11missing['editorial_document']['blocks'][7]['text'] = str_replace($needle, $replacement, $before_missing);
rc1782_ok($n11missing['editorial_document']['blocks'][7]['text'] !== $before_missing, 'N11-MISSING: real substitution occurred');
$n11missing['editorial_document'] = $seal->invoke(null, $n11missing['editorial_document']);
$n11missing['seo_result'] = IDG_Editorial_Document::projection($n11missing['editorial_document']);
rc17856_assert_document_integrity($n11missing, 'N11-MISSING');
$n11missing_preflight = IDG_Post_Creator::preflight_public($n11missing);
rc1782_ok((bool) array_filter((array) ($n11missing_preflight['validation']['errors'] ?? []), static fn($error): bool => str_contains((string) $error, 'P2 exige exactamente un enlace al tag canónico resuelto')), 'N11-MISSING: causal preflight error');
rc1782_ok(true, 'N11-MISSING: guard-specific hash + preflight validated');
rc17856_case('N11-MISSING', $saved, static function (&$w) use ($seal, $needle, $replacement): void {
    $w['editorial_document']['blocks'][7]['text'] = str_replace($needle, $replacement, $w['editorial_document']['blocks'][7]['text']);
    $w['editorial_document'] = $seal->invoke(null, $w['editorial_document']);
    $w['seo_result'] = IDG_Editorial_Document::projection($w['editorial_document']);
});
echo "RC17856_N09_STRONG_N11_CAUSAL_OK\n";
