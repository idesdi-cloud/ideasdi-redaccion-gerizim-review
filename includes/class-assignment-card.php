<?php
if (!defined('ABSPATH')) {
    exit;
}

final class IDG_Assignment_Card {
    public static function attach(array $workflow): array {
        $hash = self::hash($workflow);
        if (!empty($workflow['assignment_card']) && (string) ($workflow['assignment_card_hash'] ?? '') === $hash) {
            return $workflow;
        }
        $workflow['assignment_card'] = self::build($workflow);
        $workflow['assignment_card_hash'] = $hash;
        $workflow['assignment_card_created_at'] = function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s');
        return $workflow;
    }

    public static function build(array $workflow): string {
        $category_name = self::category_name($workflow);
        $tag_names = self::tag_names($workflow);
        $internal = class_exists('IDG_Internal_Links') ? IDG_Internal_Links::library_summary($workflow) : 'Biblioteca interna no disponible.';
        $material = class_exists('IDG_Temporary_Material') && IDG_Temporary_Material::has_material($workflow) ? 'sí' : 'no';
        $source_url = trim((string) ($workflow['source_information_url'] ?? $workflow['temp_material_url'] ?? ''));
        $official = trim((string) ($workflow['official_source'] ?? ''));

        $lines = [];
        $lines[] = '# Ficha de encargo editorial';
        $lines[] = '- Versión del plugin: ' . (defined('IDG_VERSION') ? IDG_VERSION : '');
        $lines[] = '- Registrada: ' . (function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s'));
        $lines[] = '- Keyword principal: ' . self::display((string) ($workflow['keyword'] ?? ''));
        $lines[] = '- Entidad / responsable editorial: ' . self::display((string) ($workflow['entity'] ?? ''));
        $lines[] = '- URL del responsable para enlace externo: ' . self::display($official);
        $lines[] = '- Tipo de pieza: ' . self::display((string) ($workflow['piece_type'] ?? ''));
        if (self::is_event_workflow($workflow)) {
            $lines[] = '- Tipo de contenido WordPress: ' . self::display((string) ($workflow['wordpress_content_type'] ?? 'Evento'));
            $lines[] = '- Perfil editorial: ' . self::display((string) ($workflow['editorial_context_name'] ?? 'Calendario de eventos'));
            $lines[] = '- Categoría editorial del evento: ' . self::display((string) ($workflow['event_editorial_category'] ?? ''));
            $lines[] = '- Categoría WordPress: No aplica';
            $lines[] = '- Taxonomías propias del evento: ' . self::display(self::event_taxonomy_text($workflow));
        } else {
            $lines[] = '- Categoría: ' . self::display($category_name);
            $lines[] = '- Tags seleccionados: ' . self::display(implode(', ', $tag_names));
        }
        $lines[] = '- Material de apoyo adjunto: ' . $material;
        $lines[] = '- URL oficial o fuente complementaria: ' . self::display($source_url);
        $lines[] = '';
        $lines[] = '## Hecho base';
        $lines[] = self::display((string) ($workflow['brief_fact'] ?? ''));
        $lines[] = '';
        $lines[] = '## Ángulo editorial';
        $lines[] = self::display((string) ($workflow['editorial_angle'] ?? ''));
        $lines[] = '';
        $lines[] = '## Receta base antes de investigar';
        $lines[] = self::display((string) ($workflow['recipe_base'] ?? $workflow['priority_readings'] ?? $workflow['editorial_recipe'] ?? ''));
        if (trim((string) ($workflow['editorial_plan_raw'] ?? '')) !== '') {
            $lines[] = '';
            $lines[] = '## Plan editorial aplicado';
            $lines[] = '- Tesis: ' . self::display((string) ($workflow['editorial_thesis'] ?? ''));
            $lines[] = '- Lente disciplinar: ' . self::display((string) ($workflow['editorial_lens'] ?? ''));
            $lines[] = '- Identidad de autor o marca: ' . self::display((string) ($workflow['editorial_identity'] ?? ''));
            $lines[] = '- Ejes seleccionados: ' . self::display(self::list_text($workflow['editorial_selected_axes'] ?? []));
            $lines[] = '- Traducciones perceptivas y de uso: ' . self::display(self::list_text($workflow['editorial_perceptual_translations'] ?? []));
            $lines[] = '- Ejes descartados: ' . self::display(self::list_text($workflow['editorial_discarded_axes'] ?? []));
            $lines[] = '- Riesgos editoriales: ' . self::display(self::list_text($workflow['editorial_plan_risks'] ?? []));
            $lines[] = '- Estrategia de enlaces: ' . self::display((string) ($workflow['editorial_link_strategy'] ?? ''));
            $lines[] = '';
            $lines[] = '### Receta aplicada al caso';
            $lines[] = self::display((string) ($workflow['editorial_recipe_applied'] ?? ''));
        }
        if (!empty($workflow['radar_clasificacion_editorial'])) {
            $lines[] = '';
            $lines[] = '- Clasificación editorial Radar: ' . self::display((string) $workflow['radar_clasificacion_editorial']);
        }
        $lines[] = '';
        $lines[] = '## Biblioteca interna calculada';
        $lines[] = $internal;
        if (!empty($workflow['recipe_technical_summary'])) {
            $lines[] = '';
            $lines[] = '## Reglas técnicas separadas de la receta';
            $lines[] = self::display((string) $workflow['recipe_technical_summary']);
        }
        $lines[] = '';
        $lines[] = '## Reglas duras del encargo';
        $lines[] = '- H1 con keyword principal y máximo 68 caracteres.';
        $lines[] = '- Caja editorial de 40 a 55 palabras, sin enlaces ni negritas.';
        $lines[] = '- Si hay URL del responsable, el artículo debe incluir un enlace externo coherente hacia esa URL.';
        if (self::is_event_workflow($workflow)) {
            $lines[] = '- Enlace interno desde el archivo real del CPT evento o una taxonomía propia real; no usar categorías estándar ficticias.';
        } else {
            $lines[] = '- Enlace interno según matriz: tag Index → página del tag; tag No Index → categoría.';
        }
        $lines[] = '- Entrada en bloques Gutenberg, nunca bloque clásico.';
        $lines[] = '- Metadatos completos: meta, informe SEO, copy redes, paquete reel y retroalimentación.';
        $lines[] = '- Paquete reel con CTA fijo: Conoce más de este proyecto en ideasDi.com.';
        return implode("\n", $lines);
    }

    private static function hash(array $workflow): string {
        $parts = [
            (string) ($workflow['keyword'] ?? ''),
            (string) ($workflow['entity'] ?? ''),
            (string) ($workflow['official_source'] ?? ''),
            (string) ($workflow['piece_type'] ?? ''),
            (string) ($workflow['category_id'] ?? ''),
            (string) ($workflow['editorial_context'] ?? ''),
            (string) ($workflow['editorial_context_name'] ?? ''),
            (string) ($workflow['event_editorial_category'] ?? ''),
            wp_json_encode($workflow['event_taxonomy_context'] ?? []),
            implode(',', isset($workflow['tag_ids']) && is_array($workflow['tag_ids']) ? array_map('intval', $workflow['tag_ids']) : []),
            (string) ($workflow['brief_fact'] ?? ''),
            (string) ($workflow['editorial_angle'] ?? ''),
            (string) ($workflow['priority_readings'] ?? ''),
            (string) ($workflow['editorial_recipe'] ?? ''),
            (string) ($workflow['recipe_base'] ?? ''),
            (string) ($workflow['editorial_plan_hash'] ?? ''),
            (string) ($workflow['editorial_recipe_applied'] ?? ''),
            (string) ($workflow['radar_clasificacion_editorial'] ?? ''),
            (string) ($workflow['source_information_url'] ?? ''),
            class_exists('IDG_Temporary_Material') ? IDG_Temporary_Material::hash((string) ($workflow['temp_material_text'] ?? '')) : '',
        ];
        return hash('sha256', implode('|', $parts));
    }

    private static function category_name(array $workflow): string {
        if (self::is_event_workflow($workflow)) {
            return (string) ($workflow['editorial_context_name'] ?? 'Calendario de eventos');
        }
        if (!empty($workflow['category_id'])) {
            $term = get_term((int) $workflow['category_id'], 'category');
            if ($term && !is_wp_error($term)) {
                return (string) $term->name;
            }
        }
        return '';
    }

    private static function tag_names(array $workflow): array {
        $tags = [];
        if (!empty($workflow['tag_ids']) && is_array($workflow['tag_ids'])) {
            foreach ($workflow['tag_ids'] as $tag_id) {
                $term = get_term((int) $tag_id, 'post_tag');
                if ($term && !is_wp_error($term)) {
                    $tags[] = (string) $term->name;
                }
            }
        }
        if (empty($tags) && !empty($workflow['tag_names']) && is_array($workflow['tag_names'])) {
            $tags = array_values(array_filter(array_map('strval', $workflow['tag_names'])));
        }
        return $tags;
    }

    private static function list_text($value): string {
        if (is_array($value)) {
            return implode(' · ', array_values(array_filter(array_map(static fn($item) => trim(wp_strip_all_tags((string) $item)), $value))));
        }
        return trim(wp_strip_all_tags((string) $value));
    }


    private static function is_event_workflow(array $workflow): bool {
        return (string) ($workflow['recurring_target_post_type'] ?? '') === 'evento'
            && ((string) ($workflow['editorial_context'] ?? '') === 'event_calendar' || (string) ($workflow['workflow_origin'] ?? '') === 'recurring_update');
    }

    private static function event_taxonomy_text(array $workflow): string {
        $parts = [];
        foreach ((array) ($workflow['event_taxonomy_context'] ?? []) as $row) {
            if (!is_array($row)) continue;
            $names = [];
            foreach ((array) ($row['terms'] ?? []) as $term) {
                if (is_array($term) && trim((string) ($term['name'] ?? '')) !== '') {
                    $names[] = trim((string) $term['name']);
                }
            }
            if (!empty($names)) {
                $parts[] = trim((string) ($row['label'] ?? $row['taxonomy'] ?? 'Taxonomía')) . ': ' . implode(', ', array_values(array_unique($names)));
            }
        }
        return implode(' · ', $parts);
    }

    private static function display(string $value): string {
        $value = trim($value);
        return $value === '' ? '_Sin información._' : $value;
    }
}
