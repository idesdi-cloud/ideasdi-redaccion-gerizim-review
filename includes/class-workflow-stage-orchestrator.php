<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Orquestador delgado para las fronteras planificación -> redacción.
 */
final class IDG_Workflow_Stage_Orchestrator implements IDG_Workflow_Stage_Orchestrator_Contract {
    public static function adapt(array $workflow, string $stage): array {
        return IDG_Workflow_Stage_Input_Adapter_Registry::for_stage($stage)->adapt($workflow);
    }

    public static function prepare(string $workflow_id, array $workflow, IDG_OpenAI_Client $client): array {
        $pipeline = new IDG_Workflow_Planning_Pipeline();
        return $pipeline->prepare($workflow_id, self::adapt($workflow, 'planning'), $client);
    }

    public static function redact(string $workflow_id, string $phase, array $workflow): void {
        $client = new IDG_OpenAI_Client();
        $workflow = self::prepare($workflow_id, self::adapt($workflow, 'redaction'), $client);
        $pipeline = new IDG_Workflow_Redaction_Pipeline();
        $pipeline->execute($workflow_id, $phase, $workflow, $client);
    }
}
