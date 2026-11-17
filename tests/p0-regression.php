<?php
/** GER-P0 protected checks: real plugin classes, only WP/mbstring runtime stubbed. */
declare(strict_types=1);
define('ABSPATH','/tmp/wp');
define('IDG_SESSION_KEY_PREFIX','_idg_test_');
$base=getenv('GERIZIM_SOURCE') ?: dirname(__DIR__);
if (!function_exists('mb_strlen')) {function mb_strlen(string $s):int{return iconv_strlen($s,'UTF-8');}}
if (!function_exists('mb_substr')) {function mb_substr(string $s,int $a,?int $n=null):string{return (string)iconv_substr($s,$a,$n??iconv_strlen($s,'UTF-8'),'UTF-8');}}
if (!function_exists('mb_strtolower')) {function mb_strtolower(string $s):string{return strtolower($s);}}
if (!function_exists('mb_stripos')) {function mb_stripos(string $a,string $b):int|false{return stripos($a,$b);}}
function wp_strip_all_tags(string $s):string{return strip_tags($s);}
function remove_accents(string $s):string{return iconv('UTF-8','ASCII//TRANSLIT',$s) ?: $s;}
function wp_parse_url(string $s,int $part=-1):mixed{return parse_url($s,$part);}
function home_url(string $path=''):string{return 'https://ideasdi.com/'.ltrim($path,'/');}
function esc_url_raw(string $url):string{return $url;}
function sanitize_text_field(string $s):string{return trim(strip_tags($s));}
function wp_json_encode(mixed $v,int $opts=0):string|false{return json_encode($v,$opts);}
function get_current_user_id():int{return 1;}
function update_user_meta(int $user,string $key,mixed $value):bool{return true;}
function current_time(string $type):string{return '2026-11-16 21:00:00';}
function sanitize_key(string $s):string{return strtolower(preg_replace('/[^a-z0-9_-]/i','',$s));}
foreach(['includes/contracts/interface-workflow-redaction-pipeline.php','includes/class-workflow-policies.php','includes/class-reel-contract.php','includes/class-internal-links.php','includes/class-editorial-rules.php','includes/class-final-guard.php','includes/class-workflow-redaction-pipeline.php','includes/class-editorial-document.php','includes/class-job-runner.php'] as $f)require "$base/$f";
function ok(bool $v,string $why='assertion failed'):void{if(!$v)throw new RuntimeException($why);}
function invoke(string $cls,string $method,?object $obj,array $params):mixed{return (new ReflectionMethod($cls,$method))->invokeArgs($obj,$params);}
$w=['assignment_card'=>'doc','editorial_plan_raw'=>'plan','editorial_recipe_applied'=>'recipe','keyword'=>'proyecto','entity'=>'Estudio Alfa'];
function box(int $n):string{$a=explode(' ','El Estudio Alfa presenta un proyecto que lo sitúa con 12 módulos de diseño comprobados');while(count($a)<$n)$a[]='materiales';return implode(' ',array_slice($a,0,$n)).'.';}
function article(string $box):string{return "# Proyecto de Estudio Alfa\n\n## Diseño de elementos\n\nUn proyecto de diseño.\n\nCaja editorial\n$box\n\n### Características\n\nLa obra incorpora elementos.";}
$total=0;$pass=0;$fail=[];
function t(string $name,Closure $fn):void{global $total,$pass,$fail;$total++;try{$fn();$pass++;echo "PASS | $name\n";}catch(Throwable $e){$fail[]=$name;echo "FAIL | $name | ".$e->getMessage()."\n";}}
foreach([['draft','processing','generate'],['completed','processing','seo'],['failed','processing','generate']] as [$from,$to,$action])t("state $from->$to allowed",fn()=>ok(IDG_Workflow_Policies::mark_processing(['status'=>$from],$action)['status']===$to));
t('state processing->completed valid',fn()=>ok(IDG_Workflow_Policies::mark_completed(['status'=>'processing'],'generate')['status']==='completed'));
t('state processing->failed valid',fn()=>ok(IDG_Workflow_Policies::mark_failed(['status'=>'processing'],'error')['status']==='failed'));
t('state draft->completed denied',function(){$a=['status'=>'draft'];ok(IDG_Workflow_Policies::mark_completed($a,'generate')===$a);});
t('state completed->failed denied',function(){$a=['status'=>'completed'];ok(IDG_Workflow_Policies::mark_failed($a,'error')===$a);});
t('state corrupted->processing denied',function(){$a=['status'=>'corrupted'];ok(IDG_Workflow_Policies::mark_processing($a,'generate')===$a);});
t('actual Guard rejects 57 words',fn()=>ok(str_contains(implode(' ',IDG_Final_Guard::validate_article_phase(article(box(57)),$w,'generate')['errors']),'detectadas: 57')));
t('safe compaction 57->55 with actual Guard',function()use($w){$p=new IDG_Workflow_Redaction_Pipeline();$source=article(box(57));$check=IDG_Final_Guard::validate_article_phase($source,$w,'generate');$fix=invoke($p::class,'attempt_safe_box_compaction',$p,[$source,$check,$w]);ok(!empty($fix['applied']),'compaction rejected');ok(!empty(IDG_Final_Guard::validate_article_phase($fix['content'],$w,'generate')['ok']),'Guard did not revalidate');ok(str_contains($fix['content'],'indica 12')&&str_contains($fix['content'],'### Características'),'lost facts/structure');});
t('actual Guard accepts 55 words',fn()=>ok(IDG_Final_Guard::validate_article_phase(article(box(55)),$w,'generate')['ok']));
t('actual Guard rejects missing H1',fn()=>ok(!IDG_Final_Guard::validate_article_phase(str_replace('# Proyecto de Estudio Alfa','',article(box(55))),$w,'generate')['ok']));
t('actual Guard rejects missing plan',function()use($w){$w['editorial_plan_raw']='';ok(!IDG_Final_Guard::validate_article_phase(article(box(55)),$w,'generate')['ok']);});
function patch(string $old,string $new,string $segment='LINE:1',array $workflow=[]):array{$p=new IDG_Workflow_Redaction_Pipeline();return invoke($p::class,'apply_segment_patches',$p,[$old,json_encode(['patches'=>[['segment'=>$segment,'replacement'=>$new]]]),[$segment],$workflow]);}
t('valid localized wording patch accepted',fn()=>ok(patch('Línea visible con detalles de diseño','Línea visible de diseño')['ok']));
t('localized repair refuses 12->99',fn()=>ok(!patch('El proyecto incorpora 12 módulos','El proyecto incorpora 99 módulos')['ok']));
t('localized repair refuses number removal',fn()=>ok(!patch('El proyecto incorpora 12 módulos','El proyecto incorpora módulos')['ok']));
t('localized repair refuses protected name change',fn()=>ok(!patch('El Estudio Alfa presenta la obra','El Estudio Beta presenta la obra')['ok']));
t('localized repair refuses control byte',fn()=>ok(!patch('Una frase',"Otra\x01 frase")['ok']));
t('localized repair refuses out-of-scope edit',fn()=>ok(!patch('Una frase','Otra frase','LINE:2')['ok']));
t('valid internal URL equivalence',fn()=>ok(IDG_Internal_Links::equivalent_url('https://ideasdi.com/tag/diseno/','/tag/diseno/')));
t('different internal URLs not equivalent',fn()=>ok(!IDG_Internal_Links::equivalent_url('https://ideasdi.com/a/','https://ideasdi.com/b/')));
t('URL with NUL not equivalent',fn()=>ok(!IDG_Internal_Links::equivalent_url("https://ideasdi.com/a/\0",'https://ideasdi.com/a/')));
t('malformed UTF8 URL rejected',fn()=>ok(invoke(IDG_Internal_Links::class,'absolute_audit_url',null,["/tag/\xC3\x28",'https://ideasdi.com/'])===''));
t('valid URL percent-encoding preserved',fn()=>ok(invoke(IDG_Internal_Links::class,'absolute_audit_url',null,['/tag/%C3%A1/','https://ideasdi.com/'])==='https://ideasdi.com/tag/%C3%A1/'));
function reel(bool $short=false,bool $badCTA=false):string{$r=[];for($i=1;$i<=6;$i++){$words=$i===6?'Conoce más de este proyecto en ideasDi.com':implode(' ',array_fill(0,$short&&$i===3?11:14,'diseño'));if($badCTA&&$i===6)$words='Lee este proyecto en ideasDi.com';$r[]="VO $i: $words";for($j=1;$j<=3;$j++)$r[]="Overlay $i.$j: Escena $i $j";}return implode("\n",$r);}
t('real Reel accepts six valid scenes',fn()=>ok(IDG_Reel_Contract::inspect(reel())['valid']));
t('real Reel rejects 11-word VO',fn()=>ok(!IDG_Reel_Contract::inspect(reel(true))['valid']));
t('real Reel rejects wrong CTA',fn()=>ok(!IDG_Reel_Contract::inspect(reel(false,true))['valid']));
t('real Reel rejects missing scenes',fn()=>ok(!IDG_Reel_Contract::inspect('VO 1: '.implode(' ',array_fill(0,14,'diseño')))['valid']));
t('actual Gutenberg Guard fails closed without WP API',fn()=>ok(!IDG_Final_Guard::validate_gutenberg_blocks('<!-- wp:paragraph --><p>Hola</p><!-- /wp:paragraph -->')['ok']));
t('Guard classifies name integrity',fn()=>ok(IDG_Final_Guard::classify_errors(['Un enlace fragmenta un nombre propio protegido del proyecto o responsable.'])[0]==='link_name_integrity'));
t('Guard does not assume unknown error safe',fn()=>ok(IDG_Final_Guard::classify_errors(['unexpected'])[0]==='unknown'));

// Document blocks are the authentic Gerizim schema, not a simulated validator.
t('document preserves all original headings and factual box',function()use($w){$src=article(box(55));$doc=IDG_Editorial_Document::from_markdown($src,$w);ok(IDG_Editorial_Document::markdown($doc)===$src);ok(count(array_filter($doc['blocks'],fn($b)=>$b['type']==='editorial_box'))===1);});
t('document manifest checks stored block structure',function()use($w){$src=article(box(55));$doc=IDG_Editorial_Document::from_markdown($src,$w);$w['editorial_document']=$doc;ok(IDG_Editorial_Document::prompt_manifest($w,$src,'seo')['ok']);ok(!IDG_Editorial_Document::prompt_manifest($w,'fake source','seo')['ok']);});
t('SEO cannot edit editorial box through actual block patches',function()use($w){$doc=IDG_Editorial_Document::from_markdown(article(box(55)),$w);$b=current(array_filter($doc['blocks'],fn($b)=>$b['type']==='editorial_box'));$raw="PATCH {$b['id']}\nEXPECTED_SHA256: ".hash('sha256',$b['text'])."\nTEXT: Caja mutada\nEND PATCH";$o=invoke(IDG_Editorial_Document::class,'apply_patches',null,[$doc,$raw,'seo',$w]);ok(!$o['ok'] && $o['reason']==='protected_block');});
t('editorial stage cannot edit protected factual box in patches',function()use($w){$doc=IDG_Editorial_Document::from_markdown(article(box(55)),$w);$b=current(array_filter($doc['blocks'],fn($b)=>$b['type']==='editorial_box'));$raw="PATCH {$b['id']}\nEXPECTED_SHA256: ".hash('sha256',$b['text'])."\nTEXT: Caja mutada\nEND PATCH";$o=invoke(IDG_Editorial_Document::class,'apply_patches',null,[$doc,$raw,'editorial',$w]);ok(!$o['ok'] && $o['reason']==='protected_editorial_box_changed');});
t('Reel replacement does not mutate article blocks',function()use($w){$doc=IDG_Editorial_Document::from_markdown(article(box(55)),$w);$original=IDG_Editorial_Document::markdown($doc);$o=IDG_Editorial_Document::apply_reel_package($doc,reel());ok($o['ok'] && IDG_Editorial_Document::markdown($o['document'])===$original);});
t('invalid Reel does not modify article document',function()use($w){$doc=IDG_Editorial_Document::from_markdown(article(box(55)),$w);$o=IDG_Editorial_Document::apply_reel_package($doc,reel(true));ok(!$o['ok'] && $o['document']===$doc);});
t('SEO meta description modifies only SEO component',function()use($w){$doc=IDG_Editorial_Document::from_markdown(article(box(55)),$w);$meta=implode(' ',array_fill(0,18,'diseño'));$o=IDG_Editorial_Document::apply_meta_description($doc,$meta);ok($o['ok'] && IDG_Editorial_Document::markdown($o['document'])===IDG_Editorial_Document::markdown($doc));});
t('invalid SEO meta description fails without article changes',function()use($w){$doc=IDG_Editorial_Document::from_markdown(article(box(55)),$w);$o=IDG_Editorial_Document::apply_meta_description($doc,'breve');ok(!$o['ok'] && $o['document']===$doc);});
// Real runner must not start a strategy for a rejected transition.
function get_option(string $name,mixed $default=[]):mixed {return $GLOBALS['stored_workflow']??$default;}
function update_option(string $name,mixed $value,bool $autoload=false):bool {$GLOBALS['updated_workflow']=$value;return true;}
class IDG_Logger {public static function log(string $event,string $message,array $data=[]):void {$GLOBALS['logged_events'][]=$event;}}
class IDG_Workflow_Action_Strategy_Center {public static function execute(string $id,string $action,array $workflow):void {$GLOBALS['executed_strategy']=true;}}
t('job runner does not execute or save malformed-state workflows',function(){$GLOBALS['stored_workflow']=['status'=>'corrupted','workflow_id'=>'idg_test'];unset($GLOBALS['updated_workflow']);$GLOBALS['logged_events']=[];IDG_Job_Runner::process_scheduled_action('idg_test','generate');ok(!isset($GLOBALS['updated_workflow']));ok(in_array('workflow_transition_rejected',$GLOBALS['logged_events'],true));});


// Box-specific conservative factual lock: preserve substance, not just numbers.
t('box patch refuses changed material with same count',function(){$src="Caja editorial\nEl proyecto presenta 12 módulos de acero\n\n### Contexto";$out=patch($src,'El proyecto presenta 12 módulos de madera','LINE:2');ok(!$out['ok']);});
t('box patch refuses change of measurement unit',function(){$src="Caja editorial\nEl proyecto mide 12 m\n\n### Contexto";$out=patch($src,'El proyecto mide 12 cm','LINE:2');ok(!$out['ok']);});
t('box patch allows removal of connective word only',function(){$src="Caja editorial\nEl proyecto de 12 módulos de acero\n\n### Contexto";$out=patch($src,'El proyecto 12 módulos de acero','LINE:2');ok($out['ok']);});
t('box patch refuses deletion of substantial adjective',function(){$src="Caja editorial\nEl proyecto presenta 12 módulos reutilizables\n\n### Contexto";$out=patch($src,'El proyecto presenta 12 módulos','LINE:2');ok(!$out['ok']);});


// Localized fixes must retain exact workflow identities and approved link labels.
t('H1 one-word project keyword survives localized repair',fn()=>ok(!patch('# Skyreach: proyecto aéreo','# Aerobox: proyecto aéreo','LINE:1',['keyword'=>'Skyreach'])['ok']));
t('single-word responsible entity survives localized repair',fn()=>ok(!patch('Volonaut presenta un proyecto','Aerobox presenta un proyecto','LINE:1',['entity'=>'Volonaut'])['ok']));
t('localized repair does not rename approved link anchor',fn()=>ok(!patch('El proyecto [Skyreach](https://example.org) avanza','El proyecto [Aero](https://example.org) avanza')['ok']));
t('valid non-substantive wording edit retains protected project name',fn()=>ok(patch('Skyreach presenta un nuevo proyecto','Skyreach presenta un proyecto','LINE:1',['keyword'=>'Skyreach'])['ok']));
echo "SUMMARY $pass/$total passed | ".count($fail)." failed\n";exit($fail?1:0);
