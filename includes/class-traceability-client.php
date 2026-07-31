<?php
if (!defined('ABSPATH')) {
    exit;
}

final class IDG_Traceability_Client {
    public function send(array $payload): array {
        $url = IDG_Traceability::config_string('IDG_RADAR_TRACEABILITY_URL');
        $token = IDG_Traceability::config_string('IDG_RADAR_TRACEABILITY_TOKEN');
        $url_validation = IDG_Traceability::validate_radar_url($url);

        if ($url === '' || $token === '') {
            return self::failure(false, 0, 'traceability_not_configured', 'La URL o el token de trazabilidad no están configurados.');
        }
        if (empty($url_validation['valid'])) {
            return self::failure(false, 0, (string) ($url_validation['reason'] ?? 'invalid_traceability_url'), 'La URL de trazabilidad no es válida o no cumple la política HTTPS.');
        }

        $sent_key = sanitize_text_field((string) ($payload['idempotency_key'] ?? ''));
        if ($sent_key === '') {
            return self::failure(false, 0, 'invalid_event_identity', 'El evento no incluye idempotency_key.');
        }

        $response = wp_remote_post($url, [
            'timeout' => 20,
            'redirection' => 0,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Radar-Traceability-Token' => $token,
            ],
            'body' => wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'data_format' => 'body',
        ]);

        if (is_wp_error($response)) {
            return self::failure(true, 0, 'network_error', self::safe_error($response->get_error_message(), $token));
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);
        $result = '';
        $response_key = '';
        $compatibility_field = '';
        if (is_array($decoded)) {
            $result_value = $decoded['result'] ?? '';
            if (is_string($result_value)) {
                $result = $result_value;
            }
            if ($result === '') {
                $code_value = $decoded['code'] ?? '';
                if (is_string($code_value)) {
                    $result = $code_value;
                    $compatibility_field = $result !== '' ? 'code' : '';
                }
            }
            $key_value = $decoded['idempotency_key'] ?? '';
            if (is_string($key_value)) {
                $response_key = $key_value;
            }
        }

        $is_recorded = $status === 201 && $result === 'traceability_event_recorded';
        $is_duplicate = $status === 200 && $result === 'traceability_event_already_recorded';
        if ($is_recorded || $is_duplicate) {
            if ($response_key === '' || !hash_equals($sent_key, $response_key)) {
                return self::failure(false, $status, 'response_idempotency_key_mismatch', 'Radar devolvió una idempotency_key ausente o diferente.');
            }
            return [
                'success' => true,
                'retry' => false,
                'http_status' => $status,
                'code' => $result,
                'message' => $is_recorded ? 'Evento registrado por Radar.' : 'Evento ya registrado de forma idempotente por Radar.',
                'response_field' => $compatibility_field !== '' ? $compatibility_field : 'result',
                'response_idempotency_key' => $response_key,
            ];
        }

        if ($status === 429 || ($status >= 500 && $status <= 599)) {
            return self::failure(true, $status, $result !== '' ? $result : 'temporary_http_error', self::safe_error('Radar respondió HTTP ' . $status . '.', $token));
        }

        $terminal = in_array($status, [400, 401, 403, 409], true);
        return self::failure(!$terminal && $status === 0, $status, $result !== '' ? $result : 'unexpected_response', self::safe_error('Respuesta no aceptada de Radar (HTTP ' . $status . ').', $token));
    }

    private static function failure(bool $retry, int $status, string $code, string $message): array {
        return [
            'success' => false,
            'retry' => $retry,
            'http_status' => $status,
            'code' => sanitize_key($code),
            'message' => $message,
        ];
    }

    private static function safe_error(string $message, string $token): string {
        $message = trim(wp_strip_all_tags($message));
        if ($token !== '') {
            $message = str_replace($token, '[redacted]', $message);
        }
        return function_exists('mb_substr') ? mb_substr($message, 0, 500) : substr($message, 0, 500);
    }
}
