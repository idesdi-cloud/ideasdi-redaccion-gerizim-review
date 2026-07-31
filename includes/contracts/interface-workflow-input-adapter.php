<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Adapta una entrada externa al formato histórico de workflow de Gerizim.
 *
 * RC1.6.0 exige compatibilidad binaria a nivel de array: una implementación
 * no debe renombrar, eliminar ni añadir campos por el solo hecho de adaptar.
 */
interface IDG_Workflow_Input_Adapter_Contract {
    public function source(): string;

    public function adapt(array $workflow): array;
}
