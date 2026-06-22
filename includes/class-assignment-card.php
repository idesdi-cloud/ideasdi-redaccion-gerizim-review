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
        $lines[] = '- Categoría: ' . self::display($category_name);
        $lines[] = '- Tags seleccionados: ' . self::display(implode(', ', $tag_names));
        $lines[] = '- Material de apoyo adjunto: ' . $material;
        $lines[] = '- URL oficial o fuente complementaria: ' . self::display($source_url);
        $lines[] = '';
        $lines[] = '## Hecho base';
        $lines[] = self::display((string) ($workflow['brief_fact'] ?? ''));
        $lines[] = '';
        $lines[] = '## Ángulo editorial';
        $lines[] = self::display((string) ($workflow['editorial_angle'] ?? ''));
        $lines[] = '';
        $lines[] = '## Receta editorial compacta';
        $lines[] = self::display((string) ($workflow['priority_readings'] ?? $workflow['editorial_recipe'] ?? ''));
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
        $lines[] = '- Enlace interno según matriz: tag Index → página del tag; tag No Index → categoría.';
        $lines[] = '- Borrador en bloques Gutenberg, nunca bloque clásico.';
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
            implode(',', isset($workflow['tag_ids']) && is_array($workflow['tag_ids']) ? array_map('intval', $workflow['tag_ids']) : []),
            (string) ($workflow['brief_fact'] ?? ''),
            (string) ($workflow['editorial_angle'] ?? ''),
            (string) ($workflow['priority_readings'] ?? ''),
            (string) ($workflow['editorial_recipe'] ?? ''),
            (string) ($workflow['radar_clasificacion_editorial'] ?? ''),
            (string) ($workflow['source_information_url'] ?? ''),
            class_exists('IDG_Temporary_Material') ? IDG_Temporary_Material::hash((string) ($workflow['temp_material_text'] ?? '')) : '',
        ];
        return hash('sha256', implode('|', $parts));
    }

    private static function category_name(array $workflow): string {
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
        return $tags;
    }

    private static function display(string $value): string {
        $value = trim($value);
        return $value === '' ? '_Sin información._' : $value;
    }
}
