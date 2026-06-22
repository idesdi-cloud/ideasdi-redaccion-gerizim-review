<?php
if (!defined('ABSPATH')) {
    exit;
}

final class IDG_Radar_Importer {
    public static function validate_json_string(string $json): array {
        $json = trim(wp_check_invalid_utf8($json));
        if ($json === '') {
            return self::error('Pega primero el JSON individual exportado por el Radar editorial.');
        }
        $payload = json_decode($json, true);
        if (!is_array($payload)) {
            $message = function_exists('json_last_error_msg') ? json_last_error_msg() : 'JSON inválido';
            return self::error('JSON inválido: ' . $message . '.');
        }
        $validation = self::validate_payload($payload);
        if (!$validation['success']) {
            return $validation;
        }
        $brief = isset($payload['brief']) && is_array($payload['brief']) ? $payload['brief'] : [];
        $warnings = [];
        $category = self::map_category_by_name((string) ($brief['categoria_wp'] ?? ''));
        if (!empty($category['warning'])) {
            $warnings[] = $category['warning'];
        }
        $tags = self::map_tags_by_names(self::ordered_tag_names($brief));
        foreach ($tags['warnings'] as $warning) {
            $warnings[] = $warning;
        }
        return [
            'success' => true,
            'message' => 'JSON Radar válido para Gerizim. No se modificó la ficha.',
            'warnings' => $warnings,
        ];
    }

    public static function import_from_json_string(string $json, array $current_workflow): array {
        $json = trim(wp_check_invalid_utf8($json));
        if ($json === '') {
            return self::error('Pega primero el JSON individual exportado por el Radar editorial.');
        }

        $payload = json_decode($json, true);
        if (!is_array($payload)) {
            $message = function_exists('json_last_error_msg') ? json_last_error_msg() : 'JSON inválido';
            return self::error('JSON inválido: ' . $message . '.');
        }

        $validation = self::validate_payload($payload);
        if (!$validation['success']) {
            return $validation;
        }

        if (self::has_prefilled_brief($current_workflow)) {
            return self::error('La ficha actual ya contiene datos. Inicia una nueva redacción o usa Reinicio parcial antes de importar un brief desde Radar.');
        }

        $brief = isset($payload['brief']) && is_array($payload['brief']) ? $payload['brief'] : [];
        $content = isset($payload['contenido_editorial']) && is_array($payload['contenido_editorial']) ? $payload['contenido_editorial'] : [];
        $hallazgo = isset($payload['hallazgo']) && is_array($payload['hallazgo']) ? $payload['hallazgo'] : [];

        $warnings = [];
        $category_result = self::map_category_by_name((string) ($brief['categoria_wp'] ?? ''));
        if (!empty($category_result['warning'])) {
            $warnings[] = $category_result['warning'];
        }

        $tag_names = self::ordered_tag_names($brief);
        $tag_result = self::map_tags_by_names($tag_names);
        foreach ($tag_result['warnings'] as $warning) {
            $warnings[] = $warning;
        }

        $data = $current_workflow;
        $data['keyword'] = self::clean_text((string) ($brief['keyword_principal'] ?? ''));
        $data['entity'] = self::clean_text((string) ($brief['responsable'] ?? ''));
        $data['official_source'] = self::clean_url((string) ($brief['responsable_url'] ?? ''));
        $data['source_information_url'] = self::clean_url((string) ($brief['fuente_oficial'] ?? ''));
        $data['piece_type'] = self::normalize_piece_type((string) ($brief['tipo_pieza'] ?? ''));
        if ((int) ($category_result['id'] ?? 0) > 0) {
            $data['category_id'] = (int) $category_result['id'];
        }
        $data['tag_ids'] = !empty($tag_result['ids']) ? array_values(array_unique(array_map('intval', $tag_result['ids']))) : [];
        $data['brief_fact'] = self::clean_textarea((string) ($content['hecho_base'] ?? ''));
        $data['editorial_angle'] = self::clean_textarea((string) ($content['angulo_editorial'] ?? ''));
        $data['priority_readings'] = '';
        $data['radar_brief_id'] = isset($brief['id']) ? absint($brief['id']) : 0;
        $data['radar_hallazgo_id'] = isset($hallazgo['id']) ? absint($hallazgo['id']) : (isset($brief['hallazgo_id']) ? absint($brief['hallazgo_id']) : 0);
        $data['radar_hallazgo_url'] = self::clean_url((string) ($hallazgo['url_hallazgo'] ?? ''));
        $data['radar_imported_at'] = current_time('mysql');
        $data['radar_exported_at'] = self::clean_text((string) ($payload['fecha_exportacion'] ?? ''));
        $data['radar_export_version'] = self::clean_text((string) ($payload['version_exportacion'] ?? ''));
        $data['radar_source'] = 'radar-editorial-ideasdi';
        $data['radar_tag_principal'] = self::clean_text((string) ($brief['tag_principal'] ?? ($tag_names[0] ?? '')));
        $data['radar_tags_secundarios'] = isset($brief['tags_secundarios']) && is_array($brief['tags_secundarios']) ? array_values(array_map([self::class, 'clean_text'], $brief['tags_secundarios'])) : array_slice($tag_names, 1);
        $data['radar_clasificacion_editorial'] = self::clean_text((string) ($brief['clasificacion_editorial'] ?? ''));
        $data['radar_restricciones_editoriales'] = isset($payload['restricciones_editoriales']) && is_array($payload['restricciones_editoriales']) ? self::clean_list($payload['restricciones_editoriales']) : [];
        $data['radar_contexto_editorial'] = self::legacy_readings_text($content);
        $data['radar_import_warnings'] = $warnings;
        $data['radar_notes'] = self::clean_textarea((string) ($content['notas_editoriales_internas'] ?? ''));
        $data['editor_notes'] = trim((string) ($data['editor_notes'] ?? ''));

        if (class_exists('IDG_Editorial_Recipe_Builder')) {
            $recipe = IDG_Editorial_Recipe_Builder::build($data);
            $data['editorial_recipe'] = (string) ($recipe['recipe'] ?? '');
            $data['priority_readings'] = $data['editorial_recipe'];
            $data['recipe_technical_summary'] = (string) ($recipe['technical_summary'] ?? '');
        }

        if (!empty($data['radar_clasificacion_editorial']) && self::normalize_name((string) $data['radar_clasificacion_editorial']) === 'evergreen') {
            $evergreen = self::find_tag_by_name('Evergreen');
            if ($evergreen && !in_array((int) $evergreen->term_id, $data['tag_ids'], true)) {
                $data['tag_ids'][] = (int) $evergreen->term_id;
            }
        }

        if (class_exists('IDG_Internal_Links')) {
            $data['internal_links_structured'] = IDG_Internal_Links::automatic($data);
        }
        if (class_exists('IDG_Assignment_Card')) {
            $data = IDG_Assignment_Card::attach($data);
        }

        $message = 'Brief importado desde Radar editorial.';
        if (!empty($data['radar_brief_id'])) {
            $message .= ' Brief Radar ID: ' . (int) $data['radar_brief_id'] . '.';
        }
        if (!empty($data['radar_hallazgo_id'])) {
            $message .= ' Hallazgo Radar ID: ' . (int) $data['radar_hallazgo_id'] . '.';
        }

        return [
            'success' => true,
            'workflow' => $data,
            'warnings' => $warnings,
            'message' => $message,
        ];
    }

    private static function validate_payload(array $payload): array {
        $errors = [];
        if ((string) ($payload['sistema'] ?? '') !== 'radar-editorial-ideasdi') {
            $errors[] = 'El campo sistema debe ser radar-editorial-ideasdi.';
        }
        if ((string) ($payload['destino'] ?? '') !== 'gerizim-wp') {
            $errors[] = 'El campo destino debe ser gerizim-wp.';
        }
        $version = (string) ($payload['version_exportacion'] ?? '');
        if ($version !== '' && !in_array($version, ['1.0', '1.1'], true)) {
            $errors[] = 'version_exportacion debe ser 1.1. Se acepta 1.0 solo por compatibilidad temporal.';
        }
        if (!isset($payload['brief']) || !is_array($payload['brief'])) {
            $errors[] = 'Falta el objeto brief.';
        }
        if (!isset($payload['contenido_editorial']) || !is_array($payload['contenido_editorial'])) {
            $errors[] = 'Falta el objeto contenido_editorial.';
        }
        $brief = isset($payload['brief']) && is_array($payload['brief']) ? $payload['brief'] : [];
        $content = isset($payload['contenido_editorial']) && is_array($payload['contenido_editorial']) ? $payload['contenido_editorial'] : [];
        if (trim((string) ($brief['keyword_principal'] ?? '')) === '') $errors[] = 'Falta brief.keyword_principal.';
        if (trim((string) ($brief['categoria_wp'] ?? '')) === '') $errors[] = 'Falta brief.categoria_wp.';
        if (trim((string) ($brief['tipo_pieza'] ?? '')) === '') $errors[] = 'Falta brief.tipo_pieza.';
        if (trim((string) ($brief['responsable'] ?? '')) === '') $errors[] = 'Falta brief.responsable.';
        if (trim((string) ($brief['fuente_oficial'] ?? '')) === '' && trim((string) ($brief['responsable_url'] ?? '')) === '') $errors[] = 'Falta brief.fuente_oficial o brief.responsable_url.';
        if (trim((string) ($content['hecho_base'] ?? '')) === '') $errors[] = 'Falta contenido_editorial.hecho_base.';
        if (trim((string) ($content['angulo_editorial'] ?? '')) === '') $errors[] = 'Falta contenido_editorial.angulo_editorial.';
        $piece = self::normalize_name((string) ($brief['tipo_pieza'] ?? ''));
        if ($piece !== '' && !in_array($piece, ['actualidad', 'agenda'], true)) {
            $errors[] = 'brief.tipo_pieza solo puede ser Actualidad o Agenda.';
        }
        if ($piece === 'evergreen') {
            $errors[] = 'Evergreen no puede venir como tipo_pieza; debe usar brief.clasificacion_editorial.';
        }
        if (isset($content['lecturas_prioritarias'])) {
            $errors[] = 'El JSON v1.1 no debe incluir contenido_editorial.lecturas_prioritarias como campo operativo.';
        }
        if (!empty($brief['etiquetas_wp']) && is_array($brief['etiquetas_wp']) && trim((string) ($brief['tag_principal'] ?? '')) === '') {
            $errors[] = 'Falta brief.tag_principal cuando existen etiquetas_wp.';
        }
        if (!empty($errors)) {
            return self::error(implode(' ', $errors));
        }
        return ['success' => true, 'message' => '', 'warnings' => []];
    }

    private static function has_prefilled_brief(array $workflow): bool {
        foreach (['keyword', 'entity', 'official_source', 'source_information_url', 'brief_fact', 'editorial_angle', 'priority_readings'] as $key) {
            if (trim((string) ($workflow[$key] ?? '')) !== '') {
                return true;
            }
        }
        if (!empty($workflow['category_id']) || !empty($workflow['tag_ids'])) {
            return true;
        }
        return false;
    }

    private static function ordered_tag_names(array $brief): array {
        $tags = isset($brief['etiquetas_wp']) && is_array($brief['etiquetas_wp']) ? array_values(array_filter(array_map('strval', $brief['etiquetas_wp']))) : [];
        $primary = trim((string) ($brief['tag_principal'] ?? ''));
        if ($primary !== '') {
            $tags = array_values(array_filter($tags, static fn($name) => self::normalize_name((string) $name) !== self::normalize_name($primary)));
            array_unshift($tags, $primary);
        }
        return array_values(array_unique($tags));
    }

    private static function legacy_readings_text(array $content): string {
        $readings = $content['lecturas_prioritarias'] ?? ($content['contexto_editorial_radar'] ?? []);
        if (is_array($readings)) {
            return self::clean_textarea(implode("\n", array_map('strval', $readings)));
        }
        return self::clean_textarea((string) $readings);
    }

    private static function map_category_by_name(string $category_name): array {
        $category_name = trim($category_name);
        if ($category_name === '') {
            return ['id' => 0, 'warning' => 'El JSON no incluye categoría WordPress.'];
        }
        $aliases = [
            'interior arquitectura' => ['arquitectura y diseno interior', 'arquitectura e interiores'],
            'diseno digital y 3d' => ['diseno digital y 3d', 'diseno digital'],
            'diseno de producto' => ['diseno de producto', 'diseno de productos'],
            'movilidad y transporte' => ['movilidad y transporte', 'movilidad'],
            'concursos de diseno' => ['concursos y convocatorias'],
            'eventos' => ['calendario de eventos', 'eventos'],
        ];
        $terms = get_terms(['taxonomy' => 'category', 'hide_empty' => false]);
        if (is_wp_error($terms) || empty($terms)) {
            return ['id' => 0, 'warning' => 'No se pudieron leer las categorías de WordPress.'];
        }
        $target = self::normalize_name($category_name);
        $targets = array_merge([$target], $aliases[$target] ?? []);
        foreach ($terms as $term) {
            $term_name = self::normalize_name((string) $term->name);
            if (in_array($term_name, $targets, true)) {
                return ['id' => (int) $term->term_id, 'warning' => ''];
            }
        }
        return ['id' => 0, 'warning' => 'Categoría no encontrada: ' . $category_name . '.'];
    }

    private static function map_tags_by_names(array $tag_names): array {
        $ids = [];
        $missing = [];
        foreach ($tag_names as $tag_name) {
            $tag_name = trim((string) $tag_name);
            if ($tag_name === '') continue;
            $term = self::find_tag_by_name($tag_name);
            if ($term) $ids[] = (int) $term->term_id;
            else $missing[] = $tag_name;
        }
        $warnings = [];
        if (!empty($missing)) $warnings[] = 'Etiquetas no encontradas: ' . implode(', ', $missing) . '.';
        return ['ids' => $ids, 'missing' => $missing, 'warnings' => $warnings];
    }

    private static function find_tag_by_name(string $tag_name) {
        $aliases = [
            'arquitectura residencial' => ['residencial'],
            'vivienda' => ['residencial'],
            'paisaje' => ['paisaje'],
            'experiencia digital' => ['experiencia digital'],
        ];
        $terms = get_terms(['taxonomy' => 'post_tag', 'hide_empty' => false]);
        if (is_wp_error($terms) || empty($terms)) return null;
        $target = self::normalize_name($tag_name);
        $targets = array_merge([$target], $aliases[$target] ?? []);
        foreach ($terms as $term) {
            if (in_array(self::normalize_name((string) $term->name), $targets, true)) return $term;
        }
        return null;
    }

    private static function normalize_piece_type(string $piece_type): string {
        $plain = self::normalize_name($piece_type);
        if ($plain === 'agenda') return 'Agenda';
        return 'Actualidad';
    }

    private static function normalize_name(string $value): string {
        $value = trim($value);
        $value = function_exists('remove_accents') ? remove_accents($value) : $value;
        $value = mb_strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value);
        return trim((string) preg_replace('/\s+/', ' ', (string) $value));
    }

    private static function clean_list(array $items): array {
        return array_values(array_filter(array_map(static fn($item) => self::clean_text((string) $item), $items)));
    }

    private static function clean_text(string $value): string { return class_exists('IDG_Sanitizer') ? IDG_Sanitizer::text($value) : sanitize_text_field($value); }
    private static function clean_textarea(string $value): string { return class_exists('IDG_Sanitizer') ? IDG_Sanitizer::textarea($value) : sanitize_textarea_field($value); }
    private static function clean_url(string $value): string { return class_exists('IDG_Sanitizer') ? IDG_Sanitizer::url($value) : esc_url_raw($value); }
    private static function error(string $message): array { return ['success' => false, 'message' => $message, 'warnings' => []]; }
}
