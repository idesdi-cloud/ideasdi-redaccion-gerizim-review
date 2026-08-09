<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Contrato compatible de la etapa de planificación documental/editorial.
 */
interface IDG_Workflow_Planning_Pipeline_Contract {
    public function prepare(string $workflow_id, array $workflow, IDG_OpenAI_Client $client): array;
}
