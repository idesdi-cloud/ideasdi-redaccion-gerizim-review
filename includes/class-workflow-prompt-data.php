<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Construye el mismo payload histórico de prompts fuera del Job_Runner.
 */
final class IDG_Workflow_Prompt_Data {
    public static function prepare(array $workflow, string $article, string $phase = 'generic'): array {
        $category_name = '';
        if (!empty($workflow['editorial_context_name'])) {
            $category_name = (string) $workflow['editorial_context_name'];
        } elseif ((string) ($workflow['workflow_origin'] ?? '') === 'recurring_update' && (string) ($workflow['recurring_target_post_type'] ?? '') === 'evento') {
            $category_name = 'Calendario de eventos';
        } elseif ((string) ($workflow['workflow_origin'] ?? '') === 'recurring_update' && (string) ($workflow['recurring_target_post_type'] ?? '') === 'post') {
            $category_name = 'Concursos y convocatorias';
        } elseif (!empty($workflow['category_id'])) {
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
        if (empty($tag_names) && !empty($workflow['tag_names']) && is_array($workflow['tag_names'])) {
            $tag_names = array_values(array_filter(array_map('strval', $workflow['tag_names'])));
        }

        $base_structure = class_exists('IDG_Editorial_Recipe_Builder') ? IDG_Editorial_Recipe_Builder::build($workflow) : [];
        $auto_priority = (string) ($base_structure['recipe'] ?? (class_exists('IDG_Priority_Readings') ? IDG_Priority_Readings::build_suggestion((int) ($workflow['category_id'] ?? 0), isset($workflow['tag_ids']) && is_array($workflow['tag_ids']) ? $workflow['tag_ids'] : []) : ''));
        $recipe_base = trim((string) ($workflow['recipe_base'] ?? $workflow['priority_readings'] ?? $workflow['editorial_recipe'] ?? ''));
        if ($recipe_base === '') {
            $recipe_base = $auto_priority;
        }
        $priority_readings = trim((string) ($workflow['editorial_recipe_applied'] ?? $recipe_base));
        $editorial_angle = trim((string) ($workflow['editorial_angle'] ?? ''));
        if ($editorial_angle === '') {
            $editorial_angle = 'Usar el hecho base y la investigación como prioridad factual. La categoría delimita el territorio, el lente filtra la mirada y el plan editorial aplicado selecciona únicamente los ejes pertinentes.';
        }

        return [
            'keyword' => $workflow['keyword'] ?? '',
            'entity' => $workflow['entity'] ?? '',
            'piece_type' => $workflow['piece_type'] ?? '',
            'brief_fact' => $workflow['brief_fact'] ?? '',
            'editorial_angle' => $editorial_angle,
            'priority_readings' => $priority_readings,
            'recipe_base' => $recipe_base,
            'recipe_base_structure' => class_exists('IDG_Editorial_Recipe_Builder') ? IDG_Editorial_Recipe_Builder::prompt_structure($workflow) : '',
            'semantic_library_context' => class_exists('IDG_Disciplinary_Library') ? IDG_Disciplinary_Library::prompt_block($workflow) : '',
            'editorial_plan' => class_exists('IDG_Editorial_Plan') ? IDG_Editorial_Plan::prompt_block($workflow) : '',
            'identity_required' => !empty($base_structure['identity_required']),
            'category_name' => $category_name,
            'editorial_context' => (string) ($workflow['editorial_context'] ?? (((string) ($workflow['workflow_origin'] ?? '') === 'recurring_update') ? (((string) ($workflow['recurring_target_post_type'] ?? '') === 'evento') ? 'event_calendar' : 'contest_call') : '')),
            'editorial_context_name' => (string) ($workflow['editorial_context_name'] ?? (((string) ($workflow['workflow_origin'] ?? '') === 'recurring_update') ? (((string) ($workflow['recurring_target_post_type'] ?? '') === 'evento') ? 'Calendario de eventos' : 'Concursos y convocatorias') : '')),
            'wordpress_content_type' => (string) ($workflow['wordpress_content_type'] ?? (((string) ($workflow['recurring_target_post_type'] ?? '') === 'evento') ? 'Evento' : (((string) ($workflow['workflow_origin'] ?? '') === 'recurring_update') ? 'Entrada de concurso' : 'Entrada'))),
            'event_taxonomy_context' => isset($workflow['event_taxonomy_context']) && is_array($workflow['event_taxonomy_context']) ? $workflow['event_taxonomy_context'] : [],
            'event_editorial_category' => (string) ($workflow['event_editorial_category'] ?? ''),
            'tag_names' => $tag_names,
            'official_source' => $workflow['official_source'] ?? '',
            'internal_links' => $workflow['internal_links'] ?? '',
            'internal_links_structured' => $workflow['internal_links_structured'] ?? [],
            'editor_notes' => $workflow['editor_notes'] ?? '',
            'sponsor_client' => $workflow['sponsor_client'] ?? '',
            'sponsored_topic' => $workflow['sponsored_topic'] ?? '',
            'sponsored_brief' => $workflow['sponsored_brief'] ?? '',
            'sponsored_must_include' => $workflow['sponsored_must_include'] ?? '',
            'sponsored_avoid' => $workflow['sponsored_avoid'] ?? '',
            'sponsored_required_link' => $workflow['sponsored_required_link'] ?? '',
            'sponsored_anchor' => $workflow['sponsored_anchor'] ?? '',
            'sponsored_link_rel' => $workflow['sponsored_link_rel'] ?? '',
            'sponsored_visible_label' => $workflow['sponsored_visible_label'] ?? '',
            'sponsored_restrictions' => $workflow['sponsored_restrictions'] ?? '',
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

    public static function document_material_text(array $workflow): string {
        if (class_exists('IDG_Web_Research')) {
            return IDG_Web_Research::document_material($workflow);
        }
        return (string) ($workflow['temp_material_text'] ?? '');
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
