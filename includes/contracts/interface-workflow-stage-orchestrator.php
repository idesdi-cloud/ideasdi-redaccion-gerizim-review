<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fachada delgada entre el runner y las etapas desacopladas.
 */
interface IDG_Workflow_Stage_Orchestrator_Contract {
    public static function adapt(array $workflow, string $stage): array;

    public static function prepare(string $workflow_id, array $workflow, IDG_OpenAI_Client $client): array;

    public static function redact(string $workflow_id, string $phase, array $workflow): void;
}
