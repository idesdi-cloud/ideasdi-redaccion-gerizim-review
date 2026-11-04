<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Transformaciones deterministas heredadas de la salida del modelo.
 */
final class IDG_Workflow_Output_Parser {
    /**
     * Makes the editorial-box syntax canonical without changing its prose.
     * This deliberately has no opinion on validity: Final Guard still decides
     * word count, placement, links and the single-paragraph contract.
     */
    public static function normalize_editorial_box_markdown(string $content, bool $seo_output = false): string {
        if ($seo_output) {
            $bounds = self::article_final_bounds($content);
            if ($bounds === null) {
                return $content;
            }
            [$start, $length] = $bounds;
            return substr($content, 0, $start)
                . self::normalize_editorial_box_fragment(substr($content, $start, $length))
                . substr($content, $start + $length);
        }
        return self::normalize_editorial_box_fragment($content);
    }

    /**
     * Restores a link only when the original anchor has one unambiguous,
     * ordinary-paragraph occurrence in the candidate. It never invents text,
     * URLs or a placement in the editorial box.
     *
     * @return array{content:string,ok:bool,errors:array<int,string>,restored:array<int,string>}
     */
    public static function preserve_approved_article_links(string $source, string $candidate, array $workflow, bool $seo_output = false): array {
        $source_article = self::article_fragment($source, false);
        $candidate_article = self::article_fragment($candidate, $seo_output);
        $required = self::approved_links_in_source($source_article, $workflow);
        $errors = [];
        $restored = [];
        foreach ($required as $link) {
            if (self::article_has_url($candidate_article, $link['url'])) {
                continue;
            }
            $replacement = self::restore_link_in_safe_paragraph($candidate_article, $link['anchor'], $link['url']);
            if ($replacement === null) {
                $errors[] = 'Se perdió un enlace aprobado y no existe un contexto inequívoco para restaurarlo: ' . $link['url'] . '. Revisión humana requerida.';
                continue;
            }
            $candidate_article = $replacement;
            $restored[] = $link['url'];
        }
        if ($seo_output) {
            $bounds = self::article_final_bounds($candidate);
            if ($bounds !== null) {
                [$start, $length] = $bounds;
                $candidate = substr($candidate, 0, $start) . $candidate_article . substr($candidate, $start + $length);
            }
        } else {
            $candidate = $candidate_article;
        }
        return ['content' => $candidate, 'ok' => empty($errors), 'errors' => $errors, 'restored' => $restored];
    }

    private static function normalize_editorial_box_fragment(string $content): string {
        $lines = preg_split('/\n/', str_replace(["\r\n", "\r"], "\n", $content));
        if (!is_array($lines)) return $content;
        $in_box = false;
        foreach ($lines as $index => $line) {
            if (preg_match('/^\s*\*\*\s*Caja editorial\s*:?\s*\*\*[ \t]*$/iu', (string) $line)) {
                $lines[$index] = 'Caja editorial';
                $in_box = true;
                continue;
            }
            if (!$in_box) continue;
            $trimmed = trim((string) $line);
            if ($trimmed === '' || preg_match('/^#{1,6}\s+/u', $trimmed) || preg_match('/^(?:\*\*\s*)?Caja editorial\b/iu', $trimmed)) {
                $in_box = false;
                continue;
            }
            // Only typographic bold is removed; links, text and paragraph
            // boundaries remain for Final Guard to validate.
            $lines[$index] = preg_replace('/\*\*(.+?)\*\*|__(.+?)__|<\/?(?:strong|b)\b[^>]*>/iu', '$1$2', (string) $line);
        }
        return implode("\n", $lines);
    }

    private static function article_fragment(string $content, bool $seo_output): string {
        if (!$seo_output) return $content;
        $bounds = self::article_final_bounds($content);
        return $bounds === null ? $content : substr($content, $bounds[0], $bounds[1]);
    }

    /** @return array{0:int,1:int}|null */
    private static function article_final_bounds(string $content): ?array {
        if (!preg_match('/^\s*(?:#{1,6}\s*)?(?:\*\*\s*)?(?:ARTÍCULO FINAL|ARTICULO FINAL)\s*(?:\*\*)?\s*:?\s*$/imu', $content, $marker, PREG_OFFSET_CAPTURE)) return null;
        $start = (int) $marker[0][1] + strlen((string) $marker[0][0]);
        $tail = substr($content, $start);
        $length = strlen($tail);
        if (preg_match('/^\s*(?:#{1,6}\s*)?(?:\*\*\s*)?(?:TÍTULO SEO|TITULO SEO|META DESCRIPTION|INFORME SEO INTERNO|COPY PARA REDES|PAQUETE REEL|RETROALIMENTACIÓN GERIZIM)\s*(?:\*\*)?\s*:?\s*$/imu', $tail, $next, PREG_OFFSET_CAPTURE)) {
            $length = (int) $next[0][1];
        }
        return [$start, $length];
    }

    /** @return array<int,array{url:string,anchor:string}> */
    private static function approved_links_in_source(string $source, array $workflow): array {
        $allowed = [];
        $official = trim((string) ($workflow['responsible_official_url'] ?? $workflow['official_source'] ?? ''));
        if ($official !== '') $allowed[] = $official;
        if (class_exists('IDG_Internal_Links')) {
            foreach ((array) IDG_Internal_Links::normalize($workflow) as $link) {
                if ((string) ($link['source_type'] ?? '') === 'tag' && !empty($link['url'])) $allowed[] = (string) $link['url'];
            }
        }
        $found = [];
        if (!preg_match_all('/\[([^\]\n]+)\]\((https?:\/\/[^\s)]+)\)/iu', $source, $matches, PREG_SET_ORDER)) return $found;
        foreach ($matches as $match) {
            $url = (string) $match[2];
            foreach ($allowed as $approved) {
                if (self::urls_equal($url, $approved)) {
                    $key = self::url_key($url);
                    $found[$key] = ['url' => $url, 'anchor' => trim((string) $match[1])];
                }
            }
        }
        return array_values($found);
    }

    private static function article_has_url(string $article, string $url): bool {
        return (bool) preg_match_all('/\[[^\]]+\]\((https?:\/\/[^\s)]+)\)/iu', $article, $matches)
            && (bool) array_filter((array) $matches[1], static fn($candidate): bool => self::urls_equal((string) $candidate, $url));
    }

    private static function restore_link_in_safe_paragraph(string $article, string $anchor, string $url): ?string {
        if ($anchor === '') return null;
        $lines = preg_split('/\n/', str_replace(["\r\n", "\r"], "\n", $article));
        if (!is_array($lines)) return null;
        $matches = [];
        $in_box = false;
        foreach ($lines as $index => $line) {
            $trimmed = trim((string) $line);
            if (preg_match('/^(?:\*\*\s*)?Caja editorial\b/iu', $trimmed)) { $in_box = true; continue; }
            if ($in_box && ($trimmed === '' || preg_match('/^#{1,6}\s+/u', $trimmed))) $in_box = false;
            if ($in_box || $trimmed === '' || preg_match('/^#{1,6}\s+/u', $trimmed) || str_contains((string) $line, '](')) continue;
            if (substr_count((string) $line, $anchor) === 1) $matches[] = $index;
        }
        if (count($matches) !== 1) return null;
        $index = $matches[0];
        $lines[$index] = preg_replace('/' . preg_quote($anchor, '/') . '/u', '[' . $anchor . '](' . $url . ')', (string) $lines[$index], 1);
        return implode("\n", $lines);
    }

    private static function urls_equal(string $left, string $right): bool {
        $left_key = self::url_key($left);
        $right_key = self::url_key($right);
        return $left_key !== '' && $left_key === $right_key;
    }

    /** Compare only scheme and host case-insensitively; all URL content remains exact. */
    private static function url_key(string $url): string {
        $url = trim($url);
        if ($url === '' || preg_match('/[\x00-\x20\x7f]/', $url)) return '';
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) return '';
        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower((string) $parts['host']);
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') return '';
        $key = $scheme . '://';
        if (array_key_exists('user', $parts)) {
            $key .= (string) $parts['user'];
            if (array_key_exists('pass', $parts)) $key .= ':' . (string) $parts['pass'];
            $key .= '@';
        }
        $key .= $host;
        if (array_key_exists('port', $parts)) $key .= ':' . (string) $parts['port'];
        $key .= (string) ($parts['path'] ?? '');
        if (array_key_exists('query', $parts)) $key .= '?' . (string) $parts['query'];
        if (array_key_exists('fragment', $parts)) $key .= '#' . (string) $parts['fragment'];
        return $key;
    }
    public static function sanitize(string $content): string {
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

    public static function parse_editorial(string $content): array {
        $normalized = str_replace(["\r\n", "\r"], "\n", $content);
        $markers = [
            'article' => '(?:ARTÍCULO REVISADO|ARTICULO REVISADO)',
            'diagnosis' => '(?:DIAGNÓSTICO EDITORIAL INTERNO|DIAGNOSTICO EDITORIAL INTERNO)',
            'notes' => '(?:NOTAS EDITORIALES INTERNAS)',
        ];
        $positions = [];
        foreach ($markers as $key => $pattern) {
            if (preg_match('/^\s*(?:#{1,6}\s*)?(?:\*\*)?\s*' . $pattern . '\s*(?:\*\*)?\s*:?\s*$/imu', $normalized, $m, PREG_OFFSET_CAPTURE)) {
                $positions[$key] = [
                    'start' => (int) $m[0][1],
                    'body' => (int) $m[0][1] + strlen((string) $m[0][0]),
                ];
            }
        }
        if (!isset($positions['article'])) {
            return ['article' => trim($normalized), 'diagnosis' => '', 'notes' => ''];
        }
        uasort($positions, static fn($a, $b) => $a['start'] <=> $b['start']);
        $ordered = array_keys($positions);
        $out = ['article' => '', 'diagnosis' => '', 'notes' => ''];
        foreach ($ordered as $index => $key) {
            $start = $positions[$key]['body'];
            $end = isset($ordered[$index + 1]) ? $positions[$ordered[$index + 1]]['start'] : strlen($normalized);
            $out[$key] = trim(substr($normalized, $start, $end - $start));
        }
        return $out;
    }

    public static function extract_feedback_notes(string $content): string {
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        if (!preg_match('/^\s*(?:#{1,6}\s*)?(?:\*\*)?\s*RETROALIMENTACIÓN GERIZIM\s*(?:\*\*)?\s*:?\s*$/imu', $content, $m, PREG_OFFSET_CAPTURE)) {
            return '';
        }
        $start = $m[0][1] + strlen($m[0][0]);
        $tail = trim(substr($content, $start));
        if ($tail === '') {
            return '';
        }
        if (preg_match('/^\s*(?:#{1,6}\s*)?(?:\*\*)?\s*(?:ARTÍCULO FINAL|ARTICULO FINAL|TÍTULO SEO|TITULO SEO|META DESCRIPTION|INFORME SEO INTERNO|COPY PARA REDES|PAQUETE REEL)\s*(?:\*\*)?\s*:?\s*$/imu', $tail, $next, PREG_OFFSET_CAPTURE)) {
            $tail = trim(substr($tail, 0, $next[0][1]));
        }
        return sanitize_textarea_field($tail);
    }
}
