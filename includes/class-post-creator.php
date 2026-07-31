<?php
if (!defined('ABSPATH')) {
    exit;
}

final class IDG_Post_Creator {
    public static function create_pending_post(array $workflow): array {
        $seo_result = (string) ($workflow['seo_result'] ?? '');
        if ($seo_result === '') {
            return [
                'success' => false,
                'message' => 'No existe una versión SEO final para crear la entrada.',
            ];
        }

        if (class_exists('IDG_Assignment_Card')) {
            $workflow = IDG_Assignment_Card::attach($workflow);
        }

        $sections = self::extract_sections($seo_result);
        $content = (string) ($sections['article'] ?? '');
        $content = self::prepare_public_article_content($content, $workflow);
        $title = self::extract_title($content, $workflow['keyword'] ?? 'Artículo ideasDi');
        $meta_description = self::clean_meta_description((string) ($sections['meta_description'] ?? ''));
        $seo_report = trim((string) ($sections['seo_report'] ?? ''));
        $social_copy = trim((string) ($sections['social_copy'] ?? ''));
        $reel_package = trim((string) ($sections['reel_package'] ?? ''));
        $reel_package = self::ensure_valid_reel_package($reel_package, $content, $workflow);
        $sections['reel_package'] = $reel_package;
        $feedback_notes = trim((string) ($sections['feedback_notes'] ?? $workflow['feedback_notes'] ?? ''));
        $processed_seo_result = '';

        $html_content = self::markdownish_to_html($content, $title);
        $html_before_postprocessing = $html_content;
        $html_content = self::ensure_official_source_link($html_content, $workflow);
        $html_content = self::ensure_internal_links($html_content, $workflow);
        $html_content = self::deduplicate_configured_internal_links($html_content, $workflow);
        $html_content = self::ensure_sponsored_required_link($html_content, $workflow);
        $html_content = self::ensure_minimum_bold($html_content, $workflow);
        $html_content = self::strip_bold_from_html_headings($html_content);
        $html_content = self::open_links_new_tab($html_content);
        $html_content = self::apply_sponsored_link_rel($html_content, $workflow);
        $html_content = self::maybe_add_sponsored_disclosure($html_content, $workflow);
        $postprocessing_audit = self::postprocessing_audit($html_before_postprocessing, $html_content, $workflow);
        $workflow['postprocessing_audit'] = $postprocessing_audit;
        $seo_report = self::repair_seo_report_link_counts($seo_report, $html_content, $workflow);
        $feedback_notes = self::repair_feedback_link_notes($feedback_notes, $html_content, $workflow);
        $processed_seo_result = self::rebuild_seo_result($content, $meta_description, $seo_report, $social_copy, $reel_package, $feedback_notes);
        $post_content = self::html_to_gutenberg_blocks($html_content);
        if (class_exists('IDG_Final_Guard')) {
            $validation = IDG_Final_Guard::validate_before_draft($content, $html_content, $sections, $workflow);
            $gutenberg_validation = IDG_Final_Guard::validate_gutenberg_blocks($post_content);
            if (empty($gutenberg_validation['ok'])) {
                $validation['ok'] = false;
                $validation['errors'] = array_merge((array) ($validation['errors'] ?? []), (array) ($gutenberg_validation['errors'] ?? []));
                $validation['summary'] = trim((string) ($validation['summary'] ?? '') . "\n" . implode("\n", array_map(static fn($e) => '- ERROR: ' . $e, (array) ($gutenberg_validation['errors'] ?? []))));
            }
            if (empty($validation['ok'])) {
                if (!empty($workflow['force_validation_override'])) {
                    $validation['warnings'] = array_merge((array) ($validation['warnings'] ?? []), (array) ($validation['errors'] ?? []));
                    $validation['summary'] = trim((string) ($validation['summary'] ?? '') . "
" . '- AVISO: Entrada creada en Pendiente de revisión con validación ignorada manualmente por el editor. Revisar antes de publicar.');
                    $validation['ok'] = true;
                    $validation['overridden'] = true;
                } else {
                    return [
                        'success' => false,
                        'message' => 'Validación real bloqueó la creación de la entrada: ' . implode(' · ', (array) ($validation['errors'] ?? [])),
                        'validation_summary' => (string) ($validation['summary'] ?? ''),
                    ];
                }
            }
        }

        $slug_source = (string) ($workflow['keyword'] ?? '');
        if ($slug_source === '') {
            $slug_source = $title;
        }

        $postarr = [
            'post_title' => wp_strip_all_tags($title),
            'post_name' => sanitize_title($slug_source),
            'post_content' => $post_content,
            'post_excerpt' => wp_strip_all_tags($meta_description),
            'post_status' => 'pending',
            'post_type' => 'post',
            'post_author' => get_current_user_id(),
        ];

        $post_id = wp_insert_post($postarr, true);
        if (is_wp_error($post_id)) {
            return [
                'success' => false,
                'message' => $post_id->get_error_message(),
            ];
        }

        if (!empty($workflow['category_id'])) {
            wp_set_post_terms($post_id, [(int) $workflow['category_id']], 'category', false);
        }
        if (!empty($workflow['tag_ids']) && is_array($workflow['tag_ids'])) {
            wp_set_post_terms($post_id, array_map('intval', $workflow['tag_ids']), 'post_tag', false);
        }

        update_post_meta($post_id, '_idg_keyword', sanitize_text_field($workflow['keyword'] ?? ''));
        update_post_meta($post_id, '_idg_entity', sanitize_text_field($workflow['entity'] ?? ''));
        update_post_meta($post_id, '_idg_piece_type', sanitize_text_field($workflow['piece_type'] ?? ''));
        update_post_meta($post_id, '_idg_brief_summary', wp_kses_post(self::brief_summary($workflow)));
        update_post_meta($post_id, '_idg_official_source', esc_url_raw($workflow['official_source'] ?? ''));
        update_post_meta($post_id, '_idg_internal_links', wp_kses_post(IDG_Internal_Links::summary($workflow)));
        update_post_meta($post_id, '_idg_assignment_card', wp_kses_post((string) ($workflow['assignment_card'] ?? '')));
        if (isset($validation) && is_array($validation)) {
            update_post_meta($post_id, '_idg_final_validation', wp_kses_post((string) ($validation['summary'] ?? '')));
        }
        update_post_meta($post_id, '_idg_meta_description', sanitize_text_field($meta_description));
        update_post_meta($post_id, '_idg_seo_report', wp_kses_post($seo_report));
        update_post_meta($post_id, '_idg_social_copy', wp_kses_post($social_copy));
        update_post_meta($post_id, '_idg_reel_package', wp_kses_post($reel_package));
        update_post_meta($post_id, '_idg_feedback_notes', wp_kses_post($feedback_notes));
        update_post_meta($post_id, '_idg_sponsor_client', sanitize_text_field($workflow['sponsor_client'] ?? ''));
        update_post_meta($post_id, '_idg_sponsored_link', esc_url_raw($workflow['sponsored_required_link'] ?? ''));
        update_post_meta($post_id, '_idg_sponsored_anchor', sanitize_text_field($workflow['sponsored_anchor'] ?? ''));
        update_post_meta($post_id, '_idg_sponsored_rel', sanitize_text_field($workflow['sponsored_link_rel'] ?? ''));
        update_post_meta($post_id, '_idg_sponsored_notes', wp_kses_post(self::sponsored_summary($workflow)));
        update_post_meta($post_id, '_idg_sponsored_visible_label', !empty($workflow['sponsored_visible_label']) ? '1' : '0');
        update_post_meta($post_id, '_idg_prompt_versions', wp_json_encode([
            'material_card' => IDG_Prompt_Library::version('material_card'),
            'editorial_plan' => IDG_Prompt_Library::version('editorial_plan'),
            'generate' => IDG_Prompt_Library::version('generate'),
            'editorial' => IDG_Prompt_Library::version('editorial'),
            'seo' => IDG_Prompt_Library::version('seo'),
            'web_research' => IDG_Prompt_Library::version('web_research'),
        ]));
        update_post_meta($post_id, '_idg_execution_history', wp_json_encode([
            'created_at' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'workflow_id' => $workflow['workflow_id'] ?? '',
        ]));

        $radar_brief_id = absint($workflow['radar_brief_id'] ?? 0);
        $radar_hallazgo_id = absint($workflow['radar_hallazgo_id'] ?? 0);
        $workflow_id = sanitize_text_field((string) ($workflow['workflow_id'] ?? ''));
        $is_radar_workflow = (string) ($workflow['radar_source'] ?? '') === 'radar-editorial-ideasdi' && $radar_brief_id > 0 && $workflow_id !== '';
        update_post_meta($post_id, '_idg_radar_brief_id', $radar_brief_id);
        update_post_meta($post_id, '_idg_radar_hallazgo_id', $radar_hallazgo_id);
        update_post_meta($post_id, '_idg_workflow_id', $workflow_id);
        update_post_meta($post_id, '_idg_traceability_contract_version', $is_radar_workflow && class_exists('IDG_Traceability') ? IDG_Traceability::contract_version() : '');
        update_post_meta($post_id, '_idg_traceability_post_created_key', '');
        update_post_meta($post_id, '_idg_traceability_post_created_status', '');
        update_post_meta($post_id, '_idg_traceability_post_created_synced_at_utc', '');
        update_post_meta($post_id, '_idg_traceability_published_key', '');
        update_post_meta($post_id, '_idg_traceability_published_status', '');
        update_post_meta($post_id, '_idg_traceability_published_synced_at_utc', '');
        update_post_meta($post_id, '_idg_published_at_utc', '');

        self::update_yoast_meta($post_id, $meta_description, $workflow['keyword'] ?? '', $title);

        return [
            'success' => true,
            'message' => 'Entrada creada en Pendiente de revisión.',
            'post_id' => $post_id,
            'edit_link' => get_edit_post_link($post_id, 'raw'),
            'validation_summary' => isset($validation) && is_array($validation) ? (string) ($validation['summary'] ?? '') : '',
            'postprocessed_html' => $html_content,
            'postprocessing_audit' => $postprocessing_audit,
            'seo_result' => $processed_seo_result,
            'reel_package' => $reel_package,
        ];
    }

    public static function update_existing_event(array $workflow): array {
        $post_id = (int) ($workflow['recurring_target_post_id'] ?? 0);
        $target_post_type = (string) ($workflow['recurring_target_post_type'] ?? '');
        $is_event = $target_post_type === 'evento';
        $is_contest = $target_post_type === 'post';
        $target_label = $is_contest ? 'concurso o convocatoria' : 'evento';
        if ($post_id <= 0 || (!$is_event && !$is_contest)) {
            return ['success' => false, 'message' => 'El workflow no contiene una publicación de destino válida.'];
        }
        if (!current_user_can('edit_post', $post_id)) {
            return ['success' => false, 'message' => 'No tienes permisos para editar la publicación vinculada.'];
        }
        $post_before = get_post($post_id);
        if (!$post_before instanceof WP_Post || (string) $post_before->post_type !== $target_post_type) {
            return ['success' => false, 'message' => 'La publicación vinculada ya no existe o cambió de tipo.'];
        }
        if ($is_contest && !has_category(34, $post_before)) {
            return ['success' => false, 'message' => 'La entrada vinculada ya no pertenece a la categoría Concursos y convocatorias.'];
        }
        $selected_post_id = (int) ($workflow['recurring_selected_post_id'] ?? $post_id);
        if ($selected_post_id !== $post_id) {
            return ['success' => false, 'message' => 'El ID seleccionado originalmente no coincide con la publicación vinculada. La redacción no fue aplicada.'];
        }
        if (class_exists('IDG_Recurring_Updates')) {
            $expected_fingerprint = (string) ($workflow['recurring_target_identity_fingerprint'] ?? '');
            if ($expected_fingerprint === '' && $is_event) {
                $expected_fingerprint = (string) ($workflow['recurring_target_immutable_fingerprint'] ?? '');
            }
            $content_type = $is_contest ? 'contest' : 'event';
            $current_fingerprint = IDG_Recurring_Updates::target_post_fingerprint($post_id, $content_type);
            if ($expected_fingerprint === '' || $current_fingerprint === '' || !hash_equals($expected_fingerprint, $current_fingerprint)) {
                return ['success' => false, 'message' => 'La identidad protegida de la publicación no coincide con el encargo editorial. Vuelve a seleccionarla por ID exacto.'];
            }
            $expected_signature = (string) ($workflow['recurring_target_signature'] ?? '');
            $current_signature = IDG_Recurring_Updates::editorial_target_signature($post_id, $target_post_type);
            if ($expected_signature === '' || $current_signature === '' || !hash_equals($expected_signature, $current_signature)) {
                return ['success' => false, 'message' => 'La publicación cambió después de preparar el encargo editorial. Vuelve a Actualizaciones recurrentes y genera un análisis nuevo antes de aplicar la redacción.'];
            }
        }

        $seo_result = (string) ($workflow['seo_result'] ?? '');
        if ($seo_result === '') {
            return ['success' => false, 'message' => 'No existe una versión SEO final para aplicar a la publicación.'];
        }
        if (class_exists('IDG_Assignment_Card')) {
            $workflow = IDG_Assignment_Card::attach($workflow);
        }

        $sections = self::extract_sections($seo_result);
        $content = (string) ($sections['article'] ?? '');
        $content = self::prepare_public_article_content($content, $workflow);
        $title = self::extract_title($content, $workflow['keyword'] ?? $post_before->post_title);
        if (class_exists('IDG_Recurring_Updates')) {
            $start_date = $is_contest
                ? (string) ($workflow['recurring_contest_start_date'] ?? '')
                : (string) ($workflow['event_start_date'] ?? $workflow['recurring_fecha_inicio'] ?? '');
            $end_date = $is_contest
                ? (string) ($workflow['recurring_contest_deadline'] ?? $workflow['recurring_contest_award_date'] ?? '')
                : (string) ($workflow['event_end_date'] ?? $workflow['recurring_fecha_fin'] ?? '');
            $title_years = IDG_Recurring_Updates::validate_event_title_years($title, $start_date, $end_date);
            if (!empty($title_years['errors'])) {
                return ['success' => false, 'message' => implode(' ', (array) $title_years['errors'])];
            }
            $identity_validation = IDG_Recurring_Updates::validate_editorial_identity($post_id, $workflow, $title);
            if (empty($identity_validation['ok'])) {
                return ['success' => false, 'message' => (string) ($identity_validation['message'] ?? 'La identidad editorial de la publicación no coincide.')];
            }
            if (!empty($identity_validation['warnings']) && class_exists('IDG_Logger')) {
                IDG_Logger::log('recurring_editorial_identity_warning', 'La identidad editorial presentó diferencias no bloqueantes.', [
                    'post_id' => $post_id,
                    'target_post_type' => $target_post_type,
                    'warnings' => array_values((array) $identity_validation['warnings']),
                ]);
            }
        }
        $meta_description = self::clean_meta_description((string) ($sections['meta_description'] ?? ''));
        $seo_report = trim((string) ($sections['seo_report'] ?? ''));
        $social_copy = trim((string) ($sections['social_copy'] ?? ''));
        $reel_package = trim((string) ($sections['reel_package'] ?? ''));
        $reel_package = self::ensure_valid_reel_package($reel_package, $content, $workflow);
        $sections['reel_package'] = $reel_package;
        $feedback_notes = trim((string) ($sections['feedback_notes'] ?? $workflow['feedback_notes'] ?? ''));

        $html_content = self::markdownish_to_html($content, $title);
        $html_before_postprocessing = $html_content;
        $html_content = self::ensure_official_source_link($html_content, $workflow);
        $html_content = self::ensure_internal_links($html_content, $workflow);
        $html_content = self::ensure_sponsored_required_link($html_content, $workflow);
        $html_content = self::ensure_minimum_bold($html_content, $workflow);
        $html_content = self::strip_bold_from_html_headings($html_content);
        $html_content = self::open_links_new_tab($html_content);
        $html_content = self::apply_sponsored_link_rel($html_content, $workflow);
        $html_content = self::maybe_add_sponsored_disclosure($html_content, $workflow);
        $postprocessing_audit = self::postprocessing_audit($html_before_postprocessing, $html_content, $workflow);
        $workflow['postprocessing_audit'] = $postprocessing_audit;
        $seo_report = self::repair_seo_report_link_counts($seo_report, $html_content, $workflow);
        $feedback_notes = self::repair_feedback_link_notes($feedback_notes, $html_content, $workflow);
        $processed_seo_result = self::rebuild_seo_result($content, $meta_description, $seo_report, $social_copy, $reel_package, $feedback_notes);
        $post_content = self::html_to_gutenberg_blocks($html_content);

        if (class_exists('IDG_Final_Guard')) {
            $validation = IDG_Final_Guard::validate_before_draft($content, $html_content, $sections, $workflow);
            $gutenberg_validation = IDG_Final_Guard::validate_gutenberg_blocks($post_content);
            if (empty($gutenberg_validation['ok'])) {
                $validation['ok'] = false;
                $validation['errors'] = array_merge((array) ($validation['errors'] ?? []), (array) ($gutenberg_validation['errors'] ?? []));
                $validation['summary'] = trim((string) ($validation['summary'] ?? '') . "\n" . implode("\n", array_map(static fn($e) => '- ERROR: ' . $e, (array) ($gutenberg_validation['errors'] ?? []))));
            }
            if (empty($validation['ok'])) {
                if (!empty($workflow['force_validation_override'])) {
                    $validation['warnings'] = array_merge((array) ($validation['warnings'] ?? []), (array) ($validation['errors'] ?? []));
                    $validation['summary'] = trim((string) ($validation['summary'] ?? '') . "\n- AVISO: Redacción aplicada a la publicación con validación ignorada manualmente por el editor. Revisar antes de publicar.");
                    $validation['ok'] = true;
                    $validation['overridden'] = true;
                } else {
                    return [
                        'success' => false,
                        'message' => 'Validación real bloqueó la actualización de la publicación: ' . implode(' · ', (array) ($validation['errors'] ?? [])),
                        'validation_summary' => (string) ($validation['summary'] ?? ''),
                    ];
                }
            }
        }

        $protected_before = [
            'post_title_before' => (string) $post_before->post_title,
            'post_title_expected' => wp_strip_all_tags($title),
            'post_name' => (string) $post_before->post_name,
            'post_status' => (string) $post_before->post_status,
            'post_author' => (int) $post_before->post_author,
            'thumbnail_id' => (int) get_post_thumbnail_id($post_id),
            'taxonomies' => [],
        ];
        foreach (get_object_taxonomies($target_post_type) as $taxonomy) {
            $protected_before['taxonomies'][$taxonomy] = wp_get_object_terms($post_id, $taxonomy, ['fields' => 'ids']);
        }

        $updated_id = wp_update_post([
            'ID' => $post_id,
            'post_title' => wp_strip_all_tags($title),
            'post_content' => $post_content,
            'post_excerpt' => wp_strip_all_tags($meta_description),
        ], true);
        if (is_wp_error($updated_id)) {
            return ['success' => false, 'message' => $updated_id->get_error_message()];
        }
        if ((int) $updated_id !== $post_id) {
            return ['success' => false, 'message' => 'WordPress devolvió un ID distinto a la publicación seleccionada. La redacción no fue aplicada.'];
        }

        update_post_meta($post_id, '_idg_keyword', sanitize_text_field($workflow['keyword'] ?? ''));
        update_post_meta($post_id, '_idg_entity', sanitize_text_field($workflow['entity'] ?? ''));
        update_post_meta($post_id, '_idg_piece_type', sanitize_text_field($workflow['piece_type'] ?? ''));
        update_post_meta($post_id, '_idg_brief_summary', wp_kses_post(self::brief_summary($workflow)));
        update_post_meta($post_id, '_idg_official_source', esc_url_raw($workflow['official_source'] ?? ''));
        update_post_meta($post_id, '_idg_internal_links', wp_kses_post(IDG_Internal_Links::summary($workflow)));
        update_post_meta($post_id, '_idg_assignment_card', wp_kses_post((string) ($workflow['assignment_card'] ?? '')));
        if (isset($validation) && is_array($validation)) {
            update_post_meta($post_id, '_idg_final_validation', wp_kses_post((string) ($validation['summary'] ?? '')));
        }
        update_post_meta($post_id, '_idg_meta_description', sanitize_text_field($meta_description));
        update_post_meta($post_id, '_idg_seo_report', wp_kses_post($seo_report));
        update_post_meta($post_id, '_idg_social_copy', wp_kses_post($social_copy));
        update_post_meta($post_id, '_idg_reel_package', wp_kses_post($reel_package));
        update_post_meta($post_id, '_idg_feedback_notes', wp_kses_post($feedback_notes));
        update_post_meta($post_id, '_idg_prompt_versions', wp_json_encode([
            'material_card' => IDG_Prompt_Library::version('material_card'),
            'editorial_plan' => IDG_Prompt_Library::version('editorial_plan'),
            'generate' => IDG_Prompt_Library::version('generate'),
            'editorial' => IDG_Prompt_Library::version('editorial'),
            'seo' => IDG_Prompt_Library::version('seo'),
            'web_research' => IDG_Prompt_Library::version('web_research'),
        ]));
        update_post_meta($post_id, '_idg_execution_history', wp_json_encode([
            'updated_at' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'workflow_id' => $workflow['workflow_id'] ?? '',
            'origin' => 'recurring_update',
            'analysis_id' => $workflow['recurring_analysis_id'] ?? '',
            'target_post_type' => $target_post_type,
        ]));
        self::update_yoast_meta($post_id, $meta_description, $workflow['keyword'] ?? '', $title);

        clean_post_cache($post_id);
        $post_after = get_post($post_id);
        if (!$post_after instanceof WP_Post) {
            return ['success' => false, 'message' => 'No se pudo verificar la publicación después de aplicar la redacción.'];
        }
        $title_updated = (string) $post_after->post_title === wp_strip_all_tags($title);
        $content_updated = hash_equals(hash('sha256', $post_content), hash('sha256', (string) $post_after->post_content));
        $excerpt_updated = wp_strip_all_tags((string) $post_after->post_excerpt) === wp_strip_all_tags($meta_description);
        $protected_ok = (int) $post_after->ID === $selected_post_id
            && (string) $post_after->post_title === $protected_before['post_title_expected']
            && (string) $post_after->post_name === $protected_before['post_name']
            && (string) $post_after->post_status === $protected_before['post_status']
            && (int) $post_after->post_author === $protected_before['post_author']
            && (int) get_post_thumbnail_id($post_id) === $protected_before['thumbnail_id'];
        foreach ($protected_before['taxonomies'] as $taxonomy => $term_ids) {
            $after_ids = wp_get_object_terms($post_id, $taxonomy, ['fields' => 'ids']);
            $before_clean = is_wp_error($term_ids) ? [] : array_map('intval', (array) $term_ids);
            $after_clean = is_wp_error($after_ids) ? [] : array_map('intval', (array) $after_ids);
            sort($before_clean);
            sort($after_clean);
            if ($before_clean !== $after_clean) {
                $protected_ok = false;
                break;
            }
        }
        if (!$title_updated || !$content_updated || !$excerpt_updated) {
            return [
                'success' => false,
                'message' => 'WordPress no devolvió el título, contenido o extracto esperado después de aplicar la Versión 3. La operación requiere revisión.',
                'post_id' => $post_id,
                'edit_link' => get_edit_post_link($post_id, 'raw'),
                'title_updated' => $title_updated,
                'content_updated' => $content_updated,
                'excerpt_updated' => $excerpt_updated,
                'validation_summary' => isset($validation) && is_array($validation) ? (string) ($validation['summary'] ?? '') : '',
            ];
        }
        if (!$protected_ok) {
            return [
                'success' => false,
                'message' => 'La redacción se escribió, pero la verificación detectó un cambio inesperado en un dato protegido de la publicación. Revísala inmediatamente.',
                'post_id' => $post_id,
                'edit_link' => get_edit_post_link($post_id, 'raw'),
                'validation_summary' => isset($validation) && is_array($validation) ? (string) ($validation['summary'] ?? '') : '',
            ];
        }

        return [
            'success' => true,
            'message' => 'Redacción aplicada al ' . $target_label . ' existente.',
            'post_id' => $post_id,
            'selected_post_id' => $selected_post_id,
            'actual_updated_post_id' => (int) $post_after->ID,
            'same_post_id' => ((int) $post_after->ID === $selected_post_id),
            'title_before' => (string) $post_before->post_title,
            'title_after' => (string) $post_after->post_title,
            'title_updated' => $title_updated,
            'content_updated' => $content_updated,
            'excerpt_updated' => $excerpt_updated,
            'applied_content_hash' => hash('sha256', (string) $post_after->post_content),
            'source_seo_hash' => hash('sha256', $seo_result),
            'edit_link' => get_edit_post_link($post_id, 'raw'),
            'validation_summary' => isset($validation) && is_array($validation) ? (string) ($validation['summary'] ?? '') : '',
            'postprocessed_html' => $html_content,
            'postprocessing_audit' => $postprocessing_audit,
            'seo_result' => $processed_seo_result,
            'reel_package' => $reel_package,
        ];
    }

    private static function brief_summary(array $workflow): string {
        $rows = [];
        if (!empty($workflow['brief_fact'])) {
            $rows[] = 'Hecho base: ' . trim((string) $workflow['brief_fact']);
        }
        if (!empty($workflow['editorial_angle'])) {
            $rows[] = 'Ángulo editorial: ' . trim((string) $workflow['editorial_angle']);
        }
        if (!empty($workflow['priority_readings'])) {
            $rows[] = 'Lecturas prioritarias: ' . trim((string) $workflow['priority_readings']);
        }
        if (!empty($workflow['sponsor_client'])) {
            $rows[] = 'Cliente / marca: ' . trim((string) $workflow['sponsor_client']);
        }
        if (!empty($workflow['sponsored_topic'])) {
            $rows[] = 'Tema patrocinado: ' . trim((string) $workflow['sponsored_topic']);
        }
        return implode("

", $rows);
    }

    private static function sponsored_summary(array $workflow): string {
        $rows = [];
        foreach ([
            'sponsor_client' => 'Cliente / marca',
            'sponsored_topic' => 'Tema',
            'sponsored_brief' => 'Brief del cliente',
            'sponsored_must_include' => 'Puntos obligatorios',
            'sponsored_avoid' => 'Puntos a evitar',
            'sponsored_required_link' => 'Enlace obligatorio',
            'sponsored_anchor' => 'Anchor solicitado',
            'sponsored_link_rel' => 'Tipo de enlace',
            'sponsored_restrictions' => 'Restricciones editoriales',
        ] as $key => $label) {
            if (!empty($workflow[$key])) {
                $rows[] = $label . ': ' . trim((string) $workflow[$key]);
            }
        }
        if (!empty($workflow['sponsored_visible_label'])) {
            $rows[] = 'Aviso visible patrocinado: sí';
        }
        return implode("

", $rows);
    }


    private static function repair_seo_report_link_counts(string $seo_report, string $html, array $workflow): string {
        $internal_count = 0;
        foreach (self::collect_internal_links($workflow) as $link) {
            $url = esc_url_raw((string) ($link['url'] ?? ''));
            if ($url !== '' && str_contains($html, $url)) {
                $internal_count++;
            }
        }
        $external_count = 0;
        $official = esc_url_raw((string) ($workflow['official_source'] ?? ''));
        if ($official !== '' && !self::is_ideasdi_url($official) && str_contains($html, $official)) {
            $external_count = 1;
        }
        $summary = 'Enlaces aplicados: ' . $internal_count . ' interno' . ($internal_count === 1 ? '' : 's') . '; ' . $external_count . ' externo' . ($external_count === 1 ? '' : 's');
        if (preg_match('/^\s*[-*]\s*Enlaces aplicados\s*:.+$/imu', $seo_report)) {
            $seo_report = preg_replace('/^\s*[-*]\s*Enlaces aplicados\s*:.+$/imu', '- ' . $summary, $seo_report, 1);
        } elseif (trim($seo_report) !== '') {
            $seo_report .= "\n- " . $summary;
        } else {
            $seo_report = '- ' . $summary;
        }
        return trim((string) $seo_report);
    }

    private static function repair_feedback_link_notes(string $feedback, string $html, array $workflow): string {
        if (trim($feedback) === '') {
            return $feedback;
        }
        $has_internal = false;
        foreach (self::collect_internal_links($workflow) as $link) {
            $url = esc_url_raw((string) ($link['url'] ?? ''));
            if ($url !== '' && str_contains($html, $url)) {
                $has_internal = true;
                break;
            }
        }
        if (!$has_internal) {
            return $feedback;
        }
        $lines = preg_split('/\n/', $feedback);
        if (!is_array($lines)) {
            return $feedback;
        }
        $clean = [];
        foreach ($lines as $line) {
            if (preg_match('/no\s+se\s+(?:añadió|anadio|agregó|agrego)\s+enlace\s+interno/iu', (string) $line)) {
                $clean[] = '- El enlace interno quedó integrado de forma contextual, sin usar la keyword principal como anchor.';
                continue;
            }
            $clean[] = $line;
        }
        return trim(implode("\n", $clean));
    }

    private static function rebuild_seo_result(string $content, string $meta_description, string $seo_report, string $social_copy, string $reel_package, string $feedback_notes): string {
        $parts = [];
        $parts[] = 'ARTÍCULO FINAL';
        $parts[] = trim($content);
        $parts[] = '';
        $parts[] = 'META DESCRIPTION';
        $parts[] = trim($meta_description);
        $parts[] = '';
        $parts[] = 'INFORME SEO INTERNO';
        $parts[] = trim($seo_report);
        $parts[] = '';
        $parts[] = 'COPY PARA REDES';
        $parts[] = trim($social_copy);
        $parts[] = '';
        $parts[] = 'PAQUETE REEL';
        $parts[] = trim($reel_package);
        $parts[] = '';
        $parts[] = 'RETROALIMENTACIÓN GERIZIM';
        $parts[] = trim($feedback_notes);
        return trim(implode("\n", $parts));
    }

    private static function normalize(string $text): string {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/^```(?:html|markdown|md)?\s*/im', '', $text);
        $text = preg_replace('/```\s*$/m', '', $text);
        return trim((string) $text);
    }

    private static function extract_sections(string $text): array {
        $text = self::normalize($text);
        $lines = preg_split('/\n/', $text);
        $sections = [
            'article_pre' => [],
            'article_final' => [],
            'meta_description' => [],
            'seo_report' => [],
            'social_copy' => [],
            'reel_package' => [],
            'feedback_notes' => [],
        ];
        $current = 'article_pre';

        foreach ($lines as $line) {
            $key = self::section_key_from_line($line);
            if ($key !== '') {
                $current = $key;
                $inline = self::inline_body_after_heading($line, $key);
                if ($inline !== '') {
                    $sections[$current][] = $inline;
                }
                continue;
            }
            $sections[$current][] = $line;
        }

        $article = trim(implode("\n", $sections['article_final']));
        if ($article === '') {
            $article = trim(implode("\n", $sections['article_pre']));
        }

        return [
            'article' => self::remove_internal_sections_from_article($article),
            'meta_description' => trim(implode("\n", $sections['meta_description'])),
            'seo_report' => trim(implode("\n", $sections['seo_report'])),
            'social_copy' => trim(implode("\n", $sections['social_copy'])),
            'reel_package' => trim(implode("\n", $sections['reel_package'])),
            'feedback_notes' => trim(implode("\n", $sections['feedback_notes'])),
        ];
    }

    private static function remove_internal_sections_from_article(string $article): string {
        $article = self::normalize($article);
        $lines = preg_split('/\n/', $article);
        $clean = [];
        foreach ($lines as $line) {
            if (self::section_key_from_line($line) !== '') {
                break;
            }
            $clean[] = $line;
        }
        return trim(implode("\n", $clean));
    }

    private static function section_key_from_line(string $line): string {
        $clean = trim(wp_strip_all_tags($line));
        $clean = preg_replace('/^\s*#{1,6}\s*/u', '', (string) $clean);
        $clean = trim($clean);
        $clean = trim($clean, " \t\n\r\0\x0B*-_:");
        $lower = function_exists('remove_accents') ? remove_accents($clean) : $clean;
        $lower = mb_strtolower($lower);
        $lower = preg_replace('/\s+/u', ' ', (string) $lower);
        $lower = trim($lower);

        $map = [
            'articulo final' => 'article_final',
            'meta description' => 'meta_description',
            'metadescription' => 'meta_description',
            'informe seo interno' => 'seo_report',
            'copy para redes' => 'social_copy',
            'paquete reel' => 'reel_package',
            'retroalimentacion gerizim' => 'feedback_notes',
            'retroalimentacion' => 'feedback_notes',
        ];

        foreach ($map as $label => $key) {
            if ($lower === $label || str_starts_with($lower, $label . ':')) {
                return $key;
            }
        }

        return '';
    }

    private static function inline_body_after_heading(string $line, string $key): string {
        $labels = [
            'article_final' => '(?:ARTÍCULO FINAL|ARTICULO FINAL)',
            'meta_description' => '(?:META DESCRIPTION|METADESCRIPTION)',
            'seo_report' => 'INFORME SEO INTERNO',
            'social_copy' => 'COPY PARA REDES',
            'reel_package' => 'PAQUETE REEL',
            'feedback_notes' => '(?:RETROALIMENTACIÓN GERIZIM|RETROALIMENTACION GERIZIM|RETROALIMENTACIÓN|RETROALIMENTACION)',
        ];
        if (!isset($labels[$key])) {
            return '';
        }
        $pattern = '/^\s*(?:#{1,6}\s*)?(?:\*\*)?\s*' . $labels[$key] . '\s*(?:\*\*)?\s*:?\s*(?:\*\*)?\s*(.*?)\s*$/iu';
        if (preg_match($pattern, $line, $m)) {
            $body = trim((string) ($m[1] ?? ''));
            $body = trim($body, " \t\n\r\0\x0B*");
            $body_key = self::section_key_from_line($body);
            return $body_key === '' ? $body : '';
        }
        return '';
    }


    private static function strip_markdown_links_to_text(string $text): string {
        $text = preg_replace('/\[([^\]]+)\]\(https?:\/\/[^\s\)]+\)/u', '$1', $text);
        return is_string($text) ? $text : '';
    }

    private static function clean_featured_snippet_text(string $text): string {
        $text = self::strip_markdown_links_to_text($text);
        $text = preg_replace('/https?:\/\/[^\s)]+/u', '', (string) $text);
        $text = preg_replace('/\*\*(.+?)\*\*/u', '$1', (string) $text);
        $text = preg_replace('/__(.+?)__/u', '$1', (string) $text);
        $text = preg_replace('/\s+/', ' ', (string) $text);
        return trim((string) $text);
    }

    private static function html_to_gutenberg_blocks(string $html): string {
        $html = trim($html);
        if ($html === '' || str_contains($html, '<!-- wp:')) {
            return $html;
        }

        $lines = preg_split('/\n+/', $html);
        $blocks = [];
        $list_buffer = [];
        $flush_list = function () use (&$blocks, &$list_buffer) {
            if (empty($list_buffer)) {
                return;
            }
            $list_html = implode("\n", $list_buffer);
            $blocks[] = "<!-- wp:list -->\n" . $list_html . "\n<!-- /wp:list -->";
            $list_buffer = [];
        };

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (preg_match('/^<ul\b/i', $line) || preg_match('/^<li\b/i', $line) || preg_match('/^<\/ul>/i', $line)) {
                $list_buffer[] = $line;
                if (preg_match('/^<\/ul>/i', $line)) {
                    $flush_list();
                }
                continue;
            }

            $flush_list();

            if (preg_match('/^<h2\b[^>]*>.*<\/h2>$/is', $line)) {
                $blocks[] = "<!-- wp:heading -->\n" . $line . "\n<!-- /wp:heading -->";
            } elseif (preg_match('/^<h3\b[^>]*>.*<\/h3>$/is', $line)) {
                $blocks[] = "<!-- wp:heading {\"level\":3} -->\n" . $line . "\n<!-- /wp:heading -->";
            } elseif (preg_match('/^<p\b[^>]*class=(\"|\')[^\"\']*featured-snippet-box[^\"\']*\1[^>]*>.*<\/p>$/is', $line)) {
                $blocks[] = "<!-- wp:paragraph {\"className\":\"featured-snippet-box\"} -->\n" . $line . "\n<!-- /wp:paragraph -->";
            } elseif (preg_match('/^<p\b[^>]*>.*<\/p>$/is', $line)) {
                $blocks[] = "<!-- wp:paragraph -->\n" . $line . "\n<!-- /wp:paragraph -->";
            } else {
                $blocks[] = "<!-- wp:html -->\n" . $line . "\n<!-- /wp:html -->";
            }
        }

        $flush_list();
        return implode("\n\n", $blocks);
    }


    private static function prepare_public_article_content(string $content, array $workflow): string {
        $content = self::normalize($content);
        $content = self::remove_internal_instruction_leaks($content);
        $content = self::remove_visible_source_citations($content);
        $content = self::remove_bold_from_markdown_headings($content);
        $content = self::repair_h1_length($content, $workflow);
        $content = self::ensure_featured_snippet_after_intro($content);
        $content = self::ensure_featured_snippet_contract($content, $workflow);
        $content = self::remove_generic_contest_closing($content);
        $content = self::remove_artificial_link_headings($content);
        return $content;
    }

    private static function remove_internal_instruction_leaks(string $content): string {
        $lines = preg_split('/\n/', self::normalize($content));
        if (!is_array($lines)) {
            return $content;
        }
        $bad_patterns = [
            '/\bNeed\s+provide\b/i',
            '/\bLet(?:\'|’)s\s+produce\b/i',
            '/\barticle\s+base\b/i',
            '/\bmissing\s+(?:box|snippet)\b/i',
            '/falta\s+del\s+usuario\??/iu',
            '/\bOnly\s+article\s+base\b/i',
        ];
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

    private static function remove_bold_from_markdown_headings(string $content): string {
        $lines = preg_split('/\n/', self::normalize($content));
        if (!is_array($lines)) {
            return $content;
        }
        foreach ($lines as $i => $line) {
            if (preg_match('/^\s*#{1,6}\s+(.+)$/u', (string) $line, $m)) {
                $prefix = preg_replace('/^(\s*#{1,6}\s+).*$/u', '$1', (string) $line);
                $heading = self::clean_heading_text((string) $m[1]);
                $lines[$i] = $prefix . $heading;
            }
        }
        return trim(implode("\n", $lines));
    }

    private static function clean_heading_text(string $text): string {
        $text = preg_replace('/\*\*(.*?)\*\*/u', '$1', $text);
        $text = preg_replace('/__(.*?)__/u', '$1', (string) $text);
        $text = preg_replace('/<\/?(?:strong|b)\b[^>]*>/iu', '', (string) $text);
        return trim((string) $text);
    }

    private static function remove_artificial_link_headings(string $content): string {
        $lines = preg_split('/
/', self::normalize($content));
        if (!is_array($lines)) {
            return $content;
        }
        $clean = [];
        foreach ($lines as $line) {
            $plain = trim(wp_strip_all_tags((string) $line));
            $plain = preg_replace('/^#{1,6}\s*/u', '', (string) $plain);
            $plain = trim((string) $plain, " 	

*-_:");
            $normalized = mb_strtolower(function_exists('remove_accents') ? remove_accents($plain) : $plain);
            if (in_array($normalized, ['enlace interno contextual', 'enlace externo contextual', 'enlaces contextuales'], true)) {
                continue;
            }
            $clean[] = $line;
        }
        return trim(preg_replace('/
{3,}/', "

", implode("
", $clean)));
    }


    private static function repair_h1_length(string $content, array $workflow): string {
        $rules = class_exists('IDG_Editorial_Rules') ? IDG_Editorial_Rules::get() : [];
        $max = (int) ($rules['h1_max_chars'] ?? 68);
        if ($max <= 0 || !preg_match('/^\s*#\s+(.+)$/mu', $content, $m, PREG_OFFSET_CAPTURE)) {
            return $content;
        }
        $title = trim(wp_strip_all_tags((string) $m[1][0]));
        if ($title === '' || mb_strlen($title) <= $max) {
            return $content;
        }
        // Reparación acotada: solo intenta compactar títulos cercanos al límite para evitar bloqueos mecánicos.
        if (mb_strlen($title) > $max + 18) {
            return $content;
        }
        $candidate = $title;
        $candidate = preg_replace('/\b(?:la|el)\s+nueva\s+generaci[oó]n\s+de\s+/iu', 'nuevo ', (string) $candidate);
        $candidate = preg_replace('/\bcon\s+pulso\s+propio\b/iu', 'con carácter', (string) $candidate);
        $candidate = preg_replace('/\buna\s+pantalla\s+que\s+se\s+repliega\s+en\s+el\s+espacio\b/iu', 'pantalla plegable', (string) $candidate);
        $candidate = preg_replace('/\buna\s+vivienda\s+en\s+/iu', 'vivienda en ', (string) $candidate);
        $candidate = preg_replace('/\s+/u', ' ', (string) $candidate);
        $candidate = trim((string) $candidate, " \t\n\r\0\x0B.-–—");
        if (mb_strlen($candidate) > $max) {
            $parts = preg_split('/:\s*/u', $candidate, 2);
            if (is_array($parts) && count($parts) === 2) {
                $left = trim($parts[0]);
                $right = trim($parts[1]);
                $right = preg_replace('/\b(?:la|el|una|un)\b\s*/iu', '', (string) $right);
                $right = preg_replace('/\s+/u', ' ', (string) $right);
                $candidate = trim($left . ': ' . trim((string) $right));
            }
        }
        if (mb_strlen($candidate) > $max || $candidate === '') {
            return $content;
        }
        $line_start = $m[0][1];
        $line_len = strlen($m[0][0]);
        return substr($content, 0, $line_start) . '# ' . $candidate . substr($content, $line_start + $line_len);
    }

    private static function ensure_featured_snippet_after_intro(string $content): string {
        $lines = preg_split('/\n/', self::normalize($content));
        if (!is_array($lines)) {
            return $content;
        }

        $snippet = '';
        $remove = [];
        $count = count($lines);
        for ($i = 0; $i < $count; $i++) {
            $line = trim((string) $lines[$i]);
            if (!preg_match('/^\*\*\s*Caja editorial\s*:?\s*\*\*\s*(.*)$/iu', $line, $m) && !preg_match('/^Caja editorial\s*:?\s*(.*)$/iu', $line, $m)) {
                continue;
            }
            $remove[$i] = true;
            $inline = trim((string) ($m[1] ?? ''));
            $inline = trim($inline, " \t*-_");
            if ($inline !== '') {
                $snippet = $inline;
                break;
            }
            for ($j = $i + 1; $j < $count; $j++) {
                $next = trim((string) $lines[$j]);
                if ($next === '') {
                    if ($snippet !== '') {
                        $remove[$j] = true;
                        break;
                    }
                    $remove[$j] = true;
                    continue;
                }
                if (preg_match('/^#{1,6}\s+/u', $next) || self::section_key_from_line($next) !== '') {
                    break;
                }
                $snippet = trim($snippet . ' ' . $next);
                $remove[$j] = true;
            }
            break;
        }

        $snippet = self::clean_featured_snippet_text($snippet);
        if ($snippet === '') {
            return $content;
        }

        $clean = [];
        foreach ($lines as $i => $line) {
            if (empty($remove[$i])) {
                $clean[] = rtrim((string) $line);
            }
        }

        $out = [];
        $seen_h2 = false;
        $paragraphs_after_h2 = 0;
        $inserted = false;
        $clean_count = count($clean);
        for ($i = 0; $i < $clean_count; $i++) {
            $line = rtrim((string) $clean[$i]);
            $out[] = $line;
            $trim = trim($line);
            if (preg_match('/^##\s+(.+)$/u', $trim)) {
                $seen_h2 = true;
                continue;
            }
            if (!$seen_h2 || $inserted || $trim === '' || preg_match('/^#{1,6}\s+/u', $trim) || preg_match('/^[-*]\s+/u', $trim)) {
                continue;
            }
            $paragraphs_after_h2++;
            if ($paragraphs_after_h2 >= 2) {
                $out[] = '';
                $out[] = 'Caja editorial';
                $out[] = $snippet;
                $inserted = true;
            }
        }

        if (!$inserted) {
            $out[] = '';
            $out[] = 'Caja editorial';
            $out[] = $snippet;
        }

        $result = implode("\n", $out);
        $result = preg_replace('/\n{3,}/', "\n\n", (string) $result);
        return trim((string) $result);
    }


    private static function ensure_featured_snippet_contract(string $content, array $workflow): string {
        $rules = class_exists('IDG_Editorial_Rules') ? IDG_Editorial_Rules::get() : [];
        $min = (int) ($rules['editorial_box_min_words'] ?? 40);
        $max = (int) ($rules['editorial_box_max_words'] ?? 55);
        if (!preg_match('/(^Caja editorial\s*\n)(.+?)(?=\n\s*#{1,6}\s+|\n\s*$)/isu', $content, $m, PREG_OFFSET_CAPTURE)) {
            return $content;
        }

        $prefix = (string) $m[1][0];
        $original = trim((string) $m[2][0]);
        $snippet = self::clean_featured_snippet_text($original);
        $keyword = trim(wp_strip_all_tags((string) ($workflow['keyword'] ?? '')));
        $count = self::word_count($snippet);

        $needs_rebuild = false;
        if ($keyword !== '' && !self::starts_with_plain($snippet, $keyword)) {
            $needs_rebuild = true;
        }
        if (!self::snippet_has_definition($snippet, $keyword)) {
            $needs_rebuild = true;
        }
        if ($count < $min || $count > $max) {
            $needs_rebuild = true;
        }

        if (!$needs_rebuild) {
            return $content;
        }

        $candidate = self::build_featured_snippet_contract($workflow, $snippet, $min, $max);
        if ($candidate === '') {
            $candidate = self::repair_featured_snippet_word_count_only($snippet, $workflow, $min, $max);
        }
        if ($candidate === '') {
            return $content;
        }
        return substr($content, 0, $m[1][1]) . $prefix . $candidate . substr($content, $m[2][1] + strlen($m[2][0]));
    }

    private static function snippet_has_definition(string $snippet, string $keyword): bool {
        $plain = self::plain_for_contract($snippet);
        if ($plain === '') {
            return false;
        }
        if ($keyword !== '') {
            $kw = self::plain_for_contract($keyword);
            $pos = strpos($plain, $kw);
            if ($pos !== false && $pos <= 12) {
                $after = trim(substr($plain, $pos + strlen($kw)));
                return (bool) preg_match('/^(?:es|son|se presenta como|consiste en)\b/u', $after);
            }
        }
        return (bool) preg_match('/\b(?:es|son|se presenta como|consiste en)\b/u', $plain);
    }

    private static function starts_with_plain(string $text, string $prefix): bool {
        $text = self::plain_for_contract($text);
        $prefix = self::plain_for_contract($prefix);
        return $prefix === '' || str_starts_with($text, $prefix);
    }

    private static function plain_for_contract(string $text): string {
        $text = wp_strip_all_tags($text);
        $text = function_exists('remove_accents') ? remove_accents($text) : $text;
        $text = mb_strtolower((string) $text);
        $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text);
        $text = preg_replace('/\s+/u', ' ', (string) $text);
        return trim((string) $text);
    }

    private static function build_featured_snippet_contract(array $workflow, string $fallback, int $min, int $max): string {
        $keyword = trim(wp_strip_all_tags((string) ($workflow['keyword'] ?? '')));
        if ($keyword === '') {
            return self::repair_featured_snippet_word_count_only($fallback, $workflow, $min, $max);
        }
        $entity = trim(wp_strip_all_tags((string) ($workflow['entity'] ?? '')));
        $descriptor = self::featured_snippet_descriptor($workflow);
        $value = self::featured_snippet_value($workflow);

        $candidates = [];
        if ($entity !== '') {
            $candidates[] = $keyword . ' es ' . $descriptor . '. Lo impulsa ' . $entity . ' y aporta ' . $value . '.';
            $candidates[] = $keyword . ' es ' . $descriptor . ' impulsada por ' . $entity . '. Aporta ' . $value . '.';
        }
        $candidates[] = $keyword . ' es ' . $descriptor . '. Aporta ' . $value . '.';

        foreach ($candidates as $candidate) {
            $candidate = self::normalize_featured_snippet_sentence($candidate);
            $count = self::word_count($candidate);
            if ($count >= $min && $count <= $max) {
                return $candidate;
            }
        }

        foreach ($candidates as $candidate) {
            $candidate = self::normalize_featured_snippet_sentence($candidate);
            $candidate = self::fit_featured_snippet_to_range($candidate, $workflow, $min, $max);
            $count = self::word_count($candidate);
            if ($count >= $min && $count <= $max) {
                return $candidate;
            }
        }
        return '';
    }

    private static function featured_snippet_descriptor(array $workflow): string {
        $category = self::workflow_category_plain($workflow);
        if (str_contains($category, 'concurso') || str_contains($category, 'convocatoria') || str_contains($category, 'agenda')) {
            return 'una convocatoria de diseño orientada a participación, fechas, requisitos y proceso creativo';
        }
        if (str_contains($category, 'digital') || str_contains($category, '3d')) {
            return 'una propuesta de diseño digital centrada en flujo, interfaz y visualización';
        }
        if (str_contains($category, 'moda')) {
            return 'una propuesta de moda centrada en silueta, construcción y códigos culturales';
        }
        if (str_contains($category, 'movilidad') || str_contains($category, 'transporte')) {
            return 'una pieza de diseño de movilidad centrada en forma, identidad visual y experiencia de uso';
        }
        if (str_contains($category, 'arquitectura') || str_contains($category, 'interior')) {
            return 'un proyecto de arquitectura y diseño interior centrado en espacio, recorrido y experiencia cotidiana';
        }
        if (str_contains($category, 'producto')) {
            return 'una pieza de diseño de producto centrada en materialidad, función y presencia cotidiana';
        }
        return 'una pieza de actualidad creativa centrada en diseño, contexto y experiencia de uso';
    }

    private static function featured_snippet_value(array $workflow): string {
        $category = self::workflow_category_plain($workflow);
        if (str_contains($category, 'concurso') || str_contains($category, 'convocatoria') || str_contains($category, 'agenda')) {
            return 'una ruta práctica para entender condiciones, entregables y valor del proceso dentro de la agenda creativa';
        }
        if (str_contains($category, 'digital') || str_contains($category, '3d')) {
            return 'una lectura clara sobre herramienta, proceso y manera de revisar o comunicar el proyecto';
        }
        if (str_contains($category, 'moda')) {
            return 'una lectura clara sobre cuerpo, materialidad y relación cultural de la pieza con su contexto';
        }
        if (str_contains($category, 'movilidad') || str_contains($category, 'transporte')) {
            return 'una lectura clara sobre proporción, presencia y modo en que el objeto construye identidad';
        }
        if (str_contains($category, 'arquitectura') || str_contains($category, 'interior')) {
            return 'una lectura clara sobre materialidad, uso y forma de habitar el espacio existente';
        }
        if (str_contains($category, 'producto')) {
            return 'una lectura clara sobre escala, materialidad y modo en que el objeto organiza el uso';
        }
        return 'una lectura clara sobre forma, uso y contexto dentro del diseño contemporáneo';
    }

    private static function workflow_category_plain(array $workflow): string {
        $category = '';
        if (!empty($workflow['category_name'])) {
            $category = (string) $workflow['category_name'];
        } elseif (!empty($workflow['category_id']) && function_exists('get_term')) {
            $term = get_term((int) $workflow['category_id'], 'category');
            if ($term && !is_wp_error($term)) {
                $category = (string) $term->name;
            }
        }
        $category = function_exists('remove_accents') ? remove_accents($category) : $category;
        $category = mb_strtolower((string) $category);
        return trim((string) $category);
    }

    private static function normalize_featured_snippet_sentence(string $text): string {
        $text = self::clean_featured_snippet_text($text);
        $text = preg_replace('/\s+/', ' ', (string) $text);
        $text = trim((string) $text, " \t\n\r\0\x0B,;:");
        return rtrim($text, '.') . '.';
    }

    private static function fit_featured_snippet_to_range(string $candidate, array $workflow, int $min, int $max): string {
        $candidate = self::normalize_featured_snippet_sentence($candidate);
        if (self::word_count($candidate) < $min) {
            return self::repair_featured_snippet_word_count_only($candidate, $workflow, $min, $max);
        }
        if (self::word_count($candidate) <= $max) {
            return $candidate;
        }
        $candidate = preg_replace('/,\s*con foco en[^.]+\./iu', '.', $candidate);
        $candidate = preg_replace('/\s+dentro\s+de\s+la\s+agenda\s+creativa/iu', '', (string) $candidate);
        $candidate = preg_replace('/\s+dentro\s+del\s+diseño\s+contemporáneo/iu', '', (string) $candidate);
        $candidate = self::normalize_featured_snippet_sentence($candidate);
        if (self::word_count($candidate) <= $max && self::word_count($candidate) >= $min) {
            return $candidate;
        }
        $words = preg_split('/\s+/u', $candidate, -1, PREG_SPLIT_NO_EMPTY);
        $words = is_array($words) ? $words : [];
        if (count($words) > $max) {
            $words = array_slice($words, 0, $max);
        }
        $candidate = rtrim(implode(' ', $words), ' ,;:') . '.';
        return self::normalize_featured_snippet_sentence($candidate);
    }

    private static function repair_featured_snippet_word_count_only(string $snippet, array $workflow, int $min, int $max): string {
        $snippet = self::normalize_featured_snippet_sentence($snippet);
        $count = self::word_count($snippet);
        if ($count >= $min && $count <= $max) {
            return $snippet;
        }
        if ($count < $min && $count >= max(1, $min - 12)) {
            $additions = [
                self::featured_snippet_addition($workflow),
                'También suma contexto editorial, uso y una lectura más completa.',
                'También ayuda a entender su contexto, uso y valor editorial.',
            ];
            foreach ($additions as $addition) {
                $candidate = self::normalize_featured_snippet_sentence(rtrim($snippet, '.') . ' ' . trim((string) $addition));
                $candidate_count = self::word_count($candidate);
                if ($candidate_count >= $min && $candidate_count <= $max) {
                    return $candidate;
                }
            }
        }
        if ($count > $max && $count <= $max + 12) {
            $words = preg_split('/\s+/u', $snippet, -1, PREG_SPLIT_NO_EMPTY);
            $words = is_array($words) ? $words : [];
            while (count($words) > $max) {
                array_pop($words);
            }
            $candidate = self::normalize_featured_snippet_sentence(implode(' ', $words));
            if (self::word_count($candidate) >= $min && self::word_count($candidate) <= $max) {
                return $candidate;
            }
        }
        return '';
    }

    private static function featured_snippet_addition(array $workflow): string {
        $category = '';
        if (!empty($workflow['category_id'])) {
            $term = get_term((int) $workflow['category_id'], 'category');
            if ($term && !is_wp_error($term)) {
                $category = mb_strtolower(function_exists('remove_accents') ? remove_accents((string) $term->name) : (string) $term->name);
            }
        }
        if (str_contains($category, 'producto')) {
            return 'También ordena uso, materialidad y presencia cotidiana.';
        }
        if (str_contains($category, 'moda')) {
            return 'También ordena silueta, gesto y lectura cultural.';
        }
        if (str_contains($category, 'movilidad') || str_contains($category, 'transporte')) {
            return 'También ordena proporción, uso e identidad visual.';
        }
        if (str_contains($category, 'arquitectura') || str_contains($category, 'interior')) {
            return 'También ordena recorrido, luz y experiencia cotidiana.';
        }
        if (str_contains($category, 'digital') || str_contains($category, '3d')) {
            return 'También ordena flujo, interfaz y control visual.';
        }
        if (str_contains($category, 'concurso') || str_contains($category, 'convocatoria')) {
            return 'También ayuda a ordenar fechas, requisitos y participación.';
        }
        return 'También aporta contexto, uso y lectura editorial.';
    }

    private static function remove_visible_source_citations(string $content): string {
        // Remove visible citations accidentally produced by web search, such as ([fuente](https://...)).
        $content = preg_replace('/\s*\(\[[^\]]{1,80}\]\(https?:\/\/[^\s)]+\)\)/u', '', $content);
        return is_string($content) ? $content : '';
    }

    private static function is_contest_workflow(array $workflow): bool {
        $piece_type = mb_strtolower(remove_accents((string) ($workflow['piece_type'] ?? '')));
        if (str_contains($piece_type, 'concurso') || str_contains($piece_type, 'convocatoria')) {
            return true;
        }
        if (!empty($workflow['category_id'])) {
            $term = get_term((int) $workflow['category_id'], 'category');
            if ($term && !is_wp_error($term)) {
                $cat = mb_strtolower(remove_accents((string) $term->name));
                return str_contains($cat, 'concurso') || str_contains($cat, 'convocatoria');
            }
        }
        return false;
    }

    private static function is_event_workflow(array $workflow): bool {
        return (string) ($workflow['recurring_target_post_type'] ?? '') === 'evento'
            || (string) ($workflow['wordpress_content_type'] ?? '') === 'Evento'
            || (string) ($workflow['editorial_context'] ?? '') === 'event_calendar';
    }

    private static function contest_name_for_closing(array $workflow): string {
        $name = trim((string) ($workflow['keyword'] ?? ''));
        if ($name === '') {
            $name = 'concurso';
        }
        $name = preg_replace('/^\s*(?:concurso\s+de\s+diseño|concurso\s+de\s+diseno|concurso|convocatoria)\s+/iu', '', $name);
        $name = trim((string) $name);
        return $name !== '' ? $name : 'concurso';
    }

    private static function remove_generic_contest_closing(string $content): string {
        $patterns = [
            '/\s*Para\s+obtener\s+m[aá]s\s+informaci[oó]n\s+y\s+participar\s+en\s+[^.\n]+,\s+visite\s+la\s+web\s+oficial\s+del\s+concurso\.?/iu',
            '/\s*Para\s+obtener\s+m[aá]s\s+informaci[oó]n[^.\n]+web\s+oficial[^.\n]*\.?/iu',
            '/\s*Visita\s+la\s+web\s+oficial\s+del\s+concurso[^.\n]*\.?/iu',
        ];
        foreach ($patterns as $pattern) {
            $content = preg_replace($pattern, '', $content);
        }
        return trim((string) preg_replace('/\n{3,}/', "\n\n", (string) $content));
    }

    private static function extract_title(string $article, string $fallback): string {
        $article = self::normalize($article);
        if (preg_match('/^\s*#\s+(.+)$/mu', $article, $m)) {
            return trim(wp_strip_all_tags($m[1]));
        }
        if (preg_match('/^\s*H1\s*[:\-]\s*(.+)$/miu', $article, $m)) {
            return trim(wp_strip_all_tags($m[1]));
        }

        foreach (preg_split('/\n/', wp_strip_all_tags($article)) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (self::section_key_from_line($line) !== '') {
                continue;
            }
            if (mb_strlen($line) < 120) {
                return $line;
            }
        }
        return $fallback ?: 'Artículo ideasDi';
    }


    private static function ensure_valid_reel_package(string $reel, string $article, array $workflow): string {
        $reel = self::normalize($reel);
        $needs_repair = false;

        $rules = class_exists('IDG_Editorial_Rules') ? IDG_Editorial_Rules::get() : [];
        $target_words = (int) ($rules['reel_vo_words'] ?? 14);
        $target_overlays = (int) (($rules['reel_scenes'] ?? 6) * ($rules['reel_overlays_per_scene'] ?? 3));
        $cta = trim((string) ($rules['reel_cta'] ?? 'Conoce más de este proyecto en ideasDi.com'));

        if ($reel === '' || ($cta !== '' && stripos($reel, $cta) === false)) {
            $needs_repair = true;
        }

        preg_match_all('/^\s*(?:[-*]\s*)?VO\s*(?:[—\-]\s*Bloque\s*)?(\d)\s*:\s*(.+)$/imu', $reel, $vo_matches, PREG_SET_ORDER);
        $vo_by_number = [];
        foreach ($vo_matches as $m) {
            $vo_by_number[(int) $m[1]] = trim((string) $m[2]);
        }
        for ($i = 1; $i <= 6; $i++) {
            if (empty($vo_by_number[$i])) {
                $needs_repair = true;
                break;
            }
        }
        for ($i = 1; $i <= 5; $i++) {
            if (!empty($vo_by_number[$i]) && self::word_count($vo_by_number[$i]) !== $target_words) {
                $needs_repair = true;
                break;
            }
        }

        $overlay_count = 0;
        foreach (preg_split('/\n+/', $reel) as $line) {
            if (preg_match('/^(?:[-*]\s*)?(?:Overlay|Subt[ií]tulo|Texto en pantalla)(?:\s+\d+(?:[\.\-]\d+)?|\s*[—\-]?\s*\d+)?\s*:/iu', trim((string) $line))) {
                $overlay_count++;
            }
        }
        if ($overlay_count !== max(1, $target_overlays)) {
            $needs_repair = true;
        }

        return $needs_repair ? self::build_deterministic_reel_package($article, $workflow) : $reel;
    }

    private static function build_deterministic_reel_package(string $article, array $workflow): string {
        // Paquete de seguridad contextual: mantiene formato exacto sin caer en una plantilla genérica.
        $context = self::reel_context($article, $workflow);
        $vo = [];
        $vo[] = self::fit_words($context['name'] . ' se entiende desde ' . $context['axis1'] . ', ' . $context['axis2'] . ' y ' . $context['axis3'] . ' con lectura de diseño', 14);
        $vo[] = self::fit_words('La propuesta conecta ' . $context['axis2'] . ', ' . $context['axis4'] . ' y contexto cotidiano dentro de una experiencia concreta', 14);
        $vo[] = self::fit_words('El artículo observa ' . $context['axis1'] . ', ' . $context['axis3'] . ' y detalle para explicar su alcance editorial', 14);
        $vo[] = self::fit_words('Cada decisión revela cómo ' . $context['name'] . ' combina técnica, espacio, cuerpo y cultura visual', 14);
        $vo[] = self::fit_words('La clave está en cómo ' . $context['axis4'] . ' cambia percepción, ritmo y valor de uso', 14);

        $lines = [];
        for ($i = 1; $i <= 5; $i++) {
            $lines[] = 'VO ' . $i . ': ' . $vo[$i - 1] . '.';
            foreach (self::reel_overlays_for_scene($context, $i) as $j => $overlay) {
                $lines[] = 'Overlay ' . $i . '.' . ($j + 1) . ': ' . $overlay;
            }
            $lines[] = '';
        }
        $lines[] = 'VO 6: Amplía esta lectura editorial y Conoce más de este proyecto en ideasDi.com hoy.';
        foreach (self::reel_overlays_for_scene($context, 6) as $j => $overlay) {
            $lines[] = 'Overlay 6.' . ($j + 1) . ': ' . $overlay;
        }
        return implode("
", $lines);
    }

    private static function reel_context(string $article, array $workflow): array {
        $title = self::extract_title($article, (string) ($workflow['keyword'] ?? 'El proyecto'));
        $name = trim((string) ($workflow['keyword'] ?? ''));
        if ($name === '') {
            $name = preg_replace('/[:|,].*$/u', '', $title);
        }
        $name = trim(wp_strip_all_tags((string) $name));
        $category = '';
        if (!empty($workflow['category_id'])) {
            $term = get_term((int) $workflow['category_id'], 'category');
            if ($term && !is_wp_error($term)) {
                $category = mb_strtolower(function_exists('remove_accents') ? remove_accents((string) $term->name) : (string) $term->name);
            }
        }
        $axes = ['forma', 'materialidad', 'contexto', 'experiencia'];
        if (str_contains($category, 'digital') || str_contains($category, '3d')) {
            $axes = ['flujo visual', 'interfaz', 'control técnico', 'producción'];
        } elseif (str_contains($category, 'movilidad') || str_contains($category, 'transporte')) {
            $axes = ['proporción', 'materialidad', 'conducción', 'presencia'];
        } elseif (str_contains($category, 'moda')) {
            $axes = ['silueta', 'movimiento', 'cuerpo', 'cultura deportiva'];
        } elseif (str_contains($category, 'producto')) {
            $axes = ['objeto', 'materialidad', 'gesto manual', 'uso cotidiano'];
        } elseif (str_contains($category, 'arquitectura') || str_contains($category, 'interior')) {
            $axes = ['recorrido', 'luz', 'programa', 'vida cotidiana'];
        } elseif (str_contains($category, 'concurso') || str_contains($category, 'convocatoria')) {
            $axes = ['convocatoria', 'calendario', 'categorías', 'portafolio'];
        }
        return [
            'name' => self::short_reel_name($name !== '' ? $name : 'El proyecto'),
            'axis1' => $axes[0],
            'axis2' => $axes[1],
            'axis3' => $axes[2],
            'axis4' => $axes[3],
        ];
    }

    private static function short_reel_name(string $name): string {
        $name = trim(wp_strip_all_tags($name));
        $words = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
        if (is_array($words) && count($words) > 4) {
            $name = implode(' ', array_slice($words, 0, 4));
        }
        return $name !== '' ? $name : 'El proyecto';
    }

    private static function reel_overlays_for_scene(array $context, int $scene): array {
        $sets = [
            1 => [$context['name'], ucfirst($context['axis1']), ucfirst($context['axis2'])],
            2 => [ucfirst($context['axis2']), 'Decisión visible', 'Uso en contexto'],
            3 => [ucfirst($context['axis1']), ucfirst($context['axis3']), 'Lectura editorial'],
            4 => [ucfirst($context['axis3']), ucfirst($context['axis4']), 'Detalle clave'],
            5 => [ucfirst($context['axis4']), 'Valor de uso', 'Mirada ideasDi'],
            6 => ['Cierre editorial', 'Más contexto', 'ideasDi.com'],
        ];
        $out = $sets[$scene] ?? $sets[6];
        return array_map(static function ($text) {
            $text = trim((string) $text);
            return mb_strlen($text) > 40 ? mb_substr($text, 0, 37) . '…' : $text;
        }, $out);
    }

    private static function fit_words(string $text, int $target): string {
        $text = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags($text)));
        $text = preg_replace('/\b(actual\s+actual|actual\s+clara|uso\s+cotidiano\s+y\s+uso\s+cotidiano)\b/iu', 'actual', (string) $text);
        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $words = is_array($words) ? $words : [];
        $fillers = ['con', 'contexto', 'y', 'sentido', 'editorial', 'para', 'uso', 'real', 'en', 'ideasDi'];
        $i = 0;
        while (count($words) < $target) {
            $next = $fillers[$i % count($fillers)];
            $last = end($words);
            if ($last === $next) {
                $i++;
                continue;
            }
            $words[] = $next;
            $i++;
        }
        if (count($words) > $target) {
            $words = array_slice($words, 0, $target);
        }
        $bad_endings = ['y', 'de', 'con', 'desde', 'para', 'sin', 'en', 'la', 'el', 'un', 'una'];
        if (!empty($words) && in_array(mb_strtolower(trim(end($words), '.,;:')), $bad_endings, true)) {
            $words[count($words) - 1] = 'contexto';
        }
        return implode(' ', $words);
    }

    private static function word_count(string $text): int {
        $plain = trim(wp_strip_all_tags($text));
        preg_match_all('/\b[\p{L}\p{N}][\p{L}\p{N}\-]*\b/u', $plain, $m);
        return count($m[0] ?? []);
    }

    private static function clean_meta_description(string $meta): string {
        $meta = self::normalize($meta);
        $lines = preg_split('/\n/', $meta);
        $clean_lines = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (self::section_key_from_line($line) !== '') {
                break;
            }
            if (preg_match('/^\s*(?:[-*]\s*)?(?:\[.*?\])?\s*$/u', $line)) {
                continue;
            }
            $line = preg_replace('/^[-*]\s*/u', '', $line);
            $line = preg_replace('/^META DESCRIPTION\s*:?\s*/iu', '', $line);
            $clean_lines[] = $line;
        }
        $meta = trim(wp_strip_all_tags(implode(' ', $clean_lines)));
        $meta = preg_replace('/\s+/', ' ', (string) $meta);

        if (mb_strlen($meta) > 150) {
            $meta = mb_substr($meta, 0, 150);
            $last_space = mb_strrpos($meta, ' ');
            if ($last_space !== false && $last_space > 80) {
                $meta = mb_substr($meta, 0, $last_space);
            }
        }

        return trim($meta, " \t\n\r\0\x0B.-–—");
    }

    private static function markdownish_to_html(string $text, string $title = ''): string {
        $text = self::normalize($text);
        $lines = preg_split('/\n/', $text);
        $html = [];
        $in_list = false;
        $skip_next_as_snippet = false;
        $title_plain = trim(wp_strip_all_tags($title));
        $h2_count = 0;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                if ($in_list) {
                    $html[] = '</ul>';
                    $in_list = false;
                }
                continue;
            }

            if (self::section_key_from_line($line) !== '') {
                continue;
            }

            if (preg_match('/^#\s+(.+)$/u', $line, $m)) {
                continue;
            }

            if ($title_plain !== '' && self::plain_equals($line, $title_plain)) {
                continue;
            }

            if (preg_match('/^\*\*\s*Caja editorial\s*:?\s*\*\*\s*(.*)$/iu', $line, $m) || preg_match('/^Caja editorial\s*:?\s*(.*)$/iu', $line, $m)) {
                $snippet = trim((string) ($m[1] ?? ''));
                $snippet = trim($snippet, " -*\t");
                if ($snippet !== '') {
                    if ($in_list) {
                        $html[] = '</ul>';
                        $in_list = false;
                    }
                    $snippet = self::clean_featured_snippet_text($snippet);
                    $html[] = '<p class="featured-snippet-box">' . esc_html($snippet) . '</p>';
                } else {
                    $skip_next_as_snippet = true;
                }
                continue;
            }

            if ($skip_next_as_snippet) {
                if ($in_list) {
                    $html[] = '</ul>';
                    $in_list = false;
                }
                $line = self::clean_featured_snippet_text($line);
                $html[] = '<p class="featured-snippet-box">' . esc_html($line) . '</p>';
                $skip_next_as_snippet = false;
                continue;
            }

            if (preg_match('/^###\s+(.+)$/u', $line, $m) || preg_match('/^H3\s*[:\-]\s*(.+)$/iu', $line, $m)) {
                if ($in_list) {
                    $html[] = '</ul>';
                    $in_list = false;
                }
                $html[] = '<h3>' . self::inline_markdown_to_html(self::clean_heading_text($m[1])) . '</h3>';
                continue;
            }

            if (preg_match('/^##\s+(.+)$/u', $line, $m) || preg_match('/^H2\s*[:\-]\s*(.+)$/iu', $line, $m)) {
                if ($in_list) {
                    $html[] = '</ul>';
                    $in_list = false;
                }
                $h2_count++;
                $tag = ($h2_count === 1) ? 'h2' : 'h3';
                $html[] = '<' . $tag . '>' . self::inline_markdown_to_html(self::clean_heading_text($m[1])) . '</' . $tag . '>';
                continue;
            }

            if (preg_match('/^[-*]\s+(.+)$/u', $line, $m)) {
                if (!$in_list) {
                    $html[] = '<ul>';
                    $in_list = true;
                }
                $html[] = '<li>' . self::inline_markdown_to_html($m[1]) . '</li>';
                continue;
            }

            if ($in_list) {
                $html[] = '</ul>';
                $in_list = false;
            }
            $html[] = '<p>' . self::inline_markdown_to_html($line) . '</p>';
        }

        if ($in_list) {
            $html[] = '</ul>';
        }

        return implode("\n", $html);
    }

    private static function plain_equals(string $a, string $b): bool {
        $a = mb_strtolower(trim(wp_strip_all_tags(preg_replace('/^[#\s]+/u', '', $a))));
        $b = mb_strtolower(trim(wp_strip_all_tags($b)));
        return $a !== '' && $a === $b;
    }

    private static function inline_markdown_to_html(string $text): string {
        $text = esc_html($text);

        $text = preg_replace_callback('/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/u', function ($m) {
            return '<a href="' . esc_url($m[2]) . '" target="_blank" rel="noopener noreferrer">' . esc_html($m[1]) . '</a>';
        }, $text);

        $text = preg_replace_callback('/(?<!href=")\bhttps?:\/\/[^\s<]+/u', function ($m) {
            $url = rtrim($m[0], '.,;)');
            $trail = substr($m[0], strlen($url));
            return '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($url) . '</a>' . esc_html($trail);
        }, $text);

        $text = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $text);
        $text = preg_replace('/__(.+?)__/u', '<strong>$1</strong>', $text);
        $text = preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/u', '<em>$1</em>', $text);

        return $text;
    }


    private static function strip_bold_from_html_headings(string $html): string {
        return (string) preg_replace_callback('/<h([1-6])\b([^>]*)>(.*?)<\/h\1>/isu', static function ($m) {
            $inner = preg_replace('/<\/?(?:strong|b)\b[^>]*>/iu', '', (string) $m[3]);
            return '<h' . $m[1] . $m[2] . '>' . $inner . '</h' . $m[1] . '>';
        }, $html);
    }

    private static function apply_outside_featured_boxes(string $html, callable $callback): string {
        if (stripos($html, 'featured-snippet-box') === false) {
            return (string) $callback($html);
        }
        $parts = preg_split('/(<p\b[^>]*class=(?:"|\')[^"\']*featured-snippet-box[^"\']*(?:"|\')[^>]*>.*?<\/p>)/is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($parts)) {
            return (string) $callback($html);
        }
        foreach ($parts as $i => $part) {
            if (preg_match('/^<p\b[^>]*class=(?:"|\')[^"\']*featured-snippet-box/is', $part)) {
                continue;
            }
            $parts[$i] = (string) $callback($part);
        }
        return implode('', $parts);
    }


    private static function ensure_official_source_link(string $html, array $workflow): string {
        $url = trim((string) ($workflow['official_source'] ?? ''));
        if ($url === '' || self::is_ideasdi_url($url) || str_contains($html, $url)) {
            return $html;
        }

        // RC1.5.0: esta capa ya no redacta párrafos de rescate. Solo puede
        // convertir en enlace una expresión que ya exista en el artículo.
        $entity = trim((string) ($workflow['entity'] ?? ''));
        $anchor_label = self::external_anchor_label($workflow, $url);
        $entity_matches_source = $entity !== '' && self::source_matches_entity($url, $entity);
        $candidates = self::entity_anchor_variants($entity_matches_source ? $entity : $anchor_label);
        if (!$entity_matches_source) {
            $candidates = array_merge(['sitio oficial de la marca', 'página oficial de la marca', 'información oficial del proyecto', 'nota oficial'], $candidates);
        }
        if (self::is_contest_workflow($workflow)) {
            $candidates = array_merge(['web oficial del concurso', 'página oficial del concurso'], $candidates);
        }
        if (self::is_event_workflow($workflow)) {
            $candidates = array_merge(['página oficial del evento', 'web oficial del evento'], $candidates);
        }
        foreach (array_values(array_unique(array_filter(array_map('trim', $candidates)))) as $candidate) {
            $linked = self::link_first_anchor_occurrence($html, $candidate, $url);
            if ($linked !== $html) {
                return $linked;
            }
        }
        return $html;
    }

    private static function external_anchor_label(array $workflow, string $url): string {
        $entity = trim(wp_strip_all_tags((string) ($workflow['entity'] ?? '')));
        $host = mb_strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        $path = mb_strtolower((string) wp_parse_url($url, PHP_URL_PATH));
        if (str_contains($host . $path, 'porsche') && str_contains($host . $path, 'mold')) {
            return 'Porsche Centre Moldova';
        }
        if ($entity !== '' && self::source_matches_entity($url, $entity)) {
            return $entity;
        }
        $host = preg_replace('/^www\./', '', $host);
        $host = preg_replace('/\.[a-z]{2,}(?:\.[a-z]{2,})?$/', '', (string) $host);
        $host = str_replace(['-', '_'], ' ', (string) $host);
        return ucwords(trim($host !== '' ? $host : 'la entidad responsable'));
    }

    private static function source_matches_entity(string $url, string $entity): bool {
        $host = mb_strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        $host = function_exists('remove_accents') ? remove_accents($host) : $host;
        $host = preg_replace('/[^a-z0-9]/', '', (string) $host);
        $entity_plain = mb_strtolower(function_exists('remove_accents') ? remove_accents($entity) : $entity);
        preg_match_all('/[a-z0-9]{4,}/', $entity_plain, $m);
        foreach ($m[0] ?? [] as $token) {
            if (str_contains($host, $token)) {
                return true;
            }
        }
        return false;
    }

    private static function same_plain(string $a, string $b): bool {
        $a = mb_strtolower(remove_accents(trim(wp_strip_all_tags($a))));
        $b = mb_strtolower(remove_accents(trim(wp_strip_all_tags($b))));
        return $a !== '' && $a === $b;
    }

    private static function entity_anchor_variants(string $entity): array {
        $entity = trim(wp_strip_all_tags($entity));
        $variants = [$entity];
        $variants[] = str_replace('Mexico', 'México', $entity);
        $variants[] = str_replace('mexico', 'México', $entity);
        if (str_contains($entity, '+')) {
            $variants[] = trim(preg_replace('/\s*\+\s*/', ' + ', $entity));
        }
        return array_values(array_unique(array_filter($variants)));
    }

    private static function ensure_internal_links(string $html, array $workflow): string {
        $links = self::collect_internal_links($workflow);
        if (empty($links)) {
            return $html;
        }

        foreach ($links as $link) {
            $url = esc_url_raw((string) ($link['url'] ?? ''));
            if ($url === '' || str_contains($html, $url)) {
                continue;
            }

            // RC1.5.0: integrar únicamente sobre texto ya redactado. La ausencia
            // del enlace se resuelve en Revisión SEO, nunca creando prosa en PHP.
            $candidates = [];
            $configured = trim((string) ($link['anchor'] ?? ''));
            if ($configured !== '' && !self::is_tag_literal_anchor($url, $configured, (string) ($link['type'] ?? ''))) {
                $candidates[] = $configured;
            }
            $contextual = self::contextual_internal_anchor($link, $workflow);
            if ($contextual !== '') {
                $candidates[] = $contextual;
            }
            if (self::is_event_workflow($workflow)) {
                $candidates = array_merge($candidates, [
                    'calendario de eventos de ideasDi',
                    'agenda de eventos de diseño',
                    'calendario editorial de eventos',
                ]);
            }
            if (self::is_contest_workflow($workflow)) {
                $candidates = array_merge($candidates, [
                    'concursos y convocatorias de diseño',
                    'convocatorias de diseño abiertas',
                    'agenda de concursos de ideasDi',
                ]);
            }
            $lens = trim((string) ($workflow['editorial_lens'] ?? ''));
            if ($lens !== '') {
                $candidates[] = $lens;
            }
            if (class_exists('IDG_Editorial_Recipe_Builder')) {
                $recipe = IDG_Editorial_Recipe_Builder::build($workflow);
                $candidates = array_merge($candidates, (array) ($recipe['anchor_candidates'] ?? []));
            }

            foreach (array_values(array_unique(array_filter(array_map('trim', $candidates)))) as $candidate) {
                $linked = self::link_first_anchor_occurrence($html, $candidate, $url);
                if ($linked !== $html) {
                    $html = $linked;
                    break;
                }
            }
        }
        return $html;
    }

    private static function deduplicate_configured_internal_links(string $html, array $workflow): string {
        $targets = [];
        foreach (self::collect_internal_links($workflow) as $link) {
            $url = esc_url_raw((string) ($link['url'] ?? ''));
            if ($url !== '') {
                $targets[untrailingslashit(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'))] = true;
            }
        }
        if (empty($targets) || stripos($html, '<a ') === false) {
            return $html;
        }
        $seen = [];
        return (string) preg_replace_callback('/<a\b([^>]*?)href=("|\')([^"\']+)(\2)([^>]*)>(.*?)<\/a>/isu', function ($m) use ($targets, &$seen) {
            $href = untrailingslashit(html_entity_decode((string) $m[3], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if (!isset($targets[$href])) {
                return $m[0];
            }
            if (empty($seen[$href])) {
                $seen[$href] = true;
                return $m[0];
            }
            // Conserva exactamente las palabras del artículo y elimina solo el enlace repetido.
            return (string) $m[6];
        }, $html);
    }

    private static function contextual_internal_anchor(array $link, array $workflow): string {
        $url = (string) ($link['url'] ?? '');
        $path = trim((string) wp_parse_url($url, PHP_URL_PATH), '/');
        if (str_contains($path, 'tag/automovil')) {
            return 'mirada sobre el automóvil contemporáneo';
        }
        if (str_contains($path, 'tag/pret-a-porter')) {
            return 'vestuario listo para moverse';
        }
        if (str_contains($path, 'tag/ecodiseno')) {
            return 'decisiones de diseño sostenible';
        }
        if (str_contains($path, 'tag/modelado-3d')) {
            return 'procesos de modelado tridimensional';
        }
        if (str_contains($path, 'tag/diseno-deportivo')) {
            return 'relación entre moda y rendimiento';
        }
        if (str_contains($path, 'tag/diseno-de-iluminacion')) {
            return 'relación entre atmósfera y uso';
        }
        if (str_contains($path, 'tag/diseno-interior-institucional')) {
            return 'transformación de interiores académicos';
        }
        if (str_contains($path, 'tag/reforma')) {
            return 'adaptación del espacio existente';
        }
        if (str_contains($path, 'tag/diseno-interior-residencial')) {
            return 'formas de habitar';
        }
        if (str_contains($path, 'concursos-y-convocatorias-diseno') || str_contains($path, 'concursos-de-diseno')) {
            return 'concursos y convocatorias de diseño';
        }
        if (str_contains($path, 'tag/disenos-de-audio')) {
            return 'experiencia de audio';
        }
        if (str_contains($path, 'diseno-transporte') || str_contains($path, 'diseno-de-transporte')) {
            return 'mirada editorial de movilidad';
        }
        if (str_contains($path, 'diseno-de-moda')) {
            return 'lectura editorial de la moda';
        }
        if (str_contains($path, 'diseno-de-interiores')) {
            return 'lectura espacial del diseño';
        }
        if (str_contains($path, 'diseno-productos') || str_contains($path, 'diseno-de-productos')) {
            return 'relación entre objeto y uso';
        }
        return 'lectura relacionada dentro de ideasDi';
    }

    private static function postprocessing_audit(string $before_html, string $after_html, array $workflow): array {
        $plain = static function (string $html): string {
            $text = html_entity_decode(wp_strip_all_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return trim((string) preg_replace('/\s+/u', ' ', $text));
        };
        $before_plain = $plain($before_html);
        $after_plain = $plain($after_html);
        $before_paragraphs = preg_match_all('/<p\b/iu', $before_html, $m1);
        $after_paragraphs = preg_match_all('/<p\b/iu', $after_html, $m2);
        $sponsored_disclosure_expected = self::is_sponsored_workflow($workflow) && !empty($workflow['sponsored_visible_label']);
        $prose_unchanged = hash_equals(hash('sha256', $before_plain), hash('sha256', $after_plain));
        if ($sponsored_disclosure_expected && !$prose_unchanged) {
            $after_without_disclosure = (string) preg_replace('/<p\b[^>]*class=(?:"|\')[^"\']*idg-sponsored-disclosure[^"\']*(?:"|\')[^>]*>.*?<\/p>/isu', '', $after_html, 1);
            $prose_unchanged = hash_equals(hash('sha256', $before_plain), hash('sha256', $plain($after_without_disclosure)));
        }
        return [
            'prose_unchanged' => $prose_unchanged,
            'paragraphs_before' => (int) $before_paragraphs,
            'paragraphs_after' => (int) $after_paragraphs,
            'paragraphs_added' => max(0, (int) $after_paragraphs - (int) $before_paragraphs),
            'summary' => $prose_unchanged
                ? 'El postprocesamiento no añadió ni reescribió prosa editorial.'
                : 'El postprocesamiento alteró el texto visible y requiere revisión.',
        ];
    }

    private static function official_source_applies_as_external(array $workflow): bool {
        $url = esc_url_raw((string) ($workflow['official_source'] ?? ''));
        return $url !== '' && !self::is_ideasdi_url($url);
    }

    private static function is_ideasdi_url(string $url): bool {
        $host = mb_strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host);
        return $host === 'ideasdi.com';
    }

    private static function collect_internal_links(array $workflow): array {

        return IDG_Internal_Links::normalize($workflow);
    }

    private static function anchor_from_url(string $url): string {
        $path = (string) wp_parse_url($url, PHP_URL_PATH);
        $path = trim($path, '/');
        $parts = $path !== '' ? explode('/', $path) : [];
        $last = end($parts);
        $last = $last ?: 'enlace interno';
        return trim(str_replace('-', ' ', urldecode((string) $last)));
    }


    private static function is_tag_url(string $url): bool {
        $path = (string) wp_parse_url($url, PHP_URL_PATH);
        return (bool) preg_match('#/(tag|etiqueta)/#i', $path);
    }

    private static function guess_link_type(string $url): string {
        return self::is_tag_url($url) ? 'tag' : 'otro';
    }

    private static function is_tag_literal_anchor(string $url, string $anchor, string $type = ''): bool {
        if ($type !== 'tag' && !self::is_tag_url($url)) {
            return false;
        }
        $path = (string) wp_parse_url($url, PHP_URL_PATH);
        $path = trim($path, '/');
        $parts = $path !== '' ? explode('/', $path) : [];
        $last = end($parts) ?: '';
        $literal = trim(str_replace('-', ' ', urldecode((string) $last)));
        $a = mb_strtolower(function_exists('remove_accents') ? remove_accents(trim($anchor)) : trim($anchor));
        $l = mb_strtolower(function_exists('remove_accents') ? remove_accents($literal) : $literal);
        // If the anchor is only the literal tag name, skip fallback linking. The model can still use a contextual phrase.
        return $a !== '' && $l !== '' && $a === $l;
    }

    private static function link_anchor_occurrence_in_first_two_paragraphs(string $html, string $anchor, string $url): string {
        $parts = preg_split('/(<p\b[^>]*>.*?<\/p>)/is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($parts) || count($parts) < 3) {
            return self::link_first_anchor_occurrence($html, $anchor, $url);
        }
        $out = '';
        $paragraphs = 0;
        $changed = false;
        foreach ($parts as $part) {
            if (!$changed && preg_match('/^<p\b/i', trim($part)) && stripos($part, 'featured-snippet-box') === false) {
                $paragraphs++;
                if ($paragraphs <= 2) {
                    $new_part = self::link_first_anchor_occurrence($part, $anchor, $url);
                    if ($new_part !== $part) {
                        $part = $new_part;
                        $changed = true;
                    }
                }
            }
            $out .= $part;
        }
        return $changed ? $out : $html;
    }

    private static function link_first_anchor_occurrence(string $html, string $anchor, string $url): string {
        $anchor = trim($anchor);
        if ($anchor === '') {
            return $html;
        }
        return self::apply_outside_featured_boxes($html, function (string $segment) use ($anchor, $url): string {
            $variants = array_unique([$anchor, str_replace(' ', '-', $anchor)]);
            foreach ($variants as $variant) {
                if ($variant === '') {
                    continue;
                }
                $pattern = '/(?<![>\wáéíóúÁÉÍÓÚñÑ-])(' . preg_quote($variant, '/') . ')(?![^<]*<\/a>)(?![\wáéíóúÁÉÍÓÚñÑ-])/iu';
                $count = 0;
                $new_html = preg_replace($pattern, '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">$1</a>', $segment, 1, $count);
                if ($count > 0 && is_string($new_html)) {
                    return $new_html;
                }
            }
            return $segment;
        });
    }

    private static function ensure_minimum_bold(string $html, array $workflow): string {
        $html = self::ensure_keyword_intro_bold($html, $workflow);
        $html = self::ensure_bold_per_h3_block($html, $workflow);

        $minimum_total = 4;
        if (substr_count($html, '<strong>') >= $minimum_total) {
            return $html;
        }

        foreach (self::bold_candidates($workflow, $html) as $candidate) {
            if (substr_count($html, '<strong>') >= $minimum_total) {
                break;
            }
            $html = self::bold_first_plain_occurrence($html, $candidate);
        }

        return $html;
    }

    private static function ensure_keyword_intro_bold(string $html, array $workflow): string {
        $keyword = trim((string) ($workflow['keyword'] ?? ''));
        if ($keyword === '') {
            return $html;
        }
        return self::bold_first_plain_occurrence($html, $keyword);
    }

    private static function ensure_bold_per_h3_block(string $html, array $workflow): string {
        if (!preg_match('/<h3\b/i', $html)) {
            return $html;
        }

        $parts = preg_split('/(<h3\b[^>]*>.*?<\/h3>)/is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($parts) || count($parts) < 3) {
            return $html;
        }

        $output = '';
        $count = count($parts);
        for ($i = 0; $i < $count; $i++) {
            $part = $parts[$i];
            if (preg_match('/^<h3\b/i', $part)) {
                $output .= $part;
                if (($i + 1) < $count) {
                    $block = $parts[$i + 1];
                    $output .= self::ensure_bold_in_block($block, $workflow, 2);
                    $i++;
                }
            } else {
                $output .= $part;
            }
        }
        return $output;
    }

    private static function ensure_bold_in_block(string $block, array $workflow, int $target): string {
        $plain = trim(wp_strip_all_tags($block));
        if ($plain === '') {
            return $block;
        }
        preg_match_all('/\b[\p{L}][\p{L}\-]{2,}\b/u', $plain, $word_matches);
        $word_count = count($word_matches[0] ?? []);
        if ($word_count < 45) {
            $target = min($target, 1);
        }
        if (substr_count($block, '<strong>') >= $target) {
            return $block;
        }

        $candidates = array_merge(
            self::common_category_terms($workflow, $block),
            self::frequent_terms_from_html($block),
            self::bold_candidates($workflow, $block)
        );
        $seen = [];
        foreach ($candidates as $candidate) {
            if (substr_count($block, '<strong>') >= $target) {
                break;
            }
            $key = mb_strtolower(function_exists('remove_accents') ? remove_accents((string) $candidate) : (string) $candidate);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $block = self::bold_first_plain_occurrence($block, (string) $candidate);
        }
        return $block;
    }

    private static function bold_candidates(array $workflow, string $html = ''): array {
        $candidates = [];
        foreach (['keyword', 'entity'] as $key) {
            if (!empty($workflow[$key])) {
                $candidates[] = (string) $workflow[$key];
            }
        }

        if (!empty($workflow['category_id'])) {
            $term = get_term((int) $workflow['category_id'], 'category');
            if ($term && !is_wp_error($term)) {
                $candidates[] = (string) $term->name;
            }
        }

        if (!empty($workflow['tag_ids']) && is_array($workflow['tag_ids'])) {
            foreach ($workflow['tag_ids'] as $tag_id) {
                $tag = get_term((int) $tag_id, 'post_tag');
                if ($tag && !is_wp_error($tag)) {
                    $candidates[] = (string) $tag->name;
                }
            }
        }

        foreach (self::collect_internal_links($workflow) as $link) {
            if (!empty($link['anchor'])) {
                $candidates[] = (string) $link['anchor'];
            }
        }


        foreach (self::common_category_terms($workflow, $html) as $term) {
            $candidates[] = $term;
        }

        foreach (self::frequent_terms_from_html($html) as $term) {
            $candidates[] = $term;
        }

        $clean = [];
        $seen = [];
        foreach ($candidates as $candidate) {
            $candidate = trim(wp_strip_all_tags((string) $candidate));
            if ($candidate === '' || mb_strlen($candidate) < 3) {
                continue;
            }
            $key = mb_strtolower(function_exists('remove_accents') ? remove_accents($candidate) : $candidate);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $clean[] = $candidate;
        }
        return $clean;
    }


    private static function common_category_terms(array $workflow, string $html): array {
        $category = '';
        if (!empty($workflow['category_id'])) {
            $term = get_term((int) $workflow['category_id'], 'category');
            if ($term && !is_wp_error($term)) {
                $category = mb_strtolower(function_exists('remove_accents') ? remove_accents((string) $term->name) : (string) $term->name);
            }
        }
        $map = [
            'moda' => ['silueta', 'textura', 'patronaje', 'sastrería', 'savoir-faire', 'artesanía', 'textil', 'caída'],
            'arquitectura' => ['materialidad', 'luz natural', 'atmósfera', 'recorrido', 'escala', 'interior'],
            'interiores' => ['materialidad', 'luz natural', 'atmósfera', 'recorrido', 'escala', 'interior'],
            'producto' => ['ergonomía', 'materialidad', 'interfaz', 'durabilidad', 'proceso', 'uso cotidiano'],
            'movilidad' => ['seguridad', 'infraestructura', 'flujo', 'conducción', 'transporte', 'movilidad urbana'],
            'digital' => ['interacción', 'modelado', 'render', 'experiencia de usuario', 'visualización', 'prototipado'],
            'concursos' => ['convocatoria', 'jurado', 'entregables', 'proceso creativo', 'participación'],
        ];
        $terms = [];
        foreach ($map as $needle => $items) {
            if ($category !== '' && str_contains($category, $needle)) {
                $terms = array_merge($terms, $items);
            }
        }
        return self::filter_terms_present($terms, $html);
    }

    private static function frequent_terms_from_html(string $html): array {
        $plain = wp_strip_all_tags($html);
        $plain = mb_strtolower($plain);
        preg_match_all('/\b[\p{L}][\p{L}\-]{5,}\b/u', $plain, $m);
        $stop = array_fill_keys(['cuando','porque','desde','sobre','entre','tambien','también','donde','forma','puede','pueden','parte','pieza','articulo','artículo','final','cambio','cambia','manera','lectura','mirada','editorial','diseno','diseño','proyecto','version','versión'], true);
        $counts = [];
        foreach ($m[0] ?? [] as $word) {
            $key = function_exists('remove_accents') ? remove_accents($word) : $word;
            if (isset($stop[$key]) || isset($stop[$word])) {
                continue;
            }
            $counts[$word] = ($counts[$word] ?? 0) + 1;
        }
        arsort($counts);
        return array_slice(array_keys($counts), 0, 8);
    }

    private static function filter_terms_present(array $terms, string $html): array {
        $plain = wp_strip_all_tags($html);
        $present = [];
        foreach ($terms as $term) {
            if ($term !== '' && preg_match('/\b' . preg_quote($term, '/') . '\b/iu', $plain)) {
                $present[] = $term;
            }
        }
        return $present;
    }

    private static function bold_first_plain_occurrence(string $html, string $candidate): string {
        if ($candidate === '') {
            return $html;
        }
        if (preg_match('/<strong>\s*' . preg_quote($candidate, '/') . '\s*<\/strong>/iu', $html)) {
            return $html;
        }

        return self::apply_outside_featured_boxes($html, function (string $segment) use ($candidate): string {
            $pattern = '/(?<![>\wáéíóúÁÉÍÓÚñÑ-])(' . preg_quote($candidate, '/') . ')(?![^<]*<\/a>)(?![^<]*<\/strong>)(?![\wáéíóúÁÉÍÓÚñÑ-])/iu';
            $count = 0;
            $new_html = preg_replace($pattern, '<strong>$1</strong>', $segment, 1, $count);
            return ($count > 0 && is_string($new_html)) ? $new_html : $segment;
        });
    }

    private static function open_links_new_tab(string $html): string {
        if (stripos($html, '<a ') === false) {
            return $html;
        }

        $html = preg_replace_callback('/<a\s+([^>]*href=[^>]*)>/iu', function ($m) {
            $attrs = (string) ($m[1] ?? '');
            if (!preg_match('/\starget\s*=/iu', $attrs)) {
                $attrs .= ' target="_blank"';
            }
            if (preg_match('/\srel\s*=\s*(["\'])(.*?)\1/iu', $attrs, $rel_match)) {
                $rel = $rel_match[2];
                foreach (['noopener', 'noreferrer'] as $needed) {
                    if (!preg_match('/(?:^|\s)' . preg_quote($needed, '/') . '(?:\s|$)/iu', $rel)) {
                        $rel .= ' ' . $needed;
                    }
                }
                $attrs = preg_replace('/\srel\s*=\s*(["\'])(.*?)\1/iu', ' rel="' . esc_attr(trim($rel)) . '"', $attrs, 1);
            } else {
                $attrs .= ' rel="noopener noreferrer"';
            }
            return '<a ' . trim($attrs) . '>';
        }, $html);

        return is_string($html) ? $html : '';
    }

    private static function ensure_sponsored_required_link(string $html, array $workflow): string {
        $url = trim((string) ($workflow['sponsored_required_link'] ?? ''));
        $anchor = trim((string) ($workflow['sponsored_anchor'] ?? ''));
        if ($url === '' || $anchor === '' || str_contains($html, $url)) {
            return $html;
        }
        return self::link_first_anchor_occurrence($html, $anchor, $url);
    }

    private static function is_sponsored_workflow(array $workflow): bool {
        $piece_type = mb_strtolower((string) ($workflow['piece_type'] ?? ''));
        return str_contains($piece_type, 'patrocinado') || str_contains($piece_type, 'colaboraci') || !empty($workflow['sponsor_client']) || !empty($workflow['sponsored_required_link']);
    }

    private static function maybe_add_sponsored_disclosure(string $html, array $workflow): string {
        if (!self::is_sponsored_workflow($workflow) || empty($workflow['sponsored_visible_label'])) {
            return $html;
        }
        if (str_contains($html, 'idg-sponsored-disclosure')) {
            return $html;
        }
        return '<p class="idg-sponsored-disclosure"><em>Contenido patrocinado</em></p>' . "\n" . $html;
    }

    private static function apply_sponsored_link_rel(string $html, array $workflow): string {
        $url = trim((string) ($workflow['sponsored_required_link'] ?? ''));
        if ($url === '') {
            return $html;
        }
        $rel_type = sanitize_key((string) ($workflow['sponsored_link_rel'] ?? 'sponsored'));
        $rel_tokens = ['noopener', 'noreferrer'];
        if ($rel_type === 'sponsored') {
            $rel_tokens[] = 'sponsored';
        } elseif ($rel_type === 'nofollow') {
            $rel_tokens[] = 'nofollow';
        }
        $rel = implode(' ', array_unique($rel_tokens));
        $target_url = esc_url_raw($url);

        return (string) preg_replace_callback('/<a\b([^>]*?)href=("|\')([^"\']+)(\2)([^>]*)>/iu', function ($m) use ($target_url, $rel) {
            $href = html_entity_decode((string) $m[3]);
            if (untrailingslashit($href) !== untrailingslashit($target_url)) {
                return $m[0];
            }
            $attrs = trim((string) $m[1] . ' ' . (string) $m[5]);
            $attrs = preg_replace('/\s*target=("|\')[^"\']*\1/iu', '', (string) $attrs);
            $attrs = preg_replace('/\s*rel=("|\')[^"\']*\1/iu', '', (string) $attrs);
            $attrs = trim((string) $attrs);
            return '<a ' . ($attrs !== '' ? $attrs . ' ' : '') . 'href="' . esc_url($href) . '" target="_blank" rel="' . esc_attr($rel) . '">';
        }, $html);
    }

    private static function update_yoast_meta(int $post_id, string $meta_description, string $keyword, string $title): void {
        if ($meta_description !== '') {
            update_post_meta($post_id, '_yoast_wpseo_metadesc', sanitize_text_field($meta_description));
        }
        if ($keyword !== '') {
            update_post_meta($post_id, '_yoast_wpseo_focuskw', sanitize_text_field($keyword));
        }
        if ($title !== '') {
            update_post_meta($post_id, '_yoast_wpseo_title', sanitize_text_field($title));
        }
    }
}
