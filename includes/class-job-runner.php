<?php
if (!defined('ABSPATH')) {
    exit;
}

final class IDG_Job_Runner {
    private static function run_lock_key(string $workflow_id): string {
        return 'idg_processing_run_' . hash('sha256', $workflow_id);
    }

    private static function recovery_lock_key(string $workflow_id): string {
        return 'idg_processing_recovery_' . hash('sha256', $workflow_id);
    }

    public static function schedule(string $workflow_id, string $action): bool {
        if ($workflow_id === '' || !IDG_Workflow_Policies::is_known_action($action)) {
            IDG_Logger::log('workflow_schedule_rejected', 'Acción o identificador desconocido.', ['workflow_id' => $workflow_id, 'action' => $action]);
            return false;
        }
        if (function_exists('as_enqueue_async_action')) {
            $queued_id = as_enqueue_async_action(IDG_ACTION_HOOK, [$workflow_id, $action], 'ideasdi-gerizim', true);
            return is_numeric($queued_id) && (int) $queued_id > 0;
        }

        // Historical synchronous fallback is not a queued task.
        if (class_exists('IDG_Workflow_Orchestrator')) {
            IDG_Workflow_Orchestrator::process_scheduled_action($workflow_id, $action);
        } else {
            self::process_scheduled_action($workflow_id, $action);
        }
        return false;
    }

    public static function process_scheduled_action(string $workflow_id, string $action): void {
        if ($workflow_id === '' || !IDG_Workflow_Policies::is_known_action($action)) {
            IDG_Logger::log('workflow_action_rejected', 'Acción o identificador desconocido.', ['workflow_id' => $workflow_id, 'action' => $action]);
            return;
        }
        $workflow = self::get_workflow($workflow_id);
        if (empty($workflow)) {
            IDG_Logger::log('workflow_missing', 'No se encontró el flujo solicitado.', ['workflow_id' => $workflow_id]);
            return;
        }
        if (!IDG_Workflow_Policies::transition_allowed((string) ($workflow['status'] ?? ''), IDG_Workflow_Policies::STATUS_PROCESSING)) {
            IDG_Logger::log('workflow_transition_rejected', 'Estado incompatible con el inicio de una acción.', ['workflow_id' => $workflow_id, 'action' => $action]);
            return;
        }
        if (!function_exists('add_option') || !function_exists('delete_option') || !function_exists('wp_generate_uuid4')) {
            IDG_Logger::log('workflow_lock_unavailable', 'No hay barrera atómica para ejecutar el flujo.', ['workflow_id' => $workflow_id]);
            return;
        }
        $token = wp_generate_uuid4();
        if (!is_string($token) || $token === '') {
            return;
        }
        $lock_key = self::run_lock_key($workflow_id);
        if (!add_option($lock_key, $token, '', false)) {
            IDG_Logger::log('workflow_execution_busy', 'Ya existe una ejecución reservada para el flujo.', ['workflow_id' => $workflow_id]);
            return;
        }
        try {
            // Re-read after claiming: concurrent requests cannot start the same job.
            $workflow = self::get_workflow($workflow_id);
            if (empty($workflow) || !IDG_Workflow_Policies::transition_allowed((string) ($workflow['status'] ?? ''), IDG_Workflow_Policies::STATUS_PROCESSING)) {
                return;
            }
            if (class_exists('IDG_Assignment_Card')) {
                $workflow = IDG_Assignment_Card::attach($workflow);
            }
            $workflow = IDG_Workflow_Policies::mark_processing($workflow, $action);
            $workflow['processing_run_token'] = $token;
            $workflow['processing_started_at'] = time();
            if (!self::save_workflow($workflow_id, $workflow)) {
                IDG_Logger::log('workflow_start_not_persisted', 'No se pudo guardar el estado de inicio.', ['workflow_id' => $workflow_id]);
                return;
            }
            try {
                $dispatched = IDG_Workflow_Action_Strategy_Center::execute($workflow_id, $action, $workflow);
                if ($dispatched === false) {
                    throw new RuntimeException('No existe una estrategia compatible con la acción.');
                }
                // A synchronous strategy must finish in completed/failed status.
                $after = self::get_workflow($workflow_id);
                if (($after['status'] ?? '') === IDG_Workflow_Policies::STATUS_PROCESSING
                    && ($after['processing_run_token'] ?? '') === $token) {
                    throw new RuntimeException('La estrategia terminó sin registrar un resultado final.');
                }
            } catch (Throwable $e) {
                IDG_Workflow_Policies::should_retry($workflow, $action, $e);
                $current = self::get_workflow($workflow_id);
                if (($current['processing_run_token'] ?? '') === $token
                    && ($current['status'] ?? '') === IDG_Workflow_Policies::STATUS_PROCESSING) {
                    $current = IDG_Workflow_Policies::mark_failed($current, $e->getMessage());
                    $current = self::append_history($current, $action . '_exception', $e->getMessage());
                    self::save_workflow($workflow_id, $current);
                }
                IDG_Logger::log('workflow_exception', $e->getMessage(), ['workflow_id' => $workflow_id, 'action' => $action]);
            }
        } finally {
            // The old worker must never release a lock belonging to another run.
            if (get_option($lock_key, '') === $token) {
                delete_option($lock_key);
            }
        }
    }

    /**
     * A stale processing state can only be marked failed on explicit admin action.
     * It never resends model calls or creates/updates WordPress posts.
     * Historical runs with missing evidence, and draft/recurring writes, fail closed.
     */
    public static function recover_stale_processing(string $workflow_id): array {
        $workflow = self::get_workflow($workflow_id);
        if (!IDG_Workflow_Policies::recoverable_processing($workflow)) {
            return ['ok' => false, 'reason' => 'state_not_verified_stale'];
        }
        if (!function_exists('add_option') || !function_exists('delete_option') || !function_exists('wp_generate_uuid4')) {
            return ['ok' => false, 'reason' => 'recovery_lock_unavailable'];
        }
        // If Action Scheduler exists, its queue must be inspectable and idle.
        if (function_exists('as_enqueue_async_action')) {
            if (!function_exists('as_get_scheduled_actions')) {
                return ['ok' => false, 'reason' => 'queue_unverifiable'];
            }
            foreach (['pending', 'in-progress'] as $queue_status) {
                $matching = as_get_scheduled_actions([
                    'hook' => IDG_ACTION_HOOK,
                    'args' => [$workflow_id, (string) $workflow['current_action']],
                    'status' => $queue_status,
                    'per_page' => 1,
                ], 'ids');
                if (!is_array($matching) || $matching !== []) {
                    return ['ok' => false, 'reason' => 'queue_not_idle'];
                }
            }
        }
        $old_token = (string) $workflow['processing_run_token'];
        $run_key = self::run_lock_key($workflow_id);
        $existing_lock = get_option($run_key, false);
        if ($existing_lock !== false && $existing_lock !== $old_token) {
            return ['ok' => false, 'reason' => 'run_lock_mismatch'];
        }
        $recovery_key = self::recovery_lock_key($workflow_id);
        $new_token = wp_generate_uuid4();
        if (!is_string($new_token) || $new_token === '' || !add_option($recovery_key, $new_token, '', false)) {
            return ['ok' => false, 'reason' => 'recovery_busy'];
        }
        try {
            $current = self::get_workflow($workflow_id);
            if (!IDG_Workflow_Policies::recoverable_processing($current)
                || (string) ($current['processing_run_token'] ?? '') !== $old_token
                || get_option($run_key, false) !== $existing_lock) {
                return ['ok' => false, 'reason' => 'state_changed'];
            }
            $current = IDG_Workflow_Policies::mark_failed($current, 'Ejecución anterior sin cierre verificado. Recuperación manual: revisar resultados antes de reintentar.');
            $current['processing_run_token'] = $new_token; // Fences off delayed writes from the old worker.
            $current['recovered_at'] = time();
            $current = self::append_history($current, 'stale_processing_recovered', 'Tarea antigua marcada como fallida; no se realizaron nuevos llamados ni escrituras en WordPress.');
            if (!self::save_workflow($workflow_id, $current)) {
                return ['ok' => false, 'reason' => 'save_failed'];
            }
            // Only release the lock that was bound to this recovered execution.
            if ($existing_lock === $old_token) {
                delete_option($run_key);
            }
            IDG_Logger::log('workflow_processing_recovered', 'Recuperación manual sin reejecución.', ['workflow_id' => $workflow_id]);
            return ['ok' => true, 'reason' => 'marked_failed'];
        } finally {
            if (get_option($recovery_key, '') === $new_token) {
                delete_option($recovery_key);
            }
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

    /** Guard against delayed writes by a previous execution or a stale browser. */
    public static function save_workflow(string $workflow_id, array $data): bool {
        $existing = self::get_workflow($workflow_id);
        $previous_token = (string) ($existing['processing_run_token'] ?? '');
        $incoming_token = (string) ($data['processing_run_token'] ?? '');
        if ($previous_token !== $incoming_token && $existing !== []) {
            $new_run_claim = $incoming_token !== ''
                && ($existing['status'] ?? '') !== IDG_Workflow_Policies::STATUS_PROCESSING
                && ($data['status'] ?? '') === IDG_Workflow_Policies::STATUS_PROCESSING
                && get_option(self::run_lock_key($workflow_id), false) === $incoming_token;
            $manual_recovery_claim = $incoming_token !== ''
                && ($existing['status'] ?? '') === IDG_Workflow_Policies::STATUS_PROCESSING
                && ($data['status'] ?? '') === IDG_Workflow_Policies::STATUS_FAILED
                && get_option(self::recovery_lock_key($workflow_id), false) === $incoming_token;
            if (!$new_run_claim && !$manual_recovery_claim) {
                IDG_Logger::log('workflow_stale_write_rejected', 'Escritura con token de ejecución desactualizado.', ['workflow_id' => $workflow_id]);
                return false;
            }
        }
        update_option($workflow_id, $data, false);
        if (self::get_workflow($workflow_id) !== $data) {
            IDG_Logger::log('workflow_save_not_verified', 'El estado guardado no coincide con la lectura posterior.', ['workflow_id' => $workflow_id]);
            return false;
        }
        $user_id = get_current_user_id();
        if (!$user_id && !empty($data['user_id'])) {
            $user_id = (int) $data['user_id'];
        }
        if ($user_id) {
            update_user_meta($user_id, IDG_SESSION_KEY_PREFIX . 'current', $workflow_id);
        }
        return true;
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
            foreach (['force_validation_override', 'force_confirmation', 'force_confirmation_user_id', 'force_confirmation_workflow_id', 'force_confirmation_content_hash', 'force_confirmation_nonce'] as $key) {
                unset($workflow[$key]);
            }
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
        $was_overridden = !empty($result['validation']['overridden']);
        if ($was_overridden) {
            $workflow['final_validation_overridden'] = true;
            $workflow['final_validation_ignored_errors'] = (array) ($result['validation']['ignored_errors'] ?? []);
            $workflow['manual_review_required'] = true;
        }
        $workflow = self::append_history($workflow, $was_overridden ? 'draft_created_with_editorial_override' : 'draft_created', $was_overridden
            ? 'Entrada pending nueva creada con validación rechazada, excepción editorial confirmada y revisión manual obligatoria.'
            : 'Entrada creada en Pendiente de revisión.');
        foreach (['force_validation_override', 'force_confirmation', 'force_confirmation_user_id', 'force_confirmation_workflow_id', 'force_confirmation_content_hash', 'force_confirmation_nonce'] as $key) {
            unset($workflow[$key]);
        }
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
