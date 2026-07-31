<?php
define('ABSPATH', __DIR__ . '/');

final class IDG_Job_Runner {
    public static array $calls = [];

    public static function reset(): void {
        self::$calls = [];
    }

    public static function append_history_snapshot(array $workflow, string $event, string $message): array {
        $history = isset($workflow['history']) && is_array($workflow['history']) ? $workflow['history'] : [];
        $history[] = ['event' => $event, 'message' => $message];
        $workflow['history'] = $history;
        return $workflow;
    }

    private static function record(string $stage, string $workflow_id, array $workflow): void {
        self::$calls[] = compact('stage', 'workflow_id', 'workflow');
    }

    public static function execute_generate_stage(string $workflow_id, array $workflow): void {
        self::record('generate', $workflow_id, $workflow);
    }

    public static function execute_editorial_stage(string $workflow_id, array $workflow): void {
        self::record('editorial', $workflow_id, $workflow);
    }

    public static function execute_seo_stage(string $workflow_id, array $workflow): void {
        self::record('seo', $workflow_id, $workflow);
    }

    public static function execute_draft_stage(string $workflow_id, array $workflow): void {
        self::record('draft', $workflow_id, $workflow);
    }

    public static function execute_recurring_content_stage(string $workflow_id, array $workflow): void {
        self::record('recurring_event_content', $workflow_id, $workflow);
    }
}

require_once dirname(__DIR__) . '/includes/contracts/interface-workflow-action-strategy.php';
require_once dirname(__DIR__) . '/includes/class-workflow-policies.php';
require_once dirname(__DIR__) . '/includes/strategies/class-workflow-action-strategies.php';

function strategy_ok(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

$expected_actions = [
    'generate',
    'editorial',
    'seo',
    'draft',
    'draft_force',
    'recurring_event_content',
    'recurring_event_content_force',
];

$registered = [];
foreach (IDG_Workflow_Action_Strategy_Center::all() as $strategy) {
    foreach ($strategy->actions() as $action) {
        $registered[] = $action;
        strategy_ok($strategy->supports($action), "estrategia reconoce {$action}");
    }
}
strategy_ok($registered === $expected_actions, 'orden y conjunto de acciones permanecen equivalentes');

$stage_map = [
    'generate' => 'generate',
    'editorial' => 'editorial',
    'seo' => 'seo',
    'draft' => 'draft',
    'draft_force' => 'draft',
    'recurring_event_content' => 'recurring_event_content',
    'recurring_event_content_force' => 'recurring_event_content',
];

foreach ($stage_map as $action => $expected_stage) {
    IDG_Job_Runner::reset();
    $fixture = [
        'workflow_id' => 'idg_strategy_fixture',
        'status' => 'processing',
        'history' => [],
        'force_validation_override' => true,
        'nested' => ['preserve' => [1, false, null]],
    ];
    $executed = IDG_Workflow_Action_Strategy_Center::execute('idg_strategy_fixture', $action, $fixture);
    strategy_ok($executed === true, "acción {$action} ejecutada");
    strategy_ok(count(IDG_Job_Runner::$calls) === 1, "acción {$action} ejecuta una sola etapa");
    $call = IDG_Job_Runner::$calls[0];
    strategy_ok($call['stage'] === $expected_stage, "acción {$action} conserva etapa {$expected_stage}");
    strategy_ok($call['workflow_id'] === 'idg_strategy_fixture', "acción {$action} conserva workflow_id");
    strategy_ok(($call['workflow']['nested'] ?? null) === $fixture['nested'], "acción {$action} conserva datos anidados");

    if (str_ends_with($action, '_force')) {
        strategy_ok(!empty($call['workflow']['force_validation_override']), "acción {$action} activa override");
        $last = end($call['workflow']['history']);
        $expected_event = $action === 'draft_force' ? 'draft_force_requested' : 'recurring_content_force_requested';
        strategy_ok(($last['event'] ?? '') === $expected_event, "acción {$action} conserva evento histórico");
    } elseif ($action === 'draft' || $action === 'recurring_event_content') {
        strategy_ok(!array_key_exists('force_validation_override', $call['workflow']), "acción {$action} limpia override previo");
        strategy_ok($call['workflow']['history'] === [], "acción {$action} no añade historial extra");
    } else {
        strategy_ok($call['workflow'] === $fixture, "acción {$action} pasa workflow exacto");
    }
}

IDG_Job_Runner::reset();
strategy_ok(IDG_Workflow_Action_Strategy_Center::execute('idg_strategy_fixture', 'future_action', ['status' => 'processing']) === false, 'acción desconocida conserva no-op');
strategy_ok(IDG_Job_Runner::$calls === [], 'acción desconocida no ejecuta una etapa');
strategy_ok(IDG_Workflow_Action_Strategy_Center::for_action('future_action') === null, 'acción desconocida no recibe estrategia incorrecta');

echo "PASS workflow action strategy center equivalence\n";
