<?php
declare(strict_types=1);
require __DIR__.'/wp-fixture.php';
interface IDG_Workflow_Redaction_Pipeline_Contract {}
final class IDG_Workflow_Policies {const ACTION_GENERATE='generate';const ACTION_EDITORIAL='editorial';const ACTION_SEO='seo';public static function mark_completed($w,$phase){$w['status']='completed';$w['last_action']=$phase;return $w;}}
final class IDG_Job_Runner {public static array $saved=[]; public static function save_workflow($id,$w){self::$saved[$id]=$w;} public static function append_history_snapshot($w,$event,$text){$w['history'][]=['event'=>$event,'message'=>$text];return $w;} }
final class IDG_Usage_Estimator {public static function record($label,$usage,$id,$model){return ['stage'=>$label,'model'=>$model];}}
final class IDG_Logger {public static function log(...$args){} }
final class IDG_OpenAI_Client {public int $calls=0;public function __construct(private string $seo,private string $repair=''){} public function complete($prompt){$this->calls++;return ['success'=>true,'content'=>$this->calls===1?$this->seo:($this->repair?:'{"patches":[]}'), 'model'=>'mock-no-network','usage'=>[]];}}
final class IDG_Workflow_Prompt_Data {public static function prepare($w,$article,$phase){return ['editorial_document_manifest_ok'=>true,'keyword'=>$w['keyword'],'entity'=>$w['entity']];}}
function current_time($fmt){return '2026-11-17 20:40:00';}
require_once dirname(__DIR__).'/includes/class-prompt-library.php';
require_once dirname(__DIR__).'/includes/class-validator.php';
require_once dirname(__DIR__).'/includes/class-workflow-redaction-pipeline.php';
function check_case($v,$label){global $pass,$fail;if($v){$pass++;echo "PASS $label\n";}else{$fail++;echo "FAIL $label\n";}}
$pass=0;$fail=0;
$cases=[['aeroai','AeroAI','Beyond Aero','https://www.beyond-aero.com/',1236,[1549],'https://ideasdi.com/tag/diseno-de-transporte-aeronautico/'],['tripo','Tripo P2','Tripo Studio','https://studio.tripo3d.ai/es',1232,[1589,1248],'https://ideasdi.com/tag/modelado-3d/'],['polestar','Polestar Formula 2030','Polestar','https://www.polestar.com/es/',1236,[1587,1246],'https://ideasdi.com/tag/vehiculos-electricos/'],['nike','Nike Tuned Air','NIKE','https://www.nike.com/us/es/',1238,[25001],'https://ideasdi.com/tag/calzado/'],['skyreach','Skyways Skyreach','Skyways','https://www.skyways.com/',1236,[1239],'https://ideasdi.com/tag/diseno-de-drones/'],['samsung','Gafas inteligentes Samsung','Samsung Electronics','https://www.samsung.com/global/',57,[1521],''],['sculptforge','SculptForge','Islandmed','https://sculptforge.app/',1232,[25002],'https://ideasdi.com/tag/ia-generativa/']];
foreach($cases as [$name,$keyword,$entity,$official,$category,$tags,$p2]){
 $f=json_decode(file_get_contents(__DIR__.'/fixtures/'.$name.'-report-fixture.json'),true,512,JSON_THROW_ON_ERROR);
 $w=['workflow_id'=>'p0-unified-'.$name,'keyword'=>$keyword,'entity'=>$entity,'responsible_official_url'=>$official,'canonical_context'=>['responsible_official_url'=>$official],'category_id'=>$category,'tag_ids'=>$tags,'primary_lens'=>'','secondary_lenses'=>[],'piece_type'=>'Actualidad','assignment_card'=>'fixture','editorial_plan_raw'=>'plan','editorial_recipe_applied'=>'recipe','status'=>'completed','base_article'=>$f['v1']];
 $w['editorial_document']=IDG_Editorial_Document::from_markdown($f['v2'],$w);
 $client=new IDG_OpenAI_Client($f['seo_original']);
 (new IDG_Workflow_Redaction_Pipeline())->execute($w['workflow_id'],'seo',$w,$client);
 $state=IDG_Job_Runner::$saved[$w['workflow_id']]??[];
 check_case($client->calls===1,"$name full SEO run uses exactly one model response");
 check_case(!empty($state['seo_validation']['ok']),"$name full pipeline Final Guard approved ".implode(' / ',(array)($state['seo_validation']['errors']??[])));
 $d=$state['editorial_document']??[]; $html=IDG_Editorial_Document::gutenberg($d)['html'];
 check_case(substr_count($html,'href="'.$official.'"')===1,"$name official present once in Gutenberg");
 check_case($p2==='' ? IDG_Internal_Links::audit_html($html,$w)['internal_count']===0 : substr_count($html,'href="'.$p2.'"')===1,"$name P2 correct in Gutenberg");
 check_case(!empty($state['seo_reel_validation']['valid']),"$name original Reel validated");
 check_case(empty($state['seo_response_diagnostics']['authorized_links']),"$name no unresolved link diagnostic");
}
echo "RESULT: $pass PASS $fail FAIL\n";exit($fail?1:0);
