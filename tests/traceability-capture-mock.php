<?php
define('ABSPATH', __DIR__ . '/');
define('IDG_TRACEABILITY_CAPTURE_ENABLED', true);
define('IDG_TRACEABILITY_DELIVERY_ENABLED', false);
define('IDG_TRACEABILITY_LIVE_CAPTURE_STARTED_AT', '2000-01-01T00:00:00Z');
define('IDG_TRACEABILITY_CONTRACT_VERSION', '1.1');
define('IDG_TRACEABILITY_ACTION_HOOK', 'idg_traceability_process_outbox');
define('IDG_TRACEABILITY_RECONCILE_HOOK', 'idg_traceability_reconcile');

class WP_Post {
    public int $ID; public string $post_type='post'; public string $post_status='pending'; public string $post_date='2026-07-05 12:00:00';
    public function __construct(int $id){$this->ID=$id;}
}
class IDG_Job_Runner {
    public static array $workflows=[];
    public static function get_workflow(string $id):array{return self::$workflows[$id]??[];}
    public static function save_workflow(string $id,array $data):void{self::$workflows[$id]=$data;}
}
class IDG_Traceability_Outbox {
    public static array $rows=[];
    public static array $failKeys=[];
    public static function insert_event(array $payload,string $status='queued',string $dependency_key='',string $error=''):array{
        $key=(string)$payload['idempotency_key'];
        if(isset(self::$failKeys[$key])) return ['success'=>false,'message'=>(string)self::$failKeys[$key]];
        if(isset(self::$rows[$key])){
            $stored=json_decode(self::$rows[$key]['payload_json'],true);$a=$stored;$b=$payload;unset($a['observed_at'],$b['observed_at']);
            if(json_encode($a)!==json_encode($b)) return ['success'=>false,'message'=>'idempotency_payload_conflict'];
            return ['success'=>true,'inserted'=>false,'row'=>self::$rows[$key]];
        }
        $row=['id'=>count(self::$rows)+1,'idempotency_key'=>$key,'event_type'=>$payload['event_type'],'payload_json'=>json_encode($payload),'status'=>$status,'dependency_key'=>$dependency_key,'last_error'=>$error,'updated_at'=>gmdate('Y-m-d H:i:s')];
        self::$rows[$key]=$row; IDG_Traceability::sync_row_reflection($row);
        return ['success'=>true,'inserted'=>true,'row'=>$row];
    }
    public static function get_by_key(string $key):?array{return self::$rows[$key]??null;}
    public static function install_or_upgrade():void{}
    public static function process_queue(int $limit=10):array{return[];}
    public static function reconcile(int $limit=50):array{return['has_more'=>false];}
    public static function schema_status():array{return['valid'=>true,'errors'=>[]];}
}
class IDG_Traceability_Recapture {
    public static array $intents=[];
    public static bool $fail=false;
    public static function record(array $p,string $s,string $d,string $e):bool{if(self::$fail)return false;self::$intents[$p['idempotency_key']]=compact('p','s','d','e');return true;}
    public static function record_unresolved_import(array $w,string $e='invalid_import_occurred_at'):bool{self::$intents['gerizim_imported:'.$w['radar_brief_id'].':'.$w['workflow_id']]=['workflow'=>$w,'error'=>$e];return true;}
    public static function clear_if_compatible(string $k,array $p,string $d=''):bool{unset(self::$intents[$k]);return true;}
    public static function clear(string $k):void{unset(self::$intents[$k]);}
    public static function count():int{return count(self::$intents);}
}
class IDG_Logger { public static array $logs=[]; public static function log(string $e,string $m,array $c=[]):void{self::$logs[]=compact('e','m','c');} }
$GLOBALS['post_meta']=[];$GLOBALS['posts']=[];$GLOBALS['options']=[];
function add_action(...$a){} function sanitize_text_field($v){return trim((string)$v);} function sanitize_key($v){return strtolower(preg_replace('/[^a-z0-9_\-]/','',(string)$v));} function absint($v){return abs((int)$v);} function get_post($id){return $GLOBALS['posts'][$id]??null;} function get_post_meta($id,$k,$s=true){return $GLOBALS['post_meta'][$id][$k]??'';} function update_post_meta($id,$k,$v){$GLOBALS['post_meta'][$id][$k]=$v;return true;} function delete_post_meta($id,$k){unset($GLOBALS['post_meta'][$id][$k]);return true;} function get_post_datetime($post,$field='date',$source='local'){if(str_starts_with($post->post_date,'0000-00-00'))return false;return new DateTimeImmutable(str_replace(' ','T',$post->post_date).'Z');} function wp_timezone(){return new DateTimeZone('America/Bogota');} function wp_generate_password($l=12,$s=true,$e=false){return str_repeat('a',$l);} function wp_next_scheduled($h){return false;} function wp_schedule_single_event(...$a){return true;} function wp_schedule_event(...$a){return true;} function get_option($k,$d=false){return $GLOBALS['options'][$k]??$d;} function update_option($k,$v,$a=false){$GLOBALS['options'][$k]=$v;return true;} function wp_json_encode($v,$f=0){return json_encode($v,$f);}
require_once dirname(__DIR__).'/includes/class-traceability.php';
function ok($c,$m){if(!$c){fwrite(STDERR,"FAIL: $m\n");exit(1);}echo "OK: $m\n";}

$wid='idg_11111111-1111-4111-8111-111111111111';
IDG_Job_Runner::$workflows[$wid]=['workflow_id'=>$wid,'radar_source'=>'radar-editorial-ideasdi','radar_brief_id'=>123,'radar_hallazgo_id'=>456,'workflow_origin'=>'radar_import','draft_post_id'=>40001,'radar_exported_at'=>'2026-07-01T08:00:00Z','radar_imported_at'=>'2026-07-05 08:00:00','radar_import_is_new'=>true];
$prov=IDG_Traceability::persist_radar_import_provenance($wid,123);
ok(!empty($prov['success'])&&!empty($prov['occurred_at']),'persistencia real guarda fecha UTC');
$original=$prov['occurred_at'];
ok($original!=='2026-07-01T08:00:00Z','radar_exported_at no se usa como fecha de importación');
$r=IDG_Traceability::capture_gerizim_imported($wid);ok(!empty($r['success']),'importación persistida genera intención');
$key='gerizim_imported:123:'.$wid;ok(isset(IDG_Traceability_Outbox::$rows[$key]),'clave gerizim_imported correcta');
$payload=json_decode(IDG_Traceability_Outbox::$rows[$key]['payload_json'],true);ok($payload['occurred_at']===$original,'occurred_at procede de radar_imported_at_utc');
IDG_Job_Runner::$workflows[$wid]['brief_fact']='contenido editorial actualizado';
$prov2=IDG_Traceability::persist_radar_import_provenance($wid,123);ok(!empty($prov2['preserved'])&&$prov2['occurred_at']===$original,'mismo brief conserva fecha original');
$r2=IDG_Traceability::capture_gerizim_imported($wid);ok(empty($r2['inserted'])&&count(IDG_Traceability_Outbox::$rows)===1,'mismo brief conserva un solo evento');

$post=new WP_Post(40001);$GLOBALS['posts'][40001]=$post;$GLOBALS['post_meta'][40001]=['_idg_workflow_id'=>$wid,'_idg_radar_brief_id'=>123,'_idg_traceability_contract_version'=>'1.1'];
$created=IDG_Traceability::capture_wordpress_post_created($wid,40001);ok(!empty($created['success']),'post pending genera wordpress_post_created');
$ckey='wordpress_post_created:'.$wid.':40001';ok(IDG_Traceability_Outbox::$rows[$ckey]['dependency_key']===$key,'creación depende de importación');
IDG_Traceability::capture_wordpress_published('publish','pending',$post);$pkey='wordpress_published:'.$wid.':40001';ok(isset(IDG_Traceability_Outbox::$rows[$pkey]),'transición real genera publicación');ok(IDG_Traceability_Outbox::$rows[$pkey]['dependency_key']===$ckey,'publicación depende de creación');

$rec='idg_44444444-4444-4444-8444-444444444444';IDG_Job_Runner::$workflows[$rec]=['workflow_id'=>$rec,'radar_source'=>'radar-editorial-ideasdi','radar_brief_id'=>124,'workflow_origin'=>'recurring_update'];
$before=count(IDG_Traceability_Outbox::$rows);$rr=IDG_Traceability::persist_radar_import_provenance($rec,124);ok(empty($rr['success']),'actualización recurrente no obtiene procedencia trazable');$rr2=IDG_Traceability::capture_gerizim_imported($rec);ok(!empty($rr2['skipped'])&&count(IDG_Traceability_Outbox::$rows)===$before,'actualización recurrente no genera outbox');

$invalid='idg_55555555-5555-4555-8555-555555555555';IDG_Job_Runner::$workflows[$invalid]=['workflow_id'=>$invalid,'radar_source'=>'radar-editorial-ideasdi','radar_brief_id'=>125,'radar_import_persisted'=>true,'radar_import_identity'=>hash('sha256','radar-editorial-ideasdi|125|'.$invalid),'radar_imported_at_utc'=>'fecha-invalida'];
$iv=IDG_Traceability::capture_gerizim_imported($invalid);ok(($iv['reason']??'')==='invalid_import_occurred_at','fecha original inválida bloquea reconstrucción');ok(isset(IDG_Traceability_Recapture::$intents['gerizim_imported:125:'.$invalid]),'fecha inválida conserva intención durable');


$historicalWid='idg_99999999-9999-4999-8999-999999999999';
IDG_Job_Runner::$workflows[$historicalWid]=['workflow_id'=>$historicalWid,'radar_source'=>'radar-editorial-ideasdi','radar_brief_id'=>129,'radar_import_persisted'=>true,'radar_import_identity'=>hash('sha256','radar-editorial-ideasdi|129|'.$historicalWid),'radar_imported_at_utc'=>'1999-12-31T23:59:59Z'];
$historical=IDG_Traceability::capture_gerizim_imported($historicalWid);
ok(!empty($historical['skipped'])&&($historical['reason']??'')==='historical_before_live_capture_cutoff','reconstrucción anterior al corte se excluye sin crear evento');
ok(!isset(IDG_Traceability_Outbox::$rows['gerizim_imported:129:'.$historicalWid]),'fecha anterior al corte no entra al outbox');

$lostWid='idg_77777777-7777-4777-8777-777777777777';$lostPostId=40002;$lostPost=new WP_Post($lostPostId);$GLOBALS['posts'][$lostPostId]=$lostPost;
IDG_Job_Runner::$workflows[$lostWid]=['workflow_id'=>$lostWid,'radar_source'=>'radar-editorial-ideasdi','radar_brief_id'=>127,'radar_import_persisted'=>true,'radar_import_identity'=>hash('sha256','radar-editorial-ideasdi|127|'.$lostWid),'radar_imported_at_utc'=>'2026-07-05T12:00:00Z','draft_post_id'=>$lostPostId];
$GLOBALS['post_meta'][$lostPostId]=['_idg_workflow_id'=>$lostWid,'_idg_radar_brief_id'=>127,'_idg_traceability_contract_version'=>'1.1'];
$lostKey='wordpress_published:'.$lostWid.':'.$lostPostId;IDG_Traceability_Outbox::$failKeys[$lostKey]='outbox_insert_failed';IDG_Traceability_Recapture::$fail=true;
IDG_Traceability::capture_wordpress_published('publish','pending',$lostPost);
$marker=$GLOBALS['post_meta'][$lostPostId]['_idg_traceability_recapture_failure']??[];
ok(($marker['idempotency_key']??'')===$lostKey&&($marker['event_type']??'')==='wordpress_published','fallo de recaptura conserva marca recuperable de publicación');
ok(!empty($marker['payload']['occurred_at'])&&($marker['payload']['wordpress_status']??'')==='publish','marca recuperable conserva payload de publicación');
ok(array_filter(IDG_Logger::$logs,fn($l)=>($l['e']??'')==='traceability_recapture_persistence_error'),'fallo de persistencia registra error operativo explícito');

echo "PASS capture mock\n";
