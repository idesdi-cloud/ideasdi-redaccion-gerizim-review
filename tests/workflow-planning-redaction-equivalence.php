<?php
define('ABSPATH', __DIR__ . '/');

function sanitize_key(string $value): string {
    return strtolower((string) preg_replace('/[^a-z0-9_\-]/i', '', $value));
}
function sanitize_textarea_field(string $value): string {
    return trim($value);
}

final class IDG_Workflow_Policies {
    public const ACTION_GENERATE = 'generate';
    public const ACTION_EDITORIAL = 'editorial';
    public const ACTION_SEO = 'seo';
    public static function is_known_action(string $action): bool { return in_array($action, [self::ACTION_GENERATE, self::ACTION_EDITORIAL, self::ACTION_SEO], true); }
}
final class IDG_OpenAI_Client {}

require_once dirname(__DIR__) . '/includes/class-workflow-contract.php';
require_once dirname(__DIR__) . '/includes/contracts/interface-workflow-stage-input-adapter.php';
require_once dirname(__DIR__) . '/includes/contracts/interface-workflow-planning-pipeline.php';
require_once dirname(__DIR__) . '/includes/contracts/interface-workflow-redaction-pipeline.php';
require_once dirname(__DIR__) . '/includes/contracts/interface-workflow-stage-orchestrator.php';
require_once dirname(__DIR__) . '/includes/adapters/class-workflow-stage-input-adapters.php';

final class IDG_Workflow_Planning_Pipeline implements IDG_Workflow_Planning_Pipeline_Contract {
    public static array $calls = [];
    public function prepare(string $workflow_id, array $workflow, IDG_OpenAI_Client $client): array {
        self::$calls[] = compact('workflow_id', 'workflow', 'client');
        return $workflow;
    }
}
final class IDG_Workflow_Redaction_Pipeline implements IDG_Workflow_Redaction_Pipeline_Contract {
    public static array $calls = [];
    public function phases(): array { return ['generate', 'editorial', 'seo']; }
    public function supports(string $phase): bool { return in_array($phase, $this->phases(), true); }
    public function execute(string $workflow_id, string $phase, array $workflow, IDG_OpenAI_Client $client): void {
        self::$calls[] = compact('workflow_id', 'phase', 'workflow', 'client');
    }
}

require_once dirname(__DIR__) . '/includes/class-workflow-stage-orchestrator.php';
require_once dirname(__DIR__) . '/includes/class-workflow-output-parser.php';

function stage_eq_ok(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

$fixture = [
    'workflow_id' => 'idg_fixture',
    'keyword' => 'Conservar',
    'false_value' => false,
    'null_value' => null,
    'nested' => ['order' => [3, 1, 2], 'assoc' => ['b' => 2, 'a' => 1]],
];
foreach (['planning', 'redaction'] as $stage) {
    $adapted = IDG_Workflow_Stage_Input_Adapter_Registry::for_stage($stage)->adapt($fixture);
    stage_eq_ok($adapted === $fixture, "adaptador {$stage} conserva claves, orden, tipos y valores");
}

$client = new IDG_OpenAI_Client();
$planned = IDG_Workflow_Stage_Orchestrator::prepare('idg_fixture', $fixture, $client);
stage_eq_ok($planned === $fixture, 'planificación conserva el workflow compatible');
stage_eq_ok(count(IDG_Workflow_Planning_Pipeline::$calls) === 1, 'orquestador invoca una sola planificación');
stage_eq_ok(IDG_Workflow_Planning_Pipeline::$calls[0]['workflow'] === $fixture, 'planificación recibe el array exacto');
stage_eq_ok(IDG_Workflow_Planning_Pipeline::$calls[0]['client'] === $client, 'planificación conserva la instancia del cliente');

IDG_Workflow_Planning_Pipeline::$calls = [];
IDG_Workflow_Redaction_Pipeline::$calls = [];
IDG_Workflow_Stage_Orchestrator::redact('idg_fixture', 'editorial', $fixture);
stage_eq_ok(count(IDG_Workflow_Planning_Pipeline::$calls) === 1, 'redacción prepara exactamente una vez');
stage_eq_ok(count(IDG_Workflow_Redaction_Pipeline::$calls) === 1, 'redacción ejecuta exactamente una fase');
$planning_call = IDG_Workflow_Planning_Pipeline::$calls[0];
$redaction_call = IDG_Workflow_Redaction_Pipeline::$calls[0];
stage_eq_ok($redaction_call['phase'] === 'editorial', 'fase editorial conservada');
stage_eq_ok($redaction_call['workflow'] === $fixture, 'redacción recibe el workflow exacto después de planificar');
stage_eq_ok($redaction_call['client'] === $planning_call['client'], 'planificación y redacción comparten el mismo cliente');

$raw = "Need provide internal note\n\nARTÍCULO REVISADO\nTexto visible\n\nDIAGNÓSTICO EDITORIAL INTERNO\nDiagnóstico\n\nNOTAS EDITORIALES INTERNAS\nNotas";
$clean = IDG_Workflow_Output_Parser::sanitize($raw);
stage_eq_ok(!str_contains($clean, 'Need provide'), 'sanitizador conserva limpieza histórica');
$sections = IDG_Workflow_Output_Parser::parse_editorial($clean);
stage_eq_ok($sections === ['article' => 'Texto visible', 'diagnosis' => 'Diagnóstico', 'notes' => 'Notas'], 'parser editorial conserva las tres secciones');
$feedback = IDG_Workflow_Output_Parser::extract_feedback_notes("RETROALIMENTACIÓN GERIZIM\n- Mantener\n\nMETA DESCRIPTION\nMeta");
stage_eq_ok($feedback === '- Mantener', 'retroalimentación conserva delimitación histórica');

echo "PASS workflow planning redaction equivalence\n";
