<?php
if (!defined('ABSPATH')) {
    exit;
}

final class IDG_OpenAI_Client {
    private string $api_key;
    private string $model;

    public function __construct(?string $api_key = null, ?string $model = null) {
        $settings = get_option(IDG_OPTION_KEY, []);
        $this->api_key = $api_key ?: (string) ($settings['api_key'] ?? '');
        $this->model = $model ?: (string) ($settings['model'] ?? 'gpt-5.4-mini');
    }

    public function complete(string $prompt, array $options = []): array {
        if (empty($this->api_key)) {
            return [
                'success' => false,
                'message' => 'Falta configurar la API key de OpenAI.',
                'content' => '',
            ];
        }

        $body = [
            'model' => $this->model,
            'input' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => $prompt,
                        ],
                    ],
                ],
            ],
        ];

        if (!empty($options['tools']) && is_array($options['tools'])) {
            $body['tools'] = $options['tools'];
        }
        if (!empty($options['tool_choice'])) {
            $body['tool_choice'] = $options['tool_choice'];
        }

        $client_request_id = self::client_request_id();
        $response = wp_remote_post('https://api.openai.com/v1/responses', [
            'timeout' => isset($options['timeout']) ? (int) $options['timeout'] : 90,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
                'X-Client-Request-Id' => $client_request_id,
            ],
            'body' => wp_json_encode($body),
        ]);

        if (is_wp_error($response)) {
            $transport_message = self::safe_transport_message($response->get_error_message());
            return [
                'success' => false,
                'message' => 'Error de transporte al comunicarse con OpenAI: ' . $transport_message . '. Referencia cliente: ' . $client_request_id . '.',
                'content' => '',
                'diagnostic' => [
                    'client_request_id' => $client_request_id,
                    'transport_error' => $transport_message,
                ],
            ];
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $raw = (string) wp_remote_retrieve_body($response);
        $json = json_decode($raw, true);
        $json_valid = json_last_error() === JSON_ERROR_NONE && is_array($json);
        $text = $json_valid ? self::extract_text($json) : '';
        $remote_status = $json_valid && isset($json['status']) && is_string($json['status']) ? $json['status'] : '';
        $response_object = $json_valid && isset($json['object']) && is_string($json['object']) ? $json['object'] : '';
        $response_id = $json_valid && isset($json['id']) && is_string($json['id']) ? sanitize_text_field($json['id']) : '';
        $openai_request_id = self::response_header($response, 'x-request-id');
        $diagnostic = self::diagnostic($response, $status, $raw, $json_valid ? $json : null, $client_request_id, $response_id, $remote_status);

        /*
         * Recuperación mínima de una anomalía de transporte/estado: si el cuerpo
         * recibido es inequívocamente una Response completada y contiene texto
         * utilizable, no se descarta por un status HTTP anómalo. No crea otra
         * llamada ni altera la secuencia síncrona histórica del workflow.
         */
        if ($text !== '' && $remote_status === 'completed' && $response_object === 'response' && $response_id !== '') {
            if ($status < 200 || $status >= 300) {
                $diagnostic['recovered_http_anomaly'] = true;
            }
            return [
                'success' => true,
                'message' => 'OK',
                'content' => $text,
                'usage' => $json['usage'] ?? [],
                'model' => $this->model,
                'sources' => self::extract_sources($json),
                'response_id' => $response_id,
                'diagnostic' => $diagnostic,
            ];
        }

        if ($status < 200 || $status >= 300) {
            $message = '';
            if ($json_valid && isset($json['error']['message']) && is_string($json['error']['message'])) {
                $message = trim($json['error']['message']);
            }
            if ($message === '') {
                $message = 'OpenAI API devolvió HTTP ' . ($status > 0 ? $status : 'desconocido') . ' sin un mensaje de error JSON utilizable.';
            } else {
                $message .= ' HTTP ' . ($status > 0 ? $status : 'desconocido') . '.';
            }
            $message .= self::reference_suffix($openai_request_id, $client_request_id);
            return [
                'success' => false,
                'message' => $message,
                'content' => '',
                'raw' => $raw,
                'diagnostic' => $diagnostic,
            ];
        }

        if ($text === '') {
            return [
                'success' => false,
                'message' => 'La respuesta de OpenAI no incluyó texto utilizable. HTTP ' . ($status > 0 ? $status : 'desconocido') . '.' . self::reference_suffix($openai_request_id, $client_request_id),
                'content' => '',
                'raw' => $raw,
                'diagnostic' => $diagnostic,
            ];
        }

        return [
            'success' => true,
            'message' => 'OK',
            'content' => $text,
            'usage' => $json['usage'] ?? [],
            'model' => $this->model,
            'sources' => self::extract_sources($json),
            'response_id' => $response_id,
            'diagnostic' => $diagnostic,
        ];
    }

    public function complete_with_web_search(string $prompt, string $intensity = 'media'): array {
        $context = 'medium';
        $timeout = 50;
        if ($intensity === 'baja') {
            $context = 'low';
            $timeout = 35;
        } elseif ($intensity === 'alta') {
            $context = 'high';
            $timeout = 75;
        }

        $options = [
            'tools' => [
                [
                    'type' => 'web_search',
                    'search_context_size' => $context,
                ],
            ],
            'tool_choice' => 'required',
            'timeout' => $timeout,
        ];
        $result = $this->complete($prompt, $options);
        if ($result['success']) {
            return $result;
        }

        // Fallback para modelos/cuentas que todavía usen la variante legacy del tool.
        $legacy = $options;
        $legacy['tools'] = [
            [
                'type' => 'web_search_preview',
                'search_context_size' => $context,
            ],
        ];
        $legacy_result = $this->complete($prompt, $legacy);
        if ($legacy_result['success']) {
            return $legacy_result;
        }

        return $result;
    }

    private static function client_request_id(): string {
        if (function_exists('wp_generate_uuid4')) {
            return 'idg-' . strtolower(str_replace('-', '', wp_generate_uuid4()));
        }
        return 'idg-' . bin2hex(random_bytes(16));
    }

    private static function reference_suffix(string $openai_request_id, string $client_request_id): string {
        if ($openai_request_id !== '') {
            return ' Referencia OpenAI: ' . $openai_request_id . '. Referencia cliente: ' . $client_request_id . '.';
        }
        return ' Referencia cliente: ' . $client_request_id . '.';
    }

    private static function response_header($response, string $name): string {
        if (!function_exists('wp_remote_retrieve_header')) {
            return '';
        }
        $value = wp_remote_retrieve_header($response, $name);
        if (is_array($value)) {
            $value = implode(',', $value);
        }
        return sanitize_text_field((string) $value);
    }

    private static function diagnostic($response, int $status, string $raw, ?array $json, string $client_request_id, string $response_id, string $remote_status): array {
        $diagnostic = [
            'http_status' => $status,
            'client_request_id' => $client_request_id,
        ];
        $openai_request_id = self::response_header($response, 'x-request-id');
        $content_type = self::response_header($response, 'content-type');
        if ($openai_request_id !== '') {
            $diagnostic['openai_request_id'] = $openai_request_id;
        }
        if ($content_type !== '') {
            $diagnostic['content_type'] = $content_type;
        }
        if ($response_id !== '') {
            $diagnostic['response_id'] = $response_id;
        }
        if ($remote_status !== '') {
            $diagnostic['remote_status'] = sanitize_key($remote_status);
        }
        if ($json !== null && is_array($json['error'] ?? null)) {
            foreach (['type', 'code', 'param'] as $key) {
                if (isset($json['error'][$key]) && is_scalar($json['error'][$key])) {
                    $diagnostic['error_' . $key] = sanitize_text_field((string) $json['error'][$key]);
                }
            }
        } elseif ($json === null) {
            $diagnostic['body_bytes'] = strlen($raw);
            $diagnostic['body_sha256'] = hash('sha256', $raw);
            $diagnostic['json_valid'] = false;
        }
        return $diagnostic;
    }

    private static function safe_transport_message(string $message): string {
        $message = preg_replace('/Bearer\s+[^\s]+/i', 'Bearer [redacted]', $message);
        return sanitize_text_field(substr((string) $message, 0, 240));
    }

    private static function extract_sources(array $json): array {
        $sources = [];
        $seen = [];
        $walker = function ($node) use (&$walker, &$sources, &$seen) {
            if (!is_array($node)) {
                return;
            }
            $url = '';
            $title = '';
            if (isset($node['url']) && is_string($node['url']) && preg_match('/^https?:\/\//i', $node['url'])) {
                $url = $node['url'];
                $title = isset($node['title']) && is_string($node['title']) ? $node['title'] : '';
            } elseif (($node['type'] ?? '') === 'url_citation' && !empty($node['url'])) {
                $url = (string) $node['url'];
                $title = isset($node['title']) ? (string) $node['title'] : '';
            }
            if ($url !== '') {
                $key = strtolower($url);
                if (!isset($seen[$key])) {
                    $seen[$key] = true;
                    $sources[] = [
                        'title' => sanitize_text_field($title !== '' ? $title : 'Fuente web'),
                        'url' => esc_url_raw($url),
                    ];
                }
            }
            foreach ($node as $value) {
                if (is_array($value)) {
                    $walker($value);
                }
            }
        };
        $walker($json);
        return $sources;
    }

    private static function extract_text(array $json): string {
        if (!empty($json['output_text']) && is_string($json['output_text'])) {
            return trim($json['output_text']);
        }

        $parts = [];
        foreach (($json['output'] ?? []) as $item) {
            foreach (($item['content'] ?? []) as $content) {
                if (($content['type'] ?? '') === 'output_text' && isset($content['text'])) {
                    $parts[] = $content['text'];
                }
            }
        }

        return trim(implode("\n", $parts));
    }
}
