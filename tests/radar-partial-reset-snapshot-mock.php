<?php
define('ABSPATH', __DIR__ . '/');
$GLOBALS['options'] = [];
$GLOBALS['fail_add_option'] = false;
$GLOBALS['fail_delete_option'] = false;

class IDG_Job_Runner {
    public static array $workflows = [];
    public static bool $corrupt_restore = false;
    public static function save_workflow(string $id, array $data): void {
        if (self::$corrupt_restore) {
            $data['keyword'] = 'CORRUPTED';
        }
        self::$workflows[$id] = $data;
    }
    public static function get_workflow(string $id): array { return self::$workflows[$id] ?? []; }
}
function get_option($key, $default = false) { return array_key_exists($key, $GLOBALS['options']) ? $GLOBALS['options'][$key] : $default; }
function add_option($key, $value, $deprecated = '', $autoload = false) { if ($GLOBALS['fail_add_option'] || array_key_exists($key, $GLOBALS['options'])) return false; $GLOBALS['options'][$key] = $value; return true; }
function update_option($key, $value, $autoload = false) { $GLOBALS['options'][$key] = $value; return true; }
function delete_option($key) { if ($GLOBALS['fail_delete_option']) return false; if (!array_key_exists($key, $GLOBALS['options'])) return false; unset($GLOBALS['options'][$key]); return true; }
function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }

require_once dirname(__DIR__) . '/includes/class-admin-page.php';
function ok($condition, string $message): void { if (!$condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); } echo "OK: {$message}\n"; }
function invoke_private(string $method, array $args = []) { $m = new ReflectionMethod(IDG_Admin_Page::class, $method); $m->setAccessible(true); return $m->invokeArgs(null, $args); }

$wid = 'idg_99999999-9999-4999-8999-999999999999';
$original = ['workflow_id' => $wid, 'keyword' => 'Original', 'nested' => ['b' => 2, 'a' => 1], 'radar_brief_id' => 99];
$GLOBALS['fail_add_option'] = true;
$failed = invoke_private('store_radar_partial_reset_snapshot', [$wid, $original]);
ok(empty($failed['success']) && ($failed['reason'] ?? '') === 'snapshot_storage_failed', 'fallo de almacenamiento se devuelve de forma explícita');
ok($GLOBALS['options'] === [], 'fallo de almacenamiento no deja snapshot parcial');

$GLOBALS['fail_add_option'] = false;
$stored = invoke_private('store_radar_partial_reset_snapshot', [$wid, $original]);
ok(!empty($stored['success']), 'snapshot se almacena y verifica');
$key = invoke_private('radar_partial_reset_snapshot_key', [$wid]);
$record = $GLOBALS['options'][$key] ?? [];
ok(!empty($record['snapshot_hash']) && ($record['snapshot']['keyword'] ?? '') === 'Original', 'snapshot durable conserva hash y contenido protegido');

IDG_Job_Runner::$workflows[$wid] = ['workflow_id' => $wid, 'keyword' => 'Parcial'];
IDG_Job_Runner::$corrupt_restore = true;
$restoreFailed = invoke_private('restore_radar_partial_reset_snapshot', [$wid]);
ok($restoreFailed === false, 'restauración incompleta se detecta');
ok(isset($GLOBALS['options'][$key]), 'snapshot no se borra tras fallo de restauración');

IDG_Job_Runner::$corrupt_restore = false;
$restored = invoke_private('restore_radar_partial_reset_snapshot', [$wid]);
ok($restored === true, 'restauración completa se confirma');
ok((IDG_Job_Runner::$workflows[$wid]['keyword'] ?? '') === 'Original', 'todos los campos protegidos se restauran');
ok(!isset($GLOBALS['options'][$key]), 'snapshot se borra solo después de confirmar restauración');

$storedAgain = invoke_private('store_radar_partial_reset_snapshot', [$wid, $original]);
ok(!empty($storedAgain['success']), 'snapshot puede recrearse para una restauración posterior');
$GLOBALS['fail_delete_option'] = true;
IDG_Job_Runner::$workflows[$wid] = ['workflow_id' => $wid, 'keyword' => 'Parcial otra vez'];
$deleteFailed = invoke_private('restore_radar_partial_reset_snapshot', [$wid]);
ok($deleteFailed === false, 'fallo al eliminar snapshot no se declara como restauración cerrada');
ok(isset($GLOBALS['options'][$key]), 'snapshot permanece recuperable si falla su eliminación');
ok((IDG_Job_Runner::$workflows[$wid]['keyword'] ?? '') === 'Original', 'workflow sí queda restaurado aunque el cierre de snapshot falle');
$GLOBALS['fail_delete_option'] = false;
delete_option($key);

$admin = file_get_contents(dirname(__DIR__) . '/includes/class-admin-page.php');
$barrier = strpos($admin, '// El snapshot del Reinicio parcial es una barrera previa');
$store = strpos($admin, 'store_radar_partial_reset_snapshot($workflow_id, $workflow)', $barrier);
$save = strpos($admin, 'IDG_Workflow_Orchestrator::save($workflow_id, $data,', $barrier);
if ($save === false) {
    $save = strpos($admin, 'IDG_Job_Runner::save_workflow($workflow_id, $data);', $barrier);
}
ok($barrier !== false && $store !== false && $save !== false && $store < $save, 'snapshot se confirma antes de cualquier escritura del Reinicio parcial');
ok(strpos($admin, "empty(\$snapshot_result['success'])") < strpos($admin, 'partial_reset_workflow_data($data)'), 'Reinicio parcial queda bloqueado antes de transformar el workflow si falla el snapshot');
echo "PASS partial reset snapshot mock\n";
