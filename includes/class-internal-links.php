<?php
if (!defined('ABSPATH')) {
    exit;
}

final class IDG_Internal_Links {
    /**
     * Devuelve un único enlace interno principal para reducir ruido editorial.
     * En artículos, solo se enlaza a un tag editorial canónico real y elegible.
     * Biblioteca protegida heredada desde v0.3.5: sin artículo pilar ni complementario.
     */
    public static function automatic(array $workflow): array {
        $library = self::library($workflow);
        if (!empty($library['primary']['url'])) {
            return [self::sanitize_link_row($library['primary'])];
        }
        return [];
    }

    public static function normalize(array $workflow): array {
        // Article links are always rebuilt from current canonical and WordPress data.
        if (!self::is_event_workflow($workflow)) {
            return self::automatic($workflow);
        }
        if (!empty($workflow['internal_links_structured']) && is_array($workflow['internal_links_structured'])) {
            $links = array_values(array_filter(array_map([self::class, 'sanitize_link_row'], $workflow['internal_links_structured'])));
            return self::normalize_noindex_links($links, $workflow);
        }
        return self::automatic($workflow);
    }

    public static function summary(array $workflow): string {
        $rows = [];
        foreach (self::normalize($workflow) as $link) {
            $url = (string) ($link['url'] ?? '');
            if ($url === '') {
                continue;
            }
            $label = (string) ($link['label'] ?? 'Enlace interno');
            $source = (string) ($link['source_name'] ?? '');
            $type = (string) ($link['type'] ?? '');
            $rows[] = trim($label . ($source !== '' ? ': ' . $source : '') . ' | URL: ' . $url . ($type !== '' ? ' | Tipo: ' . $type : ''));
        }
        return implode("\n", $rows);
    }

    /**
     * Biblioteca de enlaces: resolución editorial canónica para artículos.
     * Las categorías son contexto; no son destinos alternativos.
     */
    public static function library(array $workflow): array {
        if (self::is_event_workflow($workflow)) {
            return self::event_library($workflow);
        }
        $category_id = !empty($workflow['category_id']) ? (int) $workflow['category_id'] : 0;
        $category = $category_id > 0 ? get_term($category_id, 'category') : null;
        $category_name = ($category && !is_wp_error($category)) ? (string) $category->name : '';
        $category_url = self::category_url($category_id, $category_name);
        $resolved = class_exists('IDG_Canonical_Context') ? IDG_Canonical_Context::resolve($workflow) : [];
        $lenses = array_values(array_unique(array_filter(array_merge(
            [$resolved['primary_lens'] ?? null], $resolved['secondary_lenses'] ?? []
        ))));
        $terms = [];
        foreach ((array) ($workflow['tag_ids'] ?? []) as $id) {
            $term = get_term((int) $id, 'post_tag');
            if ($term instanceof WP_Term && $term->term_id > 0 && $term->taxonomy === 'post_tag') {
                $terms[$term->term_id] = $term;
            }
        }
        // Optional exact-name lookup still requires an existing WordPress term.
        if (function_exists('get_term_by')) {
            foreach (['primary_lens', 'radar_lente_sugerida', 'lens_suggested', 'radar_tag_principal', 'secondary_lenses', 'radar_tags_secundarios', 'tag_names'] as $field) {
                foreach ((array) ($workflow[$field] ?? []) as $name) {
                    if (!is_string($name) || trim($name) === '') continue;
                    $term = get_term_by('name', $name, 'post_tag');
                    if ($term instanceof WP_Term && $term->term_id > 0 && $term->taxonomy === 'post_tag') {
                        $terms[$term->term_id] = $term;
                    }
                }
            }
        }
        $primary = [];
        $tag_url = '';
        $tag_name = '';
        $selected_lens = '';
        foreach ($lenses as $lens) {
            foreach ($terms as $term) {
                // Reuse adapter aliases/folding; unknown tags never acquire a lens.
                $term_context = IDG_Canonical_Adapter::resolve(['primary_lens' => (string) $term->name]);
                if ($term_context['primary_lens'] !== $lens) continue;
                if (class_exists('IDG_Priority_Readings') && IDG_Priority_Readings::is_noindex_tag_name((string) $term->name, $category_name)) continue;
                $url = self::tag_url($term, $category_name);
                if ($url === '') continue;
                $tag_name = (string) $term->name;
                $tag_url = $url;
                $selected_lens = $lens;
                $primary = self::sanitize_link_row([
                    'url' => $tag_url,
                    'type' => 'tag_principal',
                    'label' => 'Página del tag editorial canónico',
                    'source_name' => $tag_name,
                    'source_type' => 'tag',
                    'context' => 'Usar como enlace interno principal. No enlazar la keyword ni el nombre literal del tag; crear una frase contextual de 3 a 8 palabras dentro del párrafo.',
                ]);
                break 2;
            }
        }

        return [
            'editorial_resolution_status' => $primary ? 'resolved' : 'unresolved',
            'resolved_lens' => $selected_lens,
            'category_name' => $category_name,
            'category_url' => $category_url,
            'primary_tag' => $tag_name,
            'primary_tag_status' => $tag_name !== '' && class_exists('IDG_Priority_Readings') ? IDG_Priority_Readings::tag_status($tag_name, $category_name) : '',
            'tag_url' => $tag_url,
            'primary' => $primary,
        ];
    }

    public static function library_summary(array $workflow): string {
        $library = self::library($workflow);
        $lines = [];
        if (self::is_event_workflow($workflow)) {
            $lines[] = '- Perfil editorial: ' . self::display((string) ($library['editorial_profile'] ?? 'Calendario de eventos'));
            $lines[] = '- Archivo del CPT evento: ' . self::display((string) ($library['archive_url'] ?? ''));
            $lines[] = '- Taxonomías del evento: ' . self::display((string) ($library['taxonomy_summary'] ?? ''));
            $lines[] = '- Página de taxonomía disponible: ' . self::display((string) ($library['taxonomy_url'] ?? ''));
            $link = isset($library['primary']) && is_array($library['primary']) ? $library['primary'] : [];
            $lines[] = '- Enlace interno calculado: ' . self::display((string) ($link['url'] ?? '')) . (!empty($link['context']) ? ' · ' . (string) $link['context'] : '');
            $lines[] = '- Regla específica: usar únicamente URLs reales del archivo del CPT o de sus taxonomías; no crear una categoría WordPress ficticia.';
            return implode("\n", $lines);
        }
        $lines[] = '- Categoría: ' . self::display((string) ($library['category_name'] ?? ''));
        $lines[] = '- Página categoría: ' . self::display((string) ($library['category_url'] ?? ''));
        $lines[] = '- Tag principal: ' . self::display((string) ($library['primary_tag'] ?? '')) . ' · Estado: ' . self::display((string) ($library['primary_tag_status'] ?? ''));
        $lines[] = '- Página tag: ' . self::display((string) ($library['tag_url'] ?? ''));
        $link = isset($library['primary']) && is_array($library['primary']) ? $library['primary'] : [];
        $lines[] = '- Enlace interno calculado: ' . self::display((string) ($link['url'] ?? '')) . (!empty($link['context']) ? ' · ' . (string) $link['context'] : '');
        $lines[] = '- Resolución editorial: ' . $library['editorial_resolution_status'];
        $lines[] = '- Regla: lente canónica primaria y después secundarias en orden; solo tags reales elegibles con URL real. Sin destino elegible, no hay enlace interno.';
        return implode("\n", $lines);
    }

    private static function is_event_workflow(array $workflow): bool {
        return (string) ($workflow['recurring_target_post_type'] ?? '') === 'evento'
            || (string) ($workflow['editorial_context'] ?? '') === 'event_calendar';
    }

    private static function event_library(array $workflow): array {
        $archive_url = function_exists('get_post_type_archive_link') ? get_post_type_archive_link('evento') : '';
        $archive_url = is_string($archive_url) ? esc_url_raw($archive_url) : '';
        $taxonomy_url = '';
        $taxonomy_name = '';
        $summary_parts = [];
        foreach ((array) ($workflow['event_taxonomy_context'] ?? []) as $row) {
            if (!is_array($row)) continue;
            $label = trim((string) ($row['label'] ?? $row['taxonomy'] ?? 'Taxonomía'));
            $names = [];
            foreach ((array) ($row['terms'] ?? []) as $term) {
                if (!is_array($term)) continue;
                $name = trim((string) ($term['name'] ?? ''));
                if ($name !== '') $names[] = $name;
                if ($taxonomy_url === '' && !empty($term['term_id']) && !empty($row['taxonomy']) && function_exists('get_term_link')) {
                    $candidate = get_term_link((int) $term['term_id'], (string) $row['taxonomy']);
                    if (!is_wp_error($candidate) && is_string($candidate)) {
                        $taxonomy_url = esc_url_raw($candidate);
                        $taxonomy_name = $name;
                    }
                }
            }
            if (!empty($names)) {
                $summary_parts[] = $label . ': ' . implode(', ', array_values(array_unique($names)));
            }
        }
        $primary_url = $archive_url !== '' ? $archive_url : $taxonomy_url;
        $primary = $primary_url !== '' ? self::sanitize_link_row([
            'url' => $primary_url,
            'type' => $archive_url !== '' ? 'archivo_eventos' : 'taxonomia_evento',
            'label' => $archive_url !== '' ? 'Archivo del calendario de eventos' : 'Taxonomía propia del evento',
            'source_name' => $archive_url !== '' ? 'Calendario de eventos' : $taxonomy_name,
            'source_type' => $archive_url !== '' ? 'post_type_archive' : 'taxonomy',
            'context' => 'Integrar como continuidad editorial con un anchor natural; no presentarlo como una categoría estándar de WordPress.',
        ]) : [];
        return [
            'editorial_profile' => (string) ($workflow['editorial_context_name'] ?? 'Calendario de eventos'),
            'archive_url' => $archive_url,
            'taxonomy_url' => $taxonomy_url,
            'taxonomy_summary' => implode(' · ', $summary_parts),
            'primary' => $primary,
        ];
    }

    private static function normalize_noindex_links(array $links, array $workflow): array {
        if (empty($links)) {
            return self::automatic($workflow);
        }
        $category_id = !empty($workflow['category_id']) ? (int) $workflow['category_id'] : 0;
        $category = $category_id > 0 ? get_term($category_id, 'category') : null;
        $category_name = ($category && !is_wp_error($category)) ? (string) $category->name : '';
        $primary = $links[0];
        $source_name = (string) ($primary['source_name'] ?? '');
        $type = (string) ($primary['type'] ?? '');
        $is_noindex = $source_name !== '' && class_exists('IDG_Priority_Readings') && IDG_Priority_Readings::is_noindex_tag_name($source_name, $category_name);
        if ($is_noindex || $type === 'tag_noindex') {
            $fallback = self::library($workflow);
            return !empty($fallback['primary']) ? [self::sanitize_link_row($fallback['primary'])] : [];
        }
        return array_slice($links, 0, 1);
    }

    private static function category_url(int $category_id, string $category_name): string {
        $url = '';
        if (class_exists('IDG_Priority_Readings')) {
            $url = IDG_Priority_Readings::category_curated_url($category_name);
        }
        if ($url === '' && $category_id > 0) {
            $cat_link = get_category_link($category_id);
            $url = (!is_wp_error($cat_link) && is_string($cat_link)) ? $cat_link : '';
        }
        return $url !== '' ? esc_url_raw($url) : '';
    }

    private static function tag_url(WP_Term $tag, string $category_name = ''): string {
        $tag_link = get_term_link($tag, 'post_tag');
        return (!is_wp_error($tag_link) && is_string($tag_link)) ? esc_url_raw($tag_link) : '';
    }

    private static function sanitize_link_row($row): array {
        if (!is_array($row)) {
            return [];
        }
        $url = esc_url_raw((string) ($row['url'] ?? ''));
        if ($url === '') {
            return [];
        }
        return [
            'url' => $url,
            'type' => sanitize_text_field((string) ($row['type'] ?? '')),
            'label' => sanitize_text_field((string) ($row['label'] ?? 'Enlace interno')),
            'source_name' => sanitize_text_field((string) ($row['source_name'] ?? '')),
            'source_type' => sanitize_text_field((string) ($row['source_type'] ?? '')),
            'post_id' => isset($row['post_id']) ? absint($row['post_id']) : 0,
            'context' => sanitize_text_field((string) ($row['context'] ?? 'Gerizim debe crear un anchor contextual dentro del artículo.')),
        ];
    }

    private static function display(string $value): string {
        $value = trim($value);
        return $value === '' ? '_Sin información._' : $value;
    }
}
