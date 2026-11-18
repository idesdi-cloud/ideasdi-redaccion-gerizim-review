<?php
declare(strict_types=1);
require __DIR__.'/wp-fixture.php';
$pass=0;$fail=0;
function chk($ok,string $message):void{global $pass,$fail;if($ok){$pass++;echo "PASS: $message\n";}else{$fail++;echo "FAIL: $message\n";}}
function fixture_case(string $name):array{return json_decode((string)file_get_contents(__DIR__.'/fixtures/'.$name.'-report-fixture.json'),true,512,JSON_THROW_ON_ERROR);}
function ws(string $keyword,string $entity,string $url,int $cat,array $tags):array{return ['keyword'=>$keyword,'entity'=>$entity,'responsible_official_url'=>$url,'canonical_context'=>['responsible_official_url'=>$url],'category_id'=>$cat,'tag_ids'=>$tags,'primary_lens'=>'','secondary_lenses'=>[],'piece_type'=>'Actualidad','assignment_card'=>'fixture','editorial_plan_raw'=>'plan fixture','editorial_recipe_applied'=>'recipe fixture','seo_result'=>'pending'];}
$cases=[
 ['aeroai','AeroAI','Beyond Aero','https://www.beyond-aero.com/',1236,[1549],'https://ideasdi.com/tag/diseno-de-transporte-aeronautico/'],
 ['tripo','Tripo P2','Tripo Studio','https://studio.tripo3d.ai/es',1232,[1589,1248],'https://ideasdi.com/tag/modelado-3d/'],
 ['nike','Nike Tuned Air','NIKE','https://www.nike.com/us/es/',1238,[25001],'https://ideasdi.com/tag/calzado/'],
 ['skyreach','Skyways Skyreach','Skyways','https://www.skyways.com/',1236,[1239],'https://ideasdi.com/tag/diseno-de-drones/'],
 ['samsung','Gafas inteligentes Samsung','Samsung Electronics','https://www.samsung.com/global/',57,[1521],''],
 ['sculptforge','SculptForge','Islandmed','https://sculptforge.app/',1232,[25002],'https://ideasdi.com/tag/ia-generativa/']
];
$replays=[];
foreach($cases as [$name,$keyword,$entity,$official,$cat,$tags,$internal]){
 $f=fixture_case($name);$w=ws($keyword,$entity,$official,$cat,$tags);
 $doc=IDG_Editorial_Document::from_markdown($f['v2'],$w);
 $seo=IDG_Editorial_Document::apply_seo_response($doc,$f['seo_original'],$w);
 chk(!empty($seo['ok']),"$name SEO model response accepted as components");
 if(empty($seo['ok'])){echo 'reason '.($seo['reason']??'n/a')."\n";continue;}
 $before=IDG_Editorial_Document::markdown($seo['document']);
 $aligned=IDG_Editorial_Document::complete_missing_authorized_links($seo['document'],$w);
 chk(!empty($aligned['ok']),"$name authorized links reconciled; remaining=".json_encode($aligned['remaining']??[]));
 $after=IDG_Editorial_Document::markdown($aligned['document']);$html=IDG_Editorial_Document::gutenberg($aligned['document'])['html'];
 chk(substr_count($html,'href="'.$official.'"')===1,"$name exactly one official anchor");
 chk(($internal==='' ? !str_contains($html,'href="https://ideasdi.com/tag/') : substr_count($html,'href="'.$internal.'"')===1),"$name canonical P2 exactly one if eligible");
 $audit=IDG_Internal_Links::audit_html($html,$w);
 chk(empty($audit['errors']),"$name WP tag audit valid: ".implode(' / ',$audit['errors']));
 $w['editorial_document']=$aligned['document'];$w['seo_result']=IDG_Editorial_Document::projection($aligned['document']);
 $pre=IDG_Post_Creator::preflight_public($w);
 chk(!empty($pre['ok']),"$name Gutenberg full guard: ".implode(' / ',(array)($pre['validation']['errors']??[])));
 $retry=IDG_Editorial_Document::complete_missing_authorized_links($aligned['document'],$w);
 chk(!empty($retry['ok'])&&IDG_Editorial_Document::markdown($retry['document'])===$after,"$name idempotent");
 if($name==='aeroai'){
  chk(str_contains($after,'[un programa aeronáutico]('.$internal.')'),"AeroAI un programa aeronáutico correctly linked");
  $plain_before=preg_replace('/\[([^\]]+)\]\(https?:\/\/[^)]+\)/u','$1',$before);
  $plain_after=preg_replace('/\[([^\]]+)\]\(https?:\/\/[^)]+\)/u','$1',$after);
  chk($plain_before===$plain_after,"AeroAI prose exactly unchanged after adding link");
 }
 if($name==='tripo'){
  chk(str_contains($after,'[La página de modelado 3D]('.$internal.')'),"Tripo existing P2 repaired in original paragraph");
 }
 $replays[$name]=[$w,$doc,$seo,$aligned];
}
// Guard reports missing P2, not word count for a non-existing anchor.
if(isset($replays['aeroai'])){
 [$w,$doc,$seo,$aligned]=$replays['aeroai'];
 $w['editorial_document']=$seo['document'];$w['seo_result']=IDG_Editorial_Document::projection($seo['document']);
 $pre=IDG_Post_Creator::preflight_public($w);
 $errors=implode('|',(array)($pre['validation']['errors']??[]));
 chk(str_contains($errors,'P2 exige exactamente un enlace'), 'AeroAI missing P2 still detected');
 chk(!str_contains($errors,'debe tener entre 3 y 8'), 'AeroAI missing P2 does not trigger empty-anchor word count');
 // Semantic one-paragraph editor for a P2 that is ABSENT.
 $nomod=$doc;
 foreach($nomod['blocks'] as &$block){if(($block['type']??'')==='paragraph')$block['text']=str_replace('aeronáutico','técnico',$block['text']);}unset($block);
 $need=['internal'=>true,'url'=>'https://ideasdi.com/tag/diseno-de-transporte-aeronautico/','reason'=>'missing_contextual_anchor'];
 $target=IDG_Editorial_Document::contextual_link_editor_target($nomod,$w,$need);
 chk(is_array($target),'AeroAI absent P2 has semantic editor target');
 if(is_array($target)){
   $text=$target['text'];
   $after=str_replace('configuraciones, el uso','configuraciones de [un programa aeronáutico]('.$need['url'].'), el uso',$text);
   // An editor may propose equivalent prose, with links and only verified vocabulary.
   $reply=json_encode(['paragraph'=>$after],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
   $answer=IDG_Editorial_Document::apply_contextual_anchor_revision($nomod,$w,$need,$target,$reply);
   chk(!empty($answer['ok']),'Absent P2 semantic editor accepts safe localized edit '.($answer['reason']??''));
   $forged=IDG_Editorial_Document::apply_contextual_anchor_revision($nomod,$w,$need,$target,json_encode(['paragraph'=>$text.' [nuevo concepto](https://ideasdi.com/tag/falso/)'],JSON_UNESCAPED_UNICODE));
   chk(empty($forged['ok']),'Unknown internal destination rejected');
 }
}
echo "RESULT: $pass PASS $fail FAIL\n";exit($fail?1:0);
