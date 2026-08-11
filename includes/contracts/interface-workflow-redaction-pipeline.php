<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Contrato compatible para ejecutar una fase de redacción sin publicar.
 */
interface IDG_Workflow_Redaction_Pipeline_Contract {
    /** @return string[] */
    public function phases(): array;

    public function supports(string $phase): bool;

    public function execute(string $workflow_id, string $phase, array $workflow, IDG_OpenAI_Client $client): void;
}
