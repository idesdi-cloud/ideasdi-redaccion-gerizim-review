<?php
/** Isolated controller route probe. Run as separate process; WordPress redirects exit. */
declare(strict_types=1);
define('ABSPATH','/tmp/wp');
define('IDG_ACTION_HOOK','idg_run_action');
define('IDG_SESSION_KEY_PREFIX','_idg_test_');
$source = getenv('GERIZIM_SOURCE') ?: dirname(__DIR__);
$case = $argv[1] ?? 'partial_reset';
$GLOBALS['opts'] = [];
$GLOBALS['redirect'] = null;
function current_user_can(string $cap): bool { return true; }
function check_admin_referer(string $action): void {}
function admin_url(string $path): string { return 'https://ideasdi.com/wp-admin/'.$path; }
function wp_safe_redirect(string $url): void { $GLOBALS['redirect'] = $url; }
function sanitize_key(string $s): string { return strtolower(preg_replace('/[^a-z0-9_-]/i','',$s)); }
function sanitize_text_field(string $s):string { return strip_tags($s); }
function get_option(string $key, mixed $default=false):mixed { return $GLOBALS['opts'][$key]??$default; }
function update_option(string $key,mixed $value,mixed $autoload=false):bool { $GLOBALS['opts'][$key]=$value; return true; }
function add_option(string $key,mixed $value,string $deprecated='',mixed $autoload=false):bool { if(array_key_exists($key,$GLOBALS['opts']))return false;$GLOBALS['opts'][$key]=$value;return true; }
function delete_option(string $key):bool { unset($GLOBALS['opts'][$key]);return true; }
function wp_generate_uuid4():string { return 'recovery-new-token'; }
function get_current_user_id():int { return 1; }
function update_user_meta(int $u,string $k,mixed $v):bool { return true; }
function current_time(string $x):string { return gmdate('Y-m-d H:i:s'); }
function as_enqueue_async_action(string $hook,array $args=[],string $group=''):int { return 42; }
function as_get_scheduled_actions(array $q=[],string $r='OBJECT'):array { return []; }
final class IDG_Logger { public static function log(string $a,string $b,array $c=[]):void {} }
final class IDG_Sanitizer { public static function text(string $s):string { return trim($s); } }
require "$source/includes/class-workflow-policies.php";
require "$source/includes/class-job-runner.php";
require "$source/includes/class-admin-page.php";
$age = $case==='recover_old' ? 7200 : 3;
$w=['workflow_id'=>'idg_case','status'=>'processing','current_action'=>'generate',
    'processing_run_token'=>'token-old','processing_started_at'=>time()-$age,
    'base_article'=>'Original factual text','seo_result'=>'Original seo', 'draft_post_id'=>0];
$GLOBALS['opts']['idg_case']=$w;
$GLOBALS['opts']['idg_processing_run_'.hash('sha256','idg_case')]='token-old';
$_POST=['step'=>match($case){'recover_old','recover_recent'=>'recover_stale',default=>$case},
  'workflow_id'=>'idg_case','base_article'=>'MUTATED SOURCE','seo_result'=>'MUTATED SEO'];
register_shutdown_function(function(){ $w=get_option('idg_case');echo json_encode([
 'redirect'=>$GLOBALS['redirect'], 'status'=>$w['status']??'missing',
 'base_article'=>$w['base_article']??'missing','seo_result'=>$w['seo_result']??'missing',
 'history'=>count($w['history']??[]),'active_token'=>$w['processing_run_token']??'',
 ],JSON_UNESCAPED_UNICODE)."\n"; });
IDG_Admin_Page::handle_submit_workflow();
