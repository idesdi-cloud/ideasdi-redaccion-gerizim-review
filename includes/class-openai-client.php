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

        $response = wp_remote_post('https://api.openai.com/v1/responses', [
            'timeout' => isset($options['timeout']) ? (int) $options['timeout'] : 90,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($body),
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
                'content' => '',
            ];
        }

        $status = wp_remote_retrieve_response_code($response);
        $raw = wp_remote_retrieve_body($response);
        $json = json_decode($raw, true);

        if ($status < 200 || $status >= 300) {
            $message = $json['error']['message'] ?? 'Error no identificado desde OpenAI API.';
            return [
                'success' => false,
                'message' => $message,
                'content' => '',
                'raw' => $raw,
            ];
        }

        $text = self::extract_text($json);
        if ($text === '') {
            return [
                'success' => false,
                'message' => 'La respuesta de OpenAI no incluyó texto utilizable.',
                'content' => '',
                'raw' => $raw,
            ];
        }

        return [
            'success' => true,
            'message' => 'OK',
            'content' => $text,
            'usage' => $json['usage'] ?? [],
            'model' => $this->model,
            'sources' => self::extract_sources($json),
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
