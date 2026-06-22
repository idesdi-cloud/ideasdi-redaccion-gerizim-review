<?php
if (!defined('ABSPATH')) {
    exit;
}

final class IDG_Final_Guard {
    public static function validate_before_draft(string $content, string $html, array $sections, array $workflow): array {
        $errors = [];
        $warnings = [];
        $keyword = trim((string) ($workflow['keyword'] ?? ''));
        $official = esc_url_raw((string) ($workflow['official_source'] ?? ''));
        $entity = trim((string) ($workflow['entity'] ?? ''));
        $title = self::extract_h1($content);
        $rules = class_exists('IDG_Editorial_Rules') ? IDG_Editorial_Rules::get() : [];
        $h1_max = (int) ($rules['h1_max_chars'] ?? 68);
        $box_min = (int) ($rules['editorial_box_min_words'] ?? 40);
        $box_max = (int) ($rules['editorial_box_max_words'] ?? 55);

        if (trim((string) ($workflow['assignment_card'] ?? '')) === '') {
            $errors[] = 'Falta la ficha de encargo editorial registrada.';
        }
        if ($title === '') {
            $errors[] = 'No se detectó H1 en ARTÍCULO FINAL.';
        } else {
            if (mb_strlen($title) > $h1_max) {
                $errors[] = 'El H1 supera ' . $h1_max . ' caracteres.';
            }
            if ($keyword !== '' && !self::h1_keyword_ok($title, $keyword, $rules)) {
                $policy = (string) ($rules['h1_keyword_policy'] ?? 'flexible');
                if ($policy === 'exact') {
                    $errors[] = 'El H1 no contiene la keyword principal exacta.';
                } else {
                    $warnings[] = 'El H1 no contiene la keyword principal exacta; se permite por política flexible y debe revisarse editorialmente.';
                }
            }
        }

        $snippet = self::extract_featured_snippet($content, $html);
        if ($snippet === '') {
            $errors[] = 'No se detectó caja editorial.';
        } else {
            $word_count = self::word_count($snippet);
            if ($word_count < $box_min || $word_count > $box_max) {
                $errors[] = 'La caja editorial debe tener entre ' . $box_min . ' y ' . $box_max . ' palabras. Detectadas: ' . $word_count . '.';
            }
            if (preg_match('/https?:\/\//i', $snippet) || preg_match('/\[[^\]]+\]\(https?:\/\//i', $snippet) || preg_match('/<a\s/i', $snippet)) {
                $errors[] = 'La caja editorial no puede contener enlaces.';
            }
            if (preg_match('/\*\*|__|<strong\b/i', $snippet)) {
                $errors[] = 'La caja editorial no puede contener negritas.';
            }
            $snippet_order = self::featured_snippet_order_status($content);
            if (!$snippet_order['ok']) {
                $errors[] = $snippet_order['message'];
            }
        }

        $h3_depth = self::h3_depth_status($html);
        if (!$h3_depth['ok']) {
            $errors[] = $h3_depth['message'];
        } elseif (!empty($h3_depth['message'])) {
            $warnings[] = $h3_depth['message'];
        }

        $h3_count = self::h3_count_status($html);
        if (!$h3_count['ok']) {
            $errors[] = $h3_count['message'];
        }

        if ($official !== '' && !self::is_ideasdi_url($official)) {
            if (!self::html_has_url($html, $official)) {
                $errors[] = 'Falta el enlace externo obligatorio hacia la URL del responsable.';
            } else {
                $anchor = self::anchor_for_url($html, $official);
                if ($keyword !== '' && self::same_plain($anchor, $keyword)) {
                    $errors[] = 'El enlace externo no puede usar la keyword principal como anchor.';
                }
                if ($entity !== '' && $anchor !== '' && self::looks_like_entity_name($anchor) && !self::external_entity_context_ok($official, $anchor, $entity)) {
                    $errors[] = 'El enlace externo parece apuntar a una entidad distinta del responsable configurado.';
                }
                if ($entity !== '' && $anchor !== '' && (self::same_plain($anchor, $entity) || self::entity_near_match($anchor, $entity)) && !self::source_matches_entity($official, $entity) && !self::source_matches_entity($official, $anchor) && !self::external_entity_context_ok($official, $anchor, $entity)) {
                    $errors[] = 'El anchor del enlace externo usa el responsable configurado, pero la URL no parece corresponder a esa entidad.';
                }
            }
        }

        $internal_links = class_exists('IDG_Internal_Links') ? IDG_Internal_Links::normalize($workflow) : [];
        if (!empty($internal_links)) {
            $internal_ok = false;
            foreach ($internal_links as $link) {
                $url = esc_url_raw((string) ($link['url'] ?? ''));
                if ($url !== '' && self::html_has_url($html, $url)) {
                    $internal_ok = true;
                    break;
                }
            }
            if (!$internal_ok) {
                $errors[] = 'Falta el enlace interno calculado desde matriz tag/categoría.';
            }
        }

        foreach ([
            'meta_description' => 'meta description',
            'seo_report' => 'informe SEO interno',
            'social_copy' => 'copy para redes',
            'feedback_notes' => 'retroalimentación Gerizim',
        ] as $key => $label) {
            if (trim((string) ($sections[$key] ?? '')) === '') {
                $errors[] = 'Falta ' . $label . ' en la salida final.';
            }
        }
        // RC1.4.4: el paquete reel queda como apoyo preliminar y no bloquea el borrador.
        $reel_validation = self::validate_reel_package((string) ($sections['reel_package'] ?? ''));
        $warnings = array_merge($warnings, (array) ($reel_validation['warnings'] ?? []), (array) ($reel_validation['errors'] ?? []));
        if (!str_contains($html, '<!-- wp:')) {
            $warnings[] = 'La validación Gutenberg se realiza después de convertir HTML a bloques.';
        }

        return [
            'ok' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'summary' => self::summary_lines($errors, $warnings),
        ];
    }

    public static function validate_gutenberg_blocks(string $post_content): array {
        if (str_contains($post_content, '<!-- wp:')) {
            return ['ok' => true, 'errors' => [], 'warnings' => []];
        }
        return ['ok' => false, 'errors' => ['El borrador no quedó convertido a bloques Gutenberg.'], 'warnings' => []];
    }

    private static function extract_h1(string $content): string {
        if (preg_match('/^\s*#\s+(.+)$/mu', $content, $m)) {
            return trim(wp_strip_all_tags($m[1]));
        }
        return '';
    }

    private static function extract_featured_snippet(string $content, string $html): string {
        if (preg_match('/<p\b[^>]*class=(?:"|\')[^"\']*featured-snippet-box[^"\']*(?:"|\')[^>]*>(.*?)<\/p>/isu', $html, $m)) {
            return trim(wp_strip_all_tags($m[1]));
        }
        if (preg_match('/Caja editorial\s*:?[ \t]*\n+(.+?)(?:\n\s*#{1,3}\s+|\n\s*$)/isu', $content, $m)) {
            return trim((string) $m[1]);
        }
        if (preg_match('/Caja editorial\s*:?\s*(.+)$/imu', $content, $m)) {
            return trim((string) $m[1]);
        }
        return '';
    }

    private static function word_count(string $text): int {
        $plain = trim(wp_strip_all_tags($text));
        preg_match_all('/\b[\p{L}\p{N}][\p{L}\p{N}\-]*\b/u', $plain, $m);
        return count($m[0] ?? []);
    }

    private static function h1_keyword_ok(string $title, string $keyword, array $rules): bool {
        $policy = (string) ($rules['h1_keyword_policy'] ?? 'flexible');
        $title_plain = self::plain_for_match($title);
        $keyword_plain = self::plain_for_match($keyword);
        if ($keyword_plain === '') {
            return true;
        }
        if (str_contains($title_plain, $keyword_plain)) {
            return true;
        }
        if ($policy === 'exact') {
            return false;
        }
        $tokens = preg_split('/\s+/u', $keyword_plain, -1, PREG_SPLIT_NO_EMPTY);
        $tokens = array_values(array_filter((array) $tokens, static function ($token) {
            return mb_strlen((string) $token) >= 4;
        }));
        if (empty($tokens)) {
            return false;
        }
        $matched = 0;
        foreach ($tokens as $token) {
            if (str_contains($title_plain, (string) $token)) {
                $matched++;
            }
        }
        // Flexible: aceptar si aparecen al menos la mitad de los términos significativos.
        return $matched >= max(1, (int) ceil(count($tokens) / 2));
    }

    private static function plain_for_match(string $text): string {
        $text = wp_strip_all_tags($text);
        $text = function_exists('remove_accents') ? remove_accents($text) : $text;
        $text = mb_strtolower((string) $text);
        $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text);
        $text = preg_replace('/\s+/u', ' ', (string) $text);
        return trim((string) $text);
    }


    private static function is_ideasdi_url(string $url): bool {
        $host = mb_strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host);
        return $host === 'ideasdi.com';
    }

    private static function html_has_url(string $html, string $url): bool {
        return $url !== '' && stripos($html, $url) !== false;
    }

    private static function anchor_for_url(string $html, string $url): string {
        if ($url === '') return '';
        $pattern = '/<a\s+[^>]*href=["\']' . preg_quote($url, '/') . '["\'][^>]*>(.*?)<\/a>/isu';
        if (preg_match($pattern, $html, $m)) {
            return trim(wp_strip_all_tags($m[1]));
        }
        return '';
    }



    private static function external_entity_context_ok(string $url, string $anchor, string $entity): bool {
        if (self::same_plain($anchor, $entity) || self::entity_near_match($anchor, $entity)) {
            return true;
        }
        if (self::source_matches_entity($url, $anchor) || self::source_matches_entity($url, $entity)) {
            return true;
        }
        $a = preg_split('/\s+/u', self::plain_for_match($anchor), -1, PREG_SPLIT_NO_EMPTY);
        $b = preg_split('/\s+/u', self::plain_for_match($entity), -1, PREG_SPLIT_NO_EMPTY);
        $a = array_values(array_filter((array) $a, static fn($t) => mb_strlen((string) $t) >= 4));
        $b = array_values(array_filter((array) $b, static fn($t) => mb_strlen((string) $t) >= 4));
        if (!empty(array_intersect($a, $b))) {
            return true;
        }
        $host_path = self::plain_for_match((string) wp_parse_url($url, PHP_URL_HOST) . ' ' . (string) wp_parse_url($url, PHP_URL_PATH));
        foreach (array_merge($a, $b) as $token) {
            if ($token !== '' && str_contains($host_path, $token)) {
                return true;
            }
        }
        return false;
    }

    private static function entity_near_match(string $a, string $b): bool {
        $a = self::plain_for_match($a);
        $b = self::plain_for_match($b);
        if ($a === '' || $b === '') {
            return false;
        }
        if (str_contains($a, $b) || str_contains($b, $a)) {
            return true;
        }
        return levenshtein($a, $b) <= 1;
    }

    private static function same_plain(string $a, string $b): bool {
        $a = mb_strtolower(function_exists('remove_accents') ? remove_accents(trim(wp_strip_all_tags($a))) : trim(wp_strip_all_tags($a)));
        $b = mb_strtolower(function_exists('remove_accents') ? remove_accents(trim(wp_strip_all_tags($b))) : trim(wp_strip_all_tags($b)));
        return $a !== '' && $a === $b;
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

    private static function looks_like_entity_name(string $anchor): bool {
        $anchor = trim($anchor);
        if ($anchor === '') return false;
        return (bool) preg_match('/^[\p{Lu}0-9][\p{L}0-9&+\.\- ]{1,80}$/u', $anchor);
    }

    private static function featured_snippet_order_status(string $content): array {
        $normalized = str_replace(["\r\n", "\r"], "\n", $content);
        if (!preg_match('/^##\s+.+$/mu', $normalized, $h2, PREG_OFFSET_CAPTURE)) {
            return ['ok' => true, 'message' => ''];
        }
        if (!preg_match('/^\s*(?:\*\*)?\s*Caja editorial\s*:?\s*(?:\*\*)?\s*$/imu', $normalized, $box, PREG_OFFSET_CAPTURE)) {
            return ['ok' => true, 'message' => ''];
        }
        $after_h2 = $h2[0][1] + strlen($h2[0][0]);
        $box_pos = $box[0][1];
        if ($box_pos <= $after_h2) {
            return ['ok' => false, 'message' => 'La caja editorial debe ubicarse después de los dos párrafos de introducción, no antes del desarrollo.'];
        }
        $between = trim(substr($normalized, $after_h2, $box_pos - $after_h2));
        $paragraphs = 0;
        foreach (preg_split('/\n+/', $between) as $line) {
            $line = trim((string) $line);
            if ($line === '' || preg_match('/^#{1,6}\s+/u', $line) || preg_match('/^[-*]\s+/u', $line)) {
                continue;
            }
            $paragraphs++;
        }
        if ($paragraphs < 2) {
            return ['ok' => false, 'message' => 'La caja editorial aparece antes de completar los dos párrafos de introducción.'];
        }
        return ['ok' => true, 'message' => ''];
    }

    private static function h3_count_status(string $html): array {
        preg_match_all('/<h3\b[^>]*>.*?<\/h3>/isu', $html, $matches);
        $count = count($matches[0] ?? []);
        $min = 6;
        if ($count < $min) {
            return [
                'ok' => false,
                'message' => 'El artículo debe tener al menos ' . $min . ' secciones H3 de desarrollo. Detectadas: ' . $count . '.',
            ];
        }
        return ['ok' => true, 'message' => ''];
    }

    private static function h3_depth_status(string $html): array {
        if (!preg_match('/<h3\b/i', $html)) {
            return ['ok' => true, 'message' => ''];
        }
        preg_match_all('/<h3\b[^>]*>(.*?)<\/h3>(.*?)(?=<h3\b|$)/isu', $html, $matches, PREG_SET_ORDER);
        $total = count($matches);
        if ($total === 0) {
            return ['ok' => true, 'message' => ''];
        }
        $shallow = [];
        foreach ($matches as $m) {
            $title = trim(wp_strip_all_tags((string) ($m[1] ?? '')));
            $body = (string) ($m[2] ?? '');
            $paragraph_count = preg_match_all('/<p\b(?![^>]*featured-snippet-box)[^>]*>.*?<\/p>/isu', $body, $pm);
            $has_list = preg_match('/<ul\b|<ol\b/iu', $body);
            if (!$has_list && (int) $paragraph_count < 2) {
                $shallow[] = $title !== '' ? $title : 'H3 sin título detectado';
            }
        }
        $shallow_count = count($shallow);
        if ($shallow_count === 0) {
            return ['ok' => true, 'message' => ''];
        }
        $message = 'Hay bloques H3 con desarrollo insuficiente: ' . implode('; ', array_slice($shallow, 0, 4)) . '. Cada H3 debe tener mínimo dos párrafos breves o integrarse al bloque anterior.';
        if ($shallow_count >= max(2, (int) ceil($total * 0.5))) {
            return ['ok' => false, 'message' => $message];
        }
        return ['ok' => true, 'message' => $message];
    }

    private static function validate_reel_package(string $text): array {
        $errors = [];
        $warnings = [];
        $normalized = str_replace(["\r\n", "\r"], "\n", trim($text));
        if ($normalized === '') {
            return ['errors' => ['Falta paquete reel en la salida final.'], 'warnings' => []];
        }
        $rules = class_exists('IDG_Editorial_Rules') ? IDG_Editorial_Rules::get() : [];
        $target_words = (int) ($rules['reel_vo_words'] ?? 14);
        $target_overlays = (int) (($rules['reel_scenes'] ?? 6) * ($rules['reel_overlays_per_scene'] ?? 3));
        $overlay_max = (int) ($rules['reel_overlay_max_chars'] ?? 40);
        $cta = trim((string) ($rules['reel_cta'] ?? 'Conoce más de este proyecto en ideasDi.com'));
        if ($cta !== '' && stripos($normalized, $cta) === false) {
            $errors[] = 'El paquete reel no incluye el CTA fijo obligatorio.';
        }

        preg_match_all('/^\s*(?:[-*]\s*)?VO\s*(?:[—\-]\s*Bloque\s*)?(\d)\s*:\s*(.+)$/imu', $normalized, $vo_matches, PREG_SET_ORDER);
        $vo_by_number = [];
        foreach ($vo_matches as $m) {
            $vo_by_number[(int) $m[1]] = trim((string) $m[2]);
        }
        for ($i = 1; $i <= 6; $i++) {
            if (!isset($vo_by_number[$i]) || $vo_by_number[$i] === '') {
                $errors[] = 'El paquete reel debe incluir VO ' . $i . '.';
            }
        }
        for ($i = 1; $i <= 5; $i++) {
            if (!isset($vo_by_number[$i])) {
                continue;
            }
            $count = self::word_count($vo_by_number[$i]);
            if ($count !== $target_words) {
                $errors[] = 'VO ' . $i . ' debe tener exactamente ' . $target_words . ' palabras. Detectadas: ' . $count . '.';
            }
        }

        $overlay_lines = [];
        foreach (preg_split('/\n+/', $normalized) as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            if (preg_match('/^(?:[-*]\s*)?(?:Overlay|Subt[ií]tulo|Texto en pantalla)(?:\s+\d+(?:[\.\-]\d+)?|\s*[—\-]?\s*\d+)?\s*:\s*(.+)$/iu', $line, $m)) {
                $overlay_lines[] = trim((string) $m[1]);
            }
        }
        $overlay_count = count($overlay_lines);
        if ($overlay_count !== max(1, $target_overlays)) {
            $errors[] = 'El paquete reel debe incluir ' . max(1, $target_overlays) . ' overlays en total. Detectados: ' . $overlay_count . '.';
        }
        foreach ($overlay_lines as $index => $overlay) {
            if (mb_strlen($overlay) > $overlay_max) {
                $errors[] = 'Overlay ' . ($index + 1) . ' supera ' . $overlay_max . ' caracteres.';
            }
        }
        return ['errors' => $errors, 'warnings' => $warnings];
    }

    private static function summary_lines(array $errors, array $warnings): string {
        $lines = [];
        $lines[] = 'Validación real previa al borrador: ' . (empty($errors) ? 'aprobada' : 'bloqueada');
        foreach ($errors as $error) {
            $lines[] = '- ERROR: ' . $error;
        }
        foreach ($warnings as $warning) {
            $lines[] = '- AVISO: ' . $warning;
        }
        return implode("\n", $lines);
    }
}
