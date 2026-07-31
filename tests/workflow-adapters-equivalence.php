<?php
define('ABSPATH', __DIR__ . '/');

require_once dirname(__DIR__) . '/includes/contracts/interface-workflow-input-adapter.php';
require_once dirname(__DIR__) . '/includes/class-workflow-policies.php';
require_once dirname(__DIR__) . '/includes/class-workflow-contract.php';
require_once dirname(__DIR__) . '/includes/adapters/class-workflow-input-adapters.php';

function adapter_ok(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

$fixture = [
    'keyword' => 'Diseño de prueba',
    'tag_ids' => [9, 3, 11],
    'workflow_id' => 'idg_fixture',
    'history' => [
        ['time' => '2026-07-15 12:00:00', 'event' => 'flow_started', 'message' => 'Flujo creado.'],
    ],
    'nested' => [
        'z' => 1,
        'a' => ['preserve', 'order', 0, false, null],
    ],
    'internal_links_structured' => [],
];
$serialized = serialize($fixture);

foreach (['legacy', 'admin', 'radar', 'recurring', 'traceability'] as $source) {
    $adapter = IDG_Workflow_Input_Adapter_Registry::for_source($source);
    $adapted = $adapter->adapt($fixture);
    adapter_ok($adapter->source() === $source, "registro resuelve adaptador {$source}");
    adapter_ok($adapted === $fixture, "adaptador {$source} conserva array exacto");
    adapter_ok(serialize($adapted) === $serialized, "adaptador {$source} conserva orden y tipos");
}

$unknown = IDG_Workflow_Input_Adapter_Registry::for_source('future-source');
adapter_ok($unknown->source() === 'legacy', 'fuente desconocida usa compatibilidad legacy');

$inspection = IDG_Workflow_Contract::inspect($fixture);
adapter_ok(($inspection['compatible'] ?? false) === true, 'contrato reconoce workflow histórico');
adapter_ok(($inspection['format'] ?? '') === 'legacy-array-v1', 'formato histórico queda explícito');
adapter_ok(IDG_Workflow_Contract::preserve($fixture) === $fixture, 'contrato no migra ni envuelve datos');

foreach (['generate', 'editorial', 'seo', 'draft', 'draft_force', 'recurring_event_content', 'recurring_event_content_force'] as $action) {
    adapter_ok(IDG_Workflow_Contract::is_known_action($action), "acción {$action} documentada");
}
adapter_ok(!IDG_Workflow_Contract::is_known_action('future_action'), 'acción futura no se confunde con acción conocida');

echo "PASS workflow adapters equivalence\n";
