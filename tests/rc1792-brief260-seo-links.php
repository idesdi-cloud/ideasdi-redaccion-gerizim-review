<?php
/* Local WordPress/LLM-mocked regression for the sanitized brief 260 link incident. */
require __DIR__ . '/rc1782-targeted-regression.php';
require_once ABSPATH . 'includes/class-editorial-document.php';

function rc1792_ok(bool $ok, string $label): void { if (!$ok) { fwrite(STDERR, "FAIL RC1792: {$label}\n"); exit(1); } echo "OK RC1792: {$label}\n"; }
function rc1792_count_words(string $text): int { preg_match_all('/[\\p{L}\\p{N}]+/u', $text, $m); return count($m[0] ?? []); }

$p2 = 'https://ideasdi.com/tag/mobiliario/';
$official = 'https://www.naughtone.com/';
$GLOBALS['rc1782_terms'][57] = new WP_Term(57, 'category', 'Diseño de Producto', 'diseno-de-productos');
$GLOBALS['rc1782_terms'][1020] = new WP_Term(1020, 'post_tag', 'Mobiliario', 'mobiliario');
$GLOBALS['rc1782_term_urls'][1020] = $p2;
$GLOBALS['rc1782_category_urls'][57] = 'https://ideasdi.com/category/diseno-de-productos/';
$GLOBALS['rc1782_extra_tag_matrix'] = [['tag'=>'Mobiliario','tag_slug'=>'mobiliario','category_slug'=>'diseno-de-productos','category_id'=>57,'tag_ids'=>[1020],'status'=>'Index']];
$workflow = rc1782_workflow('idg_28174248-6869-4c96-94d1-314849480978');
$workflow['radar_brief_id'] = 260;
$workflow['keyword'] = 'Pippin Chair';
$workflow['entity'] = 'NaughtOne';
$workflow['responsible_official_url'] = $official;
$workflow['official_source'] = 'https://news.millerknoll.com/pippin-chair/'; // Documentary only: never becomes corporate identity.
$workflow['source_information_url'] = $workflow['official_source'];
$workflow['canonical_context'] = ['responsible_official_url' => $official];
$workflow['category_id'] = 57; $workflow['category_slug'] = 'diseno-de-productos';
$workflow['tag_ids'] = [1020]; $workflow['tag_names'] = ['Mobiliario']; $workflow['primary_lens'] = 'Mobiliario';
rc1792_ok(get_term(57, 'category')->slug === 'diseno-de-productos' && get_term(1020, 'post_tag')->slug === 'mobiliario' && get_term_link(1020) === $p2, 'fixture representativo resuelve categoría y tag reales de WordPress');
$box = rc1782_box('Pippin', 40);
$article = "# Pippin Chair de Lucy Kurrein\n\nPippin Chair, diseñada por Lucy Kurrein para NaughtOne, explora una pieza de asiento con una estructura ligera y una presencia doméstica.\n\nEl [Mobiliario]({$p2}) contemporáneo reúne decisiones de escala, apoyo y circulación; su base de bajo carbono, desmontaje y reparación orientan el uso sin inventar cifras.\n\nCaja editorial\n{$box}\n\n### Materiales y continuidad\nLa pieza observa proporciones, acabado y uso cotidiano para situar el diseño dentro de un entorno compartido.";
$document = IDG_Editorial_Document::from_markdown($article, $workflow);
$original_visible = preg_replace('/\[([^\]]+)\]\(https?:\/\/[^)]+\)/u', '$1', IDG_Editorial_Document::markdown($document));
$reel = rc1782_reel(false);
$response = "META DESCRIPTION\nPippin Chair de Lucy Kurrein para NaughtOne revisa proporciones, materiales y uso cotidiano desde una mirada editorial.\n\nINFORME SEO INTERNO\n- Enlaces aplicados: 9 internos; 9 externos\n- Validaciones: aprobadas\n\nCOPY PARA REDES\nPippin Chair observa proporciones y uso cotidiano. #Mobiliario\n\nPAQUETE REEL\n{$reel}\n\nRETROALIMENTACIÓN GERIZIM\n- Respuesta local sin PATCH.";
$applied = IDG_Editorial_Document::apply_seo_response($document, $response, $workflow);
rc1792_ok(!empty($applied['ok']) && empty($applied['patch']['received']), 'la respuesta sin PATCH conserva componentes SEO independientes');
$links = IDG_Editorial_Document::complete_missing_authorized_links($applied['document'], $workflow);
rc1792_ok(!empty($links['ok']), 'repara P2 existente e inserta la URL corporativa autorizada');
$markdown = IDG_Editorial_Document::markdown($links['document']);
$visible = preg_replace('/\[([^\]]+)\]\(https?:\/\/[^)]+\)/u', '$1', $markdown);
preg_match('/\[([^\]]+)\]\(' . preg_quote($p2, '/') . '\)/u', $markdown, $p2_match);
rc1792_ok(substr_count($markdown, $p2) === 1 && substr_count($markdown, $official) === 1 && $visible === $original_visible, 'cada URL final aparece una vez y la prosa visible no cambia');
rc1792_ok(rc1792_count_words((string) ($p2_match[1] ?? '')) >= 3 && rc1792_count_words((string) ($p2_match[1] ?? '')) <= 8 && mb_strtolower((string) ($p2_match[1] ?? '')) !== 'mobiliario', 'P2 deja de usar el anchor literal y usa contexto visible');
preg_match('/\\[([^\\]]*NaughtOne[^\\]]*)\\]\\(' . preg_quote($official, '/') . '\\)/u', $markdown, $official_match);
rc1792_ok(!empty($official_match[1]) && !str_contains($markdown, '[Lucy Kurrein](' . $official . ')'), 'la URL corporativa se atribuye inequívocamente a NaughtOne');

$pipeline_workflow = $workflow;
$pipeline_workflow['editorial_document'] = $document;
$pipeline_workflow['editorial_validation'] = ['ok'=>true, 'errors'=>[], 'error_codes'=>[]];
$GLOBALS['rc1782_http_calls'] = [];
$GLOBALS['rc1782_http_queue'] = [rc1782_response($response)];
(new IDG_Workflow_Redaction_Pipeline())->execute($workflow['workflow_id'], IDG_Workflow_Policies::ACTION_SEO, $pipeline_workflow, new IDG_OpenAI_Client('local-only', 'local-mock'));
$persisted = (array) get_option($workflow['workflow_id'], []);
$gutenberg = IDG_Editorial_Document::gutenberg((array) ($persisted['editorial_document'] ?? []));
$report = (string) (($persisted['editorial_document']['seo']['report'] ?? ''));
rc1792_ok(count($GLOBALS['rc1782_http_calls']) === 1 && substr_count((string) ($gutenberg['html'] ?? ''), $p2) === 1 && substr_count((string) ($gutenberg['html'] ?? ''), $official) === 1, 'pipeline verifica el HTML Gutenberg y no solicita una segunda reparación SEO');
rc1792_ok(str_contains($report, 'Enlaces aplicados: 1 interno; 1 externo') && str_contains((string) ($persisted['seo_result'] ?? ''), 'preflight Gutenberg aprobado'), 'informe persistido y proyección reflejan enlaces reales y validación real');
rc1792_ok(str_contains($report, 'Validaciones verificadas por plugin: preflight Gutenberg aprobado') && !str_contains($report, 'Validaciones: aprobadas'), 'el informe sellado no conserva un PASS falso del modelo');
rc1792_ok(!empty($persisted['seo_validation']['ok']) && !empty($persisted['seo_preflight_payload_hash']), 'la ruta normal conserva el borrador tras SEO aprobado');
$persisted_reel = (string) (($persisted['editorial_document']['reel']['normalized'] ?? ''));
rc1792_ok($persisted_reel === IDG_Reel_Contract::inspect($reel)['normalized'] && str_contains($persisted_reel, 'VO 6: Conoce más de este proyecto en ideasDi.com') && substr_count($persisted_reel, 'Overlay ') === 18, 'Reel permanece intacto con CTA exacto y 18 overlays');

/* Fail-closed negatives: no invented URL, duplicate URL, wrong identity or stale patch. */
$unauthorized = IDG_Editorial_Document::from_markdown(str_replace('Pippin Chair,', '[Pippin Chair](https://evil.example/),', $article), $workflow);
$bad_pending = $workflow; $bad_pending['editorial_document'] = $unauthorized; $bad_pending['seo_result'] = IDG_Editorial_Document::projection($unauthorized);
rc1792_ok(in_array('external_link_integrity', (array) IDG_Post_Creator::preflight_public($bad_pending)['validation']['error_codes'], true), 'URL externa no autorizada sigue bloqueada');
$duplicate = IDG_Editorial_Document::from_markdown($article . "\n\n[Otra referencia]({$p2}) mantiene el duplicado.", $workflow);
rc1792_ok((IDG_Editorial_Document::complete_missing_authorized_links($duplicate, $workflow)['reason'] ?? '') === 'duplicate_authorized_link', 'duplicado autorizado falla cerrado');
$wrong_identity = $workflow; $wrong_identity['entity'] = 'Lucy Kurrein';
$wrong_document = IDG_Editorial_Document::complete_missing_authorized_links($document, $wrong_identity)['document'];
$wrong_pending = $wrong_identity; $wrong_pending['editorial_document'] = $wrong_document; $wrong_pending['seo_result'] = IDG_Editorial_Document::projection($wrong_document);
rc1792_ok(empty(IDG_Post_Creator::preflight_public($wrong_pending)['ok']), 'identidad responsable errónea no queda validada por inferencia');
$block = (array) ($document['blocks'][0] ?? []);
$stale = 'PATCH ' . ($block['id'] ?? 'missing') . "\nEXPECTED_SHA256: " . str_repeat('0', 64) . "\nTEXT:\ntexto\nEND PATCH";
rc1792_ok(empty(IDG_Editorial_Document::apply_seo_response($document, $stale, $workflow)['ok']), 'PATCH obsoleto sin componentes permanece rechazado');
$ambiguous = IDG_Editorial_Document::from_markdown(str_replace('Pippin Chair, diseñada por Lucy Kurrein para NaughtOne, explora una pieza de asiento con una estructura ligera y una presencia doméstica.', 'uno uno NaughtOne uno uno NaughtOne uno uno.', $article), $workflow);
rc1792_ok(empty(IDG_Editorial_Document::complete_missing_authorized_links($ambiguous, $workflow)['ok']), 'menciones corporativas múltiples y ambiguas fallan cerradas');
$failed = $workflow; $failed['editorial_document'] = $document; $failed['seo_result'] = IDG_Editorial_Document::projection($document); $failed['seo_validation'] = ['ok'=>false,'errors'=>['El HTML final contiene un enlace externo no autorizado.'],'error_codes'=>['external_link_integrity']]; $failed['editorial_validation'] = ['ok'=>true,'errors'=>[],'error_codes'=>[]];
rc1792_ok(!IDG_Post_Creator::override_workflow_eligible($failed), 'override inseguro no es elegible');
$recoverable = $persisted; $recoverable['seo_validation'] = ['ok'=>false,'errors'=>['P2 exige exactamente un enlace al tag canónico resuelto; detectados: 0.'],'error_codes'=>['recoverable_link_missing']]; $recoverable['editorial_validation'] = ['ok'=>true,'errors'=>[],'error_codes'=>[]];
rc1792_ok(IDG_Post_Creator::override_workflow_eligible($recoverable), 'override recuperable elegible conserva la ruta pending excepcional');
$duplicate_pending = $persisted; $duplicate_pending['draft_post_id'] = 999;
rc1792_ok(!IDG_Post_Creator::override_workflow_eligible($duplicate_pending), 'no se habilita un segundo pending');
$failed_replay = $workflow; $failed_replay['workflow_id'] = 'idg-brief260-ambiguous'; $failed_replay['editorial_document'] = $ambiguous; $failed_replay['editorial_validation'] = ['ok'=>true,'errors'=>[],'error_codes'=>[]];
$GLOBALS['rc1782_http_queue'] = [rc1782_response($response)];
(new IDG_Workflow_Redaction_Pipeline())->execute($failed_replay['workflow_id'], IDG_Workflow_Policies::ACTION_SEO, $failed_replay, new IDG_OpenAI_Client('local-only', 'local-mock'));
$failed_persisted = (array) get_option($failed_replay['workflow_id'], []);
$failed_events = array_column((array) ($failed_persisted['history'] ?? []), 'event');
rc1792_ok(empty($failed_persisted['seo_validation']['ok']) && str_contains((string) ($failed_persisted['seo_result'] ?? ''), 'preflight Gutenberg requiere intervención') && !in_array('seo_completed', $failed_events, true), 'caso fallido persiste validación rechazada y nunca recibe seo_completed');
rc1792_ok(str_contains((string) ($failed_persisted['editorial_document']['seo']['report'] ?? ''), 'preflight Gutenberg requiere intervención') && !str_contains((string) ($failed_persisted['editorial_document']['seo']['report'] ?? ''), 'Validaciones: aprobadas'), 'documento rechazado y su metadata pending reflejan intervención requerida');

/* Pre-existing P2 anchors are true no-ops under the exact Final Guard count. */
$noop_anchor = 'mobiliario de diseño pensado para espacios de trabajo-flexible';
$noop_article = "# Pippin Chair\n\n[{$noop_anchor}]({$p2}) reúne decisiones de escala y circulación.\n\n[NaughtOne]({$official}) presenta la pieza.";
$noop_workflow = $workflow; $noop_workflow['workflow_id'] = 'idg-brief260-noop-anchors';
$noop_document = IDG_Editorial_Document::from_markdown($noop_article, $noop_workflow);
$noop_before = [IDG_Editorial_Document::markdown($noop_document), IDG_Editorial_Document::gutenberg($noop_document)];
$noop_result = IDG_Editorial_Document::complete_missing_authorized_links($noop_document, $noop_workflow);
$noop_after = [IDG_Editorial_Document::markdown($noop_result['document']), IDG_Editorial_Document::gutenberg($noop_result['document'])];
rc1792_ok(!empty($noop_result['ok']) && $noop_before === $noop_after, 'anchors P2 preexistentes válidos, conectores, repetición y guion compuesto son no-op byte a byte');
rc1792_ok(substr_count($noop_after[0], $p2) === 1 && substr_count($noop_after[0], $official) === 1 && str_contains($noop_after[0], '[' . $noop_anchor . '](' . $p2 . ')') && count(preg_split('/\s+/u', $noop_anchor, -1, PREG_SPLIT_NO_EMPTY)) === 8, 'anchor de ocho tokens whitespace permanece aprobado');
$short_document = IDG_Editorial_Document::from_markdown("# Pippin Chair\n\n[El mobiliario de diseño]({$p2}) mejora la lectura del espacio.\n\n[NaughtOne]({$official}) presenta la pieza.", $noop_workflow);
$short_before = IDG_Editorial_Document::markdown($short_document);
$short_result = IDG_Editorial_Document::complete_missing_authorized_links($short_document, $noop_workflow);
rc1792_ok(!empty($short_result['ok']) && IDG_Editorial_Document::markdown($short_result['document']) === $short_before, 'anchor P2 de conectores cortos permanece aprobado');

/* Actual local pending exception: real preflight failure, manual confirmation only. */
$exception_workflow = $workflow; $exception_workflow['workflow_id'] = 'idg-brief260-pending-exception';
$exception_article = "# Pippin Chair\n\nPippin Chair de Lucy Kurrein para NaughtOne ofrece una presencia doméstica.\n\nNaughtOne presenta una pieza de asiento con estructura ligera.";
$exception_document = IDG_Editorial_Document::from_markdown($exception_article, $exception_workflow);
$exception_document['blocks'][1]['text'] = str_replace('NaughtOne', '[NaughtOne](' . $official . ')', $exception_document['blocks'][1]['text']);
$exception_document['reel'] = ['normalized'=>IDG_Reel_Contract::inspect(rc1782_reel(false))['normalized'], 'valid'=>true];
$exception_document['seo']['report'] = '- Validaciones: aprobadas';
$exception_document = IDG_Editorial_Document::normalize_seo_report_validation($exception_document, false, true);
$exception_workflow['editorial_document'] = $exception_document;
$exception_workflow['editorial_result'] = IDG_Editorial_Document::markdown($exception_document);
$exception_workflow['seo_result'] = IDG_Editorial_Document::projection($exception_document);
$exception_workflow['seo_validation'] = ['ok'=>false,'errors'=>['P2 exige exactamente un enlace al tag canónico resuelto; detectados: 0.'],'error_codes'=>['recoverable_link_missing']];
$exception_workflow['editorial_validation'] = ['ok'=>true,'errors'=>[],'error_codes'=>[]];
$exception_workflow['seo_preflight_payload_hash'] = hash('sha256', (string) $exception_workflow['seo_result']);
$exception_confirmed = rc1782_set_confirmation($exception_workflow);
$exception_preflight = IDG_Post_Creator::preflight_public($exception_confirmed);
rc1792_ok(empty($exception_preflight['ok']) && !empty($exception_preflight['write_allowed']) && !empty($exception_preflight['prepared']), 'preflight real falla y solo habilita excepción recuperable');
rc1792_ok(in_array('recoverable_link_missing', (array) ($exception_preflight['validation']['error_codes'] ?? []), true), 'preflight conserva código de fallo recuperable');
$exception_created = IDG_Post_Creator::create_pending_post($exception_confirmed);
$exception_id = (int) ($exception_created['post_id'] ?? 0);
$exception_meta = (array) ($GLOBALS['rc1782_post_meta'][$exception_id] ?? []);
rc1792_ok(!empty($exception_created['success']) && ($GLOBALS['rc1782_posts'][$exception_id]['post_status'] ?? '') === 'pending' && !empty($exception_meta['_idg_validation_override']), 'excepción confirmada crea pending con auditoría');
rc1792_ok(str_contains((string) ($exception_meta['_idg_seo_report'] ?? ''), 'preflight Gutenberg requiere intervención') && !str_contains((string) ($exception_meta['_idg_seo_report'] ?? ''), 'Validaciones: aprobadas'), 'metadata SEO persistida comunica FAIL y elimina claim del modelo');
rc1792_ok(str_contains((string) $exception_confirmed['seo_result'], 'preflight Gutenberg requiere intervención') && str_contains((string) ($exception_preflight['prepared']['seo_report'] ?? ''), 'preflight Gutenberg requiere intervención') && str_contains((string) ($exception_preflight['prepared']['processed_seo_result'] ?? ''), 'preflight Gutenberg requiere intervención'), 'documento, seo_result y preflight preparado comunican el mismo FAIL');
$exception_calls = count($GLOBALS['rc1782_http_calls']);
rc1792_ok(empty(IDG_Post_Creator::create_pending_post($exception_confirmed)['success']) && count($GLOBALS['rc1782_http_calls']) === $exception_calls && substr_count((string) ($GLOBALS['rc1782_post_meta'][$exception_id]['_idg_reel_package'] ?? ''), 'Overlay ') === 18 && str_contains((string) ($GLOBALS['rc1782_post_meta'][$exception_id]['_idg_reel_package'] ?? ''), 'VO 6: Conoce más de este proyecto en ideasDi.com'), 'idempotencia rechaza duplicado sin IA y conserva Reel/HTML');

echo "RC1792_BRIEF260_SEO_LINKS_OK\n";
