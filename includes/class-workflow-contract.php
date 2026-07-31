<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Contrato de compatibilidad del workflow histórico de Gerizim.
 *
 * El formato persistido sigue siendo el array asociativo existente. Esta
 * clase documenta sus bordes sin introducir una migración de datos ni una
 * envoltura nueva en wp_options.
 */
final class IDG_Workflow_Contract {
    public const FORMAT = 'legacy-array-v1';
    public const CONTRACT_VERSION = '1.0';

    private const INPUT_SOURCES = [
        'legacy',
        'admin',
        'radar',
        'recurring',
        'traceability',
    ];

    public static function normalize_source(string $source): string {
        $source = function_exists('sanitize_key')
            ? sanitize_key($source)
            : strtolower((string) preg_replace('/[^a-z0-9_\-]/i', '', $source));
        return in_array($source, self::INPUT_SOURCES, true) ? $source : 'legacy';
    }

    public static function is_known_action(string $action): bool {
        return IDG_Workflow_Policies::is_known_action($action);
    }

    /**
     * Devuelve un diagnóstico no destructivo. Nunca modifica el workflow.
     */
    public static function inspect(array $workflow): array {
        $warnings = [];

        if (isset($workflow['workflow_id']) && !is_scalar($workflow['workflow_id'])) {
            $warnings[] = 'workflow_id_not_scalar';
        }
        if (isset($workflow['history']) && !is_array($workflow['history'])) {
            $warnings[] = 'history_not_array';
        }
        if (isset($workflow['tag_ids']) && !is_array($workflow['tag_ids'])) {
            $warnings[] = 'tag_ids_not_array';
        }
        if (isset($workflow['internal_links_structured']) && !is_array($workflow['internal_links_structured'])) {
            $warnings[] = 'internal_links_structured_not_array';
        }

        return [
            'compatible' => true,
            'format' => self::FORMAT,
            'contract_version' => self::CONTRACT_VERSION,
            'warnings' => $warnings,
        ];
    }

    /**
     * Garantía central de RC1.6.0: conservar exactamente claves, orden y valores.
     */
    public static function preserve(array $workflow): array {
        return $workflow;
    }
}
