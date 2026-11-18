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
function current_time($fmt){return '2026-11-17 20:40:00';}
require_once dirname(__DIR__).'/includes/class-validator.php';
require_once dirname(__DIR__).'/includes/class-workflow-redaction-pipeline.php';
$f=json_decode(file_get_contents(__DIR__.'/fixtures/aeroai-report-fixture.json'),true,512,JSON_THROW_ON_ERROR);
$w=['workflow_id'=>'p0-aeroai-editorial-team','keyword'=>'AeroAI','entity'=>'Beyond Aero','responsible_official_url'=>'https://www.beyond-aero.com/','canonical_context'=>['responsible_official_url'=>'https://www.beyond-aero.com/'],'category_id'=>1236,'tag_ids'=>[1549],'primary_lens'=>'','secondary_lenses'=>[],'piece_type'=>'Actualidad','assignment_card'=>'fixture','editorial_plan_raw'=>'plan','editorial_recipe_applied'=>'recipe','status'=>'completed','base_article'=>$f['v1']];
$w['editorial_document']=IDG_Editorial_Document::from_markdown($f['v1'],$w);
$raw="ARTÍCULO REVISADO\n".$f['v1']."\n\nDIAGNÓSTICO EDITORIAL INTERNO\n- Sin cambios narrativos.\n\nNOTAS EDITORIALES INTERNAS\n- Enlaces previstos para próxima fase.";
$client=new IDG_OpenAI_Client($raw);
(new IDG_Workflow_Redaction_Pipeline())->execute($w['workflow_id'],'editorial',$w,$client);
$s=IDG_Job_Runner::$saved[$w['workflow_id']]??[];
$html=IDG_Editorial_Document::gutenberg((array)($s['editorial_document']??[]))['html']??'';
$plain_before=IDG_Editorial_Document::markdown($w['editorial_document']);
$plain_after=IDG_Editorial_Document::markdown($s['editorial_document']);
$plain_after=preg_replace('/\[([^\]]+)\]\(https?:\/\/[^)]+\)/u','$1',$plain_after);
$out=['calls'=>$client->calls,'review_ok'=>$s['editorial_validation']['ok']??false,'official'=>substr_count($html,'href="https://www.beyond-aero.com/"'),'p2'=>substr_count($html,'href="https://ideasdi.com/tag/diseno-de-transporte-aeronautico/"'),'changed'=>$s['editorial_changed']??false,'alignment'=>$s['editorial_link_team']??null,'errors'=>$s['editorial_validation']['errors']??[],'plain_preserved'=>$plain_before===$plain_after];
echo json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
if($out['calls']===1 && $out['review_ok'] && $out['official']===1 && $out['p2']===1 && $out['changed'] && $out['plain_preserved'] && count($out['alignment']['changed_block_ids']??[])>=2){echo "EDITORIAL + P2 AEROAI PASSED\n";exit(0);}echo "EDITORIAL + P2 AEROAI FAILED\n";exit(1);
