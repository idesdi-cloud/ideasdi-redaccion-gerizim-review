<?php
/** Isolated, no WordPress writes or network. Run: php tests/p0-samsung-links-contract.php */
declare(strict_types=1);
define('ABSPATH','/tmp/wp/');
foreach (['mb_strtolower'=>'strtolower','mb_strlen'=>'strlen'] as $name=>$fallback) {
    if (!function_exists($name)) eval('function '.$name.'($s){return '.$fallback.'($s);}');
}
function wp_json_encode($x,$f=0){return json_encode($x,$f);}
function esc_url_raw($s){return (string)$s;}
function esc_url($s){return (string)$s;}
function esc_html($s){return htmlspecialchars((string)$s, ENT_QUOTES,'UTF-8');}
function wp_strip_all_tags($s){return strip_tags((string)$s);}
function sanitize_text_field($s){return trim(strip_tags((string)$s));}
function sanitize_title($s){return strtolower(str_replace(' ','-',remove_accents((string)$s)));}
function remove_accents($s){return strtr((string)$s,['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u']);}
function home_url($path='/'){return 'https://ideasdi.com'.$path;}
function get_category_link($id){return 'https://ideasdi.com/diseno-productos/';}
function absint($i){return abs((int)$i);}
function is_wp_error($o){return false;}
class WP_Term {public function __construct(public int $term_id,public string $taxonomy,public string $name,public string $slug){}}
function get_term($id,$tax){
    if($tax==='category' && $id===57)return new WP_Term(57,'category','Diseño de Producto','diseno-de-productos');
    if($tax==='post_tag' && $id===1521)return new WP_Term(1521,'post_tag','Tecnología','diseno-de-productos-informaticos');
    if($tax==='post_tag' && $id===9991)return new WP_Term(9991,'post_tag','Audio','disenos-de-audio');
    return null;
}
function get_term_link($term,$taxonomy){return 'https://ideasdi.com/tag/'.$term->slug.'/';}
function serialize_blocks($blocks){return json_encode($blocks);}
$root=dirname(__DIR__).'/includes/';
foreach(['class-canonical-adapter.php','class-priority-readings.php','class-internal-links.php','class-workflow-output-parser.php','class-editorial-document.php'] as $name)require_once $root.$name;
$ok=0;$fail=0;
function check($condition,$name){global $ok,$fail;if($condition){$ok++;}else{$fail++;echo "FAIL: $name\n";}}
$w=['keyword'=>'Gafas inteligentes Samsung','entity'=>'Samsung Electronics','responsible_official_url'=>'https://www.samsung.com/global/','category_id'=>57,'tag_ids'=>[1521],'primary_lens'=>'Diseño de producto'];
$md="# Gafas inteligentes Samsung\n\n## Uso diario\n\nLas gafas inteligentes Samsung se apoyan en una montura ligera.\n\nSamsung las presentó junto a Google, Gentle Monster y Warby Parker.\n\nCaja editorial\nSamsung Electronics presenta una línea con Google y varias monturas.\n\n### Forma y uso\n\nLos sensores sirven para tareas breves y navegación.";
$a=IDG_Internal_Links::audit_html('',$w);
check($a['errors']===[] && $a['no_eligible_tag']===true && count($a['warnings'])===1,'No Index does not block');
$d=IDG_Editorial_Document::from_markdown($md,$w);
$fixed=IDG_Editorial_Document::complete_missing_authorized_links($d,$w);
check(!empty($fixed['ok']),'safe official link inserted');
if(!empty($fixed['ok'])){
 $out=IDG_Editorial_Document::markdown($fixed['document']);
 check(str_contains($out,'[Samsung](https://www.samsung.com/global/) las presentó'),'abbreviated brand');
 check(!str_contains($out,'inteligentes [Samsung]('),'keyword protected');
 check(preg_replace('/\[([^\]]+)\]\(https?:\/\/[^)]+\)/','$1',$out)===$md,'no prose mutations');
 $again=IDG_Editorial_Document::complete_missing_authorized_links($fixed['document'],$w);
 check(!empty($again['ok']) && IDG_Editorial_Document::markdown($again['document'])===$out,'idempotent');
 check(count(IDG_Internal_Links::audit_html(IDG_Editorial_Document::gutenberg($fixed['document'])['html'],$w)['errors'])===0,'Gutenberg P2 audit');
}
$resolved=$w;$resolved['tag_ids']=[9991];
check(IDG_Internal_Links::audit_html('',$resolved)['resolution_status']==='resolved','real selected tag resolves');
check(count(IDG_Internal_Links::audit_html('',$resolved)['errors'])>0,'missing resolved P2 still fails');
check(count(IDG_Internal_Links::audit_html('<a href="https://ideasdi.com/tag/other/">x</a>',$w)['errors'])>0,'unauthorized internal still fails');
$invalid=$w;$invalid['category_id']=1;
check(count(IDG_Internal_Links::audit_html('',$invalid)['errors'])>0,'invalid category still fails');
$spoof=$w;$spoof['responsible_official_url']='https://samsung.evil.com/';
check(empty(IDG_Editorial_Document::complete_missing_authorized_links(IDG_Editorial_Document::from_markdown($md,$spoof),$spoof)['ok']),'lookalike domain not trusted');
echo "RESULT: $ok PASS, $fail FAIL\n";
exit($fail?1:0);
