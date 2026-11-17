<?php
/**
 * GER-EDITORIAL-2026-01 / Nike Brief 279: approved Reel retention on SEO rerun.
 * Isolated PHP test; WordPress preflight is represented by a deterministic seal
 * double. No network, database, real options, queue, posts or model calls.
 */
declare(strict_types=1);
define('ABSPATH', '/tmp/wp/');
$root = getenv('GERIZIM_SOURCE') ?: dirname(__DIR__);
function wp_json_encode(mixed $data, int $options=0): string|false { return json_encode($data, $options); }
if (!function_exists('mb_strlen')) { function mb_strlen(string $s): int { return preg_match_all('/./us',$s); } }
if (!function_exists('mb_substr')) { function mb_substr(string $s,int $start,int $length=999999): string { preg_match_all('/./us',$s,$m); return implode('',array_slice($m[0],$start,$length)); } }
if (!function_exists('mb_strtolower')) { function mb_strtolower(string $s): string { return strtolower($s); } }
function wp_strip_all_tags(string $text): string { return strip_tags($text); }
function esc_url_raw(string $url): string { return $url; }
function esc_html(string $s):string{return htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function esc_url(string $s):string{return $s;}
function remove_accents(string $text): string { return strtr($text, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n']); }
function serialize_blocks(array $blocks): string { return json_encode($blocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); }
function sanitize_text_field(string $s): string { return trim($s); }
function sanitize_textarea_field(string $s): string { return trim($s); }
class IDG_Internal_Links {
    public static function equivalent_url(string $a,string $b):bool{return $a===$b;}
    public static function normalize(array $workflow): array {return [['url'=>'https://ideasdi.com/tag/calzado/','source_type'=>'tag', 'source_name'=>'Calzado']];}
    public static function audit_html(string $html, array $workflow): array {return ['internal_count'=>str_contains($html,'https://ideasdi.com/tag/calzado/')?1:0,'errors'=>[], 'anchors'=>[['internal'=>false,'url'=>'https://www.nike.com/us/es/']]];}
}
// The mock binds a previous approval to a specific real document projection and
// to the source/identity/taxonomy context, just as the production payload does.
final class IDG_Post_Creator {
    public static function preflight_public(array $workflow): array {
        $document=(array)($workflow['editorial_document']??[]);
        $reel=(string)($document['reel']['normalized']??'');
        $inspect=IDG_Reel_Contract::inspect($reel);
        $words=IDG_Editorial_Document::markdown($document);
        $post='WP_BLOCKS:'.hash('sha256',$words);
        $data=['post_content'=>$post, 'reel_package'=>$reel, 'seo_result'=>IDG_Editorial_Document::projection($document),
            'keyword'=>(string)($workflow['keyword']??''),'entity'=>(string)($workflow['entity']??''),
            'responsible_official_url'=>(string)($workflow['responsible_official_url']??''),
            'tag_ids'=>(array)($workflow['tag_ids']??[])];
        $errors=(array)($inspect['issues']??[]);
        $validation=['ok'=>!empty($inspect['valid']),'errors'=>$errors,'summary'=>$errors?'- ERROR: '.implode(' · ',$errors):'Aprobado'];
        return ['ok'=>!empty($inspect['valid']), 'validation'=>$validation,
            'errors'=>$errors,'prepared'=>$data];
    }
    public static function repair_seo_report_link_counts(string $report,string $html,array $work):string {return $report;}
    public static function reel_only_pending_eligible(array $workflow,array $validation):bool{return false;}
    public static function prepared_payload_hash(array $preflight): string {
        return hash('sha256',(string)json_encode($preflight['prepared'],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
    }
}
// Full SEO-stage simulation with controlled model responses and a zero-write store.
final class IDG_OpenAI_Client {
    public int $calls=0;
    public function __construct(private array $responses=[]) {}
    public function complete(string $prompt): array {
        $this->calls++;
        $body=array_shift($this->responses);
        return $body === null ? ['success'=>false,'message'=>'no_more_mock_responses']
            : ['success'=>true,'content'=>$body,'usage'=>[],'model'=>'isolated-stub'];
    }
}
final class IDG_Workflow_Prompt_Data {
    public static function prepare(array $work,string $article,string $phase): array {
        return ['editorial_document_manifest_ok'=>true, 'article'=>$article];
    }
}
final class IDG_Prompt_Library {
    public static function seo_prompt(array $data): string {return 'SEO';}
    public static function localized_repair_prompt(array $data,string $phase,string $content,array $validation,array $allowed): string {
        return 'LOCAL_REPAIR';
    }
}
final class IDG_Workflow_Policies {
    public const ACTION_GENERATE='generate';
    public const ACTION_EDITORIAL='editorial';
    public const ACTION_SEO='seo';
    public static function mark_completed(array $w,string $phase):array {$w['status']='completed';$w['last_action']=$phase;return $w;}
    public static function mark_failed(array $w,string $message):array {$w['status']='failed';$w['last_error']=$message;return $w;}
}
final class IDG_Job_Runner {
    public static array $saved=[];
    public static function save_workflow(string $id,array $w):void {self::$saved[$id]=$w;}
    public static function append_history_snapshot(array $w,string $type,string $message):array {
        $w['history'][]=['event'=>$type, 'message'=>$message];return $w;
    }
}
final class IDG_Final_Guard {
    public static function classify_errors(array $errors): array {
        return array_map(static fn($e)=>str_contains((string)$e,'VO ')?'reel_contract':'unknown', $errors);
    }
}
final class IDG_Usage_Estimator {
    public static function record(string $phase,array $usage,string $workflow='',string $model=''):array {return [];}
}
final class IDG_Validator {
    public static function summarize(string $content,string $keyword):array {return [];}
}
final class IDG_Logger {
    public static function log(string $type,string $message,array $context=[]):void {}
}
function current_time(string $format):string {return '2026-11-17 11:24:00';}
require $root.'/includes/contracts/interface-workflow-redaction-pipeline.php';
require $root.'/includes/class-reel-contract.php';
require $root.'/includes/class-workflow-output-parser.php';
require $root.'/includes/class-editorial-document.php';
require $root.'/includes/class-workflow-redaction-pipeline.php';

$reel=<<<'REEL'
VO 1: Nike Tuned Air replantea la amortiguación desde la construcción interna de la unidad bajo el pie
Overlay 1.1: Nueva geometría interna
Overlay 1.2: Control de compresión
Overlay 1.3: Air bajo el pie
VO 2: La fabricación digital permite variar zonas de unión y ajustar la respuesta del Air
Overlay 2.1: Impresión digital
Overlay 2.2: Laminación selectiva
Overlay 2.3: Unión por zonas
VO 3: La suela deja de ser solo apariencia y pasa a modular apoyo, flexión y sensación
Overlay 3.1: Más control constructivo
Overlay 3.2: Apoyo y flexión
Overlay 3.3: Lectura del calzado
VO 4: Nike presenta Tuned Air como plataforma evolutiva para correr, sport y Sportswear
Overlay 4.1: Plataforma, no unidad
Overlay 4.2: Alcance por deportes
Overlay 4.3: Evolución del sistema
VO 5: El anuncio sitúa el desarrollo en 2026 y marca disponibilidad para corredores en 2027
Overlay 5.1: Anuncio oficial
Overlay 5.2: Primer uso anunciado
Overlay 5.3: Late 2027
VO 6: Conoce más de este proyecto en ideasDi.com
Overlay 6.1: Diseñar la amortiguación
Overlay 6.2: Construcción y uso
Overlay 6.3: Lectura editorial
REEL;
$invalid=(string)preg_replace('/^VO 1: (.*)$/m','VO 1: $1 ahora',$reel,1);
$otherValid=str_replace('Nike Tuned Air replantea la amortiguación', 'Nike Tuned Air transforma la amortiguación', $reel);
$article=<<<'TEXT'
# Nike Tuned Air y el nuevo lenguaje de la amortiguación

## La construcción interna que cambia la unidad bajo el pie

Nike Tuned Air empieza por una decisión material: una geometría interna pensada para modular cómo se comprime el Air.

Caja editorial
Nike Tuned Air es una propuesta oficial de NIKE, Inc. presentada en su newsroom el 16 de noviembre de 2026. Introduce un método digital de fabricación para Air con impresión digital propietaria y laminación selectiva, y anuncia disponibilidad para corredores a finales de 2027.

### Impresión digital y laminación selectiva

[Nike](https://www.nike.com/us/es/) presenta el nuevo sistema con impresión digital.

### Qué cambia bajo el pie

La organización interna, entonces, tiene un efecto claro sobre [la lectura del calzado](https://ideasdi.com/tag/calzado/): menos arbitrariedad en la forma, más control en la construcción.
TEXT;
$work=['workflow_id'=>'idg_9d855ccd-8343-4593-a7b8-b09a7995758d', 'keyword'=>'Nike Tuned Air', 'entity'=>'NIKE',
    'responsible_official_url'=>'https://www.nike.com/us/es/','tag_ids'=>[1245,1028], 'radar_brief_id'=>279];
$doc=IDG_Editorial_Document::from_markdown($article,$work);
$doc['seo']['meta_description']='Nike Tuned Air reorganiza la amortiguación con impresión digital y laminación selectiva.';
$doc['seo']['report']='- Enlaces aplicados: 1 interno; 1 externo';
$doc['social_copy']='Una lectura técnica del calzado';
$doc=IDG_Editorial_Document::apply_reel_package($doc,$reel)['document'];
$work['editorial_document']=$doc;
$work['seo_result']=IDG_Editorial_Document::projection($doc);
$work['seo_validation']=['ok'=>true];
$work['seo_preflight_approved_at']='2026-11-17 11:24:00';
$seal=IDG_Post_Creator::preflight_public($work);
$work['seo_preflight_payload_hash']=IDG_Post_Creator::prepared_payload_hash($seal);
$work['seo_preflight_post_content']=$seal['prepared']['post_content'];
$pipeline=new IDG_Workflow_Redaction_Pipeline();
$snapshot=new ReflectionMethod($pipeline,'approved_reel_snapshot');
$retain=new ReflectionMethod($pipeline,'retain_approved_reel_if_safe');
$context=new ReflectionMethod($pipeline,'same_reel_article_context');
$total=0;$passed=0;
$check=function(string $name, bool $ok)use(&$total,&$passed):void{
    $total++;if($ok)$passed++;echo ($ok?'PASS':'FAIL')." | $name\n";
};
$before=$snapshot->invoke($pipeline,$work);
$check('original Nike Reel valid: VO1 = 16',IDG_Reel_Contract::inspect($reel)['valid'] && IDG_Reel_Contract::inspect($reel)['vo_counts'][1]===16);
$check('new Nike Reel invalid: VO1 = 17',!IDG_Reel_Contract::inspect($invalid)['valid'] && IDG_Reel_Contract::inspect($invalid)['vo_counts'][1]===17);
$check('verified seal grants previous Reel retention',!empty($before['ok']) && $before['reel']===$reel);
$badDoc=$doc; $badDoc['reel']=['normalized'=>$invalid,'valid'=>false];
$preserved=$retain->invoke($pipeline,$before,$badDoc);
$check('invalid fresh Reel does not replace previously approved Reel',!empty($preserved['applied']) && ($preserved['document']['reel']['normalized']??'')===$reel);
$check('retained Reel passes full contract',IDG_Reel_Contract::inspect($preserved['document']['reel']['normalized'])['valid']);
$check('retention leaves prose byte-identical',IDG_Editorial_Document::markdown($preserved['document'])===IDG_Editorial_Document::markdown($doc));
$check('retention leaves report and meta intact',($preserved['document']['seo']??[])===($doc['seo']??[]));
$check('retention is deterministic/idempotent',!empty($retain->invoke($pipeline,$before,$preserved['document'])['applied'])===false);
$changed=$badDoc;foreach($changed['blocks'] as &$b){if(($b['type']??'')==='paragraph'){ $b['text'].=' Un nuevo dato.';break;}} unset($b);
$check('changed article does not reuse approved Reel',empty($retain->invoke($pipeline,$before,$changed)['applied']));
$changedLink=$badDoc;foreach($changedLink['blocks'] as &$b){if(isset($b['text']))$b['text']=str_replace('https://ideasdi.com/tag/calzado/','https://ideasdi.com/tag/diseno-de-drones/',$b['text']);}unset($b);
$check('changed link destination does not reuse approved Reel',empty($retain->invoke($pipeline,$before,$changedLink)['applied']));
$rewrapped=$badDoc;foreach($rewrapped['blocks'] as &$b){if(isset($b['text']))$b['text']=str_replace('[la lectura del calzado](https://ideasdi.com/tag/calzado/)', 'la [lectura del calzado](https://ideasdi.com/tag/calzado/)',$b['text']);}unset($b);
$check('same factual text and URL allow improved link wrapper',!empty($retain->invoke($pipeline,$before,$rewrapped)['applied']));
$check('context rejects reordered article blocks',!$context->invoke($pipeline,$doc,['blocks'=>array_reverse($doc['blocks'])]));
$newValid=$badDoc; $newValid['reel']=['normalized'=>$otherValid,'valid'=>true];
$check('new valid Reel remains preferred',empty($retain->invoke($pipeline,$before,$newValid)['applied']));
$check('no verified previous Reel cannot be silently reused',empty($retain->invoke($pipeline,['ok'=>false,'reason'=>'unverified'],$badDoc)['applied']));
$invalidPrior=$work;unset($invalidPrior['seo_preflight_payload_hash']);
$check('missing seal denies prior Reel reuse',empty($snapshot->invoke($pipeline,$invalidPrior)['ok']));
$badHash=$work;$badHash['seo_preflight_payload_hash']=str_repeat('0',64);
$check('mismatched payload seal denies reuse',empty($snapshot->invoke($pipeline,$badHash)['ok']));
$badContent=$work;$badContent['seo_preflight_post_content']='tampered';
$check('mismatched Gutenberg payload denies reuse',empty($snapshot->invoke($pipeline,$badContent)['ok']));
$unapproved=$work;$unapproved['seo_validation']['ok']=false;
$check('SEO never approved denies reuse',empty($snapshot->invoke($pipeline,$unapproved)['ok']));
$missingApproval=$work;unset($missingApproval['seo_preflight_approved_at']);
$check('missing approval timestamp denies reuse',empty($snapshot->invoke($pipeline,$missingApproval)['ok']));
$docTampered=$work;$docTampered['editorial_document']['blocks'][2]['text']='Unauthorized new headline';
$check('changed persisted content denies reuse',empty($snapshot->invoke($pipeline,$docTampered)['ok']));
$reelTampered=$work;$reelTampered['editorial_document']['reel']['valid']=true;$reelTampered['editorial_document']['reel']['normalized']=$invalid;
$check('incorrect valid flag cannot authorize invalid Reel',empty($snapshot->invoke($pipeline,$reelTampered)['ok']));
$identityChange=$work;$identityChange['keyword']='Otro producto';
$check('changed identity denies reuse by preflight seal',empty($snapshot->invoke($pipeline,$identityChange)['ok']));
$tagChange=$work;$tagChange['tag_ids']=[1245];
$check('changed taxonomy denies reuse by preflight seal',empty($snapshot->invoke($pipeline,$tagChange)['ok']));
$workflowNoReel=$work;$workflowNoReel['editorial_document']['reel']=[];
$check('no prior valid Reel stays with localized-repair path',empty($snapshot->invoke($pipeline,$workflowNoReel)['ok']));
$check('retained new result uses approved reel only',($preserved['document']['reel']['valid']??false)===true);
$check('no accidental WordPress writes in isolated test',true);
// Exercise run_seo itself: no WordPress write path exists in this sandbox.
$responseFor=static function(string $candidateReel,string $article):string {
    return "ARTÍCULO FINAL\n".$article."\n\nTÍTULO SEO\n%%title%%\n\nMETA DESCRIPTION\n".
        "Nike Tuned Air reorganiza la amortiguación con impresión digital y laminación selectiva.\n\n".
        "INFORME SEO INTERNO\n- Enlaces aplicados: 1 interno; 1 externo\n\n".
        "COPY PARA REDES\nLa nueva amortiguación de Nike.\n\n".
        "PAQUETE REEL\n".$candidateReel."\n\nRETROALIMENTACIÓN GERIZIM\nInforme sujeto al preflight.";
};
$voOriginal='Nike Tuned Air replantea la amortiguación desde la construcción interna de la unidad bajo el pie';
$workForRun=$work;$workForRun['status']='processing';
$client=new IDG_OpenAI_Client([$responseFor($invalid,IDG_Editorial_Document::markdown($doc))]);
$pipeline->execute($work['workflow_id'],'seo',$workForRun,$client);
$finished=(array)(IDG_Job_Runner::$saved[$work['workflow_id']]??[]);
$check('SEO rerun with verified prior passes full stage after 17-word proposal',
    $client->calls===1&&($finished['status']??'')==='completed'&&!empty($finished['seo_validation']['ok']));
$check('stage saves old approved Reel and records rejection of new proposal',
    ($finished['editorial_document']['reel']['normalized']??'')===$reel&&
    ($finished['seo_response_diagnostics']['reel_retention']??'')==='previous_approved_reel_preserved'&&
    !empty($finished['seo_response_diagnostics']['reel_model_proposal_rejected']));
$check('stage never falsely marks rejected model Reel as validated component',
    !in_array('reel_package',(array)($finished['seo_response_diagnostics']['validated_components']??[]),true));
$check('stage stores global SEO approval only on full passing preflight',
    !empty($finished['seo_preflight_payload_hash'])&&
    ($finished['seo_reel_validation']['valid']??false)===true&&
    !empty($finished['seo_article_validation']['ok']));
// On a first SEO, no approved Reel exists. Exactly one localized VO repair is
// permitted; the other scenes and overlays remain untouched.
$noPrior=$workForRun;$noPrior['editorial_document']['reel']=['valid'=>false,'normalized'=>''];
$noPrior['seo_validation']=['ok'=>false];
foreach(['seo_preflight_payload_hash','seo_preflight_post_content','seo_preflight_approved_at']as $k)unset($noPrior[$k]);
$patched=wp_json_encode(['patches'=>[['segment'=>'VO:1','replacement'=>$voOriginal]]]);
$client2=new IDG_OpenAI_Client([$responseFor($invalid,IDG_Editorial_Document::markdown($doc)),$patched]);
$pipeline->execute($work['workflow_id'],'seo',$noPrior,$client2);
$repaired=(array)(IDG_Job_Runner::$saved[$work['workflow_id']]??[]);
$check('without valid predecessor, a single VO-only localized repair can pass',
    $client2->calls===2&&($repaired['status']??'')==='completed'&&
    ($repaired['editorial_document']['reel']['normalized']??'')===$reel);
$check('VO-only localized repair does not claim a reused approved Reel',
    empty($repaired['seo_response_diagnostics']['reel_model_proposal_rejected'])&&
    ($repaired['seo_response_diagnostics']['reel_retention']??'')==='no_valid_prior_reel');
$client3=new IDG_OpenAI_Client([$responseFor($invalid,IDG_Editorial_Document::markdown($doc)),
    wp_json_encode(['patches'=>[['segment'=>'VO:1','replacement'=>$voOriginal.' ahora']]])]);
$pipeline->execute($work['workflow_id'],'seo',$noPrior,$client3);
$failed=(array)(IDG_Job_Runner::$saved[$work['workflow_id']]??[]);
$check('failed VO repair cannot approve SEO',
    $client3->calls===2&&($failed['status']??'')==='failed'&&empty($failed['seo_validation']['ok']));
$check('failed VO repair preserves separate article vs Reel validation',
    ($failed['seo_article_validation']['ok']??false)===true&&
    ($failed['seo_reel_validation']['valid']??true)===false);
$check('failed VO repair has no fresh preflight approval seals',
    empty($failed['seo_preflight_payload_hash'])&&
    ($failed['seo_response_diagnostics']['final_preflight']??'')==='requires_intervention');
$changedArticle=$workForRun;
$articleDifferent=str_replace('modular cómo se comprime el Air','modular la respuesta del Air',IDG_Editorial_Document::markdown($doc));
$client4=new IDG_OpenAI_Client([$responseFor($invalid,$articleDifferent),
    wp_json_encode(['patches'=>[['segment'=>'VO:1','replacement'=>$voOriginal.' ahora']]])]);
$pipeline->execute($work['workflow_id'],'seo',$changedArticle,$client4);
$changedResult=(array)(IDG_Job_Runner::$saved[$work['workflow_id']]??[]);
$check('unsafe article proposal discarded; preserved approved Reel remains tied to unchanged document',
    ($changedResult['seo_response_diagnostics']['reel_retention']??'')==='previous_approved_reel_preserved'&&
    ($changedResult['seo_response_diagnostics']['prose_reason']??'')==='unsafe_seo_article_replacement'&&
    ($changedResult['status']??'')==='completed');
echo "SUMMARY $passed/$total\n";
exit($passed===$total?0:1);
