<?php
/* Real brief 231 source fixtures; model transport and WordPress writes remain local mocks. */
require __DIR__ . '/rc1782-targeted-regression.php';
require_once ABSPATH . 'includes/class-editorial-document.php';
function rc17852_ok(bool $ok, string $label): void { if (!$ok) { fwrite(STDERR, "FAIL RC17852: $label\n"); exit(1); } echo "OK RC17852: $label\n"; }
function rc17852_repair(): string { return json_encode(['patches'=>[
    ['segment'=>'VO:1','replacement'=>'Prada convierte una casa histórica en Shanghai mediante salas tiempos y atmósferas conectadas cuidadosamente'],
    ['segment'=>'VO:2','replacement'=>'La colaboración con Wong Kar Wai añade marco visual entre historia cine y hospitalidad'],
    ['segment'=>'VO:3','replacement'=>'Madera de cerezo latón y bambú afinan los contrastes materiales de la identidad espacial'],
    ['segment'=>'VO:4','replacement'=>'Obras de arte y antigüedades chinas enlazan memoria arquitectónica con una curaduría contemporánea activa'],
    ['segment'=>'VO:5','replacement'=>'El programa diario amplía la visita y convierte el comedor en destino para jornada'],
]]); }
$raw = trim((string) file_get_contents(__DIR__ . '/fixtures/rc17852-brief231-seo-original.md'));
$editorial = trim((string) file_get_contents(__DIR__ . '/fixtures/rc17852-brief231-editorial.md'));
rc17852_ok(hash('sha256', $raw) === '8b0f7bfd864fb198cf1bea17d89124bce3f6d084bd188be598fd791110f01474' && hash('sha256', $editorial) === '708a8f98e009e2bfc6bf6bb9ab85d62100698d03a85e2d2ad76d687df581f2d1', 'two exact source fixtures and hashes are present');
rc17852_ok(str_starts_with($raw, '# ') && str_starts_with($editorial, '# ') && trim(strstr($raw, "\nTÍTULO SEO", true)) === $editorial, 'raw prefix is the immutable editorial fixture');
$GLOBALS['rc1782_terms'][1231] = new WP_Term(1231, 'category', 'Arquitectura y diseño interior', 'diseno-interior');
$GLOBALS['rc1782_terms'][31] = new WP_Term(31, 'post_tag', 'Diseño interior comercial', 'diseno-interior-comercial');
$GLOBALS['rc1782_term_urls'][31] = 'https://ideasdi.com/tag/diseno-interior-comercial/';
$GLOBALS['rc1782_category_urls'][1231] = 'https://ideasdi.com/category/diseno-interior/';
$GLOBALS['rc1782_extra_tag_matrix'] = [['tag'=>'Diseño interior comercial','tag_slug'=>'diseno-interior-comercial','category_slug'=>'diseno-interior','status'=>'Index']];
$workflow = rc1782_workflow('idg_aa82442d-6db5-4981-9747-e87b83ebf2c0');
$workflow['workflow_origin']='radar'; $workflow['radar_brief_id']=231; $workflow['keyword']='Mi Shang Prada Rong Zhai'; $workflow['entity']='Prada'; $workflow['category_id']=1231; $workflow['tag_ids']=[31]; $workflow['tag_names']=['Diseño interior comercial']; $workflow['primary_lens']='Diseño interior comercial'; $workflow['secondary_lenses']=[];
$workflow['responsible_official_url']='https://www.prada.com/ww/en.html'; $workflow['canonical_context']=['responsible_official_url'=>'https://www.prada.com/ww/en.html']; $workflow['official_source']='https://www.prada.com/ww/en/pradasphere/special-projects/2025/mi-shang-prada-rong-zhai.html'; $workflow['source_information_url']=$workflow['official_source'];
$workflow['editorial_document']=IDG_Editorial_Document::from_markdown($editorial,$workflow); $workflow['editorial_result']=$editorial; $workflow['base_article']=$editorial; unset($workflow['seo_result']);
IDG_Job_Runner::save_workflow($workflow['workflow_id'],$workflow);
$client=new IDG_OpenAI_Client('local-only','local-mock'); $pipeline=new IDG_Workflow_Redaction_Pipeline(); $before_posts=count($GLOBALS['rc1782_posts']); $before_calls=count($GLOBALS['rc1782_http_calls']);
$GLOBALS['rc1782_http_queue'][]=rc1782_response($raw); $GLOBALS['rc1782_http_queue'][]=rc1782_response(rc17852_repair());
$pipeline->execute($workflow['workflow_id'],IDG_Workflow_Policies::ACTION_SEO,$workflow,$client); $done=IDG_Job_Runner::get_workflow($workflow['workflow_id']); $article=IDG_Editorial_Document::markdown($done['editorial_document']);
rc17852_ok(count($GLOBALS['rc1782_http_calls'])===$before_calls+2 && !empty($done['seo_preflight_payload_hash']), 'actual SEO workflow makes one bounded batched Reel repair and seals Gutenberg');
rc17852_ok(substr_count($article,'https://www.prada.com/ww/en.html')===1 && substr_count($article,'https://ideasdi.com/tag/diseno-interior-comercial/')===1 && !str_contains($article,$workflow['official_source']), 'only canonical corporate and P2 URLs are added');
$plain=preg_replace('/\[([^\]]+)\]\(https?:\/\/[^)]+\)/u','$1',$article); rc17852_ok($plain===$editorial, 'article prose is byte-identical outside the two markdown wraps');
preg_match('/\[([^\]]+)\]\(https:\/\/ideasdi\.com\/tag\/diseno-interior-comercial\/\)/u',$article,$p2); rc17852_ok(!empty($p2[1]) && count(preg_split('/\s+/u',trim($p2[1])))>=3 && count(preg_split('/\s+/u',trim($p2[1])))<=8, 'P2 anchor is contextual and 3–8 words');
$reel=(array)$done['editorial_document']['reel']; $inspect=IDG_Reel_Contract::inspect((string)($reel['normalized'] ?? ''));
rc17852_ok(!empty($inspect['valid']) && array_values(array_slice((array)$inspect['vo_counts'],0,5))===[14,14,14,14,14], 'all five repaired VO lines have exactly fourteen words');
preg_match('/PAQUETE REEL\n(.*?)\n\nRETROALIMENTACIÓN/su',$raw,$original_reel); $original_reel=(string)($original_reel[1]??''); $new_reel=(string)($reel['normalized']??'');
foreach (array_merge(['VO 6: Conoce más de este proyecto en ideasDi.com'], preg_grep('/^Overlay /m',explode("\n",$original_reel)) ?: []) as $line) rc17852_ok(str_contains($new_reel,$line), 'VO6 and original overlay remain byte-identical');
$html=(string)($done['seo_preflight_post_content']??''); $rendered=(string)(IDG_Editorial_Document::gutenberg($done['editorial_document'])['html']??''); rc17852_ok(substr_count($rendered,'https://www.prada.com/ww/en.html')===1 && substr_count($rendered,'https://ideasdi.com/tag/diseno-interior-comercial/')===1 && !str_contains($rendered,'featured-snippet-box"><a '), 'Gutenberg preserves both links and keeps the editorial box link-free');
rc17852_ok(str_contains((string)($done['seo_result']??''),'Mi Shang Prada Rong Zhai: cómo Prada') && str_contains((string)($done['seo_result']??''),'COPY PARA REDES'), 'SEO metadata and social copy are retained');
$first=(array)($workflow['editorial_document']['blocks'][0]??[]); $stale='PATCH '.($first['id']??'missing')."\nEXPECTED_SHA256: ".str_repeat('0',64)."\nTEXT:\ntexto\nEND PATCH";
$duplicate=str_replace('piezas de arte de Prada','[Prada](https://www.prada.com/ww/en.html) y [marca](https://www.prada.com/ww/en.html)',$raw);
$stale_result=IDG_Editorial_Document::apply_seo_response($workflow['editorial_document'],$stale,$workflow); $duplicate_result=IDG_Editorial_Document::apply_seo_response($workflow['editorial_document'],$duplicate,$workflow);
rc17852_ok(empty($stale_result['ok']) && !empty($duplicate_result['ok']) && empty($duplicate_result['prose']['applied']) && IDG_Editorial_Document::markdown($duplicate_result['document']) === IDG_Editorial_Document::markdown($workflow['editorial_document']), 'stale patch is rejected; duplicate canonical proposal preserves independent components without replacing prose');
$tampered=$done; $tampered['seo_preflight_post_content'].='mutated'; rc17852_ok(empty(IDG_Post_Creator::create_pending_post($tampered)['success']) && count($GLOBALS['rc1782_posts'])===$before_posts, 'mutated Gutenberg after preflight cannot write');
IDG_Job_Runner::execute_draft_stage($workflow['workflow_id'],$done); $pending=IDG_Job_Runner::get_workflow($workflow['workflow_id']); $post=$GLOBALS['rc1782_posts'][(int)($pending['draft_post_id']??0)]??[];
rc17852_ok(count($GLOBALS['rc1782_posts'])===$before_posts+1 && ($post['post_status']??'')==='pending' && ($post['post_content']??'')===$html, 'exactly one simulated pending post and zero live writes');
$bad=IDG_Editorial_Document::apply_seo_response($workflow['editorial_document'],str_replace('piezas de arte de Prada','piezas de arte de [Prada](https://evil.example/)',$raw),$workflow);
rc17852_ok(!empty($bad['ok']) && empty($bad['prose']['applied']) && IDG_Editorial_Document::markdown($bad['document']) === IDG_Editorial_Document::markdown($workflow['editorial_document']), 'unexpected URL does not replace the article while independent SEO components remain available');
$blocked_workflow = $workflow;
$blocked_workflow['workflow_id'] = 'idg-rc17853-rejected-patch';
unset($blocked_workflow['seo_result'], $blocked_workflow['seo_preflight_payload_hash'],
      $blocked_workflow['seo_preflight_post_content'], $blocked_workflow['seo_preflight_approved_at']);
IDG_Job_Runner::save_workflow($blocked_workflow['workflow_id'], $blocked_workflow);
$GLOBALS['rc1782_http_queue'][] = rc1782_response($stale . "

" . $raw);
$GLOBALS['rc1782_http_queue'][] = rc1782_response(rc17852_repair());
$pipeline->execute($blocked_workflow['workflow_id'], IDG_Workflow_Policies::ACTION_SEO, $blocked_workflow, $client);
$blocked = IDG_Job_Runner::get_workflow($blocked_workflow['workflow_id']);
rc17852_ok(!empty($blocked['seo_requires_intervention']) && empty($blocked['seo_preflight_payload_hash']),
    'PATCH rechazado conserva entregables pero no obtiene sello SEO');
rc17852_ok(($blocked['seo_response_diagnostics']['patch_proposal']['reason'] ?? '') === 'old_hash_mismatch'
    && ($blocked['seo_response_diagnostics']['final_preflight'] ?? '') === 'requires_intervention'
    && !empty($blocked['editorial_document']['seo']['meta_description']),
    'pipeline conserva metadatos y diagnóstico del PATCH rechazado sin seo_completed');

echo "RC17852_BRIEF231_OK\n";
