<?php
/** P0 recovery regression: authentic policies + runner with isolated WordPress/queue stubs. */
declare(strict_types=1);
define('ABSPATH', '/tmp/wp');
define('IDG_ACTION_HOOK', 'idg_run_action');
define('IDG_SESSION_KEY_PREFIX', '_idg_test_');
$root = getenv('GERIZIM_SOURCE') ?: dirname(__DIR__);
$GLOBALS['opts'] = []; $GLOBALS['calls'] = []; $GLOBALS['logs'] = [];
$GLOBALS['queue_return'] = 23; $GLOBALS['queue_state'] = [];
$GLOBALS['mode'] = 'complete'; $GLOBALS['clock'] = time(); $GLOBALS['id_counter'] = 0; $GLOBALS['queue_requests'] = []; $GLOBALS['queue_keys'] = [];
function get_option(string $k, mixed $default = false): mixed { return $GLOBALS['opts'][$k] ?? $default; }
function update_option(string $k, mixed $v, mixed $autoload=false): bool { $GLOBALS['opts'][$k] = $v; return true; }
function add_option(string $k, mixed $v, string $deprecated='', mixed $autoload=false): bool { if(array_key_exists($k,$GLOBALS['opts']))return false; $GLOBALS['opts'][$k]=$v; return true; }
function delete_option(string $k): bool { if(!array_key_exists($k,$GLOBALS['opts']))return false; unset($GLOBALS['opts'][$k]);return true; }
function wp_generate_uuid4(): string { return 'unique-token-'.(++$GLOBALS['id_counter']); }
function current_time(string $x): string { return gmdate('Y-m-d H:i:s'); }
function sanitize_key(string $x): string { return strtolower(preg_replace('/[^a-z0-9_-]/i','',$x)); }
function sanitize_text_field(string $x): string { return strip_tags($x); }
function get_current_user_id(): int { return 1; }
function update_user_meta(int $user, string $key, mixed $value): bool { return true; }
function as_enqueue_async_action(string $hook, array $args=[], string $group='', bool $unique=false): int|false {
    $key = json_encode([$hook, $args, $group]);
    $GLOBALS['queue_requests'][] = ['key'=>$key, 'unique'=>$unique];
    if ($unique && isset($GLOBALS['queue_keys'][$key])) return 0;
    $id=$GLOBALS['queue_return'];
    if ($id && $id > 0) $GLOBALS['queue_keys'][$key]=$id;
    return $id;
}
function as_get_scheduled_actions(array $q=[], string $ret='OBJECT'): array { return $GLOBALS['queue_state'][$q['status'] ?? ''] ?? []; }
final class IDG_Logger { public static function log(string $type, string $msg, array $data=[]): void { $GLOBALS['logs'][]=[$type,$msg,$data]; } }
require "$root/includes/class-workflow-policies.php";
require "$root/includes/class-job-runner.php";
final class IDG_Workflow_Action_Strategy_Center {
    public static function execute(string $id, string $action, array $workflow): bool {
        $GLOBALS['calls'][]=[$id,$action,$workflow];
        if ($GLOBALS['mode']==='throw') throw new RuntimeException('simulated failure');
        if ($GLOBALS['mode']==='nested' && count($GLOBALS['calls'])<3) IDG_Job_Runner::process_scheduled_action($id,$action);
        if ($GLOBALS['mode']==='silent') return true;
        IDG_Job_Runner::save_workflow($id,IDG_Workflow_Policies::mark_completed($workflow,$action));
        return true;
    }
}
function fresh(): void { $GLOBALS['opts']=[];$GLOBALS['calls']=[];$GLOBALS['logs']=[];$GLOBALS['queue_return']=23;$GLOBALS['queue_state']=[];$GLOBALS['mode']='complete'; $GLOBALS['queue_requests']=[]; $GLOBALS['queue_keys']=[]; }
function seed(string $id='idg_test', array $v=[]): array { $w=array_replace(['workflow_id'=>$id,'status'=>'draft','base_article'=>'safe content','seo_result'=>'safe seo','current_action'=>''],$v); IDG_Job_Runner::save_workflow($id,$w);return $w; }
function assert_true(bool $test,string $msg):void { if(!$test)throw new RuntimeException($msg); }
$total=0;$passed=0;$fail=[];
function t(string $name,Closure $fn):void { global $total,$passed,$fail; $total++; fresh();try{$fn();$passed++;echo "PASS $name\n";}catch(Throwable $e){$fail[]=$name; echo "FAIL $name | ".$e->getMessage()."\n";} }
function lock_key(string $id):string{return 'idg_processing_run_'.hash('sha256',$id);}
function old_processing(string $id='idg_test',string $action='generate',int $age=7200):array{
    $w=seed($id,['status'=>'processing','current_action'=>$action,'processing_started_at'=>time()-$age,'processing_run_token'=>'old-token']);
    add_option(lock_key($id),'old-token','',false);
    return $w;
}
t('known valid queued action id accepted',function(){seed();assert_true(IDG_Job_Runner::schedule('idg_test','generate')===true,'schedule failed');});
t('queue failure must not claim scheduled success',function(){seed();$GLOBALS['queue_return']=0;assert_true(IDG_Job_Runner::schedule('idg_test','generate')===false,'silent AS scheduling false-positive');});
t('unrecognized action cannot be queued',function(){seed();assert_true(IDG_Job_Runner::schedule('idg_test','evil')===false,'unknown queued');});
t('normal generate executes once and completes',function(){seed();IDG_Job_Runner::process_scheduled_action('idg_test','generate');$w=IDG_Job_Runner::get_workflow('idg_test');assert_true($w['status']==='completed' && count($GLOBALS['calls'])===1,'not completed exactly once');assert_true(!empty($w['processing_run_token']) && !empty($w['processing_started_at']),'missing run evidence');});
t('nested duplicate cannot dispatch second time',function(){seed();$GLOBALS['mode']='nested';IDG_Job_Runner::process_scheduled_action('idg_test','generate');assert_true(count($GLOBALS['calls'])===1,'nested job duplicate');});
t('old processing cannot be redispatched automatically',function(){old_processing();IDG_Job_Runner::process_scheduled_action('idg_test','generate');assert_true(count($GLOBALS['calls'])===0,'duplicate dispatched');});
t('unknown action cannot mutate workflow',function(){$w=seed();IDG_Job_Runner::process_scheduled_action('idg_test','garbage');assert_true(IDG_Job_Runner::get_workflow('idg_test')===$w,'unknown mutated data');});
t('corrupted status cannot start',function(){$w=seed('idg_test',['status'=>'impossible']);IDG_Job_Runner::process_scheduled_action('idg_test','generate');assert_true(count($GLOBALS['calls'])===0&&IDG_Job_Runner::get_workflow('idg_test')===$w,'corruption was dispatched');});
t('strategy throws => failed but preserves article',function(){seed();$GLOBALS['mode']='throw';IDG_Job_Runner::process_scheduled_action('idg_test','generate');$w=IDG_Job_Runner::get_workflow('idg_test');assert_true($w['status']==='failed' && $w['base_article']==='safe content','failure not preserved');});
t('silent strategy fails visibly rather than indefinite processing',function(){seed();$GLOBALS['mode']='silent';IDG_Job_Runner::process_scheduled_action('idg_test','generate');assert_true(IDG_Job_Runner::get_workflow('idg_test')['status']==='failed','stuck processing');});
t('fresh processing recovery denied',function(){old_processing('idg_test','generate',30);$before=IDG_Job_Runner::get_workflow('idg_test');$r=IDG_Job_Runner::recover_stale_processing('idg_test');assert_true(!$r['ok'] && IDG_Job_Runner::get_workflow('idg_test')===$before,'premature recovery');});
t('old generate can be marked failed without losing article',function(){old_processing();$r=IDG_Job_Runner::recover_stale_processing('idg_test');$w=IDG_Job_Runner::get_workflow('idg_test');assert_true($r['ok'] && $w['status']==='failed' && $w['base_article']==='safe content' && $w['seo_result']==='safe seo','not recovered safely');});
t('old editorial can be recovered conservatively',function(){old_processing('idg_test','editorial');$r=IDG_Job_Runner::recover_stale_processing('idg_test');assert_true($r['ok'],'editorial recovery blocked');});
t('old seo can be recovered conservatively',function(){old_processing('idg_test','seo');$r=IDG_Job_Runner::recover_stale_processing('idg_test');assert_true($r['ok'],'seo recovery blocked');});
t('draft with potential WP writes cannot auto-recover',function(){old_processing('idg_test','draft');$before=IDG_Job_Runner::get_workflow('idg_test');$r=IDG_Job_Runner::recover_stale_processing('idg_test');assert_true(!$r['ok'] && IDG_Job_Runner::get_workflow('idg_test')===$before,'unsafe draft recovery');});
t('recurring WP update cannot auto-recover',function(){old_processing('idg_test','recurring_event_content');$r=IDG_Job_Runner::recover_stale_processing('idg_test');assert_true(!$r['ok'],'unsafe recurring recovery');});
t('queued pending action prevents recovery',function(){old_processing();$GLOBALS['queue_state']['pending']=[321];$r=IDG_Job_Runner::recover_stale_processing('idg_test');assert_true(!$r['ok'],'pending AS work ignored');});
t('in progress AS action prevents recovery',function(){old_processing();$GLOBALS['queue_state']['in-progress']=[322];$r=IDG_Job_Runner::recover_stale_processing('idg_test');assert_true(!$r['ok'],'active AS ignored');});
t('legacy processing without evidence is not unblocked by age guess',function(){seed('idg_test',['status'=>'processing','current_action'=>'generate']);$r=IDG_Job_Runner::recover_stale_processing('idg_test');assert_true(!$r['ok'],'legacy unverified state recovered');});
t('wrong existing lock prevents manual recovery',function(){old_processing();update_option(lock_key('idg_test'),'unknown-token');$r=IDG_Job_Runner::recover_stale_processing('idg_test');assert_true(!$r['ok'],'foreign lock ignored');});
t('recovery closes old token against delayed writes',function(){$w=old_processing();assert_true(IDG_Job_Runner::recover_stale_processing('idg_test')['ok'],'could not recover');$w['base_article']='CORRUPTED_BY_OLD_WORKER';$w['status']='completed';IDG_Job_Runner::save_workflow('idg_test',$w);assert_true(IDG_Job_Runner::get_workflow('idg_test')['base_article']==='safe content','stale worker overwrote recovered data');});
t('recovery preserves existing creation barrier',function(){old_processing();$key='idg_pending_creation_'.hash('sha256','idg_test');add_option($key,'creating');assert_true(IDG_Job_Runner::recover_stale_processing('idg_test')['ok'],'unrelated recovery blocked');assert_true(get_option($key)==='creating','deleted draft lock');});
t('duplicate manual recovery has no second effect',function(){old_processing();assert_true(IDG_Job_Runner::recover_stale_processing('idg_test')['ok'],'first recovery failed');$before=IDG_Job_Runner::get_workflow('idg_test');$r=IDG_Job_Runner::recover_stale_processing('idg_test');assert_true(!$r['ok'] && $before===IDG_Job_Runner::get_workflow('idg_test'),'second recovery changed data');});
t('recovery lock released for explicit manual retry',function(){old_processing();assert_true(IDG_Job_Runner::recover_stale_processing('idg_test')['ok'],'recover failed');IDG_Job_Runner::process_scheduled_action('idg_test','generate');assert_true(IDG_Job_Runner::get_workflow('idg_test')['status']==='completed','manual retry still blocked');});
t('new processing cannot self-transition twice',function(){seed();IDG_Job_Runner::process_scheduled_action('idg_test','generate');assert_true(!IDG_Workflow_Policies::transition_allowed('processing','processing'),'policy permits duplicate processing');});
t('Action Scheduler must request atomic unique enqueue', function(){
    seed();
    IDG_Job_Runner::schedule('idg_test','generate');
    assert_true($GLOBALS['queue_requests'][0]['unique'] === true, 'unique=false permits a second queued action');
});
t('two queued identical actions cannot both be accepted',function(){
    seed();
    assert_true(IDG_Job_Runner::schedule('idg_test','generate')===true, 'first queue rejected');
    $second=IDG_Job_Runner::schedule('idg_test','generate');
    assert_true($second===false,'second identical queued action accepted');
    assert_true(count($GLOBALS['queue_keys'])===1,'duplicate queue stored');
});
print "RESULT $passed/$total\n";exit($passed===$total?0:1);
