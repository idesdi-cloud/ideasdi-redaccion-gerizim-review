<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Contrato de una estrategia de ejecución de acciones del workflow.
 *
 * Las estrategias de RC1.6.1 solo seleccionan y encaminan una acción hacia
 * el motor histórico. No modifican prompts, orden de llamadas, validaciones,
 * persistencia ni reglas de publicación.
 */
interface IDG_Workflow_Action_Strategy_Contract {
    /**
     * @return string[] Acciones exactas atendidas por la estrategia.
     */
    public function actions(): array;

    public function supports(string $action): bool;

    public function execute(string $workflow_id, string $action, array $workflow): void;
}
