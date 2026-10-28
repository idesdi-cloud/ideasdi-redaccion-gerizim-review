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
     * Audita todos los anchors del HTML final sin consultar ni abrir destinos.
     * Las equivalencias ignoran esquema, www, slash final, query y fragmento,
     * pero conservan host/subdominio y solo decodifican caracteres URL seguros.
     */
    public static function audit_html(string $html, array $workflow): array {
        $expected_links = self::normalize($workflow);
        $expected_url = trim((string) ($expected_links[0]['url'] ?? ''));
        $expected_key = self::url_key($expected_url);
        $base_url = function_exists('home_url') ? (string) home_url('/') : 'https://ideasdi.com/';
        $root_host = self::root_host((string) self::parse_url_part($base_url, PHP_URL_HOST));
        if ($root_host === '') {
            $root_host = 'ideasdi.com';
        }

        $anchors = [];
        if (preg_match_all('/<a\b([^>]*)>(.*?)<\/a>/isu', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $attributes = (string) ($match[1] ?? '');
                $href = self::href_from_attributes($attributes);
                if ($href === '') {
                    continue;
                }
                $absolute = self::absolute_audit_url($href, $base_url);
                $key = self::url_key($absolute);
                $host = mb_strtolower((string) self::parse_url_part($absolute, PHP_URL_HOST));
                $host = preg_replace('/\.$/u', '', $host);
                $internal = $host !== '' && ($host === $root_host || str_ends_with($host, '.' . $root_host));
                $anchors[] = [
                    'href' => $href,
                    'url' => $absolute,
                    'normalized_url' => $key,
                    'anchor' => trim(wp_strip_all_tags(html_entity_decode((string) ($match[2] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'))),
                    'internal' => $internal,
                    'expected' => $expected_key !== '' && $key !== '' && hash_equals($expected_key, $key),
                ];
            }
        }

        $internal = array_values(array_filter($anchors, static fn(array $anchor): bool => !empty($anchor['internal'])));
        $expected = array_values(array_filter($internal, static fn(array $anchor): bool => !empty($anchor['expected'])));
        $unexpected = array_values(array_filter($internal, static fn(array $anchor): bool => empty($anchor['expected'])));
        $policy_required = !self::has_verified_p2_exception($workflow);
        $resolution_status = $expected_key !== '' ? 'resolved' : 'unresolved';
        $errors = [];

        // La excepción solo elimina la cuota de exactamente un enlace editorial.
        // Nunca autoriza URL internas adicionales: incluso con unresolved,
        // un destino interno debe proceder de normalize(), no del texto de IA
        // ni de campos de workflow que cualquiera puede rellenar.
        if ($policy_required) {
            if ($resolution_status === 'unresolved') {
                $errors[] = 'P2 bloqueado: no existe un tag canónico real resuelto por IDG_Internal_Links::normalize().';
            } else {
                if (count($expected) !== 1) {
                    $errors[] = 'P2 exige exactamente un enlace al tag canónico resuelto; detectados: ' . count($expected) . '.';
                }
                if (count($internal) !== 1) {
                    $errors[] = 'P2 exige exactamente un anchor interno real en el HTML final; detectados: ' . count($internal) . '.';
                }
            }
        }
        if (!empty($unexpected)) {
            $errors[] = 'P2 prohíbe URL internas no autorizadas incluso en eventos, concursos y patrocinados; detectadas: ' . count($unexpected) . '.';
        }

        return [
            'policy_required' => $policy_required,
            'exception' => $policy_required ? '' : self::verified_p2_exception($workflow),
            'resolution_status' => $resolution_status,
            'expected_url' => $expected_url,
            'anchors' => $anchors,
            'internal_anchors' => $internal,
            'expected_anchors' => $expected,
            'unexpected_internal_anchors' => $unexpected,
            'internal_count' => count($internal),
            'expected_count' => count($expected),
            'errors' => array_values(array_unique($errors)),
        ];
    }

    public static function equivalent_url(string $left, string $right): bool {
        $left_key = self::url_key(self::absolute_audit_url($left, function_exists('home_url') ? (string) home_url('/') : 'https://ideasdi.com/'));
        $right_key = self::url_key(self::absolute_audit_url($right, function_exists('home_url') ? (string) home_url('/') : 'https://ideasdi.com/'));
        return $left_key !== '' && $right_key !== '' && hash_equals($left_key, $right_key);
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

    private static function has_verified_p2_exception(array $workflow): bool {
        return self::verified_p2_exception($workflow) !== '';
    }

    private static function verified_p2_exception(array $workflow): string {
        $origin = (string) ($workflow['workflow_origin'] ?? '');
        $target_type = (string) ($workflow['recurring_target_post_type'] ?? '');
        $context = (string) ($workflow['editorial_context'] ?? '');
        if ($target_type === 'evento' && ($origin === 'recurring_update' || $context === 'event_calendar')) {
            return 'evento_cpt_verificado';
        }
        if ($target_type === 'post' && $origin === 'recurring_update') {
            return 'concurso_recurrente_verificado';
        }
        $piece_type = mb_strtolower(function_exists('remove_accents') ? remove_accents((string) ($workflow['piece_type'] ?? '')) : (string) ($workflow['piece_type'] ?? ''));
        if ((str_contains($piece_type, 'patrocinado') || str_contains($piece_type, 'colaboraci'))
            && trim((string) ($workflow['sponsored_brief'] ?? '')) !== '') {
            return 'patrocinado_verificado';
        }
        return '';
    }

    private static function href_from_attributes(string $attributes): string {
        if (!preg_match('/(?:^|\s)href\s*=\s*(?:(["\'])(.*?)\1|([^\s>]+))/isu', $attributes, $match)) {
            return '';
        }
        return trim(html_entity_decode((string) (($match[2] ?? '') !== '' ? $match[2] : ($match[3] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private static function absolute_audit_url(string $href, string $base_url): string {
        $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($href === '' || preg_match('/[\x00-\x1F\x7F]/u', $href)) {
            return '';
        }
        if (str_starts_with($href, '//')) {
            $scheme = (string) self::parse_url_part($base_url, PHP_URL_SCHEME);
            return ($scheme !== '' ? $scheme : 'https') . ':' . $href;
        }
        if (preg_match('#^https?://#iu', $href)) {
            return $href;
        }
        if (preg_match('#^[a-z][a-z0-9+.-]*:#iu', $href)) {
            return '';
        }
        $scheme = (string) self::parse_url_part($base_url, PHP_URL_SCHEME);
        $host = (string) self::parse_url_part($base_url, PHP_URL_HOST);
        $port = self::parse_url_part($base_url, PHP_URL_PORT);
        if ($host === '') {
            return '';
        }
        $authority = ($scheme !== '' ? $scheme : 'https') . '://' . $host . ($port ? ':' . (int) $port : '');
        if (str_starts_with($href, '#') || str_starts_with($href, '?')) {
            return $authority . '/' . $href;
        }
        if (str_starts_with($href, '/')) {
            return $authority . '/' . ltrim($href, '/');
        }
        // Sin la URL del documento no es seguro equiparar una ruta relativa
        // al tag raíz: se audita como interna, pero nunca como canónica.
        return $authority . '/__ruta_relativa__/' . ltrim($href, './');
    }

    private static function url_key(string $url): string {
        if ($url === '') {
            return '';
        }
        $scheme = mb_strtolower((string) self::parse_url_part($url, PHP_URL_SCHEME));
        $host = mb_strtolower((string) self::parse_url_part($url, PHP_URL_HOST));
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return '';
        }
        $host = preg_replace('/\.$/u', '', $host);
        $host = preg_replace('/^www\./iu', '', (string) $host);
        $port = self::parse_url_part($url, PHP_URL_PORT);
        $port_part = ($port && !(($scheme === 'http' && (int) $port === 80) || ($scheme === 'https' && (int) $port === 443))) ? ':' . (int) $port : '';
        $path = (string) self::parse_url_part($url, PHP_URL_PATH);
        $path = preg_replace_callback('/%[0-9a-f]{2}/iu', static function (array $match): string {
            $character = chr((int) hexdec(substr((string) $match[0], 1)));
            return preg_match('/[A-Za-z0-9\-._~]/', $character) ? $character : strtoupper((string) $match[0]);
        }, $path);
        $segments = [];
        foreach (explode('/', (string) $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($segments);
                continue;
            }
            $segments[] = $segment;
        }
        $path = '/' . implode('/', $segments);
        return $host . $port_part . ($path === '/' ? '/' : rtrim($path, '/'));
    }

    private static function root_host(string $host): string {
        $host = mb_strtolower(trim($host));
        $host = preg_replace('/\.$/u', '', $host);
        return (string) preg_replace('/^www\./iu', '', (string) $host);
    }

    private static function parse_url_part(string $url, int $part) {
        return function_exists('wp_parse_url') ? wp_parse_url($url, $part) : parse_url($url, $part);
    }

    private static function display(string $value): string {
        $value = trim($value);
        return $value === '' ? '_Sin información._' : $value;
    }
}
