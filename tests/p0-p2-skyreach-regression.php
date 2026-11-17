<?php
/** GER-EDITORIAL-2026-01: P2 Skyreach regression with real document, link resolver and guards; isolated WP taxonomy fixtures. */
declare(strict_types=1);
define('ABSPATH', '/tmp/wp');
$root = getenv('GERIZIM_SOURCE') ?: dirname(__DIR__);
class WP_Term {
    public function __construct(public int $term_id, public string $name, public string $slug, public string $taxonomy) {}
}
function get_term(int $id, string $type=''): ?WP_Term {
    $terms = [1236=>new WP_Term(1236,'Movilidad','diseno-transporte','category'),
      1227=>new WP_Term(1227,'Drones','diseno-de-drones','post_tag'),
      1549=>new WP_Term(1549,'Aeronáutico','diseno-de-transporte-aeronautico','post_tag')];
    $term=$terms[$id]??null;
    return $term && $term->taxonomy===$type ? $term : null;
}
function is_wp_error(mixed $v):bool{return false;}
function get_term_link(WP_Term|int $term,string $tax=''):string {
    $id=$term instanceof WP_Term?$term->term_id:$term;
    return match($id){1227=>'https://ideasdi.com/tag/diseno-de-drones/',1549=>'https://ideasdi.com/tag/diseno-de-transporte-aeronautico/',default=>''};
}
function get_category_link(int $id):string{return 'https://ideasdi.com/diseno-de-transporte/';}
function home_url(string $path=''):string{return 'https://ideasdi.com/'.ltrim($path,'/');}
function esc_url_raw(string $url):string{return $url;}
function esc_url(string $url):string{return $url;}
function esc_html(string $s):string{return htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function wp_json_encode(mixed $v,int $opts=0):string|false{return json_encode($v,$opts);}
function wp_strip_all_tags(string $s):string{return strip_tags($s);}
function serialize_blocks(array $b):string{return '';}
function sanitize_text_field(string $s):string{return trim(strip_tags($s));}
function absint(mixed $v):int{return abs((int)$v);}
if(!function_exists('mb_strtolower')){function mb_strtolower(string $s):string{return strtolower($s);}}
if(!function_exists('mb_strlen')){function mb_strlen(string $s):int{return iconv_strlen($s,'UTF-8');}}
if(!function_exists('mb_substr')){function mb_substr(string $s,int $a,?int $l=null):string{return (string)iconv_substr($s,$a,$l??iconv_strlen($s,'UTF-8'),'UTF-8');}}
class IDG_Priority_Readings {
    public static function tag_matrix():array {return [
      ['tag_slug'=>'diseno-de-drones','category_slug'=>'diseno-transporte','status'=>'Index / Transversal','tag'=>'Drones'],
      ['tag_slug'=>'diseno-de-transporte-aeronautico','category_slug'=>'diseno-transporte','status'=>'Index','tag'=>'Aeronáutico'],
    ];}
    public static function is_noindex_tag_name(string $n,string $c):bool{return false;}
    public static function category_curated_url(string $n):string{return 'https://ideasdi.com/diseno-de-transporte/';}
}
class IDG_Canonical_Adapter {
    public static function resolve(array $a):array {return ['primary_lens'=>trim((string)($a['primary_lens']??''))];}
}
require "$root/includes/class-internal-links.php";
require "$root/includes/class-editorial-document.php";
function req(bool $v,string $reason):void{if(!$v)throw new RuntimeException($reason);}
$w=['category_id'=>1236,'tag_ids'=>[1227,1549], 'primary_lens'=>'Drones',
    'keyword'=>'Skyways Skyreach','entity'=>'Skyways','responsible_official_url'=>'https://www.skyways.com/'];
$fixture="# Skyways Skyreach y el salto a la carga operativa\n\n## Un VTOL híbrido para mover carga sin pista\n\nSkyways Skyreach presenta una arquitectura híbrida que separa despegue y crucero.\n\nCaja editorial\nSkyreach es un dron híbrido VTOL de carga desarrollado por Skyways, con sede en Austin, Texas. Combina ocho motores eléctricos para despegue y aterrizaje con un motor de combustible pesado para crucero, usa una bahía de 7 cu ft y comenzará vuelos con clientes de defensa y comerciales en diciembre de 2026.\n\n### La arquitectura reparte el vuelo en dos funciones\n\nNo se presenta como un dron de exhibición, sino como un vehículo aéreo que separa despegue y travesía para optimizar la carga remota.\n\n### Una bahía pensada para cargar y soltar\n\nLa bahía de 7 cu ft y el acceso superior con salida inferior cambian el uso del dron tanto como el motor.\n\n### De las pruebas al uso con clientes\n\n[Skyways](https://www.skyways.com/) presenta una evolución que todavía pasa por flight testing.";
$passed=0;$total=0;
function t(string $name,Closure $cb):void{global $passed,$total;$total++;try{$cb();$passed++;echo "PASS $name\n";}catch(Throwable $e){echo "FAIL $name: ".$e->getMessage()."\n";}}
function remove_links(string $s):string{return (string)preg_replace('/\[([^\]]+)\]\(https?:\/\/[^)]+\)/u','$1',$s);}
function result(array $work,string $markdown):array{return IDG_Editorial_Document::complete_missing_authorized_links(IDG_Editorial_Document::from_markdown($markdown,$work),$work);}
t('actual canonical Drones URL resolves from WP category/tag matrix',function()use($w){
    $l=IDG_Internal_Links::normalize($w);
    req(count($l)===1,'wrong number'); req($l[0]['url']==='https://ideasdi.com/tag/diseno-de-drones/','wrong URL');
});
t('Skyreach unmodified baseline contains no internal link',function()use($fixture){req(!str_contains($fixture,'/tag/diseno-de-drones/'),'fixture already linked');});
t('Skyreach repair inserts exactly one verified contextual P2',function()use($w,$fixture){
    $r=result($w,$fixture);req($r['ok'],'could not repair: '.($r['reason']??''));
    $a=IDG_Editorial_Document::markdown($r['document']);
    req(substr_count($a,'[el uso del dron](https://ideasdi.com/tag/diseno-de-drones/)')===1,'wrong contextual anchor');
    req(substr_count($a,'https://www.skyways.com/')===1,'official URL damaged');
    req(remove_links($a)===remove_links($fixture),'prose changed');
    $html=IDG_Editorial_Document::gutenberg($r['document'])['html'];
    $audit=IDG_Internal_Links::audit_html($html,$w);
    req($audit['expected_count']===1 && $audit['internal_count']===1 && !$audit['errors'],'P2 audit failure');
});
t('repeating reconciliation is idempotent',function()use($w,$fixture){
    $r=result($w,$fixture);req($r['ok'],'first failed');
    $again=IDG_Editorial_Document::complete_missing_authorized_links($r['document'],$w);
    req($again['ok'],'second failed');req(IDG_Editorial_Document::markdown($again['document'])===IDG_Editorial_Document::markdown($r['document']),'text changed again');
});
t('ambiguous double singular candidates fail closed',function()use($w,$fixture){
    $twice=str_replace('### De las pruebas al uso con clientes',"El uso del dron también depende de la misión.\n\n### De las pruebas al uso con clientes",$fixture);
    $r=result($w,$twice);req(!$r['ok'],'ambiguous placement accepted');req(!str_contains(IDG_Editorial_Document::markdown($r['document']),'/tag/diseno-de-drones/'),'link inserted on failure');
});
t('no dron context remains unresolved',function()use($w,$fixture){
    $absent=str_replace(['dron híbrido','un dron de exhibición','uso del dron'],['aparato híbrido','un aparato de exhibición','uso del aparato'],$fixture);
    $r=result($w,$absent);req(!$r['ok'],'created unsupported anchor');
});
t('wrong canonical tag cannot use the dron inflection',function()use($w,$fixture){
    $other=$w;$other['tag_ids']=[1549];$other['primary_lens']='Aeronáutico';
    $r=result($other,$fixture);req(!$r['ok'],'inferred dron as unrelated tag');
});
t('links and feedback report real missing P2 rather than assumed success',function()use($w,$fixture){
    $d=IDG_Editorial_Document::from_markdown($fixture,$w);
    $d['feedback_notes']="- La voz editorial se conserva.\n- El enlace interno y el externo quedan asumidos como resueltos en el documento persistido.";
    $s=IDG_Editorial_Document::reconcile_seo_feedback_links($d,$w);
    $notes=$s['feedback_notes'];req(!str_contains($notes,'asumidos como resueltos'),'false claim retained');
    req(str_contains($notes,'0 internos canónicos detectados'),'missing link not disclosed');
    req(str_contains($notes,'La voz editorial se conserva'),'unrelated note lost');
});
t('links and feedback reflect successful P2 without inventing preflight approval',function()use($w,$fixture){
    $r=result($w,$fixture);req($r['ok'],'reconciliation failed');
    $d=$r['document'];$d['feedback_notes']="- El enlace interno y el externo quedan asumidos como resueltos.\n- La tesis se conserva.";
    $s=IDG_Editorial_Document::reconcile_seo_feedback_links($d,$w);
    req(str_contains($s['feedback_notes'],'1 interno canónico detectado'),'real link count lost');
    req(!str_contains($s['feedback_notes'],'preflight aprobado'),'premature approval claim');
    req(str_contains($s['feedback_notes'],'La tesis se conserva'),'good note lost');
});
echo "SUMMARY $passed/$total\n";exit($passed===$total?0:1);
