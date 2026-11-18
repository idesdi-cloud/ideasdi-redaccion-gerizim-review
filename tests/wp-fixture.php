<?php
declare(strict_types=1);
define('ABSPATH','/tmp/wp/');
function mb_strlen($s,$enc='UTF-8'){ return iconv_strlen((string)$s,$enc); }
function mb_substr($s,$start,$length=null,$enc='UTF-8'){ return iconv_substr((string)$s,(int)$start,$length??iconv_strlen((string)$s,$enc),$enc); }
function mb_strtolower($s,$enc='UTF-8'){return strtolower(strtr((string)$s,['Á'=>'á','É'=>'é','Í'=>'í','Ó'=>'ó','Ú'=>'ú','Ü'=>'ü','Ñ'=>'ñ']));}
function mb_strtoupper($s,$enc='UTF-8'){return strtoupper((string)$s);}
function mb_stripos($haystack,$needle,$offset=0,$enc='UTF-8'){return stripos(remove_accents($haystack),remove_accents($needle),$offset);}
function mb_strpos($haystack,$needle,$offset=0,$enc='UTF-8'){return strpos($haystack,$needle,$offset);}
function remove_accents($s){return strtr((string)$s,['Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U','Ñ'=>'N','á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);}
function wp_json_encode($a,$opts=0){return json_encode($a,$opts);}
function wp_strip_all_tags($s){return strip_tags((string)$s);}
function esc_html($s){return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function esc_url($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function esc_url_raw($s){return trim((string)$s);}
function absint($x){return abs((int)$x);}
function sanitize_text_field($s){return trim((string)$s);}
function sanitize_textarea_field($s){return trim((string)$s);}
function wp_parse_url($s,$component=null){return parse_url((string)$s,$component);}
function wp_kses_post($s){return $s;}
function home_url($s='/'){return 'https://ideasdi.com'. $s;}
function is_wp_error($term){return false;}
function get_option($name,$default=null){return $default;}
function sanitize_title($s){return strtolower(preg_replace('/[^a-z0-9-]+/','-',remove_accents((string)$s)));}
class WP_Term {public function __construct(public int $term_id,public string $name,public string $slug,public string $taxonomy){} }
function get_term($id,$tax='post_tag'){
 $d=[1236=>['Movilidad','diseno-transporte','category'],1587=>['Vehículos eléctricos','vehiculos-electricos','post_tag'],1246=>['Diseño automotriz','automovil','post_tag'],57=>['Diseño de Producto','diseno-de-productos','category'],1238=>['Moda','diseno-de-indumentaria','category'],1232=>['Diseño digital','modelado-3d','category'],1231=>['Arquitectura y diseño interior','diseno-interior','category'],34=>['Concursos y convocatorias','concursos-de-diseno','category'],1239=>['Drones','diseno-de-drones','post_tag'],25001=>['Calzado','calzado','post_tag'],25002=>['IA generativa','ia-generativa','post_tag'],1521=>['Tecnología','tecnologia-informatica','post_tag'],1589=>['Modelado 3D','modelado-3d','post_tag'],1248=>['Software 3D','software-3d','post_tag'],1549=>['Aeronáutico','diseno-de-transporte-aeronautico','post_tag']];
 if(!isset($d[$id])||$d[$id][2]!==$tax)return null;return new WP_Term((int)$id,...$d[$id]);
}
function get_term_link($term,$taxonomy='post_tag'){if(is_object($term))return 'https://ideasdi.com/tag/'.$term->slug.'/'; $t=get_term((int)$term,$taxonomy);return $t?'https://ideasdi.com/tag/'.$t->slug.'/':'';}
function get_category_link($id){return 'https://ideasdi.com/diseno-de-transporte/';}
$GLOBALS['block_roundtrip']=[];
function serialize_blocks($blocks){$o='';foreach($blocks as $b){$n=$b['blockName'];$attrs=$b['attrs']??[];$attrsText=$attrs?' '.json_encode($attrs,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):'';$o.='<!-- wp:'.substr($n,5).$attrsText.' -->'.$b['innerHTML'].'<!-- /wp:'.substr($n,5).' -->\n';}$GLOBALS['block_roundtrip'][trim($o)]=$blocks;return trim($o);}
function parse_blocks($str){return $GLOBALS['block_roundtrip'][trim($str)]??[];}
$root=dirname(__DIR__).'/includes/';
foreach(['class-canonical-adapter.php','class-priority-readings.php','class-workflow-output-parser.php','class-internal-links.php','class-reel-contract.php','class-editorial-document.php','class-canonical-context.php','class-final-guard.php','class-post-creator.php'] as $p)require_once $root.$p;
