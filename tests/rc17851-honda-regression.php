<?php
/* Honda RN0: respuestas simuladas, no son la salida original del modelo ni una evaluación de IA. */
require __DIR__ . '/rc1782-targeted-regression.php';
require_once ABSPATH . 'includes/class-editorial-document.php';
function rc17851_ok(bool $ok, string $label): void { if (!$ok) { fwrite(STDERR, "FAIL RC17851: $label\n"); exit(1); } echo "OK RC17851: $label\n"; }
function rc17851_reel(): string { $out=[]; for($s=1;$s<=5;$s++){ $out[]="VO $s: Uno dos tres cuatro cinco seis siete ocho nueve diez once doce trece catorce"; for($o=1;$o<=3;$o++) $out[]="Overlay $s.$o: Escena $s idea $o"; } $out[]='VO 6: Conoce más de este proyecto en ideasDi.com'; for($o=1;$o<=3;$o++) $out[]="Overlay 6.$o: Cierre $o"; return implode("\n",$out); }
$base = trim((string) file_get_contents(__DIR__ . '/fixtures/rc17851-honda-rn0-base.md'));
rc17851_ok($base !== '' && str_contains($base, '[bicicleta de montaña](https://ideasdi.com/tag/diseno-de-bicicleta/)'), 'immutable Honda base fixture includes the approved P2 anchor');
$admin_navigation = (string) file_get_contents(ABSPATH . 'includes/class-admin-page.php');
rc17851_ok(str_contains($admin_navigation, '$editorial_safe_base') && str_contains($admin_navigation, "!empty(\$workflow['editorial_requires_manual_review'])"), 'admin navigation exposes SEO only from an authoritative safe base and never an exception after generation alone');
$workflow = rc1782_workflow('idg_66f39c97-034f-4523-aef6-a4164981bf0d');
$workflow['workflow_origin']='radar'; $workflow['radar_brief_id']=230; $workflow['keyword']='Honda RN0'; $workflow['entity']='Honda';
$workflow['official_source']='https://global.honda/en/'; $workflow['responsible_official_url']='https://global.honda/en/';
$workflow['canonical_context']=['responsible_official_url'=>'https://global.honda/en/'];
$workflow['editorial_context']='event_calendar';
$workflow['source_information_url']='https://global.honda/en/newsroom/news/2026/2261103eeng.html';
$workflow['brief_fact']='Honda RN0 presentada con ROTWILD en EICMA 2026.';
$workflow['internal_links_structured']=[['url'=>'https://ideasdi.com/tag/diseno-de-bicicleta/','anchor'=>'bicicleta de montaña']];
unset($workflow['base_article'],$workflow['editorial_result'],$workflow['seo_result']);
IDG_Job_Runner::save_workflow($workflow['workflow_id'],$workflow); $pipeline=new IDG_Workflow_Redaction_Pipeline(); $client=new IDG_OpenAI_Client('local-only','local-mock');
/* Simulada: revisión completa, localizada y representativa; no altera enlaces ni inventa hechos. */
$revised=str_replace('La transferencia no es decorativa; se nota en cómo el conjunto quiere sostener la rueda trasera y fijar mejor la bicicleta sobre terreno mixto.','La transferencia no es decorativa: ordena cómo el conjunto sostiene la rueda trasera y lee el terreno mixto.',$base);
$editorial="ARTÍCULO REVISADO\n$revised\n\nDIAGNÓSTICO EDITORIAL INTERNO\n- Simulado: ajuste localizado de ritmo; enlaces y hechos conservados.\n\nNOTAS EDITORIALES INTERNAS\n- Respuesta simulada para regresión local.";
$GLOBALS['rc1782_http_queue'][]=rc1782_response($base); $pipeline->execute($workflow['workflow_id'],IDG_Workflow_Policies::ACTION_GENERATE,$workflow,$client); $generated=IDG_Job_Runner::get_workflow($workflow['workflow_id']);
rc17851_ok(IDG_Editorial_Document::exists($generated),'Generate creates the authoritative Honda document');
$rejected_workflow=$generated; $rejected_workflow['workflow_id']='idg_66f39c97-034f-4523-aef6-a4164981bf0d-unapplyable'; IDG_Job_Runner::save_workflow($rejected_workflow['workflow_id'],$rejected_workflow);
$unsafe_editorial="ARTÍCULO REVISADO\n".str_replace('Honda RN0','[Honda RN0](https://evil.example/)',$base)."\n\nDIAGNÓSTICO EDITORIAL INTERNO\n- Simulado: enlace no autorizado.";
$GLOBALS['rc1782_http_queue'][]=rc1782_response($unsafe_editorial); $pipeline->execute($rejected_workflow['workflow_id'],IDG_Workflow_Policies::ACTION_EDITORIAL,$rejected_workflow,$client); $unapplied=IDG_Job_Runner::get_workflow($rejected_workflow['workflow_id']);
rc17851_ok(empty($unapplied['editorial_applied']) && !empty($unapplied['editorial_requires_manual_review']) && ($unapplied['editorial_result']??'')===IDG_Editorial_Document::markdown($generated['editorial_document']) && str_contains((string)($unapplied['editorial_output_raw']??''),'evil.example'),'unapplyable simulated editorial response preserves the base, raw response and explicit manual warning for SEO');
$exception = rc1782_set_confirmation($unapplied);
$exception_before = count($GLOBALS['rc1782_posts']);
$exception_result = IDG_Post_Creator::create_pending_post($exception);
rc17851_ok(!empty($exception_result['success']) && empty($exception_result['validation']['ok']) && !empty($exception_result['validation']['overridden']) && count($GLOBALS['rc1782_posts']) === $exception_before + 1, 'separate rejected-editorial exception creates only a manually reviewed pending without SEO');
$stale_exception = $unapplied;
$stale_exception['workflow_id'] .= '-stale-confirmation';
$stale_exception = rc1782_set_confirmation($stale_exception);
$stale_exception['editorial_document']['blocks'][1]['text'] .= ' Cambio posterior.';
$stale_exception['editorial_document'] = IDG_Editorial_Document::from_markdown(IDG_Editorial_Document::markdown($stale_exception['editorial_document']), $stale_exception, $stale_exception['editorial_document']);
rc17851_ok(empty(IDG_Post_Creator::create_pending_post($stale_exception)['success']), 'separate exception rejects a nonce confirmation made stale by an authoritative document change');
$GLOBALS['rc1782_http_queue'][]=rc1782_response($editorial); $pipeline->execute($workflow['workflow_id'],IDG_Workflow_Policies::ACTION_EDITORIAL,$generated,$client); $edited=IDG_Job_Runner::get_workflow($workflow['workflow_id']);
rc17851_ok(!empty($edited['editorial_changed']) && str_contains($edited['editorial_result'],'ordena cómo el conjunto'),'complete simulated editorial Markdown reconciles by existing blocks');
rc17851_ok(str_contains($edited['editorial_output_raw'],'Respuesta simulada'),'raw simulated response is retained separately');
$seo_article=str_replace('Honda RN0 aparece como una e-MTB','[Honda](https://global.honda/en/) RN0 aparece como una e-MTB',$edited['editorial_result']);
$seo="ARTÍCULO FINAL\n$seo_article\n\nMETA DESCRIPTION\nHonda RN0 explora una e-MTB de Honda con cuadro de carbono, control y lectura precisa del terreno all-mountain.\n\nINFORME SEO INTERNO\n- Respuesta simulada: enlace responsable incorporado sin alterar el enlace P2.\n\nCOPY PARA REDES\nHonda RN0 lleva una lógica de motocicleta al terreno all-mountain.\n\nPAQUETE REEL\n".rc17851_reel()."\n\nRETROALIMENTACIÓN GERIZIM\n- Simulado y validado localmente.";
$GLOBALS['rc1782_http_queue'][]=rc1782_response($seo); $pipeline->execute($workflow['workflow_id'],IDG_Workflow_Policies::ACTION_SEO,$edited,$client); $seow=IDG_Job_Runner::get_workflow($workflow['workflow_id']);
$final=IDG_Editorial_Document::markdown($seow['editorial_document']);
rc17851_ok(str_contains($final,'[Honda](https://global.honda/en/)') && str_contains($final,'[bicicleta de montaña](https://ideasdi.com/tag/diseno-de-bicicleta/)'),'SEO adds only the missing canonical official link and preserves P2');
rc17851_ok(!empty($seow['seo_preflight_payload_hash']) && !empty($seow['editorial_document']['reel']['valid']),'SEO metadata, report and Reel reach the safe document projection: '.implode(' | ',(array)($seow['seo_validation']['errors']??[])));
$posts=count($GLOBALS['rc1782_posts']); IDG_Job_Runner::execute_draft_stage($workflow['workflow_id'],$seow); $pending=IDG_Job_Runner::get_workflow($workflow['workflow_id']); $post=$GLOBALS['rc1782_posts'][(int)($pending['draft_post_id']??0)]??[];
rc17851_ok(count($GLOBALS['rc1782_posts'])===$posts+1 && ($post['post_status']??'')==='pending' && ($post['post_content']??'')===$seow['seo_preflight_post_content'],'actual mock pipeline writes one pending Gutenberg post from the approved Honda document');
$unsafe=str_replace('https://global.honda/en/','https://evil.example/',$seo_article); $rejected=IDG_Editorial_Document::apply_seo_response($edited['editorial_document'],"ARTÍCULO FINAL\n$unsafe",$workflow);
rc17851_ok(empty($rejected['ok']) && ($rejected['document']??null)===$edited['editorial_document'],'wrong full-response URL fails closed without overwrite');
$unchanged=IDG_Editorial_Document::apply_editorial_response($edited['editorial_document'],IDG_Editorial_Document::markdown($edited['editorial_document']));
rc17851_ok(!empty($unchanged['ok']) && ($unchanged['reason']??'')==='unchanged_echo','unchanged full editorial reply remains explicit no-change');
echo "RC17851_HONDA_OK\n";
