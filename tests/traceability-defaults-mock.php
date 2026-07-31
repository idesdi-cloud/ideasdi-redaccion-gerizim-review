<?php
define('ABSPATH',__DIR__.'/');
define('IDG_TRACEABILITY_ACTION_HOOK','trace_action');
define('IDG_TRACEABILITY_RECONCILE_HOOK','trace_reconcile');
class IDG_Job_Runner { public static function get_workflow(string $id):array{return [];} public static function save_workflow(string $id,array $w):void{} }
class IDG_Traceability_Outbox { public static function install_or_upgrade():void{} public static function table_exists():bool{return true;} public static function process_queue(int $l=10):array{return [];} public static function reconcile():array{return [];} }
function add_action(...$a){} function sanitize_text_field($v){return (string)$v;} function absint($v){return abs((int)$v);} function wp_next_scheduled($h){return false;} function wp_schedule_single_event(...$a){} function wp_schedule_event(...$a){}
require_once dirname(__DIR__).'/includes/class-traceability.php';
if(IDG_Traceability::capture_enabled()){fwrite(STDERR,"FAIL capture default\n");exit(1);} if(IDG_Traceability::delivery_enabled()){fwrite(STDERR,"FAIL delivery default\n");exit(1);} if(IDG_Traceability::contract_version()!=='1.1'){fwrite(STDERR,"FAIL contract default\n");exit(1);} echo "PASS traceability defaults\n";
