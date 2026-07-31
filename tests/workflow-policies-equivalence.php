<?php
define('ABSPATH', __DIR__ . '/');

final class IDG_Temporary_Material {
    public static function has_blocking_file_error(array $workflow): bool {
        return !empty($workflow['fixture_file_error']);
    }
}

require_once dirname(__DIR__) . '/includes/class-workflow-policies.php';

function policy_ok(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

$actions = [
    'generate',
    'editorial',
    'seo',
    'draft',
    'draft_force',
    'recurring_event_content',
    'recurring_event_content_force',
];
policy_ok(IDG_Workflow_Policies::known_actions() === $actions, 'orden y nombres de acciones preservados');
foreach ($actions as $action) {
    policy_ok(IDG_Workflow_Policies::is_known_action($action), "acción {$action} conocida");
}
policy_ok(!IDG_Workflow_Policies::is_known_action('future_action'), 'acción desconocida permanece fuera de política conocida');
policy_ok(IDG_Workflow_Policies::validation_step_for_action('draft_force') === 'draft', 'draft_force conserva validación de borrador');
policy_ok(IDG_Workflow_Policies::validation_step_for_action('recurring_event_content') === 'draft', 'actualización recurrente conserva validación final');
policy_ok(IDG_Workflow_Policies::validation_step_for_action('recurring_event_content_force') === 'draft', 'actualización recurrente forzada conserva validación final');
policy_ok(IDG_Workflow_Policies::base_action('draft_force') === 'draft', 'acción forzada conserva etapa base draft');
policy_ok(IDG_Workflow_Policies::base_action('recurring_event_content_force') === 'recurring_event_content', 'acción recurrente forzada conserva etapa base');
policy_ok(IDG_Workflow_Policies::is_force_action('draft_force'), 'draft_force reconocido como override');
policy_ok(!IDG_Workflow_Policies::is_force_action('draft'), 'draft normal no se confunde con override');

$fixture = ['keyword' => 'Conservar', 'nested' => ['a' => 1]];
$initialized = IDG_Workflow_Policies::initialize($fixture);
policy_ok($initialized === ['keyword' => 'Conservar', 'nested' => ['a' => 1], 'status' => 'draft'], 'inicialización solo añade estado draft');
$processing = IDG_Workflow_Policies::mark_processing($initialized, 'generate');
policy_ok($processing['status'] === 'processing' && $processing['current_action'] === 'generate', 'processing conserva estado y acción histórica');
policy_ok($processing['keyword'] === 'Conservar' && $processing['nested'] === ['a' => 1], 'transición no transforma datos del workflow');
$failed = IDG_Workflow_Policies::mark_failed($processing, 'Error de prueba');
policy_ok($failed['status'] === 'failed' && $failed['last_error'] === 'Error de prueba', 'fallo conserva estado y mensaje');
$completed = IDG_Workflow_Policies::mark_completed($processing, 'generate');
policy_ok($completed['status'] === 'completed' && $completed['last_action'] === 'generate' && $completed['last_error'] === '', 'éxito conserva estado, acción y limpieza de error');

policy_ok(IDG_Workflow_Policies::transition_allowed('draft', 'processing'), 'draft puede pasar a processing');
policy_ok(IDG_Workflow_Policies::transition_allowed('processing', 'completed'), 'processing puede completar');
policy_ok(IDG_Workflow_Policies::transition_allowed('processing', 'failed'), 'processing puede fallar');
policy_ok(IDG_Workflow_Policies::transition_allowed('completed', 'processing'), 'workflow completado puede ejecutar otra etapa');
policy_ok(!IDG_Workflow_Policies::transition_allowed('draft', 'completed'), 'mapa documenta que draft no completa directamente');
policy_ok(IDG_Workflow_Policies::blocks_interactive_mutation(['status' => 'processing']), 'processing bloquea mutaciones interactivas');
policy_ok(!IDG_Workflow_Policies::blocks_interactive_mutation(['status' => 'completed']), 'completed no bloquea mutaciones interactivas');
policy_ok(IDG_Workflow_Policies::history_limit() === 20, 'límite histórico permanece en 20');
policy_ok(IDG_Workflow_Policies::automatic_retry_limit() === 0, 'sin reintentos automáticos nuevos');
policy_ok(IDG_Workflow_Policies::retry_mode() === 'manual_only', 'reintento sigue siendo manual');
policy_ok(!IDG_Workflow_Policies::should_retry([], 'generate', new RuntimeException('x')), 'excepción no activa reintento automático');

$cases = [
    [['fixture_file_error' => true], 'generate', 'invalid_temp_material'],
    [[], 'generate', 'needs_keyword'],
    [['keyword' => 'K'], 'generate', 'needs_brief'],
    [['piece_type' => 'Patrocinado', 'keyword' => 'K'], 'generate', 'needs_sponsored_brief'],
    [[], 'editorial', 'needs_article'],
    [[], 'seo', 'needs_editorial'],
    [[], 'draft', 'needs_seo'],
    [['seo_result' => 'SEO', 'workflow_origin' => 'recurring_update'], 'recurring_event_content', 'needs_recurring_target'],
    [['keyword' => 'K', 'brief_fact' => 'Hecho'], 'generate', ''],
    [['base_article' => 'Base'], 'editorial', ''],
    [['editorial_result' => 'Editorial'], 'seo', ''],
    [['seo_result' => 'SEO'], 'draft_force', ''],
    [['seo_result' => 'SEO', 'workflow_origin' => 'recurring_update', 'recurring_target_post_id' => 11902], 'recurring_event_content_force', ''],
];
foreach ($cases as [$workflow, $action, $reason]) {
    policy_ok(IDG_Workflow_Policies::advance_block_reason($action, $workflow) === $reason, "elegibilidad {$action} conserva {$reason}");
}

echo "PASS workflow policies equivalence\n";
