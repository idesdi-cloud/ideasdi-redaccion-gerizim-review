<?php
if (!defined('ABSPATH')) {
    exit;
}

final class IDG_Logger {
    private const OPTION = 'idg_logs';
    private const MAX_LOGS = 50;

    public static function log(string $event, string $message, array $context = []): void {
        $logs = get_option(self::OPTION, []);
        if (!is_array($logs)) {
            $logs = [];
        }

        $logs[] = [
            'time' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'event' => sanitize_key($event),
            'message' => sanitize_text_field($message),
            'context' => self::clean_context($context),
        ];

        if (count($logs) > self::MAX_LOGS) {
            $logs = array_slice($logs, -self::MAX_LOGS);
        }

        update_option(self::OPTION, $logs, false);
    }

    public static function get_logs(): array {
        $logs = get_option(self::OPTION, []);
        return is_array($logs) ? array_reverse($logs) : [];
    }

    private static function clean_context(array $context): array {
        unset($context['api_key']);
        return array_map(static function ($value) {
            if (is_scalar($value) || $value === null) {
                return sanitize_text_field((string) $value);
            }
            return wp_json_encode($value);
        }, $context);
    }
}
