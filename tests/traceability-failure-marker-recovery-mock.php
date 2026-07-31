<?php
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');
define('IDG_TRACEABILITY_CAPTURE_ENABLED', true);
define('IDG_TRACEABILITY_DELIVERY_ENABLED', false);
define('IDG_TRACEABILITY_CONTRACT_VERSION', '1.1');
define('IDG_TRACEABILITY_LIVE_CAPTURE_STARTED_AT', '2026-07-04T14:53:06Z');
define('IDG_TRACEABILITY_ACTION_HOOK', 'idg_traceability_process_outbox');
define('IDG_TRACEABILITY_RECONCILE_HOOK', 'idg_traceability_reconcile');

final class PreparedMarkerQuery {
    public function __construct(public string $sql, public array $args) {}
}
final class MarkerWpdb {
    public string $postmeta = 'wp_postmeta';
    public function prepare($query, ...$args): PreparedMarkerQuery { return new PreparedMarkerQuery($query, $args); }
    public function get_var($query) {
        $sql = $query instanceof PreparedMarkerQuery ? $query->sql : (string) $query;
        if (str_contains($sql, 'SELECT COUNT(*)')) {
            $count = 0;
            foreach ($GLOBALS['post_meta'] as $meta) if (isset($meta['_idg_traceability_recapture_failure'])) $count++;
            return $count;
        }
        return null;
    }
    public function get_results($query, $mode = null): array {
        $args = $query instanceof PreparedMarkerQuery ? $query->args : [];
        $after = (int) ($args[1] ?? 0);
        $limit = (int) ($args[2] ?? 20);
        $rows = [];
        $metaId = 0;
        foreach ($GLOBALS['post_meta'] as $postId => $meta) {
            if (!isset($meta['_idg_traceability_recapture_failure'])) continue;
            $metaId++;
            if ($metaId <= $after) continue;
            $rows[] = ['meta_id' => $metaId, 'post_id' => $postId, 'meta_value' => serialize($meta['_idg_traceability_recapture_failure'])];
        }
        return array_slice($rows, 0, max(1, $limit));
    }
}
$GLOBALS['wpdb'] = new MarkerWpdb();
$GLOBALS['post_meta'] = [];
$GLOBALS['options'] = [];

class IDG_Job_Runner {
    public static function get_workflow(string $id): array { return []; }
    public static function save_workflow(string $id, array $data): void {}
}
class IDG_Traceability_Outbox {
    public static bool $fail = true;
    public static array $rows = [];
    public static function insert_event(array $payload, string $status = 'queued', string $dependency = '', string $error = ''): array {
        if (self::$fail) return ['success' => false, 'message' => 'outbox_insert_failed'];
        self::$rows[$payload['idempotency_key']] = compact('payload', 'status', 'dependency', 'error');
        return ['success' => true, 'inserted' => true];
    }
    public static function get_by_key(string $key): ?array { return null; }
    public static function schema_status(): array { return ['valid' => true, 'errors' => []]; }
    public static function table_exists(): bool { return true; }
    public static function schema_ready(): bool { return true; }
    public static function reconcile(int $limit = 50): array { return ['has_more' => false]; }
    public static function process_queue(int $limit = 10): array { return []; }
}
class IDG_Traceability_Recapture {
    public static bool $fail = true;
    public static array $intents = [];
    public static function record(array $payload, string $status, string $dependency, string $error): bool {
        if (self::$fail) return false;
        self::$intents[$payload['idempotency_key']] = compact('payload', 'status', 'dependency', 'error');
        return true;
    }
    public static function clear_if_compatible(string $key, array $payload, string $dependency = ''): bool { unset(self::$intents[$key]); return true; }
    public static function count(): int { return count(self::$intents); }
    public static function conflict_count(): int { return 0; }
    public static function recover_batch(int $limit = 20, int $cursor = 0): array { return ['recovered' => 0, 'remaining' => count(self::$intents), 'has_more' => false, 'last_option_id' => 0]; }
}
class IDG_Logger { public static array $logs = []; public static function log(string $event, string $message, array $context = []): void { self::$logs[] = compact('event', 'message', 'context'); } }

function add_action(...$args) {}
function sanitize_text_field($value) { return trim((string) $value); }
function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $value)); }
function absint($value) { return abs((int) $value); }
function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }
function maybe_unserialize($value) { return is_string($value) ? unserialize($value) : $value; }
function update_post_meta($postId, $key, $value) { $GLOBALS['post_meta'][$postId][$key] = $value; return true; }
function get_post_meta($postId, $key, $single = true) { return $GLOBALS['post_meta'][$postId][$key] ?? ''; }
function delete_post_meta($postId, $key) { unset($GLOBALS['post_meta'][$postId][$key]); return true; }
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function update_option($key, $value, $autoload = false) { $GLOBALS['options'][$key] = $value; return true; }
function wp_next_scheduled($hook) { return false; }
function wp_schedule_single_event(...$args) { return true; }
function wp_schedule_event(...$args) { return true; }

require_once dirname(__DIR__) . '/includes/class-traceability.php';
function ok($condition, string $message): void {
    if (!$condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
    echo "OK: {$message}\n";
}
function invoke_private(string $method, array $args = []) {
    $reflection = new ReflectionMethod(IDG_Traceability::class, $method);
    $reflection->setAccessible(true);
    return $reflection->invokeArgs(null, $args);
}
function published_payload(int $postId): array {
    $workflow = 'idg_aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
    return [
        'event_type' => 'wordpress_published', 'brief_id' => 88,
        'occurred_at' => '2026-07-05T12:00:00Z', 'observed_at' => '2026-07-05T12:00:01Z',
        'source_system' => 'wordpress', 'source_record_id' => (string) $postId,
        'workflow_id' => $workflow, 'wordpress_post_id' => $postId, 'wordpress_status' => 'publish',
        'idempotency_key' => 'wordpress_published:' . $workflow . ':' . $postId,
        'evidence_payload' => ['contract_version' => '1.1', 'previous_status' => 'pending', 'new_status' => 'publish'],
        'actor' => 'wordpress',
    ];
}

$payload = published_payload(7001);
$result = invoke_private('capture_or_defer', [$payload, 'queued', 'wordpress_post_created:idg_aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa:7001', '']);
ok(($result['operational_error'] ?? '') === 'recapture_persistence_failed', 'doble fallo registra error operativo explícito');
ok(IDG_Traceability::recoverable_failure_marker_count() === 1, 'marca alternativa queda visible para diagnóstico');
$rows = IDG_Traceability::recoverable_failure_marker_rows();
ok(count($rows) === 1 && !empty($rows[0]['valid']) && $rows[0]['event_type'] === 'wordpress_published', 'panel puede leer marca íntegra de publicación');

IDG_Traceability_Recapture::$fail = false;
$recovery = IDG_Traceability::recover_failure_markers(20);
ok(($recovery['recovered'] ?? 0) === 1, 'marca se reconstruye como intención durable');
ok(isset(IDG_Traceability_Recapture::$intents[$payload['idempotency_key']]), 'recaptura reconstruida conserva clave de publicación');
ok(IDG_Traceability::recoverable_failure_marker_count() === 0, 'marca alternativa se elimina después de persistencia verificada');

$payload2 = published_payload(7002);
IDG_Traceability_Recapture::$fail = true;
invoke_private('capture_or_defer', [$payload2, 'queued', 'wordpress_post_created:idg_aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa:7002', '']);
IDG_Traceability_Outbox::$fail = false;
$recovery2 = IDG_Traceability::recover_failure_markers(20);
ok(($recovery2['recovered'] ?? 0) === 1, 'marca puede reconstruirse directamente en outbox');
ok(isset(IDG_Traceability_Outbox::$rows[$payload2['idempotency_key']]), 'outbox recupera publicación desde meta persistida');
ok(IDG_Traceability::recoverable_failure_marker_count() === 0, 'meta se limpia solo después de recuperación real');

echo "PASS failure marker recovery mock\n";
