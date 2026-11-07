<?php
/* Synthetic brief 247 variants. No real 247 model response or service is used. */
require __DIR__ . '/rc1782-targeted-regression.php';
require_once ABSPATH . 'includes/class-editorial-document.php';
function rc17853_ok(bool $ok, string $label): void { if (!$ok) { fwrite(STDERR, "FAIL RC17853: $label\n"); exit(1); } echo "OK RC17853: $label\n"; }

$article = "# Gemini Omni y su interfaz\n\nDeepMind presenta Gemini Omni con una interfaz que reúne modelos y herramientas en una misma vista.\n\nCaja editorial\nGemini Omni es un proyecto de DeepMind que organiza modelos y herramientas en una interfaz de consulta. Su planteamiento busca hacer visibles distintas capacidades sin cambiar el contexto de trabajo, con una navegación centrada en tareas y en la continuidad de cada exploración.\n\n### Render y continuidad\nLa interfaz conserva el contexto mientras el equipo revisa resultados y decisiones de render durante cada exploración visual.";
$workflow = rc1782_workflow('idg-synthetic-brief247');
$workflow['radar_brief_id'] = 247; $workflow['entity'] = 'DeepMind'; $workflow['keyword'] = 'Gemini Omni';
$workflow['responsible_official_url'] = 'https://deepmind.google/';
$workflow['canonical_context'] = ['responsible_official_url' => 'https://deepmind.google/'];
$workflow['official_source'] = 'https://deepmind.google/models/gemini-omni/';
$workflow['source_information_url'] = $workflow['official_source'];
$workflow['category_id'] = 1232; $workflow['tag_names'] = ['Render']; $workflow['primary_lens'] = 'Render';
$GLOBALS['rc1782_terms'][1232] = new WP_Term(1232, 'category', 'Diseño digital', 'modelado-3d');
$GLOBALS['rc1782_terms'][247] = new WP_Term(247, 'post_tag', 'Render', 'render');
$GLOBALS['rc1782_term_urls'][247] = 'https://ideasdi.com/tag/render/'; $workflow['tag_ids'] = [247];
$GLOBALS['rc1782_extra_tag_matrix'] = [['tag'=>'Render','tag_slug'=>'render','category_slug'=>'modelado-3d','status'=>'Index']];
$document = IDG_Editorial_Document::from_markdown($article, $workflow); $original = IDG_Editorial_Document::markdown($document);
$reel = rc1782_reel(false);
$components = "META DESCRIPTION\nGemini Omni de DeepMind reúne modelos y herramientas en una interfaz continua para explorar decisiones de render.\n\nINFORME SEO INTERNO\n- Entregables recibidos sin aprobación automática.\n\nCOPY PARA REDES\nGemini Omni conecta modelos y decisiones visuales. #Render\n\nPAQUETE REEL\n$reel\n\nRETROALIMENTACIÓN GERIZIM\n- Variante sintética 247.";
$variants = [
    'sin_articulo_final' => $components,
    'cambio_ligero' => "ARTÍCULO FINAL\n" . str_replace('una misma vista', 'una vista única', $article) . "\n\n$components",
    'parrafo_anadido' => "ARTÍCULO FINAL\n$article\n\nUn párrafo no autorizado.\n\n$components",
    'reorder' => "ARTÍCULO FINAL\n" . str_replace('### Render y continuidad', '## Render y continuidad', $article) . "\n\n$components",
    'url_no_autorizada' => "ARTÍCULO FINAL\n" . str_replace('DeepMind presenta', '[DeepMind](https://evil.example/) presenta', $article) . "\n\n$components",
];
foreach ($variants as $name => $response) {
    $result = IDG_Editorial_Document::apply_seo_response($document, $response, $workflow);
    rc17853_ok(!empty($result['ok']) && IDG_Editorial_Document::markdown($result['document']) === $original, "$name conserva artículo autoritativo");
    rc17853_ok(($result['document']['seo']['meta_description'] ?? '') !== '' && !empty($result['document']['reel']['valid']), "$name conserva metadatos y Reel válidos");
}
$links = IDG_Editorial_Document::complete_missing_authorized_links($document, $workflow);
$linked = IDG_Editorial_Document::markdown($links['document'] ?? []);
rc17853_ok(!empty($links['ok']) && substr_count($linked, 'https://deepmind.google/') === 1 && substr_count($linked, 'https://ideasdi.com/tag/render/') === 1 && !str_contains($linked, $workflow['official_source']), '247 completa responsable y P2 sobre documento conservado');
$first_block = (array) ($document['blocks'][0] ?? []);
$stale_patch = 'PATCH ' . (string) ($first_block['id'] ?? 'missing') . "\nEXPECTED_SHA256: " . str_repeat('0', 64) . "\nTEXT:\ntexto no autorizado\nEND PATCH";
$stale_with_components = IDG_Editorial_Document::apply_seo_response($document, $stale_patch . "\n\n" . $components, $workflow);
rc17853_ok(!empty($stale_with_components['ok']) && empty($stale_with_components['patch']['applied'])
    && ($stale_with_components['patch']['reason'] ?? '') === 'old_hash_mismatch'
    && IDG_Editorial_Document::markdown($stale_with_components['document']) === $original,
    'PATCH rechazado conserva prosa y diagnostica old_hash_mismatch con componentes recibidos');
rc17853_ok(in_array('meta_description', (array) ($stale_with_components['received_components'] ?? []), true)
    && !empty($stale_with_components['reel_validated']),
    'se distinguen componentes recibidos y Reel realmente validado');
$double_rejected = IDG_Editorial_Document::apply_seo_response($document,
    "ARTÍCULO FINAL\n" . str_replace('### Render y continuidad', '## Render y continuidad', $article)
    . "\n\n" . $stale_patch . "\n\n" . $components, $workflow);
rc17853_ok(!empty($double_rejected['ok']) && empty($double_rejected['prose']['applied'])
    && empty($double_rejected['patch']['applied'])
    && ($double_rejected['patch']['reason'] ?? '') === 'old_hash_mismatch'
    && IDG_Editorial_Document::markdown($double_rejected['document']) === $original,
    'artículo y PATCH rechazados conservan dos diagnósticos independientes');
$stale_alone = IDG_Editorial_Document::apply_seo_response($document, $stale_patch, $workflow);
rc17853_ok(empty($stale_alone['ok']) && ($stale_alone['patch']['reason'] ?? '') === 'old_hash_mismatch',
    'PATCH inválido sin componentes queda en fallo estricto');

echo "RC17853_BRIEF247_SYNTHETIC_OK\n";
