<?php
declare(strict_types=1);
require __DIR__.'/wp-fixture.php';
interface IDG_Workflow_Redaction_Pipeline_Contract {}
final class IDG_Workflow_Policies {const ACTION_GENERATE='generate';const ACTION_EDITORIAL='editorial';const ACTION_SEO='seo';public static function mark_completed($w,$phase){$w['status']='completed';$w['last_action']=$phase;return $w;}}
final class IDG_Job_Runner {public static array $saved=[]; public static function save_workflow($id,$w){self::$saved[$id]=$w;} public static function append_history_snapshot($w,$event,$text){$w['history'][]=['event'=>$event,'message'=>$text];return $w;}}
final class IDG_Usage_Estimator {public static function record($label,$usage,$id,$model){return ['stage'=>$label,'model'=>$model];}}
final class IDG_Logger {public static function log(...$args){} }
final class IDG_OpenAI_Client {public int $calls=0;public function __construct(private string $review){} public function complete($prompt){$this->calls++;return ['success'=>true,'content'=>$this->review,'model'=>'mock-no-network','usage'=>[]];}}
final class IDG_Workflow_Prompt_Data {public static function prepare($w,$article,$phase){return ['editorial_document_manifest_ok'=>true,'keyword'=>$w['keyword'],'entity'=>$w['entity']];}}
final class IDG_Prompt_Library {public static function editorial_prompt($data){return 'mock editorial';}}
function current_time($fmt){return '2026-11-17 19:30:00';}
require_once dirname(__DIR__).'/includes/class-validator.php';
require_once dirname(__DIR__).'/includes/class-workflow-redaction-pipeline.php';
$fixture=json_decode(file_get_contents(__DIR__.'/fixtures/tripo-brief-269.json'),true,512,JSON_THROW_ON_ERROR);
$w=['workflow_id'=>'p0-tripo-editorial-early-alignment','keyword'=>'Tripo P2','entity'=>'Tripo Studio',
 'responsible_official_url'=>'https://studio.tripo3d.ai/es','canonical_context'=>['responsible_official_url'=>'https://studio.tripo3d.ai/es'],
 'category_id'=>1232,'tag_ids'=>[1589,1248],'primary_lens'=>'','secondary_lenses'=>[],'piece_type'=>'Actualidad',
 'assignment_card'=>'brief fixture','editorial_plan_raw'=>'plan fixture','editorial_recipe_applied'=>'recipe fixture',
 'status'=>'completed','base_article'=>(string)$fixture['v2']];
$w['editorial_document']=IDG_Editorial_Document::from_markdown($w['base_article'],$w);
$raw="ARTÍCULO REVISADO\n".$fixture['v2']."\n\nDIAGNÓSTICO EDITORIAL INTERNO\n- Sin modificaciones narrativas.\n\nNOTAS EDITORIALES INTERNAS\n- Mantener la caja intacta.";
$client=new IDG_OpenAI_Client($raw);
(new IDG_Workflow_Redaction_Pipeline())->execute($w['workflow_id'],'editorial',$w,$client);
$s=IDG_Job_Runner::$saved[$w['workflow_id']]??[];
$html=IDG_Editorial_Document::gutenberg((array)($s['editorial_document']??[]))['html']??'';
$out=[
 'status'=>$s['status']??'missing','calls'=>$client->calls,'review_ok'=>$s['editorial_validation']['ok']??false,
 'external'=>substr_count($html,'href="https://studio.tripo3d.ai/es"'),'internal'=>substr_count($html,'href="https://ideasdi.com/tag/modelado-3d/"'),
 'changed'=>$s['editorial_changed']??false,'alignment'=>$s['editorial_link_team']??null,'errors'=>$s['editorial_validation']['errors']??[],
];
echo json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
if($out['status']==='completed'&&$out['calls']===1&&$out['review_ok']&&$out['external']===1&&$out['internal']===1&&$out['changed']&&count($out['alignment']['changed_block_ids']??[])>=1){echo "EDITORIAL TEAM PRE-SEO PASSED\n";exit(0);}echo "EDITORIAL TEAM PRE-SEO FAILED\n";exit(1);
