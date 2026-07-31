<?php
define('ABSPATH', __DIR__.'/');
class IDG_Traceability { public static function config_string(string $n,string $d=''):string{return $d;} public static function live_capture_cutoff():array{return ['valid'=>true,'timestamp'=>0];} public static function iso_to_timestamp(string $v):int{return strtotime($v)?:0;} public static function sync_row_reflection(array $r):void{} }
function sanitize_text_field($v){return (string)$v;} function wp_strip_all_tags($v){return strip_tags($v);} function wp_json_encode($v,$f=0){return json_encode($v,$f);} 
require_once dirname(__DIR__).'/includes/class-traceability-outbox.php';
$expected=[1=>60,2=>300,3=>900,4=>3600,5=>10800,6=>21600,7=>43200,8=>0];
foreach($expected as $attempt=>$delay){if(IDG_Traceability_Outbox::retry_delay_after_attempt($attempt)!==$delay){fwrite(STDERR,"FAIL delay $attempt\n");exit(1);}echo "OK delay $attempt\n";}
echo "PASS retry pure\n";
