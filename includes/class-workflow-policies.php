<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Políticas operativas centralizadas para workflows Gerizim.
 *
 * RC1.6.2 documenta y aplica aquí estados, transiciones, elegibilidad,
 * reintentos y bloqueos que antes estaban repartidos entre el runner y el
 * panel administrativo. No agrega campos al workflow ni cambia su formato.
 */
final class IDG_Workflow_Policies {
    public const VERSION = '1.0';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public const ACTION_GENERATE = 'generate';
    public const ACTION_EDITORIAL = 'editorial';
    public const ACTION_SEO = 'seo';
    public const ACTION_DRAFT = 'draft';
    public const ACTION_DRAFT_FORCE = 'draft_force';
    public const ACTION_RECURRING_CONTENT = 'recurring_event_content';
    public const ACTION_RECURRING_CONTENT_FORCE = 'recurring_event_content_force';

    private const KNOWN_ACTIONS = [
        self::ACTION_GENERATE,
        self::ACTION_EDITORIAL,
        self::ACTION_SEO,
        self::ACTION_DRAFT,
        self::ACTION_DRAFT_FORCE,
        self::ACTION_RECURRING_CONTENT,
        self::ACTION_RECURRING_CONTENT_FORCE,
    ];

    private const FORCE_ACTIONS = [
        self::ACTION_DRAFT_FORCE => self::ACTION_DRAFT,
        self::ACTION_RECURRING_CONTENT_FORCE => self::ACTION_RECURRING_CONTENT,
    ];

    private const VALIDATION_STEPS = [
        self::ACTION_GENERATE => self::ACTION_GENERATE,
        self::ACTION_EDITORIAL => self::ACTION_EDITORIAL,
        self::ACTION_SEO => self::ACTION_SEO,
        self::ACTION_DRAFT => self::ACTION_DRAFT,
        self::ACTION_DRAFT_FORCE => self::ACTION_DRAFT,
        self::ACTION_RECURRING_CONTENT => self::ACTION_DRAFT,
        self::ACTION_RECURRING_CONTENT_FORCE => self::ACTION_DRAFT,
    ];

    private const ALLOWED_TRANSITIONS = [
        '' => [self::STATUS_DRAFT, self::STATUS_PROCESSING, self::STATUS_COMPLETED, self::STATUS_FAILED],
        self::STATUS_DRAFT => [self::STATUS_PROCESSING],
        self::STATUS_PROCESSING => [self::STATUS_PROCESSING, self::STATUS_COMPLETED, self::STATUS_FAILED],
        self::STATUS_COMPLETED => [self::STATUS_PROCESSING],
        self::STATUS_FAILED => [self::STATUS_PROCESSING],
    ];

    private const MAX_WORKFLOW_HISTORY = 20;
    private const MAX_AUTOMATIC_RETRIES = 0;

    /** @return string[] */
    public static function known_actions(): array {
        return self::KNOWN_ACTIONS;
    }

    public static function is_known_action(string $action): bool {
        return in_array($action, self::KNOWN_ACTIONS, true);
    }

    public static function validation_step_for_action(string $action): string {
        return self::VALIDATION_STEPS[$action] ?? $action;
    }

    public static function is_force_action(string $action): bool {
        return isset(self::FORCE_ACTIONS[$action]);
    }

    public static function base_action(string $action): string {
        return self::FORCE_ACTIONS[$action] ?? $action;
    }

    public static function history_limit(): int {
        return self::MAX_WORKFLOW_HISTORY;
    }

    public static function automatic_retry_limit(): int {
        return self::MAX_AUTOMATIC_RETRIES;
    }

    public static function retry_mode(): string {
        return 'manual_only';
    }

    /**
     * Los workflows editoriales históricos no reintentan automáticamente.
     * El error queda visible y el editor decide si ejecuta de nuevo la etapa.
     */
    public static function should_retry(array $workflow, string $action, $error = null): bool {
        return self::MAX_AUTOMATIC_RETRIES > 0;
    }

    public static function blocks_interactive_mutation(array $workflow): bool {
        return (string) ($workflow['status'] ?? '') === self::STATUS_PROCESSING;
    }

    public static function transition_allowed(string $from, string $to): bool {
        return in_array($to, self::ALLOWED_TRANSITIONS[$from] ?? [], true);
    }

    public static function initialize(array $workflow): array {
        $workflow['status'] = self::STATUS_DRAFT;
        return $workflow;
    }

    public static function mark_processing(array $workflow, string $action): array {
        self::transition_allowed((string) ($workflow['status'] ?? ''), self::STATUS_PROCESSING);
        $workflow['status'] = self::STATUS_PROCESSING;
        $workflow['current_action'] = $action;
        return $workflow;
    }

    public static function mark_failed(array $workflow, string $message): array {
        self::transition_allowed((string) ($workflow['status'] ?? ''), self::STATUS_FAILED);
        $workflow['status'] = self::STATUS_FAILED;
        $workflow['last_error'] = $message;
        return $workflow;
    }

    public static function mark_completed(array $workflow, string $last_action): array {
        self::transition_allowed((string) ($workflow['status'] ?? ''), self::STATUS_COMPLETED);
        $workflow['status'] = self::STATUS_COMPLETED;
        $workflow['last_action'] = $last_action;
        $workflow['last_error'] = '';
        return $workflow;
    }

    /**
     * Devuelve el mismo código de bloqueo usado históricamente por el panel.
     * Cadena vacía significa que la acción puede avanzar.
     */
    public static function advance_block_reason(string $action, array $workflow): string {
        $step = self::validation_step_for_action($action);

        if ($step === self::ACTION_GENERATE
            && class_exists('IDG_Temporary_Material')
            && IDG_Temporary_Material::has_blocking_file_error($workflow)) {
            return 'invalid_temp_material';
        }
        if ($step === self::ACTION_GENERATE && trim((string) ($workflow['keyword'] ?? '')) === '') {
            return 'needs_keyword';
        }
        if ($step === self::ACTION_GENERATE
            && trim((string) ($workflow['brief_fact'] ?? '')) === ''
            && !self::is_sponsored_workflow($workflow)) {
            return 'needs_brief';
        }
        if ($step === self::ACTION_GENERATE
            && self::is_sponsored_workflow($workflow)
            && trim((string) ($workflow['sponsored_brief'] ?? '')) === ''
            && trim((string) ($workflow['brief_fact'] ?? '')) === '') {
            return 'needs_sponsored_brief';
        }
        if ($step === self::ACTION_EDITORIAL && trim((string) ($workflow['base_article'] ?? '')) === '') {
            return 'needs_article';
        }
        if ($step === self::ACTION_SEO && trim((string) ($workflow['editorial_result'] ?? '')) === '') {
            return 'needs_editorial';
        }
        if ($step === self::ACTION_DRAFT && trim((string) ($workflow['seo_result'] ?? '')) === '') {
            return 'needs_seo';
        }
        if ($step === self::ACTION_DRAFT
            && (string) ($workflow['workflow_origin'] ?? '') === 'recurring_update'
            && (int) ($workflow['recurring_target_post_id'] ?? 0) <= 0) {
            return 'needs_recurring_target';
        }

        return '';
    }

    private static function is_sponsored_workflow(array $workflow): bool {
        $piece_type_raw = (string) ($workflow['piece_type'] ?? '');
        $piece_type = function_exists('mb_strtolower') ? mb_strtolower($piece_type_raw) : strtolower($piece_type_raw);
        return str_contains($piece_type, 'patrocinado') || str_contains($piece_type, 'colaboraci');
    }
}
