<?php
if (!defined('ABSPATH')) {
    exit;
}

final class IDG_Sanitizer {
    public static function textarea(string $value): string {
        return wp_kses_post(wp_unslash($value));
    }

    public static function text(string $value): string {
        return sanitize_text_field(wp_unslash($value));
    }

    public static function url(string $value): string {
        return esc_url_raw(wp_unslash($value));
    }

    public static function int_array($value): array {
        if (!is_array($value)) {
            return [];
        }
        return array_values(array_filter(array_map('absint', wp_unslash($value))));
    }
}
