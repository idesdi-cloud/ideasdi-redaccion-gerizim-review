<?php
declare(strict_types=1);
/**
 * Isolated P0 regressions: one canonical factual box and Reel VO-length repair.
 * No WordPress installation, database, model connection or post write required.
 * CLI: php tests/p0-structural-reel-regression.php
 */
define('ABSPATH', '/tmp/wp');
if (!function_exists('mb_strlen')) {
    function mb_strlen($s, $encoding='UTF-8') { return iconv_strlen((string)$s, $encoding); }
    function mb_substr($s, $start, $length=null, $encoding='UTF-8') { return iconv_substr((string)$s, $start, $length, $encoding); }
    function mb_strtolower($s, $encoding='UTF-8') { return strtolower((string)$s); }
    function mb_stripos($s, $needle, $offset=0, $encoding='UTF-8') { return stripos((string)$s, (string)$needle, $offset); }
}
function wp_strip_all_tags($s) { return strip_tags((string)$s); }
function wp_json_encode($v,$flags=0) { return json_encode($v,$flags); }
function remove_accents($s) { return $s; }
function sanitize_text_field($s) { return trim(strip_tags((string)$s)); }
function esc_url_raw($s) { return $s; }
function esc_html($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function esc_url($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function serialize_blocks($bs) { return implode('', array_map(fn($b)=>$b['innerHTML'], $bs)); }
class IDG_Usage_Estimator { public static function record(...$args) { return []; } }
class IDG_OpenAI_Client {
    public array $responses=[];
    public int $calls=0;
    public string $lastPrompt='';
    public function __construct(array $responses=[]) { $this->responses = $responses; }
    public function complete(string $prompt): array { $this->calls++; $this->lastPrompt=$prompt; return array_shift($this->responses) ?? ['success'=>false]; }
}
$base=dirname(__DIR__).'/includes/';
require $base.'contracts/interface-workflow-redaction-pipeline.php';
foreach(['class-workflow-output-parser.php','class-editorial-document.php','class-final-guard.php','class-reel-contract.php','class-prompt-library.php','class-workflow-redaction-pipeline.php'] as $f) require $base.$f;
$passed=0;$failed=0;
function check(bool $ok,string $label): void {global $passed,$failed;if($ok){$passed++; echo "PASS $label\n";}else{$failed++;echo "FAIL $label\n";}}

$box='SculptForge es una aplicación de escultura 3D gratuita y de código abierto impulsada por islandmed y colaboradores. Funciona en Windows, macOS y Linux, orientada a VFX, animación y videojuegos. Integra escultura, retopología, pintura, baking, LODs, soporte para Blender, Maya y Unreal, y herramientas de IA opcionales.';
$prefix="# SculptForge encadena escultura y pipeline 3D\n\n## Un software 3D abierto\n\nSculptForge reúne herramientas de escultura y retopología.\n\n";
$suffix="\n\n### El gesto clave está en encadenar tareas\n\nEste software conecta tareas de producción 3D.\n";
$workflow=['keyword'=>'SculptForge','assignment_card'=>'encargo validado','editorial_plan_raw'=>'plan validado','editorial_recipe_applied'=>'receta validada'];
$cases=['Caja editorial','**Caja editorial**','__Caja editorial__','### Caja editorial','### **Caja editorial**','#### Caja editorial:'];
foreach($cases as $label){
 $original=$prefix.$label."\n\n".$box.$suffix;
 $normalized=IDG_Workflow_Output_Parser::normalize_editorial_box_markdown($original);
 check(substr_count($normalized,"\nCaja editorial\n")===1 && !str_contains($normalized,'### Caja editorial'), 'normalizes box label '.$label);
 check(IDG_Workflow_Output_Parser::normalize_editorial_box_markdown($normalized)===$normalized,'box idempotent '.$label);
 check(!empty(IDG_Final_Guard::validate_article_phase($normalized,$workflow,'generate')['ok']),'generation validated '.$label);
 $doc=IDG_Editorial_Document::from_markdown($original,$workflow);
 $html=IDG_Editorial_Document::gutenberg($doc)['html'];
 check(substr_count($html,'class="featured-snippet-box"')===1 && !str_contains($html,'<h3>Caja editorial</h3>'),'Gutenberg HTML renders exactly one box '.$label);
 check(count(array_filter($doc['blocks'],fn($b)=>$b['type']==='editorial_box'))===1,'direct document parser retains box '.$label);
}
$two=$prefix."### Caja editorial\n\n".$box."\n\nCaja editorial\n\n".$box.$suffix;
check(IDG_Workflow_Output_Parser::normalize_editorial_box_markdown($two)===$two,'duplicate box labels not auto-rewritten');
check(empty(IDG_Final_Guard::validate_article_phase($two,$workflow,'generate')['ok']),'duplicate boxes not silently approved');
$example=$prefix."```markdown\n### Caja editorial\n```\n\n### Caja editorial\n\n".$box.$suffix;
check(str_contains(IDG_Workflow_Output_Parser::normalize_editorial_box_markdown($example),"```markdown\n### Caja editorial\n```"),'fenced example not reinterpreted');
$seo=$prefix."### Caja editorial\n\n".$box.$suffix."\n\nPAQUETE REEL\nVO 1: ### Caja editorial\n";
check(str_contains(IDG_Workflow_Output_Parser::normalize_editorial_box_markdown($seo,true),'VO 1: ### Caja editorial'),'SEO non-article section untouched');

$valid=<<<'REEL'
VO 1: SculptForge une escultura, retopología y bake en un flujo 3D pensado para producción real.
Overlay 1.1: Escultura + retopología
Overlay 1.2: Bake integrado
Overlay 1.3: Pipeline 3D abierto
VO 2: La interfaz recuerda a herramientas conocidas, pero su interés está en encadenar tareas.
Overlay 2.1: Familiar no es igual
Overlay 2.2: Menos saltos
Overlay 2.3: Más control del activo
VO 3: La IA suma pinceles y generación desde texto o imágenes, sin dirigir todo el proceso.
Overlay 3.1: IA como apoyo
Overlay 3.2: Pinceles
Overlay 3.3: Texto e imagen
VO 4: Blender, Maya y Unreal lo sitúan dentro de un circuito de trabajo ya habitual.
Overlay 4.1: Interoperabilidad
Overlay 4.2: Blender
Overlay 4.3: Unreal Engine
VO 5: Su valor está en preparar activos para animación y videojuegos sin romper el flujo.
Overlay 5.1: Activos listos
Overlay 5.2: Animación
Overlay 5.3: Videojuegos
VO 6: Conoce más de este proyecto en ideasDi.com
Overlay 6.1: Proyecto abierto
Overlay 6.2: Software 3D
Overlay 6.3: ideasDi.com
REEL;
check(IDG_Reel_Contract::inspect($valid)['valid'],'SculptForge original Reel valid');
check(IDG_Reel_Contract::vo_length_segments_only($valid)===[],'valid Reel never re-repaired');
$over='SculptForge une escultura, retopología y bake en un flujo 3D claramente pensado para producción real.';
$invalid=str_replace('SculptForge une escultura, retopología y bake en un flujo 3D pensado para producción real.',$over,$valid);
$inspection=IDG_Reel_Contract::inspect($invalid);
check(!empty($inspection['valid']) && $inspection['vo_counts'][1]===15,'modified VO count evaluated independently');
// A deliberately 17-word variant, without changing a protected entity, number or negation.
$over='SculptForge une escultura, retopología y bake en un flujo 3D claramente pensado para la producción real cotidiana.';
$invalid=str_replace('SculptForge une escultura, retopología y bake en un flujo 3D pensado para producción real.',$over,$valid);
$inspection=IDG_Reel_Contract::inspect($invalid);
check($inspection['vo_counts'][1]===17 && !$inspection['valid'],'17-word VO recognized');
check(IDG_Reel_Contract::vo_length_segments_only($invalid)===['VO:1'],'only VO:1 exposed for targeted repair');
$structural=str_replace('Overlay 1.1: Escultura + retopología','Broken 1.1: Escultura + retopología',$invalid);
check(IDG_Reel_Contract::vo_length_segments_only($structural)===[],'structural Reel failure does not enter length-only repair');

$doc=IDG_Editorial_Document::from_markdown($prefix.'Caja editorial' ."\n\n".$box.$suffix,$workflow);
$doc['reel']=['normalized'=>$invalid,'valid'=>false];
$workflow['editorial_document']=$doc;
$projection=IDG_Editorial_Document::projection($doc);
$pipeline=new IDG_Workflow_Redaction_Pipeline();
$reflect=new ReflectionMethod($pipeline,'attempt_targeted_reel_repair');
$patchedJson=json_encode(['patches'=>[['segment'=>'VO:1','replacement'=>'SculptForge une escultura, retopología y bake en un flujo 3D pensado para producción real.']]],JSON_UNESCAPED_UNICODE);
$client=new IDG_OpenAI_Client([['success'=>true,'content'=>$patchedJson]]);
$result=$reflect->invoke($pipeline,'test-workflow',$projection,$invalid,['VO:1'],$workflow,$client);
check(!empty($result['applied']),'targeted patch was accepted');
check(IDG_Reel_Contract::inspect($result['document']['reel']['normalized'])['valid'],'targeted repair validates full Reel');
check(IDG_Editorial_Document::markdown($result['document'])===IDG_Editorial_Document::markdown($doc),'targeted repair preserves article prose');
check($client->calls===1 && strlen($client->lastPrompt)<2500,'targeted repair uses one compact prompt');
$client=new IDG_OpenAI_Client([['success'=>true,'content'=>json_encode(['patches'=>[['segment'=>'VO:1','replacement'=>'Otro producto une escultura, retopología y bake en un flujo 3D pensado para producción real.']]])]]);
$result=$reflect->invoke($pipeline,'test-workflow',$projection,$invalid,['VO:1'],$workflow,$client);
check(empty($result['applied']),'targeted repair rejects changed protected entity');
$client=new IDG_OpenAI_Client([['success'=>false]]);
$result=$reflect->invoke($pipeline,'test-workflow',$projection,$invalid,['VO:1'],$workflow,$client);
check(empty($result['applied']) && $result['audit']['reason']==='model_unavailable','unavailable client keeps original Reel and manual path');
$client=new IDG_OpenAI_Client([['success'=>true,'content'=>json_encode(['patches'=>[['segment'=>'VO:2','replacement'=>'wrong']]])]]);
$result=$reflect->invoke($pipeline,'test-workflow',$projection,$invalid,['VO:1'],$workflow,$client);
check(empty($result['applied']),'unauthorized VO segment rejected');
echo "RESULT $passed PASS / $failed FAIL\n";
exit($failed?1:0);
