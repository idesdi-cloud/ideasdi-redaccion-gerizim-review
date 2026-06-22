<?php
if (!defined('ABSPATH')) {
    exit;
}

final class IDG_Job_Runner {
    private const MAX_WORKFLOW_HISTORY = 20;
    public static function schedule(string $workflow_id, string $action): bool {
        if (function_exists('as_enqueue_async_action')) {
            as_enqueue_async_action(IDG_ACTION_HOOK, [$workflow_id, $action], 'ideasdi-gerizim');
            return true;
        }

        // Fallback: execute immediately when Action Scheduler is not present.
        self::process_scheduled_action($workflow_id, $action);
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
        $workflow['status'] = 'processing';
        $workflow['current_action'] = $action;
        self::save_workflow($workflow_id, $workflow);

        try {
            if ($action === 'generate') {
                self::run_generate($workflow_id, $workflow);
            } elseif ($action === 'editorial') {
                self::run_editorial($workflow_id, $workflow);
            } elseif ($action === 'seo') {
                self::run_seo($workflow_id, $workflow);
            } elseif ($action === 'draft' || $action === 'draft_force') {
                if ($action === 'draft_force') {
                    $workflow['force_validation_override'] = true;
                    $workflow = self::append_history($workflow, 'draft_force_requested', 'El editor solicitó crear borrador ignorando la validación real.');
                } else {
                    unset($workflow['force_validation_override']);
                }
                self::run_draft($workflow_id, $workflow);
            }
        } catch (Throwable $e) {
            $workflow['status'] = 'failed';
            $workflow['last_error'] = $e->getMessage();
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
        $data['status'] = 'draft';
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

    private static function append_history(array $workflow, string $event, string $message): array {
        $history = isset($workflow['history']) && is_array($workflow['history']) ? $workflow['history'] : [];
        $history[] = [
            'time' => current_time('mysql'),
            'event' => sanitize_key($event),
            'message' => sanitize_text_field($message),
        ];
        if (count($history) > self::MAX_WORKFLOW_HISTORY) {
            $history = array_slice($history, -self::MAX_WORKFLOW_HISTORY);
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

    private static function run_generate(string $workflow_id, array $workflow): void {
        $client = new IDG_OpenAI_Client();
        $workflow = self::ensure_document_card($workflow_id, $workflow, $client);
        $prompt = IDG_Prompt_Library::generate_prompt(self::prepare_prompt_data($workflow, '', 'generate'));
        $result = $client->complete($prompt);

        if (!$result['success']) {
            $workflow['status'] = 'failed';
            $workflow['last_error'] = $result['message'];
            $workflow = self::append_history($workflow, 'generate_failed', $result['message']);
            self::save_workflow($workflow_id, $workflow);
            IDG_Logger::log('generate_failed', $result['message'], ['workflow_id' => $workflow_id]);
            return;
        }

        $workflow['base_article'] = self::sanitize_model_output((string) $result['content']);
        $workflow['generated_from_brief'] = true;
        $workflow['status'] = 'completed';
        $workflow['last_action'] = 'generate';
        $workflow['last_error'] = '';
        $workflow['usage_generate'] = $result['usage'] ?? [];
        $workflow['usage_generate_estimate'] = IDG_Usage_Estimator::record('generate', (array) ($result['usage'] ?? []), $workflow_id, (string) ($result['model'] ?? ''));
        $workflow = self::append_history($workflow, 'generate_completed', 'Artículo base generado desde el brief editorial.');
        self::save_workflow($workflow_id, $workflow);
        IDG_Logger::log('generate_completed', 'Artículo base generado desde WordPress.', ['workflow_id' => $workflow_id]);
    }

    private static function run_editorial(string $workflow_id, array $workflow): void {
        $client = new IDG_OpenAI_Client();
        $workflow = self::ensure_document_card($workflow_id, $workflow, $client);
        $prompt = IDG_Prompt_Library::editorial_prompt(self::prepare_prompt_data($workflow, (string) ($workflow['base_article'] ?? ''), 'editorial'));
        $result = $client->complete($prompt);

        if (!$result['success']) {
            $workflow['status'] = 'failed';
            $workflow['last_error'] = $result['message'];
            $workflow = self::append_history($workflow, 'editorial_failed', $result['message']);
            self::save_workflow($workflow_id, $workflow);
            IDG_Logger::log('editorial_failed', $result['message'], ['workflow_id' => $workflow_id]);
            return;
        }

        $workflow['editorial_result'] = self::sanitize_model_output((string) $result['content']);
        $workflow['status'] = 'completed';
        $workflow['last_action'] = 'editorial';
        $workflow['last_error'] = '';
        $workflow['usage_editorial'] = $result['usage'] ?? [];
        $workflow['usage_editorial_estimate'] = IDG_Usage_Estimator::record('editorial', (array) ($result['usage'] ?? []), $workflow_id, (string) ($result['model'] ?? ''));
        $workflow = self::append_history($workflow, 'editorial_completed', 'Revisión editorial completada.');
        self::save_workflow($workflow_id, $workflow);
        IDG_Logger::log('editorial_completed', 'Revisión editorial completada.', ['workflow_id' => $workflow_id]);
    }

    private static function run_seo(string $workflow_id, array $workflow): void {
        $article = (string) ($workflow['editorial_result'] ?? $workflow['base_article'] ?? '');
        $client = new IDG_OpenAI_Client();
        $workflow = self::ensure_document_card($workflow_id, $workflow, $client);
        $prompt = IDG_Prompt_Library::seo_prompt(self::prepare_prompt_data($workflow, $article, 'seo'));
        $result = $client->complete($prompt);

        if (!$result['success']) {
            $workflow['status'] = 'failed';
            $workflow['last_error'] = $result['message'];
            $workflow = self::append_history($workflow, 'seo_failed', $result['message']);
            self::save_workflow($workflow_id, $workflow);
            IDG_Logger::log('seo_failed', $result['message'], ['workflow_id' => $workflow_id]);
            return;
        }

        $workflow['seo_result'] = self::sanitize_model_output((string) $result['content']);
        $workflow['status'] = 'completed';
        $workflow['last_action'] = 'seo';
        $workflow['last_error'] = '';
        $workflow['usage_seo'] = $result['usage'] ?? [];
        $workflow['usage_seo_estimate'] = IDG_Usage_Estimator::record('seo', (array) ($result['usage'] ?? []), $workflow_id, (string) ($result['model'] ?? ''));
        $workflow['warnings'] = IDG_Validator::summarize($result['content'], (string) ($workflow['keyword'] ?? ''));
        $workflow['feedback_notes'] = self::extract_feedback_notes($result['content']);
        $workflow = self::append_history($workflow, 'seo_completed', 'Revisión SEO completada.');
        self::save_workflow($workflow_id, $workflow);
        IDG_Logger::log('seo_completed', 'Revisión SEO completada.', ['workflow_id' => $workflow_id]);
    }

    private static function run_draft(string $workflow_id, array $workflow): void {
        if (class_exists('IDG_Assignment_Card')) {
            $workflow = IDG_Assignment_Card::attach($workflow);
            self::save_workflow($workflow_id, $workflow);
        }
        $result = IDG_Post_Creator::create_pending_post($workflow);
        if (!$result['success']) {
            $workflow['status'] = 'failed';
            $workflow['last_error'] = $result['message'];
            if (!empty($result['validation_summary'])) {
                $workflow['final_validation_summary'] = (string) $result['validation_summary'];
            }
            $workflow = self::append_history($workflow, 'draft_failed', $result['message']);
            self::save_workflow($workflow_id, $workflow);
            IDG_Logger::log('draft_failed', $result['message'], ['workflow_id' => $workflow_id]);
            return;
        }

        $workflow['status'] = 'completed';
        $workflow['last_action'] = 'draft';
        $workflow['draft_post_id'] = $result['post_id'];
        $workflow['draft_edit_link'] = $result['edit_link'];
        $workflow['last_error'] = '';
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
        $workflow = self::append_history($workflow, 'draft_created', !empty($workflow['force_validation_override']) ? 'Entrada creada en Pendiente de revisión con validación ignorada manualmente.' : 'Entrada creada en Pendiente de revisión.');
        unset($workflow['force_validation_override']);
        self::save_workflow($workflow_id, $workflow);
        IDG_Logger::log('draft_created', 'Borrador creado en Pendiente de revisión.', ['workflow_id' => $workflow_id, 'post_id' => $result['post_id']]);
    }


    private static function sanitize_model_output(string $content): string {
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $bad_patterns = [
            '/\bNeed\s+provide\b/i',
            '/\bLet(?:\'|’)s\s+produce\b/i',
            '/\bOnly\s+article\s+base\b/i',
            '/\bmissing\s+(?:box|snippet)\b/i',
            '/falta\s+del\s+usuario\??/iu',
        ];
        $lines = preg_split('/\n/', $content);
        if (!is_array($lines)) {
            return trim($content);
        }
        $clean = [];
        foreach ($lines as $line) {
            $drop = false;
            foreach ($bad_patterns as $pattern) {
                if (preg_match($pattern, (string) $line)) {
                    $drop = true;
                    break;
                }
            }
            if (!$drop) {
                $clean[] = $line;
            }
        }
        return trim((string) preg_replace('/\n{3,}/', "\n\n", implode("\n", $clean)));
    }

    private static function ensure_web_research(string $workflow_id, array $workflow, IDG_OpenAI_Client $client): array {
        if (!class_exists('IDG_Web_Research')) {
            return $workflow;
        }
        return IDG_Web_Research::run($workflow_id, $workflow, $client);
    }

    private static function document_material_text(array $workflow): string {
        if (class_exists('IDG_Web_Research')) {
            return IDG_Web_Research::document_material($workflow);
        }
        return (string) ($workflow['temp_material_text'] ?? '');
    }

    private static function ensure_document_card(string $workflow_id, array $workflow, IDG_OpenAI_Client $client): array {
        $workflow = self::ensure_web_research($workflow_id, $workflow, $client);

        $material = self::document_material_text($workflow);
        if (trim($material) === '') {
            $workflow['document_card'] = '';
            $workflow['document_card_hash'] = '';
            return $workflow;
        }

        $hash = IDG_Temporary_Material::hash($material);
        if ($hash !== '' && !empty($workflow['document_card']) && (string) ($workflow['document_card_hash'] ?? '') === $hash) {
            return $workflow;
        }

        $workflow = self::append_history($workflow, 'material_card_started', 'Creando ficha documental temporal.');
        self::save_workflow($workflow_id, $workflow);

        $base_data = self::prepare_prompt_data($workflow, '', 'material');
        $chunks = IDG_Temporary_Material::chunks($material);
        if (empty($chunks)) {
            return $workflow;
        }

        $partial_cards = [];
        $total = count($chunks);
        foreach ($chunks as $index => $chunk) {
            $prompt = IDG_Prompt_Library::material_card_prompt($base_data, $chunk, $index + 1, $total);
            $result = $client->complete($prompt);
            if (!$result['success']) {
                $workflow['temp_material_warnings'][] = 'No se pudo crear la ficha documental temporal: ' . $result['message'];
                $workflow = self::append_history($workflow, 'material_card_failed', $result['message']);
                self::save_workflow($workflow_id, $workflow);
                return $workflow;
            }
            $partial_cards[] = trim((string) $result['content']);
            IDG_Usage_Estimator::record('material_card', (array) ($result['usage'] ?? []), $workflow_id, (string) ($result['model'] ?? ''));
        }

        $card = trim(implode("

", $partial_cards));
        if (count($partial_cards) > 1) {
            $merge_prompt = IDG_Prompt_Library::material_card_merge_prompt($base_data, $card);
            $merge_result = $client->complete($merge_prompt);
            if ($merge_result['success']) {
                IDG_Usage_Estimator::record('material_card_merge', (array) ($merge_result['usage'] ?? []), $workflow_id, (string) ($merge_result['model'] ?? ''));
                $card = trim((string) $merge_result['content']);
            } else {
                $workflow['temp_material_warnings'][] = 'La ficha documental temporal se creó por partes, pero no se pudo unificar automáticamente.';
            }
        }

        $workflow['document_card'] = $card;
        $workflow['document_card_hash'] = $hash;
        $workflow['document_card_created_at'] = current_time('mysql');
        $workflow = self::append_history($workflow, 'material_card_completed', 'Ficha documental temporal creada.');
        self::save_workflow($workflow_id, $workflow);
        IDG_Logger::log('material_card_completed', 'Ficha documental temporal creada.', ['workflow_id' => $workflow_id, 'chunks' => count($chunks)]);
        return $workflow;
    }

    private static function extract_feedback_notes(string $content): string {
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        if (!preg_match('/^\s*(?:#{1,6}\s*)?(?:\*\*)?\s*RETROALIMENTACIÓN GERIZIM\s*(?:\*\*)?\s*:?\s*$/imu', $content, $m, PREG_OFFSET_CAPTURE)) {
            return '';
        }
        $start = $m[0][1] + strlen($m[0][0]);
        $tail = trim(substr($content, $start));
        if ($tail === '') {
            return '';
        }
        if (preg_match('/^\s*(?:#{1,6}\s*)?(?:\*\*)?\s*(?:ARTÍCULO FINAL|ARTICULO FINAL|META DESCRIPTION|INFORME SEO INTERNO|COPY PARA REDES|PAQUETE REEL)\s*(?:\*\*)?\s*:?\s*$/imu', $tail, $next, PREG_OFFSET_CAPTURE)) {
            $tail = trim(substr($tail, 0, $next[0][1]));
        }
        return sanitize_textarea_field($tail);
    }

    private static function prepare_prompt_data(array $workflow, string $article, string $phase = 'generic'): array {
        $category_name = '';
        if (!empty($workflow['category_id'])) {
            $term = get_term((int) $workflow['category_id'], 'category');
            if ($term && !is_wp_error($term)) {
                $category_name = $term->name;
            }
        }

        $tag_names = [];
        if (!empty($workflow['tag_ids']) && is_array($workflow['tag_ids'])) {
            foreach ($workflow['tag_ids'] as $tag_id) {
                $tag = get_term((int) $tag_id, 'post_tag');
                if ($tag && !is_wp_error($tag)) {
                    $tag_names[] = $tag->name;
                }
            }
        }

        $auto_priority = class_exists('IDG_Editorial_Recipe_Builder') ? IDG_Editorial_Recipe_Builder::recipe_text($workflow) : IDG_Priority_Readings::build_suggestion((int) ($workflow['category_id'] ?? 0), isset($workflow['tag_ids']) && is_array($workflow['tag_ids']) ? $workflow['tag_ids'] : []);
        $priority_readings = trim((string) ($workflow['priority_readings'] ?? $workflow['editorial_recipe'] ?? ''));
        if ($priority_readings === '' || str_contains($priority_readings, ';') || mb_strlen($priority_readings) > 240 || !empty($workflow['radar_source'])) {
            $priority_readings = $auto_priority;
        }
        $editorial_angle = trim((string) ($workflow['editorial_angle'] ?? ''));
        if ($editorial_angle === '') {
            $tag_text = !empty($tag_names) ? implode(', ', $tag_names) : 'sin tags específicos';
            $editorial_angle = 'Leer el caso desde la categoría ' . ($category_name !== '' ? $category_name : 'seleccionada') . ' y sus etiquetas (' . $tag_text . '), usando el hecho base y la fuente documental como prioridad factual. La lectura prioritaria automática debe orientar el enfoque sin convertirse en lista de subtítulos.';
        }

        return [
            'keyword' => $workflow['keyword'] ?? '',
            'entity' => $workflow['entity'] ?? '',
            'piece_type' => $workflow['piece_type'] ?? '',
            'brief_fact' => $workflow['brief_fact'] ?? '',
            'editorial_angle' => $editorial_angle,
            'priority_readings' => $priority_readings,
            'category_name' => $category_name,
            'tag_names' => $tag_names,
            'official_source' => $workflow['official_source'] ?? '',
            'internal_links' => $workflow['internal_links'] ?? '',
            'internal_links_structured' => $workflow['internal_links_structured'] ?? [],
            'editor_notes' => $workflow['editor_notes'] ?? '',
            'document_card' => $workflow['document_card'] ?? '',
            'assignment_card' => $workflow['assignment_card'] ?? '',
            'web_research_status' => $workflow['web_research_status'] ?? '',
            'web_research_intensity' => $workflow['web_research_intensity'] ?? '',
            'web_research_reason' => $workflow['web_research_reason'] ?? '',
            'web_research_card' => $workflow['web_research_card'] ?? '',
            'source_url_status' => $workflow['source_url_status'] ?? '',
            'source_information_url' => $workflow['source_information_url'] ?? '',
            'temporary_material_excerpt' => self::material_excerpt_for_phase($workflow, $phase),
            'temporary_material_mode' => self::material_mode_for_phase($workflow, $phase),
            'article' => $article,
        ];
    }
    private static function material_excerpt_for_phase(array $workflow, string $phase): string {
        $combined = self::document_material_text($workflow);
        if (trim($combined) === '') {
            return '';
        }
        if ($phase === 'generate') {
            return IDG_Temporary_Material::limit_text($combined, IDG_Temporary_Material::MAX_PROMPT_CHARS);
        }
        // Para revisión editorial y SEO usamos la ficha documental, no la nota completa,
        // para evitar reescrituras promocionales o pérdida de la voz editorial.
        return '';
    }

    private static function material_mode_for_phase(array $workflow, string $phase): string {
        $has_combined = trim(self::document_material_text($workflow)) !== '';
        $research_status = trim((string) ($workflow['web_research_status'] ?? ''));
        if (!$has_combined) {
            return 'Sin material documental activo. La investigación web controlada puede construir una ficha desde el brief.';
        }
        if ($phase === 'generate') {
            return 'Uso alto: brief + ficha documental temporal + extracto controlado del material/documentación/investigación web para generar el artículo base. Investigación web: ' . ($research_status !== '' ? $research_status : 'no registrada') . '.';
        }
        if ($phase === 'editorial') {
            return 'Uso medio: solo ficha documental temporal para verificar datos, conservar precisión y neutralizar tono promocional.';
        }
        if ($phase === 'seo') {
            return 'Uso bajo: solo ficha documental temporal para validar precisión; no reescribir desde documentación o búsqueda.';
        }
        if ($phase === 'material') {
            return 'Creación de ficha documental temporal desde material, URL e investigación web controlada.';
        }
        return 'Material documental activo.';
    }

}
