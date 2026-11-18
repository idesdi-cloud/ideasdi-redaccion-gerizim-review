<?php
declare(strict_types=1);
require __DIR__.'/wp-fixture.php';
$fixture=json_decode(file_get_contents(__DIR__.'/fixtures/tripo-brief-269.json'),true,512,JSON_THROW_ON_ERROR);
$passed=0;$failed=0;
function check_269(bool $pass,string $label):void {global $passed,$failed; if($pass){$passed++;echo "PASS $label\n";}else{$failed++;echo "FAIL $label\n";}}
$w=['workflow_id'=>'p0-tripo-fixture','keyword'=>$fixture['keyword'],'entity'=>$fixture['entity'], 'responsible_official_url'=>$fixture['official_url'],'canonical_context'=>['responsible_official_url'=>$fixture['official_url']],'category_id'=>1232,'tag_ids'=>[1589,1248],'primary_lens'=>'','secondary_lenses'=>[],'piece_type'=>'Actualidad','assignment_card'=>'brief fixture','editorial_plan_raw'=>'plan fixture','editorial_recipe_applied'=>'recipe fixture'];
$orig=IDG_Editorial_Document::from_markdown($fixture['v2'],$w);
$w['editorial_document']=$orig;
$initial=IDG_Post_Creator::preflight_public($w);
check_269(empty($initial['ok']),'Original Tripo P2 indeed fails before repair');
check_269(!empty($initial['validation']['errors']),'Original preflight shows errors');
$seo=IDG_Editorial_Document::apply_seo_response($orig,$fixture['seo_original'],$w);
check_269(!empty($seo['ok']),'Original rejected SEO model response can preserve independent metadata');
$fixed=IDG_Editorial_Document::complete_missing_authorized_links($seo['document'],$w);
check_269(!empty($fixed['ok']),'Authorized external and internal links repaired together');
check_269(empty($fixed['remaining']),'No unresolved link left after correction');
$after=IDG_Editorial_Document::markdown($fixed['document']);
$html=IDG_Editorial_Document::gutenberg($fixed['document'])['html'];
check_269(substr_count($html,'href="'.$fixture['official_url'].'"')===1,'One authorized Tripo Studio external link');
check_269(substr_count($html,'href="'.$fixture['internal_url'].'"')===1,'One canonical modelado-3d link');
check_269(str_contains($after,'[Tripo Studio]('.$fixture['official_url'].')'),'Actual responsible-name phrase linked without identity inference');
check_269(str_contains($after,'[La página de modelado 3D]('.$fixture['internal_url'].')'),'Existing internal anchor repaired in its own paragraph');
check_269(substr_count($after,'https://ideasdi.com/tag/modelado-3d/')===1,'Repair does not duplicate P2');
$before_plain=preg_replace('/\[([^\]]+)\]\(https?:\/\/[^)]+\)/u','$1',IDG_Editorial_Document::markdown($orig));
$after_plain=preg_replace('/\[([^\]]+)\]\(https?:\/\/[^)]+\)/u','$1',$after);
check_269($before_plain===$after_plain,'Tripo visible prose is byte-identical after removing Markdown links');
$w['editorial_document']=$fixed['document'];$w['seo_result']=IDG_Editorial_Document::projection($fixed['document']);
$pre=IDG_Post_Creator::preflight_public($w);
check_269(!empty($pre['ok']),'Tripo real full Gutenberg preflight passes');
check_269(empty($pre['validation']['errors']),'Tripo real final validation has zero errors');
check_269(!empty($fixed['document']['reel']['valid']),'Original real Reel remains valid');
$retry=IDG_Editorial_Document::complete_missing_authorized_links($fixed['document'],$w);
check_269(!empty($retry['ok']) && IDG_Editorial_Document::markdown($retry['document'])===$after,'Repeated reconciliation is idempotent');
check_269(!IDG_Editorial_Document::suspicious_responsible_subdomain($fixture['official_url'],'Tripo Studio'),'Legitimate creative-domain mismatch is permitted');
check_269(IDG_Editorial_Document::suspicious_responsible_subdomain('https://tripo.evil.com/','Tripo Studio'),'Foreign registrable domain with brand in subdomain is rejected');
$spoof=$w;$spoof['responsible_official_url']='https://tripo.evil.com/';$spoof['canonical_context']['responsible_official_url']='https://tripo.evil.com/';
$bad=IDG_Editorial_Document::complete_missing_authorized_links($orig,$spoof);
check_269(!str_contains(IDG_Editorial_Document::markdown($bad['document']),'https://tripo.evil.com/'),'No automatic link to brand-spoof URL');
// Simulate a P2 link at the start of a paragraph: deterministic expansion
// cannot invent a preceding phrase, so the local language editor may act.
$minimal="[Modelado 3D]({$fixture['internal_url']}) es el marco en el que Tripo P2 organiza el trabajo posterior.";
$synthetic=$orig;
$synthetic['blocks'][3]['text']=$minimal;
// Isolate link rewrite and remove other P2 links so a one-link target is unambiguous.
foreach ($synthetic['blocks'] as $i=>&$blk) {
 if ($i===3 || ($blk['type']??'')!=='paragraph') continue;
 $blk['text']=str_replace('[modelado 3D]('.$fixture['internal_url'].')','modelado 3D',(string)$blk['text']);
}unset($blk);
$need=['url'=>$fixture['internal_url'],'internal'=>true,'reason'=>'invalid_internal_anchor'];
$rewritable=IDG_Editorial_Document::contextual_anchor_revision_target($synthetic,$need);
check_269(is_array($rewritable) && $rewritable['index']===3,'Editor targets the paragraph containing the defective P2');
$replacement="En [las prácticas del modelado 3D]({$fixture['internal_url']}) se organiza el trabajo posterior de Tripo P2.";
$accepted=IDG_Editorial_Document::apply_contextual_anchor_revision($synthetic,$w,$need,$rewritable,json_encode(['paragraph'=>$replacement],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
if (empty($accepted['ok'])) echo 'EDITOR_REJECTION: '.($accepted['reason']??'unknown')."\n";
check_269(!empty($accepted['ok']),'Safe model language revision accepted for a single paragraph');
check_269(!empty($accepted['ok']) && $accepted['document']['blocks'][3]['text']===$replacement,'Editor only changes the selected paragraph');
$wrong=IDG_Editorial_Document::apply_contextual_anchor_revision($synthetic,$w,$need,$rewritable,json_encode(['paragraph'=>str_replace($fixture['internal_url'],'https://ideasdi.com/tag/falso/',$replacement)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
check_269(empty($wrong['ok']),'Invented destination refused');
$extra=IDG_Editorial_Document::apply_contextual_anchor_revision($synthetic,$w,$need,$rewritable,json_encode(['paragraph'=>$replacement.' [otro]( '.$fixture['internal_url'].')'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
check_269(empty($extra['ok']),'Additional noncanonical markup rejected');
$numeric=IDG_Editorial_Document::apply_contextual_anchor_revision($synthetic,$w,$need,$rewritable,json_encode(['paragraph'=>$replacement.' Genera 80K texturas.'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
check_269(empty($numeric['ok']),'New numerical claim refused');
$name=IDG_Editorial_Document::apply_contextual_anchor_revision($synthetic,$w,$need,$rewritable,json_encode(['paragraph'=>str_replace('Tripo P2','otro sistema',$replacement)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
check_269(empty($name['ok']),'Protected project identity cannot be replaced');
$stale=$rewritable;$stale['text'].=' modificado';
$staleResult=IDG_Editorial_Document::apply_contextual_anchor_revision($synthetic,$w,$need,$stale,json_encode(['paragraph'=>$replacement],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
check_269(empty($staleResult['ok']),'Stale target cannot modify paragraph');
$badJSON=IDG_Editorial_Document::apply_contextual_anchor_revision($synthetic,$w,$need,$rewritable,'{"paragraph":');
check_269(empty($badJSON['ok']),'Invalid editor JSON never edits document');
// Test scoped to P2 anchor, without unnecessary changes to exception mechanism.
check_269(str_contains((string)file_get_contents(__DIR__.'/../includes/class-final-guard.php'),'El anchor del enlace interno debe tener entre 3 y 8 palabras.'),'Original P2 quality guard stays active');
echo "RESULT: $passed passed; $failed failed\n";
exit($failed?1:0);
