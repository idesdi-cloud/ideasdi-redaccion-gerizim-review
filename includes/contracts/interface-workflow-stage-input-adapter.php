<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Adaptador transparente para las fronteras internas de planificación y
 * redacción. Debe conservar exactamente el array legacy-array-v1.
 */
interface IDG_Workflow_Stage_Input_Adapter_Contract {
    public function stage(): string;

    public function adapt(array $workflow): array;
}
