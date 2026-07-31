<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fachada estable para crear, guardar y ejecutar workflows sin exponer el
 * motor concreto que los procesa.
 */
interface IDG_Workflow_Orchestrator_Contract {
    public static function adapt(array $workflow, string $source = 'legacy'): array;

    public static function create(array $workflow, string $source = 'legacy'): string;

    public static function save(string $workflow_id, array $workflow, string $source = 'legacy'): void;

    public static function schedule(string $workflow_id, string $action): bool;

    public static function process_scheduled_action(string $workflow_id, string $action): void;
}
