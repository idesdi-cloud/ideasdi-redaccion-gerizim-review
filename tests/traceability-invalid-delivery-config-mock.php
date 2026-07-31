<?php
define('ABSPATH',__DIR__.'/');
define('IDG_TRACEABILITY_CAPTURE_ENABLED',true);
define('IDG_TRACEABILITY_DELIVERY_ENABLED',true);
define('IDG_RADAR_TRACEABILITY_URL','https://radar.example.test/trace');
define('IDG_RADAR_TRACEABILITY_TOKEN', hash('sha256', __FILE__ . PHP_VERSION));
define('IDG_TRACEABILITY_CONTRACT_VERSION','1.1');
define('IDG_TRACEABILITY_ACTION_HOOK','trace_action');
define('IDG_TRACEABILITY_RECONCILE_HOOK','trace_reconcile');
class IDG_Traceability_Outbox { public static function install_or_upgrade():void{} public static function table_exists():bool{return true;} public static function process_queue(int $l=10):array{return [];} public static function reconcile():array{return [];} }
function add_action(...$a){} function sanitize_text_field($v){return (string)$v;} function absint($v){return abs((int)$v);} function wp_next_scheduled($h){return false;} function wp_schedule_single_event(...$a){} function wp_schedule_event(...$a){}
require_once dirname(__DIR__).'/includes/class-traceability.php';
if(IDG_Traceability::delivery_configuration_valid()){fwrite(STDERR,"FAIL invalid cutoff allowed delivery\n");exit(1);} echo "PASS invalid cutoff prevents delivery\n";
