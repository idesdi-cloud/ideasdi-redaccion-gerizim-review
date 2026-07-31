<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Orquestador delgado de RC1.6.0.
 *
 * No contiene reglas editoriales ni lógica de etapas. Delega creación,
 * persistencia, cola y ejecución al IDG_Job_Runner existente para conservar
 * prompts, número de llamadas, validaciones y publicación.
 */
final class IDG_Workflow_Orchestrator implements IDG_Workflow_Orchestrator_Contract {
    public static function adapt(array $workflow, string $source = 'legacy'): array {
        return IDG_Workflow_Input_Adapter_Registry::for_source($source)->adapt($workflow);
    }

    public static function create(array $workflow, string $source = 'legacy'): string {
        return IDG_Job_Runner::new_workflow(self::adapt($workflow, $source));
    }

    public static function save(string $workflow_id, array $workflow, string $source = 'legacy'): void {
        IDG_Job_Runner::save_workflow($workflow_id, self::adapt($workflow, $source));
    }

    public static function schedule(string $workflow_id, string $action): bool {
        // El contrato clasifica acciones conocidas, pero no bloquea acciones
        // históricas o futuras: la semántica final permanece en Job_Runner.
        IDG_Workflow_Contract::is_known_action($action);
        IDG_Workflow_Policies::automatic_retry_limit();
        return IDG_Job_Runner::schedule($workflow_id, $action);
    }

    public static function process_scheduled_action(string $workflow_id, string $action): void {
        IDG_Workflow_Contract::is_known_action($action);
        IDG_Job_Runner::process_scheduled_action($workflow_id, $action);
    }
}
