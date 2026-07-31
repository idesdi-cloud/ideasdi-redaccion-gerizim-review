<?php
define('ABSPATH',__DIR__.'/');
$GLOBALS['test_secret']='runtime-' . hash('sha256', __FILE__ . PHP_VERSION);
class IDG_Traceability { public static function config_string(string $n,string $d=''):string{return $n==='IDG_RADAR_TRACEABILITY_TOKEN'?($GLOBALS['test_secret']??''):$d;} }
$GLOBALS['options']=[];
function get_option($k,$d=false){return $GLOBALS['options'][$k]??$d;} function update_option($k,$v,$a=false){$GLOBALS['options'][$k]=$v;return true;} function current_time($t){return '2026-07-05 10:00:00';} function get_current_user_id(){return 1;} function sanitize_key($v){return strtolower(preg_replace('/[^a-z0-9_\-]/','',(string)$v));} function sanitize_text_field($v){return trim(strip_tags((string)$v));} function wp_json_encode($v){return json_encode($v);}
require_once dirname(__DIR__).'/includes/class-logger.php';
IDG_Logger::log('test','error '.$GLOBALS['test_secret'],['token'=>$GLOBALS['test_secret'],'nested'=>['authorization'=>'Bearer '.$GLOBALS['test_secret'],'safe'=>$GLOBALS['test_secret']]]);
$logs=get_option('idg_logs',[]);$encoded=json_encode($logs);
if(str_contains($encoded,$GLOBALS['test_secret'])){fwrite(STDERR,"FAIL sensitive value persisted\n");exit(1);} if(str_contains($encoded,'authorization')||str_contains($encoded,'"token"')){fwrite(STDERR,"FAIL sensitive key persisted\n");exit(1);} echo "PASS logger redaction\n";
