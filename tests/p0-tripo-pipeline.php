<?php
declare(strict_types=1);
require __DIR__.'/wp-fixture.php';
interface IDG_Workflow_Redaction_Pipeline_Contract {}
final class IDG_Workflow_Policies {const ACTION_GENERATE='generate';const ACTION_EDITORIAL='editorial';const ACTION_SEO='seo';public static function mark_completed($w,$phase){$w['status']='completed';$w['last_action']=$phase;return $w;}}
final class IDG_Job_Runner {public static array $saved=[]; public static function save_workflow($id,$w){self::$saved[$id]=$w;} public static function append_history_snapshot($w,$event,$text){$w['history'][]=['event'=>$event,'message'=>$text];return $w;} }
final class IDG_Usage_Estimator {public static function record($label,$usage,$id,$model){return ['stage'=>$label,'model'=>$model];}}
final class IDG_Logger {public static function log(...$args){} }
final class IDG_OpenAI_Client {public int $calls=0;public function __construct(private string $seo){} public function complete($prompt){$this->calls++;return ['success'=>true,'content'=>$this->calls===1?$this->seo:'{"patches":[]}', 'model'=>'mock-no-network','usage'=>[]];}}
final class IDG_Workflow_Prompt_Data {public static function prepare($w,$article,$phase){return ['editorial_document_manifest_ok'=>true,'keyword'=>$w['keyword'],'entity'=>$w['entity']];}}
final class IDG_Prompt_Library {public static function seo_prompt($d){return 'mock seo prompt';} public static function localized_repair_prompt(...$a){return 'mock repair';} public static function bounded_link_insertion_prompt(...$a){return 'mock bounded repair';} public static function reel_vo_length_repair_prompt(...$a){return 'mock reel repair';} public static function contextual_anchor_revision_prompt(...$a){return 'mock scoped rewrite';}}
function current_time($fmt){return '2026-11-17 18:29:00';}
require_once dirname(__DIR__).'/includes/class-validator.php';
require_once dirname(__DIR__).'/includes/class-workflow-redaction-pipeline.php';
$fixture=json_decode(file_get_contents(__DIR__.'/fixtures/tripo-brief-269.json'),true,512,JSON_THROW_ON_ERROR);
$w=['workflow_id'=>'p0-tripo-full-stage','keyword'=>'Tripo P2','entity'=>'Tripo Studio','responsible_official_url'=>'https://studio.tripo3d.ai/es','canonical_context'=>['responsible_official_url'=>'https://studio.tripo3d.ai/es'],'category_id'=>1232,'tag_ids'=>[1589,1248],'primary_lens'=>'','secondary_lenses'=>[],'piece_type'=>'Actualidad','assignment_card'=>'brief fixture','editorial_plan_raw'=>'plan fixture','editorial_recipe_applied'=>'recipe fixture','status'=>'completed','base_article'=>(string)$fixture['v2']];
$w['editorial_document']=IDG_Editorial_Document::from_markdown($w['base_article'],$w);
$client=new IDG_OpenAI_Client((string)$fixture['seo_original']);
(new IDG_Workflow_Redaction_Pipeline())->execute($w['workflow_id'],'seo',$w,$client);
$state=IDG_Job_Runner::$saved[$w['workflow_id']]??[];
$out=['status'=>$state['status']??'not_saved','last_error'=>$state['last_error']??'', 'calls'=>$client->calls,'seo_ok'=>$state['seo_validation']['ok']??false,'errors'=>$state['seo_validation']['errors']??[],'meta_len'=>mb_strlen((string)($state['editorial_document']['seo']['meta_description']??'')),'link_counts'=>[],'reel_ok'=>$state['seo_reel_validation']['valid']??null,'meta_compaction'=>$state['seo_response_diagnostics']['meta_safe_compaction']??'not_logged','link_repair'=>$state['seo_response_diagnostics']['link_repair']??null];
if(!empty($state['editorial_document'])){$html=IDG_Editorial_Document::gutenberg($state['editorial_document'])['html'];$out['link_counts']=['official'=>substr_count($html,'href="https://studio.tripo3d.ai/es"'),'internal'=>substr_count($html,'href="https://ideasdi.com/tag/modelado-3d/"')];}
echo json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";
if(($out['seo_ok']??false)&&$out['calls']===1&&$out['link_counts']['official']===1&&$out['link_counts']['internal']===1&&$out['meta_len']>=106&&$out['meta_len']<=150&&$out['reel_ok']){echo "TRIPO FULL PIPELINE PASSED\n";exit(0);}echo "TRIPO FULL PIPELINE FAILED\n";exit(1);
