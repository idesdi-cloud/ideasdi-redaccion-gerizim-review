<?php
define('ABSPATH', __DIR__ . '/');
class IDG_Traceability {
    public static string $url='https://radar.example.test/trace';
    public static function config_string(string $n,string $d=''): string { return $n==='IDG_RADAR_TRACEABILITY_URL'?self::$url:($n==='IDG_RADAR_TRACEABILITY_TOKEN'?($GLOBALS['test_secret'] ?? ''):$d); }
    public static function validate_radar_url(string $url): array { return str_starts_with($url,'https://') || str_starts_with($url,'http://localhost') ? ['valid'=>true,'reason'=>''] : ['valid'=>false,'reason'=>'insecure_traceability_url']; }
}
class WP_Error { public function __construct(private string $m){} public function get_error_message(){return $this->m;} }
$GLOBALS['scenario']='201-result';
$GLOBALS['test_secret']='runtime-' . hash('sha256', __FILE__ . PHP_VERSION);
function sanitize_key($v){ return strtolower(preg_replace('/[^a-z0-9_\-]/','',(string)$v)); }
function sanitize_text_field($v){ return trim((string)$v); }
function wp_json_encode($v,$flags=0){ return json_encode($v,$flags); }
function wp_strip_all_tags($v){ return strip_tags($v); }
function wp_remote_post($url,$args){
    $s=$GLOBALS['scenario'];
    if($s==='network') return new WP_Error('timeout ' . $GLOBALS['test_secret']);
    $key='gerizim_imported:123:idg_test';
    $map=[
        '201-result'=>[201,['result'=>'traceability_event_recorded','idempotency_key'=>$key]],
        '200-result'=>[200,['result'=>'traceability_event_already_recorded','idempotency_key'=>$key]],
        '201-code'=>[201,['code'=>'traceability_event_recorded','idempotency_key'=>$key]],
        'wrong-key'=>[201,['result'=>'traceability_event_recorded','idempotency_key'=>'other-key']],
        'missing-key'=>[201,['result'=>'traceability_event_recorded']],
        '400'=>[400,['result'=>'validation_failed']],
        '401'=>[401,['result'=>'unauthorized']],
        '403'=>[403,['result'=>'forbidden']],
        '409'=>[409,['result'=>'payload_conflict']],
        '429'=>[429,['result'=>'rate_limited']],
        '500'=>[500,['result'=>'server_error']],
    ];
    [$code,$body]=$map[$s];
    return ['response'=>['code'=>$code],'body'=>json_encode($body),'headers'=>[]];
}
function is_wp_error($v){ return $v instanceof WP_Error; }
function wp_remote_retrieve_response_code($r){ return $r['response']['code']; }
function wp_remote_retrieve_body($r){ return $r['body']; }
require_once dirname(__DIR__).'/includes/class-traceability-client.php';
function ok($c,$m){if(!$c){fwrite(STDERR,"FAIL: $m\n");exit(1);}echo "OK: $m\n";}
$c=new IDG_Traceability_Client();
$payload=['event_type'=>'gerizim_imported','idempotency_key'=>'gerizim_imported:123:idg_test'];
foreach(['201-result'=>[true,false,'result'],'200-result'=>[true,false,'result'],'201-code'=>[true,false,'code'],'wrong-key'=>[false,false,''],'missing-key'=>[false,false,''],'400'=>[false,false,''],'401'=>[false,false,''],'403'=>[false,false,''],'409'=>[false,false,''],'429'=>[false,true,''],'500'=>[false,true,''],'network'=>[false,true,'']] as $scenario=>$expect){
 $GLOBALS['scenario']=$scenario; $r=$c->send($payload);
 ok((bool)$expect[0]===!empty($r['success']),"escenario $scenario clasifica éxito");
 ok((bool)$expect[1]===(bool)($r['retry']??false),"escenario $scenario clasifica retry");
 if($expect[2]!=='') ok(($r['response_field']??'')===$expect[2],"escenario $scenario registra campo de respuesta");
 if(in_array($scenario,['wrong-key','missing-key'],true)) ok(($r['code']??'')==='response_idempotency_key_mismatch',"escenario $scenario rechaza clave de respuesta");
 if($scenario==='network') ok(!str_contains($r['message'],$GLOBALS['test_secret']),'token redactado de error');
}
IDG_Traceability::$url='http://radar.example.test/trace';
$r=$c->send($payload);
ok(($r['code']??'')==='insecure_traceability_url','HTTP productivo se rechaza antes de petición');
echo "PASS client mock\n";
