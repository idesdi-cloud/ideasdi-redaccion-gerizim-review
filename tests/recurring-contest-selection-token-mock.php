<?php
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

final class WP_Post {
    public int $ID;
    public string $post_type;

    public function __construct(int $id, string $post_type) {
        $this->ID = $id;
        $this->post_type = $post_type;
    }
}

$GLOBALS['idg_test_posts'] = [
    11902 => new WP_Post(11902, 'post'),
    32413 => new WP_Post(32413, 'evento'),
];

function get_post(int $post_id) {
    return $GLOBALS['idg_test_posts'][$post_id] ?? null;
}
function wp_json_encode($value): string {
    return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
function get_current_user_id(): int {
    return 77;
}
function wp_salt(string $scheme = 'auth'): string {
    return 'test-selection-secret-' . $scheme;
}

require_once dirname(__DIR__) . '/includes/class-recurring-updates.php';

function recurring_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

$method = new ReflectionMethod(IDG_Recurring_Updates::class, 'selection_token');
$method->setAccessible(true);

$contest = [
    'id' => 11902,
    'post_type' => 'post',
    'title' => 'Concurso Internacional de Diseño de Iluminación LAMP 2023',
];
$event = [
    'id' => 32413,
    'post_type' => 'evento',
    'title' => 'ICFF 2026',
];
$invalid = [
    'id' => 99,
    'post_type' => 'page',
];

$contest_token = (string) $method->invoke(null, $contest);
$event_token = (string) $method->invoke(null, $event);
$invalid_token = (string) $method->invoke(null, $invalid);

recurring_assert($contest_token !== '', 'Concurso normal genera token de selección no vacío.');
recurring_assert($event_token !== '', 'Evento CPT conserva token de selección no vacío.');
recurring_assert(hash_equals($contest_token, (string) $method->invoke(null, $contest)), 'Token de concurso es estable para mismo usuario, ID y tipo.');
recurring_assert(!hash_equals($contest_token, $event_token), 'Tokens distinguen ID y tipo de contenido.');
recurring_assert($invalid_token === '', 'Tipos no admitidos siguen bloqueados.');
recurring_assert(IDG_Recurring_Updates::immutable_post_fingerprint(11902) === '', 'Protección inmutable de aplicación sigue limitada a Eventos.');
recurring_assert(IDG_Recurring_Updates::immutable_post_fingerprint(32413) !== '', 'Protección inmutable de Eventos permanece activa.');

echo "PASS recurring contest selection token mock\n";
