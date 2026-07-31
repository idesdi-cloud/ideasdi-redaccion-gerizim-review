<?php
if (!defined('ABSPATH')) {
    exit;
}

final class IDG_Traceability_Admin {
    private const QUEUE_RESULT_TRANSIENT_PREFIX = 'idg_traceability_process_queue_result_';
    private const QUEUE_RESULT_TRANSIENT_TTL = 300;

    public static function render_section(): void {
        $action = isset($_GET['traceability_action']) ? sanitize_key((string) $_GET['traceability_action']) : '';
        $queue_result = $action === 'processed' ? self::take_queue_result(get_current_user_id()) : null;
        $counts = class_exists('IDG_Traceability_Outbox') ? IDG_Traceability_Outbox::counts() : [];
        $latest = class_exists('IDG_Traceability_Outbox') ? IDG_Traceability_Outbox::latest_summary() : [];
        $problems = class_exists('IDG_Traceability_Outbox') ? IDG_Traceability_Outbox::problematic_rows(50) : [];
        $recapture_problems = class_exists('IDG_Traceability_Recapture') ? IDG_Traceability_Recapture::problem_rows(30) : [];
        $failure_markers = IDG_Traceability::recoverable_failure_marker_rows(30);
        $diag = IDG_Traceability::diagnostics();
        $cutoff = IDG_Traceability::live_capture_cutoff();
        ?>
        <div class="idg-card" style="margin-top:20px;">
            <h2>Trazabilidad Radar</h2>
            <p class="description">Diagnóstico local y cola idempotente. Esta sección no muestra tokens ni payloads editoriales.</p>
            <?php if ($action !== '') : ?>
                <div class="notice notice-info inline"><p><?php echo esc_html(self::notice_text($action, $queue_result)); ?></p></div>
            <?php endif; ?>
            <table class="widefat striped" style="max-width:900px;">
                <tbody>
                    <tr><th>Versión del contrato</th><td><?php echo esc_html(IDG_Traceability::contract_version()); ?></td></tr>
                    <tr><th>Captura habilitada</th><td><?php echo !empty($diag['capture_enabled']) ? 'Sí' : 'No'; ?></td></tr>
                    <tr><th>Entrega habilitada</th><td><?php echo !empty($diag['delivery_enabled']) ? 'Sí' : 'No'; ?></td></tr>
                    <tr><th>URL configurada</th><td><?php echo !empty($diag['url_configured']) ? 'Sí' : 'No'; ?></td></tr>
                    <tr><th>URL válida</th><td><?php echo !empty($diag['url_valid']) ? 'Sí' : 'No'; ?></td></tr>
                    <tr><th>Token configurado</th><td><?php echo !empty($diag['token_configured']) ? 'Sí' : 'No'; ?></td></tr>
                    <tr><th>Fecha de corte</th><td><?php echo esc_html($cutoff['raw'] !== '' ? $cutoff['raw'] : 'No configurada'); ?><?php echo !empty($diag['cutoff_valid']) ? ' · válida' : ' · inválida'; ?><br><span class="description">Corte histórico mínimo: <?php echo esc_html((string) ($cutoff['minimum'] ?? '')); ?></span></td></tr>
                    <tr><th>Tabla disponible</th><td><?php echo !empty($diag['table_available']) ? 'Sí' : 'No'; ?></td></tr>
                    <tr><th>Esquema completo</th><td><?php echo !empty($diag['schema_ready']) ? 'Sí' : 'No'; ?><?php if (empty($diag['schema_ready']) && !empty($diag['schema_errors'])) : ?><br><span class="description"><?php echo esc_html(implode(' · ', (array) $diag['schema_errors'])); ?></span><?php endif; ?></td></tr>
                    <tr><th>Scheduler disponible</th><td><?php echo !empty($diag['scheduler_available']) ? 'Sí' : 'No'; ?></td></tr>
                    <tr><th>Contrato compatible</th><td><?php echo !empty($diag['contract_compatible']) ? 'Sí' : 'No'; ?></td></tr>
                    <tr><th>Recapturas pendientes</th><td><?php echo esc_html((string) ($diag['recapture_pending'] ?? 0)); ?></td></tr>
                    <tr><th>Conflictos de recaptura</th><td><?php echo esc_html((string) ($diag['recapture_conflicts'] ?? 0)); ?></td></tr>
                    <tr><th>Marcas recuperables</th><td><?php echo esc_html((string) ($diag['recoverable_failure_markers'] ?? 0)); ?></td></tr>
                    <tr><th>queued</th><td><?php echo esc_html((string) ($counts['queued'] ?? 0)); ?></td></tr>
                    <tr><th>retry</th><td><?php echo esc_html((string) ($counts['retry'] ?? 0)); ?></td></tr>
                    <tr><th>failed</th><td><?php echo esc_html((string) ($counts['failed'] ?? 0)); ?></td></tr>
                    <tr><th>blocked</th><td><?php echo esc_html((string) ($counts['blocked'] ?? 0)); ?></td></tr>
                    <tr><th>sent</th><td><?php echo esc_html((string) ($counts['sent'] ?? 0)); ?></td></tr>
                    <tr><th>Última entrega</th><td><?php echo esc_html((string) (($latest['last_sent_at'] ?? '') ?: 'Sin entregas')); ?></td></tr>
                    <tr><th>Último error resumido</th><td><?php echo esc_html((string) (($latest['last_error'] ?? '') ?: 'Sin errores')); ?></td></tr>
                </tbody>
            </table>
            <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:16px;">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('idg_traceability_process_queue'); ?>
                    <input type="hidden" name="action" value="idg_traceability_process_queue">
                    <?php submit_button('Procesar cola ahora', 'secondary', 'submit', false); ?>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('idg_traceability_retry_temporary'); ?>
                    <input type="hidden" name="action" value="idg_traceability_retry_temporary">
                    <?php submit_button('Reintentar eventos temporales', 'secondary', 'submit', false); ?>
                </form>
            </div>
            <p class="description">El diagnóstico no crea eventos ni prueba la conexión. Los errores contractuales solo pueden reactivarse de forma individual después de revisar la causa.</p>

            <h3 style="margin-top:24px;">Filas problemáticas</h3>
            <?php if (empty($problems)) : ?>
                <p>Sin eventos failed o blocked.</p>
            <?php else : ?>
                <table class="widefat striped" style="max-width:1100px;">
                    <thead><tr><th>ID</th><th>Evento</th><th>Estado</th><th>Intentos</th><th>Error</th><th>Último error de transporte</th><th>Error de reflejo</th><th>Actualizado</th><th>Acción</th></tr></thead>
                    <tbody>
                    <?php foreach ($problems as $row) : ?>
                        <tr>
                            <td><?php echo esc_html((string) ($row['id'] ?? '')); ?></td>
                            <td><strong><?php echo esc_html((string) ($row['event_type'] ?? '')); ?></strong><br><code><?php echo esc_html((string) ($row['idempotency_key'] ?? '')); ?></code></td>
                            <td><?php echo esc_html((string) ($row['status'] ?? '')); ?></td>
                            <td><?php echo esc_html((string) ($row['attempts'] ?? 0)); ?></td>
                            <td><?php echo esc_html((string) ($row['last_error'] ?? '')); ?></td>
                            <td><?php echo esc_html((string) ($row['last_transport_error'] ?? '')); ?></td>
                            <td><?php echo esc_html((string) ($row['reflection_last_error'] ?? '')); ?><?php if (!empty($row['reflection_attempts'])) : ?><br><span class="description"><?php echo esc_html((string) $row['reflection_attempts']); ?> intentos</span><?php endif; ?></td>
                            <td><?php echo esc_html((string) ($row['updated_at'] ?? '')); ?></td>
                            <td>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                    <?php wp_nonce_field('idg_traceability_reactivate_event_' . (int) ($row['id'] ?? 0)); ?>
                                    <input type="hidden" name="action" value="idg_traceability_reactivate_event">
                                    <input type="hidden" name="event_id" value="<?php echo esc_attr((string) ($row['id'] ?? 0)); ?>">
                                    <?php submit_button('Reactivar revisado', 'small', 'submit', false); ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <h3 style="margin-top:24px;">Recapturas problemáticas</h3>
            <?php if (empty($recapture_problems)) : ?>
                <p>Sin conflictos ni recapturas bloqueadas.</p>
            <?php else : ?>
                <table class="widefat striped" style="max-width:1100px;">
                    <thead><tr><th>Evento</th><th>Clave</th><th>Estado</th><th>Error</th><th>Actualizado</th></tr></thead>
                    <tbody>
                    <?php foreach ($recapture_problems as $intent) : ?>
                        <tr>
                            <td><?php echo esc_html((string) ($intent['event_type'] ?? '')); ?></td>
                            <td><code><?php echo esc_html((string) ($intent['idempotency_key'] ?? '')); ?></code></td>
                            <td><?php echo esc_html((string) (($intent['state'] ?? '') ?: ($intent['status'] ?? ''))); ?></td>
                            <td><?php echo esc_html((string) ($intent['last_error'] ?? '')); ?></td>
                            <td><?php echo esc_html((string) ($intent['updated_at'] ?? '')); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <h3 style="margin-top:24px;">Fallos de persistencia recuperables</h3>
            <?php if (empty($failure_markers)) : ?>
                <p>Sin marcas alternativas pendientes.</p>
            <?php else : ?>
                <table class="widefat striped" style="max-width:1100px;">
                    <thead><tr><th>Post</th><th>Evento</th><th>Clave</th><th>Error</th><th>Integridad</th><th>Registrado</th></tr></thead>
                    <tbody>
                    <?php foreach ($failure_markers as $marker) : ?>
                        <tr>
                            <td><?php echo esc_html((string) ($marker['post_id'] ?? 0)); ?></td>
                            <td><?php echo esc_html((string) ($marker['event_type'] ?? '')); ?></td>
                            <td><code><?php echo esc_html((string) ($marker['idempotency_key'] ?? '')); ?></code></td>
                            <td><?php echo esc_html((string) ($marker['persistence_error'] ?? '')); ?></td>
                            <td><?php echo !empty($marker['valid']) ? 'Verificada' : 'Inválida'; ?></td>
                            <td><?php echo esc_html((string) ($marker['marked_at_utc'] ?? '')); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function handle_process_queue(): void {
        self::authorize('idg_traceability_process_queue');
        $result = self::normalize_queue_result(IDG_Traceability_Outbox::process_queue(20));
        $user_id = get_current_user_id();
        if (!self::store_queue_result($user_id, $result)) {
            IDG_Logger::log('traceability_queue_result_not_persisted', 'No se pudo guardar temporalmente el resultado del procesamiento manual.', [
                'user_id' => $user_id,
                'early_exit_reason' => (string) ($result['early_exit_reason'] ?? ''),
            ]);
        }
        IDG_Logger::log('traceability_queue_processed', 'Procesamiento manual de cola completado.', [
            'processed' => (int) ($result['processed'] ?? 0),
            'sent' => (int) ($result['sent'] ?? 0),
            'retry' => (int) ($result['retry'] ?? 0),
            'failed' => (int) ($result['failed'] ?? 0),
            'blocked' => (int) ($result['blocked'] ?? 0),
            'candidate_count' => (int) ($result['candidate_count'] ?? 0),
            'candidate_ids' => (array) ($result['candidate_ids'] ?? []),
            'claim_success_count' => count((array) ($result['claim_success_ids'] ?? [])),
            'claim_success_ids' => (array) ($result['claim_success_ids'] ?? []),
            'claim_failed_count' => count((array) ($result['claim_failed_ids'] ?? [])),
            'claim_failed_ids' => (array) ($result['claim_failed_ids'] ?? []),
            'claim_failure_reasons' => (array) ($result['claim_failure_reasons'] ?? []),
            'early_exit_reason' => (string) ($result['early_exit_reason'] ?? ''),
            'sql_error' => (string) ($result['sql_error'] ?? ''),
            'delivery_enabled' => IDG_Traceability::delivery_enabled() ? 'yes' : 'no',
        ]);
        self::redirect('processed');
    }

    public static function handle_retry_temporary(): void {
        self::authorize('idg_traceability_retry_temporary');
        $count = IDG_Traceability_Outbox::retry_temporary_events();
        IDG_Traceability_Outbox::reconcile(50);
        IDG_Traceability::schedule_processing();
        IDG_Logger::log('traceability_retry_requested', 'Reintento manual de eventos temporales solicitado.', ['rows' => $count]);
        self::redirect('retried');
    }

    public static function handle_reactivate_event(): void {
        $id = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;
        self::authorize('idg_traceability_reactivate_event_' . $id);
        $result = IDG_Traceability_Outbox::reactivate_reviewed_event($id);
        if (!empty($result['success'])) {
            IDG_Traceability::schedule_processing();
            IDG_Logger::log('traceability_event_reactivated', 'Evento de trazabilidad reactivado después de revisión.', ['event_id' => $id, 'previous_error' => (string) ($result['previous_error'] ?? ''), 'user_id' => get_current_user_id()]);
            self::redirect('reactivated');
        }
        IDG_Logger::log('traceability_event_reactivation_failed', 'No se pudo reactivar el evento de trazabilidad.', ['event_id' => $id, 'reason' => (string) ($result['reason'] ?? '')]);
        self::redirect('reactivation_failed');
    }

    private static function authorize(string $nonce): void {
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos suficientes.');
        }
        check_admin_referer($nonce);
    }

    private static function redirect(string $action): void {
        wp_safe_redirect(admin_url('admin.php?page=ideasdi-gerizim-settings&traceability_action=' . rawurlencode($action)));
        exit;
    }

    private static function queue_result_transient_key(int $user_id): string {
        return self::QUEUE_RESULT_TRANSIENT_PREFIX . max(0, $user_id);
    }

    private static function store_queue_result(int $user_id, array $result): bool {
        $key = self::queue_result_transient_key($user_id);
        $normalized = self::normalize_queue_result($result);
        if (set_transient($key, $normalized, self::QUEUE_RESULT_TRANSIENT_TTL)) {
            return true;
        }
        return get_transient($key) === $normalized;
    }

    private static function take_queue_result(int $user_id): ?array {
        $key = self::queue_result_transient_key($user_id);
        $result = get_transient($key);
        delete_transient($key);
        return is_array($result) ? self::normalize_queue_result($result) : null;
    }

    private static function normalize_queue_result(array $result): array {
        $allowed_reasons = [
            'schema_not_ready',
            'delivery_disabled',
            'delivery_configuration_invalid',
            'sql_selection_error',
            'no_candidates',
            'candidates_not_claimed',
            'claim_verification_failed',
            'completed',
        ];
        $reason = sanitize_key((string) ($result['early_exit_reason'] ?? ''));
        if (!in_array($reason, $allowed_reasons, true)) {
            $reason = '';
        }
        $failure_reasons = [];
        foreach ((array) ($result['claim_failure_reasons'] ?? []) as $id => $failure_reason) {
            $id = absint($id);
            $failure_reason = sanitize_key((string) $failure_reason);
            if ($id > 0 && in_array($failure_reason, ['claim_update_failed', 'claimed_row_not_found', 'lock_token_mismatch'], true)) {
                $failure_reasons[$id] = $failure_reason;
            }
        }
        return [
            'processed' => max(0, (int) ($result['processed'] ?? 0)),
            'sent' => max(0, (int) ($result['sent'] ?? 0)),
            'retry' => max(0, (int) ($result['retry'] ?? 0)),
            'failed' => max(0, (int) ($result['failed'] ?? 0)),
            'blocked' => max(0, (int) ($result['blocked'] ?? 0)),
            'candidate_ids' => array_values(array_filter(array_map('absint', (array) ($result['candidate_ids'] ?? [])))),
            'candidate_count' => max(0, (int) ($result['candidate_count'] ?? 0)),
            'claim_success_ids' => array_values(array_filter(array_map('absint', (array) ($result['claim_success_ids'] ?? [])))),
            'claim_failed_ids' => array_values(array_filter(array_map('absint', (array) ($result['claim_failed_ids'] ?? [])))),
            'claim_failure_reasons' => $failure_reasons,
            'early_exit_reason' => $reason,
            'sql_error' => sanitize_text_field(wp_strip_all_tags((string) ($result['sql_error'] ?? ''))),
        ];
    }

    private static function notice_text(string $action, ?array $queue_result = null): string {
        if ($action === 'processed') {
            $result = self::normalize_queue_result($queue_result ?? []);
            $reason = (string) ($result['early_exit_reason'] ?? '');
            if ($reason === '') {
                $reason = 'result_unavailable';
            }
            return sprintf(
                'Procesados: %d · Enviados: %d · Reintentos: %d · Fallidos: %d · Bloqueados: %d · Candidatos: %d · Reclamos correctos: %d · Reclamos fallidos: %d · Motivo: %s',
                (int) $result['processed'],
                (int) $result['sent'],
                (int) $result['retry'],
                (int) $result['failed'],
                (int) $result['blocked'],
                (int) $result['candidate_count'],
                count((array) $result['claim_success_ids']),
                count((array) $result['claim_failed_ids']),
                $reason
            );
        }
        if ($action === 'retried') {
            return 'Los eventos temporales fueron preparados para un nuevo intento. Los errores contractuales no se reactivaron.';
        }
        if ($action === 'reactivated') {
            return 'El evento revisado fue reactivado individualmente.';
        }
        if ($action === 'reactivation_failed') {
            return 'El evento no pudo reactivarse; revisa dependencia, fecha de corte, esquema o lock.';
        }
        return 'Acción de trazabilidad completada.';
    }
}
