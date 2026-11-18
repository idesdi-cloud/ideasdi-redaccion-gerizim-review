<?php
declare(strict_types=1);
require __DIR__.'/wp-fixture.php';
interface IDG_Workflow_Redaction_Pipeline_Contract {}
final class IDG_Workflow_Policies {const ACTION_GENERATE='generate';const ACTION_EDITORIAL='editorial';const ACTION_SEO='seo';public static function mark_completed($w,$phase){$w['status']='completed';$w['last_action']=$phase;return $w;}}
final class IDG_Job_Runner {public static array $saved=[]; public static function save_workflow($id,$w){self::$saved[$id]=$w;} public static function append_history_snapshot($w,$event,$text){$w['history'][]=['event'=>$event,'message'=>$text];return $w;} }
final class IDG_Usage_Estimator {public static function record($label,$usage,$id,$model){return ['stage'=>$label,'model'=>$model];}}
final class IDG_Logger {public static function log(...$args){} }
final class IDG_OpenAI_Client {public int $calls=0;public function __construct(private string $seo,private string $repair){} public function complete($prompt){$this->calls++;return ['success'=>true,'content'=>$this->calls===1?$this->seo:$this->repair,'model'=>'mock-no-network','usage'=>[]];}}
final class IDG_Workflow_Prompt_Data {public static function prepare($w,$article,$phase){return ['editorial_document_manifest_ok'=>true,'keyword'=>$w['keyword'],'entity'=>$w['entity']];}}
function current_time($fmt){return '2026-11-17 20:40:00';}
require_once dirname(__DIR__).'/includes/class-prompt-library.php';
require_once dirname(__DIR__).'/includes/class-validator.php';
require_once dirname(__DIR__).'/includes/class-workflow-redaction-pipeline.php';
$f=json_decode(file_get_contents(__DIR__.'/fixtures/aeroai-report-fixture.json'),true,512,JSON_THROW_ON_ERROR);
$official='https://www.beyond-aero.com/';$p2='https://ideasdi.com/tag/diseno-de-transporte-aeronautico/';
$w=['workflow_id'=>'p0-aeroai-missing-p2-editor','keyword'=>'AeroAI','entity'=>'Beyond Aero','responsible_official_url'=>$official,'canonical_context'=>['responsible_official_url'=>$official],'category_id'=>1236,'tag_ids'=>[1549],'primary_lens'=>'','secondary_lenses'=>[],'piece_type'=>'Actualidad','assignment_card'=>'fixture','editorial_plan_raw'=>'plan','editorial_recipe_applied'=>'recipe','status'=>'completed','base_article'=>$f['v1']];
$w['editorial_document']=IDG_Editorial_Document::from_markdown($f['v2'],$w);
// Leave no literal topical anchor: force language editor on existing material.
foreach($w['editorial_document']['blocks'] as &$block){if(($block['type']??'')==='paragraph')$block['text']=str_replace('aeronáutico','técnico',$block['text']);}unset($block);
$need=['internal'=>true,'url'=>$p2,'reason'=>'missing_contextual_anchor'];
$det=IDG_Editorial_Document::complete_missing_authorized_links($w['editorial_document'],$w);
if(!empty($det['ok'])){echo "FAIL precondition: deterministic link still available\n";exit(1);}
$target=IDG_Editorial_Document::contextual_link_editor_target($det['document'],$w,$need);
if(!$target){echo "FAIL no target\n";exit(1);}
$text=$target['text'];
$edited=str_replace('configuraciones, el uso','configuraciones de [un programa aeronáutico]('.$p2.'), el uso',$text);
if($edited===$text){echo "FAIL fixture target changed\n";exit(1);}
$client=new IDG_OpenAI_Client($f['seo_original'],json_encode(['paragraph'=>$edited],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
(new IDG_Workflow_Redaction_Pipeline())->execute($w['workflow_id'],'seo',$w,$client);
$state=IDG_Job_Runner::$saved[$w['workflow_id']]??[];
$doc=$state['editorial_document']??[];$html=IDG_Editorial_Document::gutenberg($doc)['html'];
$out=['calls'=>$client->calls,'ok'=>$state['seo_validation']['ok']??false,'errors'=>$state['seo_validation']['errors']??[], 'repair'=>$state['seo_response_diagnostics']['link_repair']??null, 'p2'=>substr_count($html,'href="'.$p2.'"'),'official'=>substr_count($html,'href="'.$official.'"')];
echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n";
if($out['calls']===2 && $out['ok']===true && $out['p2']===1 && $out['official']===1 && !empty($out['repair']['applied'])){echo "FULL LOCAL EDITOR PIPELINE PASSED\n";exit(0);}echo "FULL LOCAL EDITOR PIPELINE FAILED\n";exit(1);
