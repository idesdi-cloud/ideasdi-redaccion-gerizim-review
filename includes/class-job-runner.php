<?php
if (!defined('ABSPATH')) {
    exit;
}

final class IDG_Job_Runner {
    public static function schedule(string $workflow_id, string $action): bool {
        if (function_exists('as_enqueue_async_action')) {
            as_enqueue_async_action(IDG_ACTION_HOOK, [$workflow_id, $action], 'ideasdi-gerizim');
            return true;
        }

        // Fallback: execute immediately when Action Scheduler is not present.
        if (class_exists('IDG_Workflow_Orchestrator')) {
            IDG_Workflow_Orchestrator::process_scheduled_action($workflow_id, $action);
        } else {
            self::process_scheduled_action($workflow_id, $action);
        }
        return false;
    }

    public static function process_scheduled_action(string $workflow_id, string $action): void {
        $workflow = self::get_workflow($workflow_id);
        if (empty($workflow)) {
            IDG_Logger::log('workflow_missing', 'No se encontró el flujo solicitado.', ['workflow_id' => $workflow_id]);
            return;
        }

        if (class_exists('IDG_Assignment_Card')) {
            $workflow = IDG_Assignment_Card::attach($workflow);
        }
        $workflow = IDG_Workflow_Policies::mark_processing($workflow, $action);
        self::save_workflow($workflow_id, $workflow);

        try {
            IDG_Workflow_Action_Strategy_Center::execute($workflow_id, $action, $workflow);
        } catch (Throwable $e) {
            IDG_Workflow_Policies::should_retry($workflow, $action, $e);
            $workflow = IDG_Workflow_Policies::mark_failed($workflow, $e->getMessage());
            $workflow = self::append_history($workflow, $action . '_exception', $e->getMessage());
            self::save_workflow($workflow_id, $workflow);
            IDG_Logger::log('workflow_exception', $e->getMessage(), ['workflow_id' => $workflow_id, 'action' => $action]);
        }
    }

    public static function new_workflow(array $data): string {
        $workflow_id = 'idg_' . wp_generate_uuid4();
        $data['workflow_id'] = $workflow_id;
        $data['created_at'] = current_time('mysql');
        $data['user_id'] = get_current_user_id();
        $data = IDG_Workflow_Policies::initialize($data);
        if (class_exists('IDG_Assignment_Card')) {
            $data = IDG_Assignment_Card::attach($data);
        }
        $data = self::append_history($data, 'flow_started', 'Flujo creado.');
        self::save_workflow($workflow_id, $data);
        return $workflow_id;
    }

    public static function get_workflow(string $workflow_id): array {
        $workflow = get_option($workflow_id, []);
        return is_array($workflow) ? $workflow : [];
    }

    public static function save_workflow(string $workflow_id, array $data): void {
        update_option($workflow_id, $data, false);
        $user_id = get_current_user_id();
        if (!$user_id && !empty($data['user_id'])) {
            $user_id = (int) $data['user_id'];
        }
        if ($user_id) {
            update_user_meta($user_id, IDG_SESSION_KEY_PREFIX . 'current', $workflow_id);
        }
    }

    public static function delete_workflow(string $workflow_id): void {
        if ($workflow_id !== '') {
            delete_option($workflow_id);
        }
    }

    public static function add_history(string $workflow_id, string $event, string $message): void {
        $workflow = self::get_workflow($workflow_id);
        if (empty($workflow)) {
            return;
        }
        $workflow = self::append_history($workflow, $event, $message);
        self::save_workflow($workflow_id, $workflow);
    }

    /**
     * Punto de compatibilidad para estrategias: conserva exactamente el
     * historial producido por el runner histórico.
     */
    public static function append_history_snapshot(array $workflow, string $event, string $message): array {
        return self::append_history($workflow, $event, $message);
    }

    private static function append_history(array $workflow, string $event, string $message): array {
        $history = isset($workflow['history']) && is_array($workflow['history']) ? $workflow['history'] : [];
        $history[] = [
            'time' => current_time('mysql'),
            'event' => sanitize_key($event),
            'message' => sanitize_text_field($message),
        ];
        if (count($history) > IDG_Workflow_Policies::history_limit()) {
            $history = array_slice($history, -IDG_Workflow_Policies::history_limit());
        }
        $workflow['history'] = $history;
        return $workflow;
    }

    public static function current_workflow(): array {
        $workflow_id = (string) get_user_meta(get_current_user_id(), IDG_SESSION_KEY_PREFIX . 'current', true);
        return $workflow_id ? self::get_workflow($workflow_id) : [];
    }

    public static function clear_current_workflow(?int $user_id = null): void {
        $user_id = $user_id ?: get_current_user_id();
        delete_user_meta($user_id, IDG_SESSION_KEY_PREFIX . 'current');
    }

    /**
     * Entradas estables usadas por el centro de estrategias. Las fases de
     * planificación y redacción se resuelven fuera del runner desde RC1.6.3.
     */
    public static function execute_generate_stage(string $workflow_id, array $workflow): void {
        IDG_Workflow_Stage_Orchestrator::redact($workflow_id, IDG_Workflow_Policies::ACTION_GENERATE, $workflow);
    }

    public static function execute_editorial_stage(string $workflow_id, array $workflow): void {
        IDG_Workflow_Stage_Orchestrator::redact($workflow_id, IDG_Workflow_Policies::ACTION_EDITORIAL, $workflow);
    }

    public static function execute_seo_stage(string $workflow_id, array $workflow): void {
        IDG_Workflow_Stage_Orchestrator::redact($workflow_id, IDG_Workflow_Policies::ACTION_SEO, $workflow);
    }

    public static function execute_draft_stage(string $workflow_id, array $workflow): void {
        self::run_draft($workflow_id, $workflow);
    }

    public static function execute_recurring_content_stage(string $workflow_id, array $workflow): void {
        self::run_recurring_event_content($workflow_id, $workflow);
    }

    private static function run_draft(string $workflow_id, array $workflow): void {
        if (class_exists('IDG_Assignment_Card')) {
            $workflow = IDG_Assignment_Card::attach($workflow);
            self::save_workflow($workflow_id, $workflow);
        }
        $result = IDG_Post_Creator::create_pending_post($workflow);
        if (!$result['success']) {
            $workflow = IDG_Workflow_Policies::mark_failed($workflow, (string) $result['message']);
            if (!empty($result['validation_summary'])) {
                $workflow['final_validation_summary'] = (string) $result['validation_summary'];
            }
            $workflow = self::append_history($workflow, 'draft_failed', $result['message']);
            self::save_workflow($workflow_id, $workflow);
            IDG_Logger::log('draft_failed', $result['message'], ['workflow_id' => $workflow_id]);
            return;
        }

        $workflow = IDG_Workflow_Policies::mark_completed($workflow, 'draft');
        $workflow['draft_post_id'] = $result['post_id'];
        $workflow['draft_edit_link'] = $result['edit_link'];
        if (!empty($result['validation_summary'])) {
            $workflow['final_validation_summary'] = (string) $result['validation_summary'];
        }
        if (!empty($result['seo_result'])) {
            $workflow['seo_result'] = (string) $result['seo_result'];
        }
        if (!empty($result['reel_package'])) {
            $workflow['reel_package_postprocessed'] = (string) $result['reel_package'];
        }
        if (!empty($result['postprocessed_html'])) {
            $workflow['postprocessed_html'] = (string) $result['postprocessed_html'];
        }
        if (!empty($result['postprocessing_audit']) && is_array($result['postprocessing_audit'])) {
            $workflow['postprocessing_audit'] = $result['postprocessing_audit'];
        }
        $workflow = self::append_history($workflow, 'draft_created', !empty($workflow['force_validation_override']) ? 'Entrada creada en Pendiente de revisión con validación ignorada manualmente.' : 'Entrada creada en Pendiente de revisión.');
        unset($workflow['force_validation_override']);
        self::save_workflow($workflow_id, $workflow);
        if (class_exists('IDG_Traceability')) {
            IDG_Traceability::safe_capture_wordpress_post_created($workflow_id, (int) $result['post_id']);
        }
        IDG_Logger::log('draft_created', 'Entrada creada en Pendiente de revisión.', ['workflow_id' => $workflow_id, 'post_id' => $result['post_id']]);
    }


    private static function run_recurring_event_content(string $workflow_id, array $workflow): void {
        if (class_exists('IDG_Assignment_Card')) {
            $workflow = IDG_Assignment_Card::attach($workflow);
            self::save_workflow($workflow_id, $workflow);
        }
        $target_post_type = (string) ($workflow['recurring_target_post_type'] ?? '');
        $target_label = $target_post_type === 'post' ? 'concurso o convocatoria' : 'evento';
        $result = IDG_Post_Creator::update_existing_event($workflow);
        if (empty($result['success'])) {
            $workflow = IDG_Workflow_Policies::mark_failed($workflow, (string) ($result['message'] ?? 'No se pudo aplicar la redacción a la publicación.'));
            if (!empty($result['validation_summary'])) {
                $workflow['final_validation_summary'] = (string) $result['validation_summary'];
            }
            $workflow = self::append_history($workflow, 'recurring_event_content_failed', $workflow['last_error']);
            self::save_workflow($workflow_id, $workflow);
            IDG_Logger::log('recurring_event_content_failed', $workflow['last_error'], [
                'workflow_id' => $workflow_id,
                'post_id' => (int) ($workflow['recurring_target_post_id'] ?? 0),
            ]);
            return;
        }

        $workflow = IDG_Workflow_Policies::mark_completed($workflow, 'recurring_event_content');
        $workflow['recurring_event_content_updated'] = true;
        $workflow['recurring_event_content_updated_at'] = current_time('mysql');
        $workflow['recurring_actual_updated_post_id'] = (int) ($result['actual_updated_post_id'] ?? $result['post_id'] ?? 0);
        $workflow['recurring_same_post_id'] = !empty($result['same_post_id']);
        $workflow['recurring_title_updated_verified'] = !empty($result['title_updated']);
        $workflow['recurring_title_before_editorial'] = (string) ($result['title_before'] ?? '');
        $workflow['recurring_title_after_editorial'] = (string) ($result['title_after'] ?? '');
        $workflow['recurring_content_updated_verified'] = !empty($result['content_updated']);
        $workflow['recurring_excerpt_updated_verified'] = !empty($result['excerpt_updated']);
        $workflow['recurring_applied_content_hash'] = (string) ($result['applied_content_hash'] ?? '');
        $workflow['recurring_source_seo_hash'] = (string) ($result['source_seo_hash'] ?? '');
        $workflow['recurring_target_edit_link'] = (string) ($result['edit_link'] ?? $workflow['recurring_target_edit_link'] ?? '');
        if (!empty($result['validation_summary'])) {
            $workflow['final_validation_summary'] = (string) $result['validation_summary'];
        }
        if (!empty($result['seo_result'])) {
            $workflow['seo_result'] = (string) $result['seo_result'];
        }
        if (!empty($result['reel_package'])) {
            $workflow['reel_package_postprocessed'] = (string) $result['reel_package'];
        }
        if (!empty($result['postprocessed_html'])) {
            $workflow['postprocessed_html'] = (string) $result['postprocessed_html'];
        }
        if (!empty($result['postprocessing_audit']) && is_array($result['postprocessing_audit'])) {
            $workflow['postprocessing_audit'] = $result['postprocessing_audit'];
        }
        if (class_exists('IDG_Recurring_Updates')) {
            $workflow['recurring_target_signature'] = IDG_Recurring_Updates::editorial_target_signature((int) ($workflow['recurring_target_post_id'] ?? 0), $target_post_type);
        }
        $message = !empty($workflow['force_validation_override'])
            ? 'Redacción aplicada al ' . $target_label . ' ID ' . (int) ($workflow['recurring_actual_updated_post_id'] ?? 0) . ' con validación ignorada manualmente.'
            : 'Redacción aplicada al mismo ' . $target_label . ' ID ' . (int) ($workflow['recurring_actual_updated_post_id'] ?? 0) . ' después de la validación final.';
        $workflow = self::append_history($workflow, 'recurring_event_content_updated', $message);
        unset($workflow['force_validation_override']);
        self::save_workflow($workflow_id, $workflow);
        IDG_Logger::log('recurring_event_content_updated', $message, [
            'workflow_id' => $workflow_id,
            'post_id' => (int) ($workflow['recurring_target_post_id'] ?? 0),
        ]);
    }
}
