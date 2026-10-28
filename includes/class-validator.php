<?php
if (!defined('ABSPATH')) {
    exit;
}

final class IDG_Validator {
    public static function summarize(string $content, string $keyword = ''): array {
        $plain = wp_strip_all_tags($content);
        $first_line = self::first_non_empty_line($plain);
        $warnings = [];

        if (mb_strlen($first_line) > 68) {
            $warnings[] = 'El posible H1 supera 68 caracteres.';
        }
        $opening = self::opening_excerpt($content);
        if ($keyword !== '' && !self::keyword_flexible_match($opening, $keyword)) {
            $warnings[] = 'La keyword principal o una variante reconocible no aparece cerca de la apertura; es un aviso editorial, no un bloqueo.';
        }
        if (stripos($content, 'Fuente oficial:') !== false) {
            $warnings[] = 'Evitar el rótulo visible “Fuente oficial:”.';
        }
        if (preg_match('/<h3[^>]*>\s*Conclusi[oó]n\s*<\/h3>/i', $content)) {
            $warnings[] = 'Evitar “Conclusión” como H3 final.';
        }

        return $warnings;
    }


    private static function keyword_flexible_match(string $content, string $keyword): bool {
        $text = self::plain_for_match($content);
        $kw = self::plain_for_match($keyword);
        if ($kw === '' || str_contains($text, $kw)) {
            return true;
        }
        $tokens = preg_split('/\s+/u', $kw, -1, PREG_SPLIT_NO_EMPTY);
        $tokens = array_values(array_filter((array) $tokens, static fn($token) => mb_strlen((string) $token) >= 4));
        if (empty($tokens)) {
            return false;
        }
        $matched = 0;
        foreach ($tokens as $token) {
            if (str_contains($text, (string) $token)) {
                $matched++;
            }
        }
        return $matched >= max(1, (int) ceil(count($tokens) / 2));
    }

    private static function opening_excerpt(string $content): string {
        $plain = wp_strip_all_tags(str_replace(["\r\n", "\r"], "\n", $content));
        $lines = preg_split('/\n/u', $plain);
        $opening = [];
        foreach ((array) $lines as $line) {
            $line = trim((string) $line);
            if ($line === '' || preg_match('/^#{1,6}\s+/u', $line) || preg_match('/^Caja editorial\b/iu', $line)) {
                continue;
            }
            $opening[] = $line;
            if (count($opening) >= 3) {
                break;
            }
        }
        return implode("\n", $opening);
    }

    private static function plain_for_match(string $text): string {
        $text = wp_strip_all_tags($text);
        $text = function_exists('remove_accents') ? remove_accents($text) : $text;
        $text = mb_strtolower((string) $text);
        $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text);
        $text = preg_replace('/\s+/u', ' ', (string) $text);
        return trim((string) $text);
    }

    private static function first_non_empty_line(string $text): string {
        foreach (preg_split('/\R/', $text) as $line) {
            $line = trim($line);
            if ($line !== '') {
                return $line;
            }
        }
        return '';
    }
}
