<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Transformaciones deterministas heredadas de la salida del modelo.
 */
final class IDG_Workflow_Output_Parser {
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
        if (preg_match('/^\s*(?:#{1,6}\s*)?(?:\*\*)?\s*(?:ARTÍCULO FINAL|ARTICULO FINAL|META DESCRIPTION|INFORME SEO INTERNO|COPY PARA REDES|PAQUETE REEL)\s*(?:\*\*)?\s*:?\s*$/imu', $tail, $next, PREG_OFFSET_CAPTURE)) {
            $tail = trim(substr($tail, 0, $next[0][1]));
        }
        return sanitize_textarea_field($tail);
    }
}
