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
            'message' => self::clean_scalar($message),
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
        $clean = [];
        foreach ($context as $key => $value) {
            $key_string = strtolower((string) $key);
            if (preg_match('/api[_-]?key|token|authorization|secret|password/', $key_string)) {
                continue;
            }
            if (is_array($value)) {
                $clean[$key] = self::clean_context($value);
            } elseif (is_scalar($value) || $value === null) {
                $clean[$key] = self::clean_scalar((string) $value);
            } else {
                $clean[$key] = self::clean_scalar(wp_json_encode($value));
            }
        }
        return $clean;
    }

    private static function clean_scalar(string $value): string {
        $value = sanitize_text_field($value);
        if (class_exists('IDG_Traceability')) {
            $token = IDG_Traceability::config_string('IDG_RADAR_TRACEABILITY_TOKEN');
            if ($token !== '') {
                $value = str_replace($token, '[redacted]', $value);
            }
        }
        return function_exists('mb_substr') ? mb_substr($value, 0, 1000) : substr($value, 0, 1000);
    }
}
