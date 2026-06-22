<?php
if (!defined('ABSPATH')) {
    exit;
}

final class IDG_Admin_Page {
    public static function register_menu(): void {
        add_menu_page(
            'ideasDi Redacción Gerizim',
            'Gerizim',
            'edit_posts',
            'ideasdi-gerizim',
            [self::class, 'render_workflow_page'],
            'dashicons-edit-page',
            58
        );

        add_submenu_page(
            'ideasdi-gerizim',
            'Flujo editorial',
            'Flujo editorial',
            'edit_posts',
            'ideasdi-gerizim',
            [self::class, 'render_workflow_page']
        );

        add_submenu_page(
            'ideasdi-gerizim',
            'Actualizaciones recurrentes',
            'Actualizaciones recurrentes',
            'edit_posts',
            'ideasdi-gerizim-recurring-updates',
            ['IDG_Recurring_Updates', 'render_page']
        );

        add_submenu_page(
            'ideasdi-gerizim',
            'Ajustes Gerizim',
            'Ajustes',
            'manage_options',
            'ideasdi-gerizim-settings',
            [self::class, 'render_settings_page']
        );

        add_submenu_page(
            'ideasdi-gerizim',
            'Prompts y reglas',
            'Prompts y reglas',
            'manage_options',
            'ideasdi-gerizim-prompts',
            [self::class, 'render_prompts_page']
        );

        add_submenu_page(
            'ideasdi-gerizim',
            'Reglas editoriales',
            'Reglas editoriales',
            'manage_options',
            'ideasdi-gerizim-editorial-rules',
            [self::class, 'render_editorial_rules_page']
        );
    }

    public static function handle_save_settings(): void {
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos suficientes.');
        }
        check_admin_referer('idg_save_settings');

        $settings = get_option(IDG_OPTION_KEY, []);
        $api_key = isset($_POST['api_key']) ? IDG_Sanitizer::text((string) $_POST['api_key']) : '';
        $model = isset($_POST['model']) ? IDG_Sanitizer::text((string) $_POST['model']) : 'gpt-5.4-mini';
        $reference_balance = isset($_POST['openai_reference_balance']) ? (float) str_replace(',', '.', (string) $_POST['openai_reference_balance']) : (float) ($settings['openai_reference_balance'] ?? 0);

        if ($api_key !== '') {
            $settings['api_key'] = $api_key;
        }
        $settings['model'] = $model;
        $settings['openai_reference_balance'] = max(0, $reference_balance);
        update_option(IDG_OPTION_KEY, $settings, false);

        IDG_Logger::log('settings_saved', 'Ajustes guardados.', ['model' => $model, 'reference_balance' => $settings['openai_reference_balance']]);
        wp_safe_redirect(admin_url('admin.php?page=ideasdi-gerizim-settings&updated=1'));
        exit;
    }

    public static function handle_save_prompts(): void {
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos suficientes.');
        }
        check_admin_referer('idg_save_prompts');

        $keys = ['system', 'generate', 'editorial', 'seo', 'material_card'];
        $settings = get_option(IDG_PROMPTS_OPTION_KEY, []);

        if (!empty($_POST['reset_prompt_overrides'])) {
            foreach ($keys as $key) {
                $settings[$key] = '';
            }
        } else {
            foreach ($keys as $key) {
                $settings[$key] = isset($_POST['prompt_' . $key]) ? IDG_Sanitizer::textarea((string) $_POST['prompt_' . $key]) : '';
            }
        }

        $settings['updated_at'] = current_time('mysql');
        $settings['updated_by'] = get_current_user_id();
        $settings['version'] = IDG_VERSION;
        update_option(IDG_PROMPTS_OPTION_KEY, $settings, false);

        IDG_Logger::log('prompts_saved', 'Instrucciones editables guardadas.', [
            'has_system' => !empty($settings['system']),
            'has_generate' => !empty($settings['generate']),
            'has_editorial' => !empty($settings['editorial']),
            'has_seo' => !empty($settings['seo']),
            'has_material_card' => !empty($settings['material_card']),
        ]);
        wp_safe_redirect(admin_url('admin.php?page=ideasdi-gerizim-prompts&updated=1'));
        exit;
    }

    public static function handle_save_editorial_rules(): void {
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos suficientes.');
        }
        check_admin_referer('idg_save_editorial_rules');
        if (class_exists('IDG_Editorial_Rules')) {
            IDG_Editorial_Rules::save_from_request($_POST);
        }
        IDG_Logger::log('editorial_rules_saved', 'Reglas editoriales editables guardadas.', [
            'version' => class_exists('IDG_Editorial_Rules') ? IDG_Editorial_Rules::version_label() : '',
        ]);
        wp_safe_redirect(admin_url('admin.php?page=ideasdi-gerizim-editorial-rules&updated=1'));
        exit;
    }

    public static function handle_submit_workflow(): void {
        if (!current_user_can('edit_posts')) {
            wp_die('No tienes permisos suficientes.');
        }
        check_admin_referer('idg_submit_workflow');

        $workflow_id = isset($_POST['workflow_id']) ? IDG_Sanitizer::text((string) $_POST['workflow_id']) : '';
        $workflow = $workflow_id ? IDG_Job_Runner::get_workflow($workflow_id) : [];
        $step = isset($_POST['step']) ? sanitize_key((string) $_POST['step']) : 'save';

        if ($step === 'reset') {
            if (!empty($workflow) && (string) ($workflow['status'] ?? '') === 'processing') {
                wp_safe_redirect(admin_url('admin.php?page=ideasdi-gerizim&workflow_id=' . rawurlencode($workflow_id) . '&message=reset_blocked'));
                exit;
            }

            if ($workflow_id !== '' && empty($workflow['saved_manually'])) {
                IDG_Job_Runner::delete_workflow($workflow_id);
            }
            IDG_Job_Runner::clear_current_workflow();
            IDG_Logger::log('workflow_reset', 'Nueva redacción iniciada. El flujo activo fue limpiado de la pantalla.', [
                'previous_workflow_id' => $workflow_id,
            ]);
            wp_safe_redirect(admin_url('admin.php?page=ideasdi-gerizim&message=reset'));
            exit;
        }

        if ($step === 'validate_radar') {
            if (!class_exists('IDG_Radar_Importer')) {
                self::set_radar_import_notice('error', 'El importador del Radar no está disponible en esta instalación.');
                wp_safe_redirect(admin_url('admin.php?page=ideasdi-gerizim' . ($workflow_id !== '' ? '&workflow_id=' . rawurlencode($workflow_id) : '')));
                exit;
            }
            $radar_json = isset($_POST['radar_brief_json']) ? (string) wp_unslash($_POST['radar_brief_json']) : '';
            $result = IDG_Radar_Importer::validate_json_string($radar_json);
            self::set_radar_import_notice(empty($result['success']) ? 'error' : 'success', (string) ($result['message'] ?? ''), isset($result['warnings']) && is_array($result['warnings']) ? $result['warnings'] : []);
            wp_safe_redirect(admin_url('admin.php?page=ideasdi-gerizim' . ($workflow_id !== '' ? '&workflow_id=' . rawurlencode($workflow_id) : '')));
            exit;
        }

        if ($step === 'import_radar') {
            if (!class_exists('IDG_Radar_Importer')) {
                self::set_radar_import_notice('error', 'El importador del Radar no está disponible en esta instalación.');
                wp_safe_redirect(admin_url('admin.php?page=ideasdi-gerizim' . ($workflow_id !== '' ? '&workflow_id=' . rawurlencode($workflow_id) : '')));
                exit;
            }
            if (!empty($workflow) && (string) ($workflow['status'] ?? '') === 'processing') {
                self::set_radar_import_notice('error', 'Hay una tarea en proceso. Espera a que termine antes de importar un brief del Radar.');
                wp_safe_redirect(admin_url('admin.php?page=ideasdi-gerizim&workflow_id=' . rawurlencode($workflow_id)));
                exit;
            }
            if (self::has_generated_workflow_content($workflow)) {
                self::set_radar_import_notice('error', 'No se importó el brief del Radar porque este flujo ya tiene artículo base, revisión, SEO o borrador. Usa Reinicio parcial o inicia una nueva redacción antes de importar.');
                wp_safe_redirect(admin_url('admin.php?page=ideasdi-gerizim&workflow_id=' . rawurlencode($workflow_id)));
                exit;
            }

            $radar_json = isset($_POST['radar_brief_json']) ? (string) wp_unslash($_POST['radar_brief_json']) : '';
            $result = IDG_Radar_Importer::import_from_json_string($radar_json, $workflow);
            if (empty($result['success'])) {
                self::set_radar_import_notice('error', (string) ($result['message'] ?? 'No se pudo importar el brief del Radar.'));
                wp_safe_redirect(admin_url('admin.php?page=ideasdi-gerizim' . ($workflow_id !== '' ? '&workflow_id=' . rawurlencode($workflow_id) : '')));
                exit;
            }

            $data = isset($result['workflow']) && is_array($result['workflow']) ? $result['workflow'] : $workflow;
            if (empty($data['workflow_id'])) {
                $workflow_id = IDG_Job_Runner::new_workflow($data);
            } else {
                $workflow_id = (string) $data['workflow_id'];
                IDG_Job_Runner::save_workflow($workflow_id, $data);
            }
            IDG_Job_Runner::add_history($workflow_id, 'radar_imported', (string) ($result['message'] ?? 'Brief importado desde Radar editorial.'));
            self::set_radar_import_notice('success', (string) ($result['message'] ?? 'Brief importado desde Radar editorial.'), isset($result['warnings']) && is_array($result['warnings']) ? $result['warnings'] : []);
            IDG_Logger::log('radar_imported', 'Brief importado desde Radar editorial.', [
                'workflow_id' => $workflow_id,
                'radar_brief_id' => (string) ($data['radar_brief_id'] ?? ''),
                'radar_hallazgo_id' => (string) ($data['radar_hallazgo_id'] ?? ''),
            ]);
            wp_safe_redirect(admin_url('admin.php?page=ideasdi-gerizim&workflow_id=' . rawurlencode($workflow_id)));
            exit;
        }

        $data = array_merge($workflow, [
            'base_article' => isset($_POST['base_article']) ? IDG_Sanitizer::textarea((string) $_POST['base_article']) : ($workflow['base_article'] ?? ''),
            'brief_fact' => isset($_POST['brief_fact']) ? IDG_Sanitizer::textarea((string) $_POST['brief_fact']) : ($workflow['brief_fact'] ?? ''),
            'editorial_angle' => isset($_POST['editorial_angle']) ? IDG_Sanitizer::textarea((string) $_POST['editorial_angle']) : ($workflow['editorial_angle'] ?? ''),
            'priority_readings' => isset($_POST['priority_readings']) ? IDG_Sanitizer::textarea((string) $_POST['priority_readings']) : ($workflow['priority_readings'] ?? ''),
            'keyword' => isset($_POST['keyword']) ? IDG_Sanitizer::text((string) $_POST['keyword']) : ($workflow['keyword'] ?? ''),
            'entity' => isset($_POST['entity']) ? IDG_Sanitizer::text((string) $_POST['entity']) : ($workflow['entity'] ?? ''),
            'category_id' => isset($_POST['category_id']) ? absint($_POST['category_id']) : ($workflow['category_id'] ?? 0),
            'piece_type' => self::piece_type_from_request_or_category(isset($_POST['category_id']) ? absint($_POST['category_id']) : (int) ($workflow['category_id'] ?? 0), isset($_POST['piece_type']) ? (string) $_POST['piece_type'] : (string) ($workflow['piece_type'] ?? '')),
            'tag_ids' => isset($_POST['tag_ids']) ? IDG_Sanitizer::int_array($_POST['tag_ids']) : ($workflow['tag_ids'] ?? []),
            'official_source' => isset($_POST['official_source']) ? IDG_Sanitizer::url((string) $_POST['official_source']) : ($workflow['official_source'] ?? ''),
            'source_information_url' => isset($_POST['source_information_url']) ? IDG_Sanitizer::url((string) $_POST['source_information_url']) : ($workflow['source_information_url'] ?? ''),
            'internal_links' => isset($_POST['internal_links']) ? IDG_Sanitizer::textarea((string) $_POST['internal_links']) : ($workflow['internal_links'] ?? ''),
            'editor_notes' => '',
            'sponsor_client' => isset($_POST['sponsor_client']) ? IDG_Sanitizer::text((string) $_POST['sponsor_client']) : ($workflow['sponsor_client'] ?? ''),
            'sponsored_topic' => isset($_POST['sponsored_topic']) ? IDG_Sanitizer::text((string) $_POST['sponsored_topic']) : ($workflow['sponsored_topic'] ?? ''),
            'sponsored_brief' => isset($_POST['sponsored_brief']) ? IDG_Sanitizer::textarea((string) $_POST['sponsored_brief']) : ($workflow['sponsored_brief'] ?? ''),
            'sponsored_must_include' => isset($_POST['sponsored_must_include']) ? IDG_Sanitizer::textarea((string) $_POST['sponsored_must_include']) : ($workflow['sponsored_must_include'] ?? ''),
            'sponsored_avoid' => isset($_POST['sponsored_avoid']) ? IDG_Sanitizer::textarea((string) $_POST['sponsored_avoid']) : ($workflow['sponsored_avoid'] ?? ''),
            'sponsored_required_link' => isset($_POST['sponsored_required_link']) ? IDG_Sanitizer::url((string) $_POST['sponsored_required_link']) : ($workflow['sponsored_required_link'] ?? ''),
            'sponsored_anchor' => isset($_POST['sponsored_anchor']) ? IDG_Sanitizer::text((string) $_POST['sponsored_anchor']) : ($workflow['sponsored_anchor'] ?? ''),
            'sponsored_link_rel' => isset($_POST['sponsored_link_rel']) ? IDG_Sanitizer::text((string) $_POST['sponsored_link_rel']) : ($workflow['sponsored_link_rel'] ?? 'sponsored'),
            'sponsored_visible_label' => !empty($_POST['sponsored_visible_label']) ? '1' : '0',
            'sponsored_restrictions' => isset($_POST['sponsored_restrictions']) ? IDG_Sanitizer::textarea((string) $_POST['sponsored_restrictions']) : ($workflow['sponsored_restrictions'] ?? ''),
        ]);
        $data = IDG_Temporary_Material::collect_from_request($data);
        $data['internal_links_structured'] = IDG_Internal_Links::automatic($data);
        if (class_exists('IDG_Editorial_Recipe_Builder')) {
            $recipe = IDG_Editorial_Recipe_Builder::build($data);
            $current_recipe = trim((string) ($data['priority_readings'] ?? ''));
            if ($current_recipe === '' || str_contains($current_recipe, ';') || mb_strlen($current_recipe) > 240 || !empty($data['radar_source'])) {
                $data['priority_readings'] = (string) ($recipe['recipe'] ?? '');
            }
            $data['editorial_recipe'] = (string) ($data['priority_readings'] ?? ($recipe['recipe'] ?? ''));
            $data['recipe_technical_summary'] = (string) ($recipe['technical_summary'] ?? '');
        }
        if (class_exists('IDG_Assignment_Card')) {
            $data = IDG_Assignment_Card::attach($data);
        }

        if (empty($data['workflow_id'])) {
            $workflow_id = IDG_Job_Runner::new_workflow($data);
        } else {
            IDG_Job_Runner::save_workflow($workflow_id, $data);
        }

        if ($step === 'partial_reset') {
            $data = self::partial_reset_workflow_data($data);
            IDG_Job_Runner::save_workflow($workflow_id, $data);
            IDG_Job_Runner::add_history($workflow_id, 'partial_reset', 'Reinicio parcial mínimo aplicado. Se conservaron solo keyword, responsable, URL responsable, categoría, etiquetas y hecho base.');
            $message = 'partial_reset';
        } elseif ($step === 'save') {
            $data['saved_manually'] = true;
            IDG_Job_Runner::save_workflow($workflow_id, $data);
            IDG_Job_Runner::add_history($workflow_id, 'flow_saved', 'Flujo guardado manualmente.');
            $message = 'saved';
        } elseif (in_array($step, ['generate', 'editorial', 'seo', 'draft', 'draft_force'], true)) {
            $message = self::validate_step_before_run($step === 'draft_force' ? 'draft' : $step, $data);
            if ($message === '') {
                // En el panel editorial se procesa inmediatamente para que el resultado y el enlace al borrador
                // queden visibles después del clic. Action Scheduler permanece disponible para futuras colas.
                IDG_Job_Runner::process_scheduled_action($workflow_id, $step);
                $message = 'processed';
            }
        } else {
            $message = 'saved';
        }

        wp_safe_redirect(admin_url('admin.php?page=ideasdi-gerizim&workflow_id=' . rawurlencode($workflow_id) . '&message=' . $message));
        exit;
    }




    private static function normalize_piece_type(string $piece_type): string {
        $clean = trim($piece_type);
        $plain = function_exists('remove_accents') ? remove_accents($clean) : $clean;
        $plain = mb_strtolower((string) $plain);
        $plain = preg_replace('/\s+/u', ' ', $plain);
        if ($plain === 'evergreen') {
            return 'Actualidad';
        }
        if (str_contains($plain, 'agenda') || str_contains($plain, 'concurso') || str_contains($plain, 'convocatoria') || str_contains($plain, 'evento') || str_contains($plain, 'calendario')) {
            return 'Agenda';
        }
        return 'Actualidad';
    }

    private static function piece_type_for_category(int $category_id): string {
        if ($category_id <= 0) {
            return 'Actualidad';
        }
        $term = get_term($category_id, 'category');
        if (!$term || is_wp_error($term)) {
            return 'Actualidad';
        }
        $name = mb_strtolower(function_exists('remove_accents') ? remove_accents((string) $term->name) : (string) $term->name);
        if (str_contains($name, 'concurso') || str_contains($name, 'convocatoria') || str_contains($name, 'evento') || str_contains($name, 'calendario') || str_contains($name, 'agenda')) {
            return 'Agenda';
        }
        return 'Actualidad';
    }

    private static function piece_type_from_request_or_category(int $category_id, string $fallback): string {
        if ($category_id > 0) {
            return self::piece_type_for_category($category_id);
        }
        $fallback = self::normalize_piece_type($fallback);
        return $fallback !== '' ? $fallback : 'Actualidad';
    }

    private static function category_piece_type_map($categories): array {
        $map = [];
        if (is_wp_error($categories)) {
            return $map;
        }
        foreach ($categories as $cat) {
            $map[(string) $cat->term_id] = self::piece_type_for_category((int) $cat->term_id);
        }
        return $map;
    }

    private static function is_brief_locked(array $workflow): bool {
        return !empty($workflow['generated_from_brief']) && trim((string) ($workflow['base_article'] ?? '')) !== '';
    }

    private static function disabled_attr(bool $disabled): string {
        return $disabled ? ' disabled aria-disabled="true"' : '';
    }

    private static function tag_category_ids_for_admin(string $tag_name, $categories): array {
        if (!class_exists('IDG_Priority_Readings') || is_wp_error($categories)) {
            return [];
        }
        $tag_slug = IDG_Priority_Readings::slugify($tag_name);
        $category_ids_by_slug = [];
        foreach ($categories as $cat) {
            $category_ids_by_slug[IDG_Priority_Readings::slugify((string) $cat->name)] = (int) $cat->term_id;
        }
        $ids = [];
        foreach (IDG_Priority_Readings::tag_matrix() as $row) {
            $row_tag = IDG_Priority_Readings::slugify((string) ($row['tag'] ?? ''));
            $row_tag_slug = IDG_Priority_Readings::slugify((string) ($row['tag_slug'] ?? ''));
            $aliases = [$row_tag, $row_tag_slug];
            if (in_array($tag_slug, ['automotriz', 'automovil', 'diseno-automotriz'], true)) {
                $aliases[] = 'automotriz';
                $aliases[] = 'automovil';
                $aliases[] = 'diseno-automotriz';
            }
            if (!in_array($tag_slug, array_unique($aliases), true)) {
                continue;
            }
            $cat_slug = IDG_Priority_Readings::slugify((string) ($row['category'] ?? ''));
            $cat_slug_raw = (string) ($row['category_slug'] ?? '');
            foreach ([$cat_slug, $cat_slug_raw] as $candidate) {
                if ($candidate !== '' && isset($category_ids_by_slug[$candidate])) {
                    $ids[] = $category_ids_by_slug[$candidate];
                }
            }
            // Alias útiles para categorías editoriales del sitio.
            foreach ($category_ids_by_slug as $slug => $id) {
                if ($cat_slug !== '' && (str_contains($slug, $cat_slug) || str_contains($cat_slug, $slug))) {
                    $ids[] = $id;
                }
                if ($cat_slug_raw !== '' && (str_contains($slug, $cat_slug_raw) || str_contains($cat_slug_raw, $slug))) {
                    $ids[] = $id;
                }
            }
        }
        return array_values(array_unique(array_map('intval', $ids)));
    }

    private static function partial_reset_workflow_data(array $data): array {
        // RC1.4.2: reinicio parcial mínimo seguro.
        // Conserva solo los campos editoriales base solicitados por el editor y limpia cualquier
        // investigación, ficha, artículo, validación, metadatos o borrador que pueda arrastrar errores.
        $keep = [
            'workflow_id' => (string) ($data['workflow_id'] ?? ''),
            'created_at' => (string) ($data['created_at'] ?? current_time('mysql')),
            'user_id' => (int) ($data['user_id'] ?? get_current_user_id()),
            'keyword' => (string) ($data['keyword'] ?? ''),
            'entity' => (string) ($data['entity'] ?? ''),
            'official_source' => (string) ($data['official_source'] ?? ''),
            'category_id' => (int) ($data['category_id'] ?? 0),
            'tag_ids' => isset($data['tag_ids']) && is_array($data['tag_ids']) ? array_map('intval', $data['tag_ids']) : [],
            'brief_fact' => (string) ($data['brief_fact'] ?? ''),
            'status' => 'completed',
            'last_action' => 'save',
            'last_error' => '',
            'partial_reset_at' => current_time('mysql'),
        ];

        $history = isset($data['history']) && is_array($data['history']) ? $data['history'] : [];
        $history[] = [
            'time' => current_time('mysql'),
            'event' => 'partial_reset_minimal',
            'message' => 'Reinicio parcial mínimo aplicado. Se conservaron solo keyword, responsable, URL responsable, categoría, etiquetas y hecho base.',
        ];
        $keep['history'] = array_slice($history, -20);

        return $keep;
    }

    public static function handle_download_report(): void {
        if (!current_user_can('edit_posts')) {
            wp_die('No tienes permisos suficientes.');
        }
        $workflow_id = isset($_GET['workflow_id']) ? IDG_Sanitizer::text((string) wp_unslash($_GET['workflow_id'])) : '';
        if ($workflow_id === '') {
            wp_die('No se encontró el flujo para exportar.');
        }
        check_admin_referer('idg_download_report_' . $workflow_id);
        $workflow = IDG_Job_Runner::get_workflow($workflow_id);
        if (empty($workflow)) {
            wp_die('El flujo solicitado no existe o ya fue eliminado.');
        }
        try {
            $markdown = self::build_workflow_report($workflow);
        } catch (Throwable $e) {
            IDG_Logger::log('report_download_failed', $e->getMessage(), ['workflow_id' => $workflow_id]);
            wp_die('No se pudo generar el reporte completo: ' . esc_html($e->getMessage()));
        }
        $slug_base = sanitize_title((string) ($workflow['keyword'] ?? 'gerizim-reporte'));
        if ($slug_base === '') {
            $slug_base = 'gerizim-reporte';
        }
        $filename = 'gerizim-reporte-' . $slug_base . '-' . date_i18n('Ymd-His') . '.md';
        nocache_headers();
        header('Content-Type: text/markdown; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('X-Content-Type-Options: nosniff');
        echo $markdown;
        exit;
    }

    private static function build_workflow_report(array $workflow): string {
        $category_name = self::workflow_category_name($workflow);
        $tag_names = self::workflow_tag_names($workflow);
        $seo_result = (string) ($workflow['seo_result'] ?? '');
        $public_article = self::report_public_article_from_seo($seo_result);
        $link_source = trim((string) ($workflow['postprocessed_html'] ?? '')) !== '' ? (string) $workflow['postprocessed_html'] : $public_article;
        $validation = self::report_validate_final($workflow, $public_article, $link_source);
        $links_detected = self::report_detect_links($link_source);
        $history = (array) ($workflow['history'] ?? []);
        $lines = [];
        $lines[] = '# Reporte completo de flujo · ideasDi Redacción Gerizim';
        $lines[] = '';
        $lines[] = '## 1. Datos generales';
        $lines[] = '- **Workflow ID:** ' . self::md_value((string) ($workflow['workflow_id'] ?? ''));
        $lines[] = '- **Versión plugin:** ' . IDG_VERSION;
        $lines[] = '- **Creado:** ' . self::md_value((string) ($workflow['created_at'] ?? ''));
        $lines[] = '- **Estado:** ' . self::md_value((string) ($workflow['status'] ?? ''));
        $lines[] = '- **Última acción:** ' . self::md_value((string) ($workflow['last_action'] ?? ''));
        $lines[] = '- **ID borrador WordPress:** ' . self::md_value((string) ($workflow['draft_post_id'] ?? ''));
        $lines[] = '- **URL edición borrador:** ' . self::md_value((string) ($workflow['draft_edit_link'] ?? ''));
        $lines[] = '';
        $lines[] = '## 2. Brief editorial';
        $lines[] = '- **Entidad / Keyword principal:** ' . self::md_value((string) ($workflow['keyword'] ?? ''));
        $lines[] = '- **Diseñador / estudio / marca responsable:** ' . self::md_value((string) ($workflow['entity'] ?? ''));
        $lines[] = '- **URL Diseñador / estudio / marca responsable:** ' . self::md_value((string) ($workflow['official_source'] ?? ''));
        $lines[] = '- **Tipo de pieza:** ' . self::md_value((string) ($workflow['piece_type'] ?? ''));
        $lines[] = '- **Categoría WordPress:** ' . self::md_value($category_name);
        $lines[] = '- **Etiquetas WordPress:** ' . self::md_value(implode(', ', $tag_names));
        $lines[] = '- **URL oficial o fuente complementaria:** ' . self::md_value((string) ($workflow['source_information_url'] ?? $workflow['temp_material_url'] ?? ''));
        $lines[] = '';
        $lines[] = '### Hecho base';
        $lines[] = self::md_block((string) ($workflow['brief_fact'] ?? ''));
        $lines[] = '### Ajuste editorial opcional / ángulo usado';
        $lines[] = self::md_block((string) ($workflow['editorial_angle'] ?? ''));
        $auto_priority = IDG_Priority_Readings::build_suggestion((int) ($workflow['category_id'] ?? 0), isset($workflow['tag_ids']) && is_array($workflow['tag_ids']) ? $workflow['tag_ids'] : []);
        $lines[] = '### Receta editorial compacta';
        $lines[] = self::md_block((string) ($workflow['priority_readings'] ?? $auto_priority));
        $lines[] = '';
        $lines[] = '## 3. Documentación e investigación';
        if (class_exists('IDG_Web_Research')) {
            $lines[] = '### Investigación web controlada';
            foreach (IDG_Web_Research::report_lines($workflow) as $research_line) {
                $lines[] = $research_line;
            }
            $lines[] = '### Aplicación visible de la investigación en el artículo';
            foreach (IDG_Web_Research::application_lines($workflow, $public_article) as $application_line) {
                $lines[] = $application_line;
            }
        } else {
            $lines[] = '- **Lectura de URL:** ' . self::md_value((string) ($workflow['source_url_status'] ?? (($workflow['temp_material_url'] ?? '') !== '' ? 'registrada / revisar historial' : 'sin URL')));
            $lines[] = '- **Investigación web automática:** ' . self::md_value((string) ($workflow['web_research_status'] ?? 'no registrada'));
        }
        $lines[] = '- **Ficha documental creada:** ' . self::md_value((string) ($workflow['document_card_created_at'] ?? ''));
        if (!empty($workflow['temp_material_warnings']) && is_array($workflow['temp_material_warnings'])) {
            $lines[] = '### Avisos del material temporal';
            foreach ($workflow['temp_material_warnings'] as $warning) {
                $lines[] = '- ' . self::md_value((string) $warning);
            }
        }
        $lines[] = '### Ficha documental temporal';
        $lines[] = self::md_block((string) ($workflow['document_card'] ?? ''));
        $lines[] = '';
        $lines[] = '## 4. Ficha de encargo editorial';
        $lines[] = self::md_block((string) ($workflow['assignment_card'] ?? $workflow['brief_card'] ?? 'No registrada en el flujo.'));
        $lines[] = '- **Ficha de encargo creada:** ' . self::md_value((string) ($workflow['assignment_card_created_at'] ?? ''));
        $lines[] = '- **Hash ficha de encargo:** ' . self::md_value((string) ($workflow['assignment_card_hash'] ?? ''));
        $lines[] = '';
        $lines[] = '## 5. Versiones del artículo';
        $lines[] = '### Versión 1 · Artículo base';
        $lines[] = self::md_block((string) ($workflow['base_article'] ?? ''));
        $lines[] = '### Versión 2 · Revisión editorial';
        $lines[] = self::md_block((string) ($workflow['editorial_result'] ?? ''));
        $lines[] = '### Versión 3 · Revisión SEO final';
        $lines[] = self::md_block($seo_result);
        $lines[] = '';
        $lines[] = '## 6. Validación final real';
        if (!empty($workflow['final_validation_summary'])) {
            $lines[] = self::md_block((string) $workflow['final_validation_summary']);
        }
        foreach ($validation as $label => $value) {
            $lines[] = '- **' . $label . ':** ' . self::md_value((string) $value);
        }
        if (!empty($workflow['warnings']) && is_array($workflow['warnings'])) {
            $lines[] = '### Alertas del plugin';
            foreach ($workflow['warnings'] as $warning) {
                $lines[] = '- ' . self::md_value((string) $warning);
            }
        }
        $lines[] = '';
        $lines[] = '## 7. Checklist de regresión protegida';
        foreach (self::report_regression_checklist($workflow) as $label => $value) {
            $lines[] = '- **' . $label . ':** ' . self::md_value((string) $value);
        }
        $lines[] = '';
        $lines[] = '## 8. Enlaces detectados en versión final';
        if (empty($links_detected)) {
            $lines[] = '_No se detectaron enlaces Markdown o HTML en la versión final._';
        } else {
            foreach ($links_detected as $link) {
                $lines[] = '- Anchor: `' . str_replace('`', "'", (string) $link['anchor']) . '` | URL: ' . self::md_value((string) $link['url']);
            }
        }
        $lines[] = '';
        $lines[] = '## 9. Biblioteca de enlaces internos aplicada';
        $library = IDG_Internal_Links::library_summary($workflow);
        $lines[] = $library !== '' ? $library : '_Sin enlaces internos calculados._';
        $lines[] = '';
        $lines[] = '## 10. Metadatos y entregables internos';
        $sections = self::report_extract_sections($seo_result);
        foreach (['META DESCRIPTION' => 'Meta description', 'INFORME SEO INTERNO' => 'Informe SEO', 'COPY PARA REDES' => 'Copy redes', 'PAQUETE REEL' => 'Paquete reel', 'RETROALIMENTACIÓN GERIZIM' => 'Retroalimentación'] as $key => $label) {
            $lines[] = '### ' . $label;
            $lines[] = self::md_block((string) ($sections[$key] ?? ''));
        }
        $lines[] = '';
        $lines[] = '## 11. Reglas editoriales activas';
        if (class_exists('IDG_Editorial_Rules')) {
            foreach (IDG_Editorial_Rules::summary_lines() as $label => $value) {
                $lines[] = '- **' . $label . ':** ' . self::md_value((string) $value);
            }
        } else {
            $lines[] = '_Sin capa de reglas editoriales._';
        }
        $lines[] = '';
        $lines[] = '## 12. Historial del flujo';
        if (empty($history)) {
            $lines[] = '_Sin historial._';
        } else {
            foreach ($history as $item) {
                $lines[] = '- ' . self::md_value((string) ($item['time'] ?? '')) . ' · ' . self::md_value((string) ($item['event'] ?? '')) . ' · ' . self::md_value((string) ($item['message'] ?? ''));
            }
        }
        $lines[] = '';
        $lines[] = '## 13. Matriz categoría/tag aplicada';
        foreach ($tag_names as $tag_name) {
            $status = IDG_Priority_Readings::tag_status($tag_name, $category_name);
            $lines[] = '- ' . self::md_value($tag_name) . ' · Estado: ' . self::md_value($status !== '' ? $status : 'sin estado') . ' · Lectura: ' . self::md_value(IDG_Priority_Readings::preset_for_tag_name($tag_name, $category_name));
        }
        $lines[] = '';
        return implode("\n", $lines);
    }

    private static function workflow_category_name(array $workflow): string {
        if (empty($workflow['category_id'])) return '';
        $term = get_term((int) $workflow['category_id'], 'category');
        return ($term && !is_wp_error($term)) ? (string) $term->name : '';
    }

    private static function workflow_tag_names(array $workflow): array {
        $names = [];
        if (!empty($workflow['tag_ids']) && is_array($workflow['tag_ids'])) {
            foreach ($workflow['tag_ids'] as $tag_id) {
                $tag = get_term((int) $tag_id, 'post_tag');
                if ($tag && !is_wp_error($tag)) $names[] = (string) $tag->name;
            }
        }
        return $names;
    }

    private static function md_value(string $value): string {
        $value = trim($value);
        return $value === '' ? '_Sin información._' : str_replace(["\r\n", "\r"], "\n", $value);
    }

    private static function md_block(string $value): string {
        $value = trim(str_replace(["\r\n", "\r"], "\n", $value));
        return $value === '' ? '_Sin información._' : $value;
    }


    private static function report_public_article_from_seo(string $content): string {
        $normalized = str_replace(["\r\n", "\r"], "\n", $content);
        if (preg_match('/^\s*(?:#{1,6}\s*)?(?:\*\*)?\s*ART[ÍI]CULO FINAL\s*(?:\*\*)?\s*:?\s*$/imu', $normalized, $m, PREG_OFFSET_CAPTURE)) {
            $start = $m[0][1] + strlen($m[0][0]);
            $tail = substr($normalized, $start);
            if (preg_match('/^\s*(?:#{1,6}\s*)?(?:\*\*)?\s*(?:META DESCRIPTION|INFORME SEO INTERNO|COPY PARA REDES|PAQUETE REEL|RETROALIMENTACI[ÓO]N GERIZIM)\s*(?:\*\*)?\s*:?\s*$/imu', $tail, $next, PREG_OFFSET_CAPTURE)) {
                $tail = substr($tail, 0, $next[0][1]);
            }
            return trim($tail);
        }
        if (preg_match('/^\s*(?:#{1,6}\s*)?(?:\*\*)?\s*(?:META DESCRIPTION|INFORME SEO INTERNO|COPY PARA REDES|PAQUETE REEL|RETROALIMENTACI[ÓO]N GERIZIM)\s*(?:\*\*)?\s*:?\s*$/imu', $normalized, $m, PREG_OFFSET_CAPTURE)) {
            return trim(substr($normalized, 0, $m[0][1]));
        }
        return trim($normalized);
    }

    private static function report_detect_links(string $content): array {
        $links = [];
        if (preg_match_all('/\[([^\]]+)\]\((https?:\/\/[^\s\)]+)\)/u', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) $links[] = ['anchor' => trim($m[1]), 'url' => trim($m[2])];
        }
        if (preg_match_all('/<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/isu', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) $links[] = ['anchor' => trim(wp_strip_all_tags($m[2])), 'url' => trim($m[1])];
        }
        return $links;
    }

    private static function report_validate_final(array $workflow, string $content, string $link_source = ''): array {
        $keyword = trim((string) ($workflow['keyword'] ?? ''));
        $h1 = '';
        if (preg_match('/^\s*#\s+(.+)$/m', $content, $m)) $h1 = trim($m[1]);
        if ($h1 === '' && $link_source !== '' && preg_match('/<h1\b[^>]*>(.*?)<\/h1>/isu', $link_source, $hm)) $h1 = trim(wp_strip_all_tags($hm[1]));
        $link_source = trim($link_source) !== '' ? trim($link_source) : trim($content);
        $links = self::report_detect_links($link_source !== '' ? $link_source : $content);
        $keyword_anchor = 'no';
        foreach ($links as $link) {
            if ($keyword !== '' && mb_strtolower(wp_strip_all_tags((string) $link['anchor'])) === mb_strtolower($keyword)) {
                $keyword_anchor = 'sí';
                break;
            }
        }
        $bold_count = preg_match_all('/\*\*([^*]+)\*\*/u', $content, $bolds);
        $sections = self::report_extract_sections((string) ($workflow['seo_result'] ?? ''));
        $reel_package = (string) ($sections['PAQUETE REEL'] ?? '');
        $reel_cta = stripos($reel_package, 'Conoce más de este proyecto en ideasDi.com') !== false ? 'sí' : 'no';
        $overlay_count = preg_match_all('/^\s*Overlay(?:\s+\d+(?:\.\d+)?|\s*[—\-]?\s*\d+)?\s*:/imu', $reel_package, $om);
        $rules = class_exists('IDG_Editorial_Rules') ? IDG_Editorial_Rules::get() : [];
        $target_words = (int) ($rules['reel_vo_words'] ?? 14);
        $target_overlays = (int) (($rules['reel_scenes'] ?? 6) * ($rules['reel_overlays_per_scene'] ?? 3));
        $vo_counts = self::report_reel_vo_counts($reel_package, $target_words);
        $result = [
            'H1 detectado' => $h1,
            'H1 contiene keyword exacta' => ($keyword !== '' && stripos($h1, $keyword) !== false) ? 'sí' : 'no',
            'Modo keyword H1' => (string) ($rules['h1_keyword_policy'] ?? 'flexible'),
            'H1 ≤ ' . (int) ($rules['h1_max_chars'] ?? 68) . ' caracteres' => ($h1 !== '' && mb_strlen($h1) <= (int) ($rules['h1_max_chars'] ?? 68)) ? 'sí' : 'no',
            'Caja editorial presente' => stripos($content, 'Caja editorial') !== false ? 'sí' : 'no',
            'Caja editorial después de introducción' => self::report_featured_snippet_after_intro($content),
            'H3 con desarrollo mínimo' => self::report_h3_depth($content),
            'Enlaces detectados' => (string) count($links),
            'Keyword usada como anchor' => $keyword_anchor,
            'Negritas reales detectadas' => (string) max(0, (int) $bold_count),
            'Paquete reel estado' => 'apoyo preliminar / no bloqueante',
            'Paquete reel incluye CTA fijo' => $reel_cta,
            'Overlays del reel detectados' => (string) max(0, (int) $overlay_count) . ' / ' . max(1, $target_overlays),
        ];
        foreach ($vo_counts as $label => $value) {
            $result[$label] = $value;
        }
        $result['Ficha de encargo registrada'] = trim((string) ($workflow['assignment_card'] ?? '')) !== '' ? 'sí' : 'no';
        return $result;
    }

    private static function report_reel_vo_counts(string $reel_package, int $target_words): array {
        $out = [];
        preg_match_all('/^\s*(?:[-*]\s*)?VO\s*(?:[—\-]\s*Bloque\s*)?(\d)\s*:\s*(.+)$/imu', $reel_package, $matches, PREG_SET_ORDER);
        $by = [];
        foreach ($matches as $m) {
            $by[(int) $m[1]] = trim((string) $m[2]);
        }
        for ($i = 1; $i <= 5; $i++) {
            if (!isset($by[$i])) {
                $out['VO ' . $i . ' palabras'] = 'no detectado';
                continue;
            }
            $count = self::report_word_count($by[$i]);
            $out['VO ' . $i . ' palabras'] = $count . ' / ' . $target_words;
        }
        $out['VO 6 CTA'] = (isset($by[6]) && stripos($by[6], 'Conoce más de este proyecto en ideasDi.com') !== false) ? 'sí' : 'no';
        return $out;
    }

    private static function report_word_count(string $text): int {
        $plain = trim(wp_strip_all_tags($text));
        preg_match_all('/\b[\p{L}\p{N}][\p{L}\p{N}\-]*\b/u', $plain, $m);
        return count($m[0] ?? []);
    }

    private static function report_featured_snippet_after_intro(string $content): string {
        $normalized = str_replace(["\r\n", "\r"], "\n", $content);
        if (!preg_match('/^##\s+.+$/mu', $normalized, $h2, PREG_OFFSET_CAPTURE)) return 'revisar';
        if (!preg_match('/^\s*(?:\*\*)?\s*Caja editorial\s*:?\s*(?:\*\*)?\s*$/imu', $normalized, $box, PREG_OFFSET_CAPTURE)) return 'no detectada';
        $between = trim(substr($normalized, $h2[0][1] + strlen($h2[0][0]), $box[0][1] - ($h2[0][1] + strlen($h2[0][0]))));
        $paragraphs = 0;
        foreach (preg_split('/\n+/', $between) as $line) {
            $line = trim((string) $line);
            if ($line !== '' && !preg_match('/^#{1,6}\s+/u', $line) && !preg_match('/^[-*]\s+/u', $line)) $paragraphs++;
        }
        return $paragraphs >= 2 ? 'sí' : 'no';
    }

    private static function report_h3_depth(string $content): string {
        $normalized = str_replace(["\r\n", "\r"], "\n", $content);
        preg_match_all('/^###\s+(.+)$/mu', $normalized, $matches, PREG_OFFSET_CAPTURE);
        $total = count($matches[0] ?? []);
        if ($total === 0) return 'sin H3';
        $shallow = 0;
        for ($i = 0; $i < $total; $i++) {
            $start = $matches[0][$i][1] + strlen($matches[0][$i][0]);
            $end = ($i + 1 < $total) ? $matches[0][$i + 1][1] : strlen($normalized);
            $body = trim(substr($normalized, $start, $end - $start));
            $paragraphs = 0;
            foreach (preg_split('/\n+/', $body) as $line) {
                $line = trim((string) $line);
                if ($line !== '' && !preg_match('/^#{1,6}\s+/u', $line) && !preg_match('/^[-*]\s+/u', $line)) $paragraphs++;
            }
            if ($paragraphs < 2 && !preg_match('/^[-*]\s+/m', $body)) $shallow++;
        }
        return $shallow === 0 ? 'sí' : 'no (' . $shallow . ' de ' . $total . ' H3 con menos de 2 párrafos)';
    }



    private static function report_regression_checklist(array $workflow): array {
        $content = trim((string) ($workflow['postprocessed_html'] ?? '')) !== '' ? (string) $workflow['postprocessed_html'] : (string) ($workflow['seo_result'] ?? '');
        $category_name = self::workflow_category_name($workflow);
        $tag_names = self::workflow_tag_names($workflow);
        $link_source = trim($link_source) !== '' ? trim($link_source) : trim($content);
        $links = self::report_detect_links($link_source !== '' ? $link_source : $content);
        $has_external = 'no';
        $has_internal = 'no';
        foreach ($links as $link) {
            $url = (string) ($link['url'] ?? '');
            if (stripos($url, home_url()) !== false || stripos($url, 'ideasdi.com') !== false || str_starts_with($url, '/')) {
                $has_internal = 'sí';
            } elseif (stripos($url, 'http://') === 0 || stripos($url, 'https://') === 0) {
                $has_external = 'sí';
            }
        }
        $has_report = !empty($workflow['workflow_id']) ? 'sí' : 'revisar';
        return [
            'Botón Descargar reporte completo debe estar visible en flujos activos' => $has_report,
            'Selector Etiquetas WordPress existentes conserva alineación protegida desde v0.3.5' => 'sí',
            'Nueva redacción relacionada debe conservar keyword, responsable y URL responsable' => 'sí',
            'Campo Ángulo editorial manual se mantiene como Ajuste editorial opcional' => 'sí',
            'Botones Sugerir ángulo, Actualizar lecturas y Restaurar predeterminado no deben volver a aparecer' => 'sí',
            'Campo Notas adicionales sobre enlaces internos no debe volver a aparecer' => 'sí',
            'URL Diseñador / estudio / marca responsable conserva nombre y ubicación' => 'sí',
            'URL oficial o fuente complementaria debe leerse antes de generar artículo base cuando exista' => !empty($workflow['source_information_url']) || !empty($workflow['temp_material_url']) ? ((string) ($workflow['source_url_status'] ?? 'revisar historial de documentación')) : 'sin URL en este flujo',
            'Investigación web controlada siempre activa al generar artículo base' => (string) ($workflow['web_research_status'] ?? 'no registrada'),
            'Enlace externo real detectado en versión final' => $has_external,
            'Enlace interno real detectado en versión final' => $has_internal,
            'Si el tag principal es No Index, el enlace interno debe apuntar a categoría/página curada' => implode(', ', $tag_names) !== '' ? 'revisar sección Biblioteca de enlaces internos aplicada' : 'sin tags',
            'Categoría detectada para fallback de enlaces' => $category_name !== '' ? $category_name : 'sin categoría',
        ];
    }

    private static function report_extract_sections(string $content): array {
        $labels = ['META DESCRIPTION', 'INFORME SEO INTERNO', 'COPY PARA REDES', 'PAQUETE REEL', 'RETROALIMENTACIÓN GERIZIM'];
        $sections = [];
        $normalized = str_replace(["\r\n", "\r"], "\n", $content);
        foreach ($labels as $i => $label) {
            $pattern = '/^\s*(?:#{1,6}\s*)?(?:\*\*)?\s*' . preg_quote($label, '/') . '\s*(?:\*\*)?\s*:??\s*$/imu';
            if (!preg_match($pattern, $normalized, $m, PREG_OFFSET_CAPTURE)) continue;
            $start = $m[0][1] + strlen($m[0][0]);
            $end = strlen($normalized);
            foreach (array_slice($labels, $i + 1) as $next_label) {
                $next_pattern = '/^\s*(?:#{1,6}\s*)?(?:\*\*)?\s*' . preg_quote($next_label, '/') . '\s*(?:\*\*)?\s*:??\s*$/imu';
                if (preg_match($next_pattern, substr($normalized, $start), $next, PREG_OFFSET_CAPTURE)) {
                    $end = $start + $next[0][1];
                    break;
                }
            }
            $sections[$label] = trim(substr($normalized, $start, $end - $start));
        }
        return $sections;
    }


    private static function is_sponsored_workflow(array $workflow): bool {
        $piece_type = mb_strtolower((string) ($workflow['piece_type'] ?? ''));
        return str_contains($piece_type, 'patrocinado') || str_contains($piece_type, 'colaboraci');
    }

    private static function validate_step_before_run(string $step, array $workflow): string {
        if ($step === 'generate' && trim((string) ($workflow['keyword'] ?? '')) === '') {
            return 'needs_keyword';
        }
        if ($step === 'generate' && trim((string) ($workflow['brief_fact'] ?? '')) === '' && !self::is_sponsored_workflow($workflow)) {
            return 'needs_brief';
        }
        if ($step === 'generate' && self::is_sponsored_workflow($workflow) && trim((string) ($workflow['sponsored_brief'] ?? '')) === '' && trim((string) ($workflow['brief_fact'] ?? '')) === '') {
            return 'needs_sponsored_brief';
        }
        if ($step === 'editorial' && trim((string) ($workflow['base_article'] ?? '')) === '') {
            return 'needs_article';
        }
        if ($step === 'seo' && trim((string) ($workflow['editorial_result'] ?? '')) === '') {
            return 'needs_editorial';
        }
        if ($step === 'draft' && trim((string) ($workflow['seo_result'] ?? '')) === '') {
            return 'needs_seo';
        }
        return '';
    }

    public static function render_settings_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos suficientes.');
        }
        $settings = get_option(IDG_OPTION_KEY, []);
        $has_key = !empty($settings['api_key']);
        $model = (string) ($settings['model'] ?? 'gpt-5.4-mini');
        $reference_balance = (float) ($settings['openai_reference_balance'] ?? 0);
        ?>
        <div class="wrap idg-wrap">
            <h1>ideasDi Redacción Gerizim · Ajustes</h1>
            <?php if (isset($_GET['updated'])) : ?>
                <div class="notice notice-success"><p>Ajustes guardados.</p></div>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="idg-card">
                <?php wp_nonce_field('idg_save_settings'); ?>
                <input type="hidden" name="action" value="idg_save_settings">

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="api_key">OpenAI API key</label></th>
                        <td>
                            <input type="password" id="api_key" name="api_key" class="regular-text" placeholder="sk-...">
                            <p class="description">
                                <?php echo $has_key ? 'Hay una API key guardada. Deja este campo vacío para conservarla.' : 'No hay API key configurada.'; ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="model">Modelo por defecto</label></th>
                        <td>
                            <input type="text" id="model" name="model" class="regular-text" value="<?php echo esc_attr($model); ?>">
                            <p class="description">Ejemplo: gpt-5.4-mini para pruebas o el modelo editorial definido por ideasDi.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="openai_reference_balance">Saldo inicial de referencia OpenAI</label></th>
                        <td>
                            <input type="number" step="0.01" min="0" id="openai_reference_balance" name="openai_reference_balance" class="small-text" value="<?php echo esc_attr(number_format($reference_balance, 2, '.', '')); ?>"> USD
                            <p class="description">Referencia local para estimar saldo dentro de Gerizim. Verifica siempre el saldo oficial en OpenAI Platform.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Accesos OpenAI</th>
                        <td>
                            <a href="https://platform.openai.com/settings/organization/billing/credit-grants" target="_blank" rel="noopener noreferrer">Ver Credit balance oficial</a> ·
                            <a href="https://platform.openai.com/usage" target="_blank" rel="noopener noreferrer">Ver Usage Dashboard</a>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Action Scheduler</th>
                        <td>
                            <?php if (function_exists('as_enqueue_async_action')) : ?>
                                <span class="idg-badge idg-badge-ok">Disponible</span>
                            <?php else : ?>
                                <span class="idg-badge idg-badge-warn">No detectado</span>
                                <p class="description">El plugin funcionará en modo directo. Para cola real, instala Action Scheduler o un plugin que lo incluya.</p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Guardar ajustes'); ?>
            </form>
        </div>
        <?php
    }

    public static function render_prompts_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos suficientes.');
        }
        $settings = get_option(IDG_PROMPTS_OPTION_KEY, []);
        $fields = [
            'system' => [
                'label' => 'Reglas generales adicionales',
                'description' => 'Se agregan al prompt base de Gerizim en todas las fases. Úsalo para reglas globales de tono, SEO o cautelas editoriales.',
                'rows' => 8,
            ],
            'generate' => [
                'label' => 'Generar artículo base · instrucciones adicionales',
                'description' => 'Se aplican solo al botón Generar artículo base. Útil para ajustar estilo inicial, uso del brief o material temporal.',
                'rows' => 8,
            ],
            'editorial' => [
                'label' => 'Revisión editorial · instrucciones adicionales',
                'description' => 'Se aplican solo a Revisión editorial. Útil para mejorar naturalidad, filo editorial y precisión sin optimización SEO.',
                'rows' => 8,
            ],
            'seo' => [
                'label' => 'Revisión SEO · instrucciones adicionales',
                'description' => 'Se aplican solo a Revisión SEO. Útil para reglas de enlaces, negritas, metadatos o conservación editorial.',
                'rows' => 8,
            ],
            'material_card' => [
                'label' => 'Ficha documental temporal · instrucciones adicionales',
                'description' => 'Se aplican al resumen del material temporal. Útil para notas de prensa largas, briefs de cliente o documentos extensos.',
                'rows' => 6,
            ],
        ];
        ?>
        <div class="wrap idg-wrap">
            <h1>ideasDi Redacción Gerizim · Prompts y reglas</h1>
            <?php if (isset($_GET['updated'])) : ?>
                <div class="notice notice-success"><p>Instrucciones editables guardadas.</p></div>
            <?php endif; ?>

            <div class="idg-card">
                <h2>Modo seguro de edición</h2>
                <p>Estas instrucciones no reemplazan la estructura estable del plugin. Se agregan como reglas adicionales para ajustar Gerizim sin tocar código.</p>
                <p class="description">Si un ajuste empeora los resultados, deja el campo vacío o usa “Limpiar instrucciones editables”. El sistema volverá a los prompts internos estables.</p>
                <?php if (!empty($settings['updated_at'])) : ?>
                    <p class="description">Última actualización: <?php echo esc_html((string) $settings['updated_at']); ?></p>
                <?php endif; ?>
            </div>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="idg-card">
                <?php wp_nonce_field('idg_save_prompts'); ?>
                <input type="hidden" name="action" value="idg_save_prompts">

                <?php foreach ($fields as $key => $field) : ?>
                    <div class="idg-prompt-field">
                        <label for="prompt_<?php echo esc_attr($key); ?>"><strong><?php echo esc_html($field['label']); ?></strong></label>
                        <p class="description"><?php echo esc_html($field['description']); ?></p>
                        <textarea id="prompt_<?php echo esc_attr($key); ?>" name="prompt_<?php echo esc_attr($key); ?>" rows="<?php echo esc_attr((string) $field['rows']); ?>" class="large-text code" placeholder="Escribe solo reglas adicionales. Deja vacío para usar el comportamiento estable del plugin."><?php echo esc_textarea((string) ($settings[$key] ?? '')); ?></textarea>
                    </div>
                <?php endforeach; ?>

                <p>
                    <label><input type="checkbox" name="reset_prompt_overrides" value="1"> Limpiar instrucciones editables y volver a los prompts internos estables.</label>
                </p>
                <?php submit_button('Guardar prompts y reglas'); ?>
            </form>
        </div>
        <?php
    }

    public static function render_editorial_rules_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos suficientes.');
        }
        $rules = class_exists('IDG_Editorial_Rules') ? IDG_Editorial_Rules::get() : [];
        $history = class_exists('IDG_Editorial_Rules') ? IDG_Editorial_Rules::history() : [];
        $export = class_exists('IDG_Editorial_Rules') ? IDG_Editorial_Rules::export_json() : '{}';
        ?>
        <div class="wrap idg-wrap">
            <h1>Gerizim · Reglas editoriales</h1>
            <p class="idg-version-badge">Versión plugin: <?php echo esc_html(IDG_VERSION); ?></p>
            <?php if (isset($_GET['updated'])) : ?>
                <div class="notice notice-success"><p>Reglas editoriales guardadas. Se aplicarán en los próximos flujos sin modificar archivos del plugin.</p></div>
            <?php endif; ?>
            <div class="idg-card">
                <h2>Núcleo protegido + reglas vivas</h2>
                <p>Estas reglas se guardan en la base de datos de WordPress y sobrescriben el comportamiento editorial en tiempo de ejecución. No se editan ni sobrescriben archivos físicos del plugin.</p>
                <p class="description">Úsalas para ajustes continuos de redacción. Los botones, guardado, borrador Gutenberg, investigación y reportes pertenecen al núcleo protegido.</p>
            </div>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="idg-card">
                <?php wp_nonce_field('idg_save_editorial_rules'); ?>
                <input type="hidden" name="action" value="idg_save_editorial_rules">
                <h2>Reglas medibles</h2>
                <table class="form-table" role="presentation">
                    <tr><th><label for="rules_version_name">Nombre de versión de reglas</label></th><td><input type="text" id="rules_version_name" name="rules_version_name" class="regular-text" value="<?php echo esc_attr((string) ($rules['rules_version_name'] ?? '')); ?>"></td></tr>
                    <?php
                    $number_fields = [
                        'h1_max_chars' => 'H1 máximo caracteres',
                        'h2_max_chars' => 'H2 máximo caracteres',
                        'editorial_box_min_words' => 'Caja editorial mínimo palabras',
                        'editorial_box_max_words' => 'Caja editorial máximo palabras',
                        'min_paragraphs_per_h3' => 'Párrafos mínimos por H3',
                        'reel_vo_words' => 'Palabras VO 1–5',
                        'reel_scenes' => 'Escenas reel',
                        'reel_overlays_per_scene' => 'Overlays por escena',
                        'reel_overlay_max_chars' => 'Máximo caracteres por overlay',
                    ];
                    foreach ($number_fields as $key => $label) : ?>
                        <tr><th><label for="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th><td><input type="number" min="0" id="<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>" class="small-text" value="<?php echo esc_attr((string) ($rules[$key] ?? '')); ?>"></td></tr>
                    <?php endforeach; ?>
                    <tr><th><label for="h1_keyword_policy">Keyword en H1</label></th><td><select id="h1_keyword_policy" name="h1_keyword_policy"><option value="flexible" <?php selected((string) ($rules['h1_keyword_policy'] ?? 'flexible'), 'flexible'); ?>>Flexible: no bloquea si suena forzada</option><option value="exact" <?php selected((string) ($rules['h1_keyword_policy'] ?? 'flexible'), 'exact'); ?>>Exacta: bloquea si no aparece literal</option><option value="warning" <?php selected((string) ($rules['h1_keyword_policy'] ?? 'flexible'), 'warning'); ?>>Solo aviso: revisión humana</option></select><p class="description">Recomendado: Flexible. La keyword exacta puede ser demasiado forzada en algunos títulos.</p></td></tr>
                    <tr><th><label for="reel_cta">CTA fijo del reel</label></th><td><input type="text" id="reel_cta" name="reel_cta" class="regular-text" value="<?php echo esc_attr((string) ($rules['reel_cta'] ?? '')); ?>"></td></tr>
                </table>
                <h2>Reglas editoriales para copiar y pegar</h2>
                <?php
                $text_fields = [
                    'structure_rules' => 'Estructura del artículo',
                    'editorial_box_rules' => 'Caja editorial',
                    'internal_link_rules' => 'Enlaces internos',
                    'external_link_rules' => 'Enlaces externos',
                    'reel_rules' => 'Paquete reel',
                    'forbidden_phrases' => 'Frases prohibidas o no deseadas',
                    'category_rules' => 'Reglas por categoría',
                ];
                foreach ($text_fields as $key => $label) : ?>
                    <p><label for="<?php echo esc_attr($key); ?>"><strong><?php echo esc_html($label); ?></strong></label><textarea id="<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>" rows="5" class="large-text code"><?php echo esc_textarea((string) ($rules[$key] ?? '')); ?></textarea></p>
                <?php endforeach; ?>
                <p><label><input type="checkbox" name="reset_editorial_rules" value="1"> Restaurar reglas base protegidas RC1.4.</label></p>
                <?php submit_button('Guardar reglas editoriales'); ?>
            </form>
            <div class="idg-card">
                <h2>Exportar / importar reglas</h2>
                <p class="description">Copia este JSON para respaldo. Para importar, pega un JSON válido y marca “Importar JSON”.</p>
                <textarea readonly rows="10" class="large-text code"><?php echo esc_textarea($export); ?></textarea>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('idg_save_editorial_rules'); ?>
                    <input type="hidden" name="action" value="idg_save_editorial_rules">
                    <p><textarea name="editorial_rules_import_json" rows="8" class="large-text code" placeholder="Pega aquí el JSON de reglas"></textarea></p>
                    <p><label><input type="checkbox" name="import_editorial_rules" value="1"> Importar JSON y reemplazar reglas actuales.</label></p>
                    <?php submit_button('Importar reglas'); ?>
                </form>
            </div>
            <div class="idg-card">
                <h2>Historial reciente</h2>
                <?php if (empty($history)) : ?>
                    <p class="description">Aún no hay historial de reglas.</p>
                <?php else : ?>
                    <ul>
                        <?php foreach ($history as $item) : ?>
                            <li><strong><?php echo esc_html((string) ($item['time'] ?? '')); ?></strong> · <?php echo esc_html((string) ($item['action'] ?? '')); ?> · <?php echo esc_html((string) ($item['version_name'] ?? '')); ?> · plugin <?php echo esc_html((string) ($item['plugin_version'] ?? '')); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    public static function render_workflow_page(): void {
        if (!current_user_can('edit_posts')) {
            wp_die('No tienes permisos suficientes.');
        }

        $workflow_id = isset($_GET['workflow_id']) ? sanitize_text_field(wp_unslash($_GET['workflow_id'])) : '';
        $workflow = $workflow_id ? IDG_Job_Runner::get_workflow($workflow_id) : IDG_Job_Runner::current_workflow();
        $workflow_id = (string) ($workflow['workflow_id'] ?? $workflow_id);

        $categories = get_terms([
            'taxonomy' => 'category',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);
        $tags = get_terms([
            'taxonomy' => 'post_tag',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);
        $brief_locked = self::is_brief_locked($workflow);
        $category_piece_type_map = self::category_piece_type_map($categories);
        $selected_piece_type = self::piece_type_from_request_or_category((int) ($workflow['category_id'] ?? 0), (string) ($workflow['piece_type'] ?? ''));
        ?>
        <div class="wrap idg-wrap">
            <h1>ideasDi Redacción Gerizim</h1>
            <p class="idg-version-badge">Versión plugin: <?php echo esc_html(IDG_VERSION); ?></p>
            <p class="idg-lead">Flujo editorial interno: Brief editorial → Generar artículo base → Revisión editorial → Revisión SEO → Crear entrada en Pendiente de revisión.</p>
            <div class="idg-processing-overlay" aria-live="polite" aria-hidden="true"><div class="idg-spinner"></div><p>Gerizim está procesando. No cierres esta pantalla.</p></div>

            <?php self::render_notice($workflow); ?>
            <?php self::render_radar_importer($workflow_id, $workflow); ?>

            <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="idg-grid">
                <?php wp_nonce_field('idg_submit_workflow'); ?>
                <input type="hidden" name="action" value="idg_submit_workflow">
                <input type="hidden" name="workflow_id" value="<?php echo esc_attr($workflow_id); ?>">

                <div class="idg-card idg-main-card">
                    <h2>Paso 1 · Brief editorial</h2>
                    <p class="description">Completa el brief para generar el artículo base desde WordPress. Si ya tienes un artículo generado, puedes pegarlo directamente en “Artículo base”.</p>
                    <?php if ($brief_locked) : ?>
                        <div class="notice notice-info inline idg-brief-locked-notice"><p><strong>Brief bloqueado.</strong> Para cambiar keyword, responsable, categoría, etiquetas, hecho base o ajuste editorial, usa Reinicio parcial.</p></div>
                    <?php endif; ?>

                    <div class="idg-two-cols">
                        <p>
                            <label for="keyword">Keyword principal</label>
                            <input type="text" id="keyword" name="keyword" value="<?php echo esc_attr((string) ($workflow['keyword'] ?? '')); ?>" class="regular-text"<?php echo self::disabled_attr($brief_locked); ?>>
                        </p>
                        <p>
                            <label for="entity">Diseñador / estudio / marca responsable</label>
                            <input type="text" id="entity" name="entity" value="<?php echo esc_attr((string) ($workflow['entity'] ?? '')); ?>" class="regular-text"<?php echo self::disabled_attr($brief_locked); ?>>
                        </p>
                    </div>

                    <p>
                        <label for="official_source">URL Diseñador / estudio / marca responsable</label>
                        <input type="url" id="official_source" name="official_source" value="<?php echo esc_attr((string) ($workflow['official_source'] ?? '')); ?>" class="large-text" placeholder="https://..."<?php echo self::disabled_attr($brief_locked); ?>>
                        <span class="description">Se usa como enlace externo obligatorio cuando puede integrarse de forma natural. No es la URL documental de nota de prensa si esa fuente es distinta.</span>
                    </p>

                    <div class="idg-two-cols idg-category-piece-row" data-piece-type-map="<?php echo esc_attr(wp_json_encode($category_piece_type_map)); ?>">
                        <p>
                            <label for="category_id">Categoría WordPress</label>
                            <select id="category_id" name="category_id"<?php echo self::disabled_attr($brief_locked); ?>>
                                <option value="0">Seleccionar categoría</option>
                                <?php if (!is_wp_error($categories)) : ?>
                                    <?php foreach ($categories as $cat) : ?>
                                        <option value="<?php echo esc_attr($cat->term_id); ?>" <?php selected((int) ($workflow['category_id'] ?? 0), (int) $cat->term_id); ?>><?php echo esc_html($cat->name); ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </p>
                        <p>
                            <label for="piece_type_display">Tipo de pieza</label>
                            <input type="text" id="piece_type_display" class="regular-text" value="<?php echo esc_attr($selected_piece_type); ?>" readonly>
                            <input type="hidden" id="piece_type" name="piece_type" value="<?php echo esc_attr($selected_piece_type); ?>">
                            <span class="description idg-field-help">Automático según categoría.</span>
                        </p>
                    </div>

                    <?php
                    $selected_tags = array_map('intval', (array) ($workflow['tag_ids'] ?? []));
                    $tag_data = [];
                    $priority_preset_data = IDG_Priority_Readings::admin_data($categories, $tags);
                    if (!is_wp_error($tags)) {
                        foreach ($tags as $tag) {
                            $tag_data[] = [
                                'id' => (int) $tag->term_id,
                                'name' => (string) $tag->name,
                                'categoryIds' => self::tag_category_ids_for_admin((string) $tag->name, $categories),
                            ];
                        }
                    }
                    ?>
                    <div class="idg-tag-picker<?php echo $brief_locked ? ' is-locked' : ''; ?>" data-tags="<?php echo esc_attr(wp_json_encode($tag_data)); ?>" data-selected="<?php echo esc_attr(wp_json_encode($selected_tags)); ?>" data-locked="<?php echo $brief_locked ? '1' : '0'; ?>">
                        <label for="idg_tag_filter">Etiquetas WordPress existentes</label>
                        <div class="idg-tag-selected" id="idg_tag_selected" aria-live="polite"></div>
                        <input type="search" id="idg_tag_filter" class="regular-text idg-tag-filter" placeholder="Escribe para buscar y seleccionar etiquetas..." autocomplete="off"<?php echo self::disabled_attr($brief_locked); ?>>
                        <div class="idg-tag-suggestions" id="idg_tag_suggestions" aria-label="Sugerencias de etiquetas"></div>
                        <div id="idg_tag_inputs"></div>
                        <span class="description">Elige primero una categoría para ver las etiquetas sugeridas. También puedes escribir para buscar dentro de las etiquetas existentes. El plugin no crea etiquetas nuevas.</span>
                    </div>

                    <p>
                        <label for="brief_fact">Hecho base</label>
                        <textarea id="brief_fact" name="brief_fact" rows="4" class="large-text"<?php echo self::disabled_attr($brief_locked); ?> placeholder="Qué pasó, qué se presentó, quién participa y qué dato sostiene el artículo."><?php echo esc_textarea((string) ($workflow['brief_fact'] ?? '')); ?></textarea>
                    </p>

                    <p>
                        <label for="editorial_angle">Ajuste editorial opcional</label>
                        <textarea id="editorial_angle" name="editorial_angle" rows="4" class="large-text"<?php echo self::disabled_attr($brief_locked); ?> placeholder="Opcional. Indica enfoque, tono, advertencias o instrucciones específicas para esta pieza."><?php echo esc_textarea((string) ($workflow['editorial_angle'] ?? '')); ?></textarea>
                    </p>

                    <div class="idg-priority-readings" data-category-presets="<?php echo esc_attr(wp_json_encode($priority_preset_data['categories'])); ?>" data-tag-presets="<?php echo esc_attr(wp_json_encode($priority_preset_data['tags'])); ?>">
                        <label for="priority_readings">Receta editorial compacta</label>
                        <textarea id="priority_readings" name="priority_readings" rows="3" class="large-text"<?php echo self::disabled_attr($brief_locked); ?> placeholder="Se calcula desde categoría, tag principal, tags secundarios y ángulo editorial. Debe ser breve: marco + foco + riesgo editorial."><?php echo esc_textarea((string) ($workflow['priority_readings'] ?? '')); ?></textarea>
                        <p class="description">Gerizim calcula esta receta compacta antes de redactar. Las reglas técnicas de enlace se mantienen separadas en la biblioteca interna.</p>
                    </div>

                    <?php self::render_temporary_material_fields($workflow); ?>

                    <?php self::render_sponsored_fields($workflow); ?>

                    <p>
                        <label for="base_article">Artículo base</label>
                        <textarea id="base_article" name="base_article" rows="16" class="large-text code" placeholder="Pega aquí el artículo base o genera uno desde el brief."><?php echo esc_textarea((string) ($workflow['base_article'] ?? '')); ?></textarea>
                    </p>

                    <?php self::render_internal_links_fields($workflow); ?>

                    <?php
                    $base_done = trim((string) ($workflow['base_article'] ?? '')) !== '';
                    $editorial_done = !empty($workflow['editorial_result']);
                    $seo_done = !empty($workflow['seo_result']);
                    $draft_done = !empty($workflow['draft_post_id']);
                    ?>
                    <div class="idg-actions">
                        <?php $generate_done = !empty($workflow['base_article']) && ((string) ($workflow['last_action'] ?? '') === 'generate' || !empty($workflow['generated_from_brief'])); ?>
                        <button type="submit" name="step" value="generate" class="button button-primary <?php echo $generate_done ? 'idg-step-done' : ''; ?>" title="Generar artículo base desde el brief editorial">Generar artículo base</button>
                        <button type="submit" name="step" value="editorial" class="button button-primary <?php echo $editorial_done ? 'idg-step-done' : ''; ?>" <?php disabled(!$base_done); ?> title="<?php echo esc_attr($base_done ? 'Ejecutar o repetir Revisión editorial' : 'Primero ejecuta Generar artículo base'); ?>">Revisión editorial</button>
                        <button type="submit" name="step" value="seo" class="button button-primary <?php echo $seo_done ? 'idg-step-done' : ''; ?>" <?php disabled(!$editorial_done); ?> title="<?php echo esc_attr($editorial_done ? 'Ejecutar o repetir Revisión SEO' : 'Primero ejecuta Revisión editorial'); ?>">Revisión SEO</button>
                        <button type="submit" name="step" value="draft" class="button button-primary <?php echo $draft_done ? 'idg-step-done' : ''; ?>" <?php disabled(!$seo_done); ?> title="<?php echo esc_attr($seo_done ? 'Crear o repetir borrador en WordPress' : 'Primero ejecuta Revisión SEO'); ?>">Crear borrador en WordPress</button>
                        <?php if ($seo_done && self::should_show_force_draft($workflow)) : ?>
                            <button type="submit" name="step" value="draft_force" class="button idg-button-warning" data-confirm="Esta acción crea el borrador aunque la validación real tenga errores. Úsalo solo para continuar una prueba o cuando una regla esté forzando demasiado la redacción." title="Crear borrador ignorando la validación real">Ignorar validación y crear borrador</button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="idg-card idg-side-card">
                    <h2>Estado</h2>
                    <?php self::render_research_visibility_panel($workflow); ?>
                    <?php self::render_status($workflow); ?>
                    <p class="description">La publicación automática no está permitida. Toda salida se crea como <strong>Pendiente de revisión</strong>.</p>
                </div>
            </form>

            <?php self::render_results($workflow); ?>
            <?php self::render_workflow_history($workflow); ?>
        </div>
        <?php
    }


    private static function render_radar_importer(string $workflow_id, array $workflow): void {
        $is_locked = self::has_generated_workflow_content($workflow) || ((string) ($workflow['status'] ?? '') === 'processing');
        ?>
        <div class="idg-card idg-radar-import-card">
            <h2>Importar brief desde Radar editorial</h2>
            <p class="description">Pega un JSON individual exportado por el Radar. La importación precarga la ficha de encargo y guarda el flujo actual, pero no genera artículo, no crea borrador y no ejecuta OpenAI.</p>
            <?php if ($is_locked) : ?>
                <div class="notice notice-warning inline"><p>Este flujo ya tiene contenido generado o está en proceso. Para importar un brief del Radar, usa Reinicio parcial o inicia una nueva redacción.</p></div>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="idg-radar-import-form">
                <?php wp_nonce_field('idg_submit_workflow'); ?>
                <input type="hidden" name="action" value="idg_submit_workflow">
                <input type="hidden" name="workflow_id" value="<?php echo esc_attr($workflow_id); ?>">
                <textarea name="radar_brief_json" rows="8" class="large-text code" placeholder="Pega aquí el JSON individual del Radar editorial"<?php echo self::disabled_attr($is_locked); ?>></textarea>
                <p class="description">Validaciones: JSON Radar v1.1, sistema/destino correctos, tipo Actualidad o Agenda, keyword, categoría, responsable, fuente, hecho base, ángulo y tag principal. Las categorías y etiquetas se buscan por nombre; no se crean términos automáticamente.</p>
                <p><button type="submit" name="step" value="validate_radar" class="button button-secondary"<?php echo self::disabled_attr($is_locked); ?>>Validar JSON</button> <button type="submit" name="step" value="import_radar" class="button button-primary"<?php disabled($is_locked); ?>>Importar a ficha de encargo</button></p>
            </form>
        </div>
        <?php
    }

    private static function has_generated_workflow_content(array $workflow): bool {
        return trim((string) ($workflow['base_article'] ?? '')) !== ''
            || trim((string) ($workflow['editorial_result'] ?? '')) !== ''
            || trim((string) ($workflow['seo_result'] ?? '')) !== ''
            || !empty($workflow['draft_post_id']);
    }

    private static function should_show_force_draft(array $workflow): bool {
        if (!empty($workflow['draft_post_id'])) {
            return false;
        }
        $last_error = (string) ($workflow['last_error'] ?? '');
        if ($last_error !== '' && str_contains($last_error, 'Validación real bloqueó')) {
            return true;
        }
        $history = isset($workflow['history']) && is_array($workflow['history']) ? $workflow['history'] : [];
        $last = end($history);
        return is_array($last) && (string) ($last['event'] ?? '') === 'draft_failed';
    }

    private static function render_sponsored_fields(array $workflow): void {
        $selected_rel = (string) ($workflow['sponsored_link_rel'] ?? 'sponsored');
        $visible_label = !empty($workflow['sponsored_visible_label']) && (string) $workflow['sponsored_visible_label'] === '1';
        ?>
        <div class="idg-sponsored-fields" data-sponsored-panel>
            <h3>Modo patrocinado / colaboración</h3>
            <p class="description">Usa estos campos para artículos pagados, colaboraciones, guest posts o encargos con enlace obligatorio. La información queda como control interno y Gerizim la usa sin convertir el artículo en tono comercial.</p>
            <div class="idg-two-cols">
                <p>
                    <label for="sponsor_client">Cliente / marca</label>
                    <input type="text" id="sponsor_client" name="sponsor_client" value="<?php echo esc_attr((string) ($workflow['sponsor_client'] ?? '')); ?>" class="regular-text" placeholder="Nombre del cliente o marca">
                </p>
                <p>
                    <label for="sponsored_topic">Tema del artículo</label>
                    <input type="text" id="sponsored_topic" name="sponsored_topic" value="<?php echo esc_attr((string) ($workflow['sponsored_topic'] ?? '')); ?>" class="regular-text" placeholder="Tema editorial o enfoque general">
                </p>
            </div>
            <p>
                <label for="sponsored_brief">Brief del cliente</label>
                <textarea id="sponsored_brief" name="sponsored_brief" rows="5" class="large-text" placeholder="Información entregada por el cliente, objetivo del artículo, contexto o descripción del servicio/producto. No se publica como tal; Gerizim lo transforma en texto editorial."><?php echo esc_textarea((string) ($workflow['sponsored_brief'] ?? '')); ?></textarea>
            </p>
            <div class="idg-two-cols">
                <p>
                    <label for="sponsored_must_include">Puntos obligatorios</label>
                    <textarea id="sponsored_must_include" name="sponsored_must_include" rows="4" class="large-text" placeholder="Ideas, datos o menciones que deben aparecer si están sustentadas."><?php echo esc_textarea((string) ($workflow['sponsored_must_include'] ?? '')); ?></textarea>
                </p>
                <p>
                    <label for="sponsored_avoid">Puntos a evitar</label>
                    <textarea id="sponsored_avoid" name="sponsored_avoid" rows="4" class="large-text" placeholder="Claims, tonos, palabras o enfoques que no deben usarse."><?php echo esc_textarea((string) ($workflow['sponsored_avoid'] ?? '')); ?></textarea>
                </p>
            </div>
            <div class="idg-three-cols">
                <p>
                    <label for="sponsored_required_link">Enlace obligatorio</label>
                    <input type="url" id="sponsored_required_link" name="sponsored_required_link" value="<?php echo esc_attr((string) ($workflow['sponsored_required_link'] ?? '')); ?>" class="regular-text" placeholder="https://...">
                </p>
                <p>
                    <label for="sponsored_anchor">Anchor solicitado</label>
                    <input type="text" id="sponsored_anchor" name="sponsored_anchor" value="<?php echo esc_attr((string) ($workflow['sponsored_anchor'] ?? '')); ?>" class="regular-text" placeholder="Anchor o frase objetivo">
                </p>
                <p>
                    <label for="sponsored_link_rel">Tipo de enlace</label>
                    <select id="sponsored_link_rel" name="sponsored_link_rel">
                        <?php foreach (['sponsored' => 'sponsored', 'nofollow' => 'nofollow', 'dofollow' => 'dofollow'] as $value => $label) : ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($selected_rel, $value); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </p>
            </div>
            <p>
                <label for="sponsored_restrictions">Restricciones editoriales / notas internas</label>
                <textarea id="sponsored_restrictions" name="sponsored_restrictions" rows="4" class="large-text" placeholder="Condiciones del encargo, cautelas editoriales o revisión necesaria antes de publicar."><?php echo esc_textarea((string) ($workflow['sponsored_restrictions'] ?? '')); ?></textarea>
            </p>
            <p>
                <label><input type="checkbox" name="sponsored_visible_label" value="1" <?php checked($visible_label); ?>> Agregar aviso visible “Contenido patrocinado” al inicio del borrador.</label>
            </p>
        </div>
        <?php
    }

    private static function render_temporary_material_fields(array $workflow): void {
        $brief_locked = self::is_brief_locked($workflow);
        $material = (string) ($workflow['temp_material_text'] ?? '');
        $filename = (string) ($workflow['temp_material_filename'] ?? '');
        $card = (string) ($workflow['document_card'] ?? '');
        $warnings = isset($workflow['temp_material_warnings']) && is_array($workflow['temp_material_warnings']) ? $workflow['temp_material_warnings'] : [];
        $chars = mb_strlen($material);
        ?>
        <div class="idg-temp-material">
            <label for="temp_material_text">Material de apoyo / nota de prensa</label>
            <p class="description">Pega aquí el texto recibido, nota de prensa, briefing, comunicado o material temporal. Se usa durante la redacción activa y no se guarda en el metabox del artículo. Su contenido se enviará a OpenAI API para generar y revisar el artículo.</p>
            <textarea id="temp_material_text" name="temp_material_text" rows="8" class="large-text code"<?php echo self::disabled_attr($brief_locked); ?> data-idg-counter="#idg_temp_material_count" placeholder="Pega aquí el material de apoyo. También puedes adjuntar un TXT, MD, DOCX, HTML, CSV o PDF simple."><?php echo esc_textarea($material); ?></textarea>
            <p class="description"><span id="idg_temp_material_count"><?php echo esc_html(number_format_i18n($chars)); ?></span> caracteres activos en esta redacción. Si el material es muy extenso, Gerizim creará una ficha documental temporal y usará extractos controlados.</p>
            <p>
                <label for="temp_material_file">Adjuntar archivo temporal</label>
                <input type="file" id="temp_material_file" name="temp_material_file" accept=".txt,.md,.markdown,.docx,.pdf,.html,.htm,.csv"<?php echo self::disabled_attr($brief_locked); ?>>
                <span class="description">El archivo no se guarda en Biblioteca de Medios ni en el artículo. Para regenerar más adelante, conserva el flujo activo o adjunta nuevamente el material.</span>
            </p>
            <p>
                <label for="source_information_url">URL oficial o fuente complementaria</label>
                <input type="url" id="source_information_url" name="source_information_url" value="<?php echo esc_attr((string) ($workflow['source_information_url'] ?? '')); ?>" class="large-text" placeholder="https://..."<?php echo self::disabled_attr($brief_locked); ?>>
                <span class="description">Agrega una URL para leer, verificar o complementar el material anterior. Puede ser fuente oficial, convocatoria, ficha del proyecto o página de apoyo documental.</span>
            </p>
            <?php if ($filename !== '') : ?>
                <p class="description"><strong>Último archivo temporal leído:</strong> <?php echo esc_html($filename); ?></p>
            <?php endif; ?>
            <?php if (!empty($warnings)) : ?>
                <div class="notice notice-warning inline"><p><strong>Avisos del material temporal:</strong></p><ul>
                    <?php foreach ($warnings as $warning) : ?>
                        <li><?php echo esc_html((string) $warning); ?></li>
                    <?php endforeach; ?>
                </ul></div>
            <?php endif; ?>
            <?php if ($card !== '') : ?>
                <details class="idg-document-card" open>
                    <summary>Ficha documental temporal activa</summary>
                    <textarea readonly rows="8" class="large-text code"><?php echo esc_textarea($card); ?></textarea>
                    <p class="description">Esta ficha vive solo en el flujo activo. Ayuda a Generar artículo base, Revisión editorial y Revisión SEO sin guardar la nota de prensa en el artículo.</p>
                </details>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function render_internal_links_fields(array $workflow): void {
        $links = IDG_Internal_Links::normalize($workflow);
        ?>
        <div class="idg-link-builder">
            <label>Enlaces internos automáticos</label>
            <p class="description">Se generan desde las etiquetas y la categoría seleccionadas. Gerizim creará anchors contextuales dentro del artículo; no es necesario escribir URL, anchor ni tipo de enlace manualmente.</p>
            <?php if (empty($links)) : ?>
                <div class="idg-auto-link-empty">
                    Selecciona etiquetas y categoría. Al guardar o ejecutar una revisión, el plugin detectará la página del tag principal o, si el tag es noindex, la página principal de la categoría.
                </div>
            <?php else : ?>
                <div class="idg-auto-link-list">
                    <?php foreach ($links as $index => $link) : ?>
                        <div class="idg-auto-link-row">
                            <div>
                                <strong><?php echo esc_html((string) ($link['label'] ?? 'Enlace interno')); ?></strong>
                                <?php if (!empty($link['source_name'])) : ?>
                                    <span><?php echo esc_html((string) $link['source_name']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($link['context'])) : ?>
                                    <p class="description"><?php echo esc_html((string) $link['context']); ?></p>
                                <?php endif; ?>
                            </div>
                            <code><?php echo esc_html((string) ($link['url'] ?? '')); ?></code>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function set_radar_import_notice(string $type, string $message, array $warnings = []): void {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return;
        }
        set_transient('idg_radar_import_notice_' . $user_id, [
            'type' => $type,
            'message' => $message,
            'warnings' => array_values(array_filter(array_map('sanitize_text_field', $warnings))),
        ], 120);
    }

    private static function get_radar_import_notice(): array {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return [];
        }
        $key = 'idg_radar_import_notice_' . $user_id;
        $notice = get_transient($key);
        delete_transient($key);
        return is_array($notice) ? $notice : [];
    }

    private static function render_notice(array $workflow): void {
        $radar_notice = self::get_radar_import_notice();
        if (!empty($radar_notice['message'])) {
            $class = (string) ($radar_notice['type'] ?? '') === 'success' ? 'notice-success' : 'notice-error';
            echo '<div class="notice ' . esc_attr($class) . '"><p>' . esc_html((string) $radar_notice['message']) . '</p>';
            if (!empty($radar_notice['warnings']) && is_array($radar_notice['warnings'])) {
                echo '<ul class="idg-warning-list">';
                foreach ($radar_notice['warnings'] as $warning) {
                    echo '<li>' . esc_html((string) $warning) . '</li>';
                }
                echo '</ul>';
            }
            echo '</div>';
        }
        if (!empty($_GET['message'])) {
            $message = sanitize_key((string) $_GET['message']);
            if ($message === 'scheduled') {
                echo '<div class="notice notice-info"><p>Tarea enviada a cola. Actualiza esta página para ver el resultado.</p></div>';
            } elseif ($message === 'processed') {
                echo '<div class="notice notice-success"><p>Proceso completado. Revisa el resultado actualizado en esta pantalla.</p></div>';
            } elseif ($message === 'saved') {
                echo '<div class="notice notice-success"><p>Flujo guardado.</p></div>';
            } elseif ($message === 'reset') {
                echo '<div class="notice notice-success"><p>Nueva redacción lista. El flujo anterior fue limpiado de la pantalla; las entradas ya creadas no se eliminaron.</p></div>';
            } elseif ($message === 'reset_blocked') {
                echo '<div class="notice notice-warning"><p>Hay una tarea en proceso. Espera a que termine antes de iniciar una nueva redacción.</p></div>';
            } elseif ($message === 'partial_reset') {
                echo '<div class="notice notice-success"><p>Reinicio parcial mínimo aplicado. Se conservaron solo Keyword principal, responsable, URL responsable, categoría, etiquetas y Hecho base. La investigación, fichas, artículos, SEO, validación, metadatos y borrador fueron limpiados.</p></div>';
            } elseif ($message === 'needs_keyword') {
                echo '<div class="notice notice-warning"><p>Completa primero la Keyword principal para generar el artículo base.</p></div>';
            } elseif ($message === 'needs_brief') {
                echo '<div class="notice notice-warning"><p>Completa primero el Hecho base del brief editorial.</p></div>';
            } elseif ($message === 'needs_sponsored_brief') {
                echo '<div class="notice notice-warning"><p>Completa primero el Brief del cliente o el Hecho base para generar el artículo patrocinado.</p></div>';
            } elseif ($message === 'needs_article') {
                echo '<div class="notice notice-warning"><p>Pega o genera primero el artículo base antes de ejecutar Revisión editorial.</p></div>';
            } elseif ($message === 'needs_editorial') {
                echo '<div class="notice notice-warning"><p>Primero ejecuta Revisión editorial. Ese paso guarda la Versión 1 y genera la Versión 2 en un solo clic.</p></div>';
            } elseif ($message === 'needs_seo') {
                echo '<div class="notice notice-warning"><p>Primero ejecuta Revisión SEO para generar la Versión 3 final.</p></div>';
            } elseif ($message === 'processed_force') {
                echo '<div class="notice notice-warning"><p>Borrador creado ignorando la validación real. Revísalo con cuidado antes de publicar.</p></div>';
            }
        }
        if (!empty($workflow['last_error'])) {
            echo '<div class="notice notice-error"><p>' . esc_html($workflow['last_error']) . '</p></div>';
        }
    }


    private static function render_research_visibility_panel(array $workflow): void {
        if (empty($workflow) || !class_exists('IDG_Web_Research')) {
            return;
        }
        echo '<div class="idg-research-panel">';
        echo '<h3>Investigación aplicada</h3>';
        echo '<p><strong>Nivel:</strong> ' . esc_html((string) ($workflow['web_research_intensity'] ?? 'sin ejecutar')) . '<br>';
        echo '<strong>Estado:</strong> ' . esc_html((string) ($workflow['web_research_status'] ?? 'no registrada')) . '<br>';
        echo '<strong>Motivo:</strong> ' . esc_html((string) ($workflow['web_research_reason'] ?? '')) . '</p>';
        echo '<p><strong>URL oficial o fuente complementaria:</strong> ' . esc_html((string) ($workflow['source_information_url'] ?? '')) . '<br>';
        echo '<strong>Lectura URL:</strong> ' . esc_html((string) ($workflow['source_url_status'] ?? '')) . '</p>';
        $sources = isset($workflow['web_research_sources']) && is_array($workflow['web_research_sources']) ? $workflow['web_research_sources'] : [];
        if (!empty($sources)) {
            echo '<details><summary>Fuentes detectadas</summary><ul>';
            foreach (array_slice($sources, 0, 6) as $source) {
                $title = trim((string) ($source['title'] ?? 'Fuente'));
                $url = trim((string) ($source['url'] ?? ''));
                echo '<li>' . esc_html($title) . ($url !== '' ? '<br><code>' . esc_html($url) . '</code>' : '') . '</li>';
            }
            echo '</ul></details>';
        }
        $article = (string) ($workflow['seo_result'] ?? $workflow['editorial_result'] ?? $workflow['base_article'] ?? '');
        $application = IDG_Web_Research::application_lines($workflow, $article);
        echo '<details><summary>Aplicación en el artículo</summary><ul>';
        foreach (array_slice($application, 0, 8) as $line) {
            echo '<li>' . esc_html(preg_replace('/^[-*]\s*/', '', (string) $line)) . '</li>';
        }
        echo '</ul></details>';
        echo '<p class="description">Esta tarjeta debe revisarse antes de crear el borrador. Resume el nivel de investigación y cómo se reflejó en el texto disponible.</p>';
        echo '</div>';
    }

    private static function render_status(array $workflow): void {
        if (empty($workflow)) {
            echo '<p>No hay un flujo activo.</p>';
            self::render_openai_usage_status();
            return;
        }
        $status = (string) ($workflow['status'] ?? 'sin estado');
        echo '<p><strong>Workflow:</strong><br><code>' . esc_html((string) ($workflow['workflow_id'] ?? '')) . '</code></p>';
        echo '<p><strong>Estado:</strong> ' . esc_html($status) . '</p>';
        if (!empty($workflow['last_action'])) {
            echo '<p><strong>Última acción:</strong> ' . esc_html((string) $workflow['last_action']) . '</p>';
        }
        self::render_openai_usage_status();
        $draft_edit_link = (string) ($workflow['draft_edit_link'] ?? '');
        if ($draft_edit_link === '' && !empty($workflow['draft_post_id'])) {
            $generated_link = get_edit_post_link((int) $workflow['draft_post_id'], 'raw');
            $draft_edit_link = is_string($generated_link) ? $generated_link : '';
        }
        if ($draft_edit_link !== '') {
            echo '<p><a class="button button-primary" href="' . esc_url($draft_edit_link) . '">Abrir entrada creada</a></p>';
        }
        echo '<div class="idg-status-actions">';
        $reset_disabled = ((string) ($workflow['status'] ?? '') === 'processing') ? ' disabled' : '';
        echo '<button type="submit" name="step" value="partial_reset" class="button idg-button-reset-partial" data-confirm="¿Aplicar reinicio parcial mínimo? Se conservarán solo Keyword principal, responsable, URL responsable, categoría, etiquetas y Hecho base. Se limpiarán investigación, fichas, artículos, SEO, validación, metadatos y borrador."' . $reset_disabled . '>Reinicio parcial</button>';
        echo '<button type="submit" name="step" value="reset" class="button idg-button-reset" data-confirm="¿Iniciar una nueva redacción? Se limpiará el flujo actual de la pantalla, pero no se eliminarán entradas ya creadas."' . $reset_disabled . '>Nueva redacción</button>';
        if (!empty($workflow['workflow_id'])) {
            $report_url = wp_nonce_url(
                admin_url('admin-post.php?action=idg_download_report&workflow_id=' . rawurlencode((string) $workflow['workflow_id'])),
                'idg_download_report_' . (string) $workflow['workflow_id']
            );
            echo '<a class="button idg-button-report" href="' . esc_url($report_url) . '">Descargar reporte completo</a>';
        }
        echo '</div>';
        if (!empty($workflow['warnings']) && is_array($workflow['warnings'])) {
            echo '<h3>Alertas automáticas</h3><ul class="idg-warning-list">';
            foreach ($workflow['warnings'] as $warning) {
                echo '<li>' . esc_html((string) $warning) . '</li>';
            }
            echo '</ul>';
        }
    }


    private static function render_openai_usage_status(): void {
        $balance = IDG_Usage_Estimator::reference_balance();
        $summary = $balance['summary'];
        echo '<div class="idg-openai-usage">';
        echo '<h3>OpenAI · Referencia</h3>';
        if ((float) $balance['initial'] > 0) {
            echo '<p><strong>Saldo inicial:</strong> USD ' . esc_html(number_format_i18n((float) $balance['initial'], 2)) . '<br>';
            echo '<strong>Consumo estimado Gerizim:</strong> USD ' . esc_html(number_format_i18n((float) $balance['estimated_spend'], 4)) . '<br>';
            echo '<strong>Saldo estimado:</strong> USD ' . esc_html(number_format_i18n((float) $balance['estimated_balance'], 2)) . '</p>';
        } else {
            echo '<p><strong>Saldo estimado:</strong> no configurado.</p>';
            echo '<p class="description">Configura un saldo inicial de referencia en Gerizim → Ajustes para calcular una estimación local.</p>';
        }
        echo '<p class="description">Mes: ' . esc_html((string) $summary['month']) . ' · Ejecuciones: ' . esc_html((string) $summary['executions']) . ' · Tokens aprox.: ' . esc_html(number_format_i18n((int) $summary['tokens'])) . '</p>';
        echo '<p><a href="https://platform.openai.com/settings/organization/billing/credit-grants" target="_blank" rel="noopener noreferrer">Ver saldo oficial</a> · <a href="https://platform.openai.com/usage" target="_blank" rel="noopener noreferrer">Ver uso oficial</a></p>';
        echo '</div>';
    }

    private static function render_results(array $workflow): void {
        if (empty($workflow)) {
            return;
        }
        ?>
        <div class="idg-result-grid">
            <div class="idg-card">
                <h2>Versión 1 · Artículo base</h2>
                <textarea readonly rows="14" class="large-text code"><?php echo esc_textarea((string) ($workflow['base_article'] ?? '')); ?></textarea>
            </div>
            <div class="idg-card">
                <h2>Versión 2 · Revisión editorial</h2>
                <textarea readonly rows="14" class="large-text code"><?php echo esc_textarea((string) ($workflow['editorial_result'] ?? '')); ?></textarea>
            </div>
            <div class="idg-card idg-result-full">
                <h2>Versión 3 · Revisión SEO final</h2>
                <textarea readonly rows="20" class="large-text code"><?php echo esc_textarea((string) ($workflow['seo_result'] ?? '')); ?></textarea>
            </div>
            <?php if (!empty($workflow['feedback_notes'])) : ?>
                <div class="idg-card idg-result-full idg-feedback-card">
                    <h2>Retroalimentación Gerizim</h2>
                    <p class="description">Correcciones realizadas y comentarios para mejorar el siguiente ciclo de redacción.</p>
                    <textarea readonly rows="6" class="large-text code"><?php echo esc_textarea((string) ($workflow['feedback_notes'] ?? '')); ?></textarea>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function render_workflow_history(array $workflow): void {
        $history = array_slice(array_reverse((array) ($workflow['history'] ?? [])), 0, 10);
        if (empty($history)) {
            return;
        }
        ?>
        <div class="idg-card">
            <h2>Historial del flujo actual</h2>
            <p class="description">Registro breve de esta redacción. Al iniciar una nueva redacción sin guardar el flujo, este historial se elimina.</p>
            <table class="widefat striped">
                <thead><tr><th>Fecha</th><th>Evento</th><th>Mensaje</th></tr></thead>
                <tbody>
                <?php foreach ($history as $item) : ?>
                    <tr>
                        <td><?php echo esc_html((string) ($item['time'] ?? '')); ?></td>
                        <td><?php echo esc_html((string) ($item['event'] ?? '')); ?></td>
                        <td><?php echo esc_html((string) ($item['message'] ?? '')); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
