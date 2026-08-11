<?php
/**
 * Regresión focal: cliente OpenAI síncrono de RC1.6.3 + diagnóstico seguro.
 * No background, polling, GET, scheduler, reentrada ni llamadas extra.
 */
define('ABSPATH', __DIR__ . '/');
define('IDG_OPTION_KEY', 'idg_settings');

final class WP_Error {
    public function __construct(private string $message) {}
    public function get_error_message(): string { return $this->message; }
}

$GLOBALS['responses'] = [];
$GLOBALS['post_count'] = 0;
$GLOBALS['get_count'] = 0;
$GLOBALS['posts'] = [];
$GLOBALS['uuid'] = 0;

function get_option($key, $default = []) { return ['api_key' => 'sk-test-secret', 'model' => 'test-model']; }
function wp_generate_uuid4() { $GLOBALS['uuid']++; return sprintf('00000000-0000-4000-8000-%012d', $GLOBALS['uuid']); }
function sanitize_text_field($value) { return preg_replace('/[\r\n\t]+/', ' ', (string) $value); }
function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_\-]/i', '', (string) $value)); }
function esc_url_raw($value) { return (string) $value; }
function wp_json_encode($value) { return json_encode($value); }
function is_wp_error($value) { return $value instanceof WP_Error; }
function wp_remote_retrieve_response_code($response) { return $response['status'] ?? 0; }
function wp_remote_retrieve_body($response) { return $response['body'] ?? ''; }
function wp_remote_retrieve_header($response, $name) {
    foreach (($response['headers'] ?? []) as $key => $value) {
        if (strtolower((string) $key) === strtolower($name)) return $value;
    }
    return '';
}
function wp_remote_post($url, $args) {
    $GLOBALS['post_count']++;
    $GLOBALS['posts'][] = [$url, $args];
    return array_shift($GLOBALS['responses']);
}
function wp_remote_get(...$args) { $GLOBALS['get_count']++; throw new RuntimeException('GET no permitido en cliente síncrono'); }

require_once dirname(__DIR__) . '/includes/class-openai-client.php';

function ok(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    echo "PASS: {$message}\n";
}
function reset_case(array $responses): void {
    $GLOBALS['responses'] = $responses;
    $GLOBALS['post_count'] = 0;
    $GLOBALS['get_count'] = 0;
    $GLOBALS['posts'] = [];
}
function completed_body(string $text = 'ARTÍCULO FINAL'): array {
    return [
        'id' => 'resp_completed_123',
        'object' => 'response',
        'status' => 'completed',
        'output' => [[
            'type' => 'message',
            'role' => 'assistant',
            'content' => [[
                'type' => 'output_text',
                'text' => $text,
            ]],
        ]],
        'usage' => ['input_tokens' => 100, 'output_tokens' => 20, 'total_tokens' => 120],
    ];
}
function response(int $status, $body, array $headers = []): array {
    return [
        'status' => $status,
        'body' => is_string($body) ? $body : json_encode($body),
        'headers' => $headers,
    ];
}

// 1. Camino normal de RC1.6.3: POST síncrono, una llamada, sin background.
reset_case([response(200, completed_body(), ['x-request-id' => 'req-normal', 'content-type' => 'application/json'])]);
$client = new IDG_OpenAI_Client();
$result = $client->complete('prompt original');
ok($result['success'] === true, '200 completed conserva éxito síncrono');
ok($result['content'] === 'ARTÍCULO FINAL', '200 completed conserva contenido');
ok($GLOBALS['post_count'] === 1, '200 completed usa exactamente un POST');
ok($GLOBALS['get_count'] === 0, '200 completed no usa GET');
$sent_body = json_decode($GLOBALS['posts'][0][1]['body'], true);
ok(!array_key_exists('background', $sent_body), 'request no introduce background');
ok(isset($GLOBALS['posts'][0][1]['headers']['X-Client-Request-Id']), 'request añade correlación X-Client-Request-Id');

// 2. Incidente objetivo: status HTTP anómalo, pero el cuerpo recibido es una Response completed utilizable.
reset_case([response(502, completed_body('RECUPERADO'), ['x-request-id' => 'req-recovered', 'content-type' => 'application/json'])]);
$client = new IDG_OpenAI_Client();
$result = $client->complete('prompt original');
ok($result['success'] === true, 'completed utilizable no se descarta por HTTP anómalo');
ok($result['content'] === 'RECUPERADO', 'completed anómalo conserva texto ya generado');
ok(($result['diagnostic']['recovered_http_anomaly'] ?? false) === true, 'anomalía HTTP recuperada queda diagnosticada');
ok(($result['response_id'] ?? '') === 'resp_completed_123', 'response_id queda disponible para trazabilidad');
ok($GLOBALS['post_count'] === 1 && $GLOBALS['get_count'] === 0, 'recuperación no crea llamadas adicionales');

// 3. 429 JSON: conserva mensaje real, status y referencias.
reset_case([response(429, [
    'error' => ['message' => 'Rate limit alcanzado', 'type' => 'rate_limit_error', 'code' => 'rate_limit_exceeded'],
], ['x-request-id' => 'req-429', 'content-type' => 'application/json'])]);
$client = new IDG_OpenAI_Client();
$result = $client->complete('prompt original');
ok($result['success'] === false, '429 sigue siendo fallo');
ok(str_contains($result['message'], 'Rate limit alcanzado HTTP 429.'), '429 muestra mensaje y status');
ok(str_contains($result['message'], 'Referencia OpenAI: req-429.'), '429 muestra x-request-id');
ok(($result['diagnostic']['error_type'] ?? '') === 'rate_limit_error', '429 conserva error_type seguro');
ok($GLOBALS['post_count'] === 1 && $GLOBALS['get_count'] === 0, '429 no reintenta ni agrega llamadas');

// 4. 502 HTML/no JSON: deja de ocultarse tras el mensaje genérico.
$html = '<html><body>bad gateway</body></html>';
reset_case([response(502, $html, ['content-type' => 'text/html'])]);
$client = new IDG_OpenAI_Client();
$result = $client->complete('prompt original');
ok($result['success'] === false, '502 HTML sigue siendo fallo');
ok(str_contains($result['message'], 'OpenAI API devolvió HTTP 502 sin un mensaje de error JSON utilizable.'), '502 HTML informa status exacto');
ok(str_contains($result['message'], 'Referencia cliente: idg-'), '502 HTML incluye referencia cliente');
ok(($result['diagnostic']['body_bytes'] ?? 0) === strlen($html), '502 HTML registra tamaño, no necesita exponer cuerpo en diagnóstico');
ok(($result['diagnostic']['body_sha256'] ?? '') === hash('sha256', $html), '502 HTML registra hash del cuerpo');
ok($GLOBALS['post_count'] === 1 && $GLOBALS['get_count'] === 0, '502 HTML no reintenta ni agrega llamadas');

// 5. Error de transporte: mensaje útil y correlación, una sola llamada.
reset_case([new WP_Error('cURL error 28: Operation timed out')]);
$client = new IDG_OpenAI_Client();
$result = $client->complete('prompt original');
ok($result['success'] === false, 'WP_Error sigue siendo fallo');
ok(str_contains($result['message'], 'Error de transporte al comunicarse con OpenAI:'), 'WP_Error se distingue de error HTTP');
ok(str_contains($result['message'], 'Referencia cliente: idg-'), 'WP_Error conserva referencia correlacionable');
ok($GLOBALS['post_count'] === 1 && $GLOBALS['get_count'] === 0, 'WP_Error no reintenta ni agrega llamadas');

// 6. 2xx sin texto: conserva semántica histórica y añade diagnóstico.
reset_case([response(200, ['id' => 'resp_empty', 'status' => 'completed', 'output' => []], ['x-request-id' => 'req-empty'])]);
$client = new IDG_OpenAI_Client();
$result = $client->complete('prompt original');
ok($result['success'] === false, '2xx sin texto sigue siendo fallo');
ok(str_contains($result['message'], 'La respuesta de OpenAI no incluyó texto utilizable.'), '2xx sin texto mantiene mensaje específico');
ok($GLOBALS['post_count'] === 1 && $GLOBALS['get_count'] === 0, '2xx sin texto no agrega llamadas');

echo "PASS openai sync HTTP recovery mock\n";
