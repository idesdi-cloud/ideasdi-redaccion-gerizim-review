<?php
declare(strict_types=1);
require __DIR__.'/wp-fixture.php';
$pass=0;$fail=0;
function check($ok,string $label):void{global $pass,$fail;if($ok){$pass++;echo "PASS $label\n";}else{$fail++;echo "FAIL $label\n";}}
function report_sections(string $file):array{
 if ($file==='polestar') {
  $fixture=json_decode(file_get_contents(__DIR__.'/fixtures/polestar-brief-272.json'),true,512,JSON_THROW_ON_ERROR);
  return [(string)$fixture['v1'],(string)$fixture['seo_original']];
 }
 if (!is_file('/mnt/data/'.$file)) throw new RuntimeException('prior report missing: '.$file);
 $txt=file_get_contents('/mnt/data/'.$file);
 if(!preg_match('/### Versión 1 · Artículo base\n(.*?)\n#### /su',$txt,$a))throw new RuntimeException('V1 absent '.$file);
 if(!preg_match('/### Versión 3 · Revisión SEO final\n(.*?)\n#### /su',$txt,$b))throw new RuntimeException('V3 absent '.$file);
 return [trim($a[1]),trim($b[1])];
}
function make_workflow(string $keyword,string $entity,string $url,int $cat,array $tags):array{return ['keyword'=>$keyword,'entity'=>$entity,'responsible_official_url'=>$url,'canonical_context'=>['responsible_official_url'=>$url],'category_id'=>$cat,'tag_ids'=>$tags,'primary_lens'=>'','secondary_lenses'=>[],'piece_type'=>'Actualidad','assignment_card'=>'brief fixture','editorial_plan_raw'=>'plan fixture','editorial_recipe_applied'=>'recipe fixture','seo_result'=>'nonempty'];}
$specs=[
 ['polestar','polestar','Polestar Formula 2030','Polestar','https://www.polestar.com/es/',1236,[1587,1246],'https://ideasdi.com/tag/vehiculos-electricos/'],
 ['nike','gerizim-reporte-nike-tuned-air-20261117-112813.md','Nike Tuned Air','NIKE','https://www.nike.com/us/es/',1238,[25001],'https://ideasdi.com/tag/calzado/'],
 ['skyreach','gerizim-reporte-skyways-skyreach-20261117-085056.md','Skyways Skyreach','Skyways','https://www.skyways.com/',1236,[1239],'https://ideasdi.com/tag/diseno-de-drones/'],
 ['samsung','gerizim-reporte-gafas-inteligentes-samsung-20261117-160753.md','Gafas inteligentes Samsung','Samsung Electronics','https://www.samsung.com/global/',57,[1521],''],
 ['sculptforge','gerizim-reporte-sculptforge-20261117-132111.md','SculptForge','Islandmed','https://sculptforge.app/',1232,[25002],'https://ideasdi.com/tag/ia-generativa/']
];
$polestar=null;
foreach($specs as [$name,$file,$keyword,$entity,$url,$cat,$tags,$p2]){
 if ($name!=='polestar' && !is_file('/mnt/data/'.$file)) continue;
 try{
  [$v1,$v3]=report_sections($file);$w=make_workflow($keyword,$entity,$url,$cat,$tags);
  $doc=IDG_Editorial_Document::from_markdown($v1,$w);
  $seo=IDG_Editorial_Document::apply_seo_response($doc,$v3,$w);
  $applied=IDG_Editorial_Document::complete_missing_authorized_links($seo['document'],$w);
  $meta=IDG_Editorial_Document::compact_meta_if_safe($applied['document'],$w);
  $html=IDG_Editorial_Document::gutenberg($meta['document'])['html'];
  $audit=IDG_Internal_Links::audit_html($html,$w);
  $w['editorial_document']=$meta['document'];$w['seo_result']=IDG_Editorial_Document::projection($meta['document']);
  $preflight=IDG_Post_Creator::preflight_public($w);
  check($seo['ok'],'case '.$name.' accepts SEO components');
  check($applied['ok'],'case '.$name.' links reconcile');
  check(substr_count($html,'href="'.$url.'"')===1,'case '.$name.' exactly one corporate URL');
  check($p2==='' ? $audit['internal_count']===0 : substr_count($html,'href="'.$p2.'"')===1,'case '.$name.' canonical P2 count');
  check(empty($audit['errors']),'case '.$name.' no internal audit errors');
  check(!empty($preflight['ok']),'case '.$name.' full guard + simulated Gutenberg preflight');
  $next=IDG_Editorial_Document::complete_missing_authorized_links($applied['document'],$w);
  check($next['ok'] && IDG_Editorial_Document::markdown($next['document'])===IDG_Editorial_Document::markdown($applied['document']),'case '.$name.' idempotent');
  if($name==='polestar')$polestar=[$w,$v1,$v3,$doc,$seo,$applied,$meta];
 }catch(Throwable $e){check(false,'case '.$name.' threw '.$e->getMessage());}
}
if($polestar){[$w,$v1,$v3,$doc,$seo,$applied,$meta]=$polestar;
 $original=IDG_Editorial_Document::markdown($doc);$final=IDG_Editorial_Document::markdown($applied['document']);
 check(!str_contains($original,'https://www.polestar.com/es/'),'Polestar V1 had no corporate anchor');
 check(!str_contains($original,'https://ideasdi.com/tag/vehiculos-electricos/'),'Polestar V1 had no P2 anchor');
 check(str_contains($final,'la marca [Polestar](https://www.polestar.com/es/)'),'Polestar official fallback apposes brand');
 check(str_contains($final,'[un concept car](https://ideasdi.com/tag/vehiculos-electricos/)'),'Polestar P2 reused existing object phrase');
 $without=preg_replace('/\[([^\]]+)\]\(https?:\/\/[^)]+\)/u','$1',$final);
 check(str_replace('la marca Polestar','la marca',$without)===$original,'Polestar prose otherwise byte-identical');
 check($meta['applied'],'Polestar safe meta contraction');
 check(mb_strlen($meta['document']['seo']['meta_description'])===141,'Polestar meta 141 chars');
 // Check links are preserved independently after P2 becomes unavailable in prose.
 $denied=$doc;
 foreach($denied['blocks'] as &$block){if(($block['type']??'')==='paragraph')$block['text']=str_ireplace(['concept car','coche','automóvil','vehículo','prototipo'],'artefacto',$block['text']);}unset($block);
 $part=IDG_Editorial_Document::complete_missing_authorized_links($denied,$w);
 check(!$part['ok'] && count($part['remaining'])===1,'partial links reports only the missing destination');
 check(str_contains(IDG_Editorial_Document::markdown($part['document']),'[Polestar](https://www.polestar.com/es/)'),'partial failure preserves successful official link');
 check(!str_contains(IDG_Editorial_Document::markdown($part['document']),'https://ideasdi.com/tag/vehiculos-electricos/'),'partial failure never fabricates P2');
 // Explicitly forbid a malicious canonical URL, even with plausible anchor.
 $target=IDG_Editorial_Document::anchor_insertion_target($denied,$w,['url'=>'https://ideasdi.com/tag/vehiculos-electricos/','internal'=>true]);
 check(is_array($target),'bounded repair target selected from an unlinked paragraph');
 $bad=['after'=>'Polestar Formula 2030','addition'=>' [un nuevo rendimiento](https://ideasdi.com/tag/vehiculos-electricos/)'];
 $badres=IDG_Editorial_Document::apply_bounded_link_insertion($denied,$w,['url'=>'https://ideasdi.com/tag/vehiculos-electricos/','internal'=>true],$target,json_encode($bad));
 check(!$badres['ok'],'reject invented claim added by model');
 $forged=IDG_Editorial_Document::apply_bounded_link_insertion($denied,$w,['url'=>'https://ideasdi.com/tag/otra/','internal'=>true],$target,json_encode(['after'=>'uno','addition'=>' [el diseño](https://ideasdi.com/tag/otra/)']));
 check(!$forged['ok'],'reject unauthorized internal destination');
 $lookalike=$w;$lookalike['responsible_official_url']='https://polestar.evil.com/';$lookalike['canonical_context']['responsible_official_url']='https://polestar.evil.com/';
 $evil=IDG_Editorial_Document::complete_missing_authorized_links($doc,$lookalike);
 check(!str_contains(IDG_Editorial_Document::markdown($evil['document']),'https://polestar.evil.com/'),'reject official brand apposition on lookalike domain');
 $long=$meta['document'];$long['seo']['meta_description']=str_repeat('Factual contenido ',11);$compact=IDG_Editorial_Document::compact_meta_if_safe($long,$w);
 check(!$compact['applied'],'no arbitrary truncation of long metadata');
 $goodmeta=IDG_Editorial_Document::compact_meta_if_safe($meta['document'],$w);
 check(!$goodmeta['applied'],'idempotent metadata handling');
 // P2 is a real selected tag; a duplicate must still fail closed.
 $dup=$applied['document'];foreach($dup['blocks'] as &$b){if(($b['type']??'')==='paragraph'&&str_contains($b['text'],'https://ideasdi.com/tag/vehiculos-electricos/')){$b['text'].=' [otra frase](https://ideasdi.com/tag/vehiculos-electricos/)';break;}}unset($b);
 $dup_result=IDG_Editorial_Document::complete_missing_authorized_links($dup,$w);
 check(!$dup_result['ok'],'duplicate internal canonical URL still rejected');
 $html=IDG_Editorial_Document::gutenberg($dup['document']??$dup)['html'];$audit=IDG_Internal_Links::audit_html($html,$w);
 check(!empty($audit['errors']),'duplicates rejected by real internal audit');
}
echo "RESULT: $pass passed; $fail failed\n";exit($fail?1:0);
