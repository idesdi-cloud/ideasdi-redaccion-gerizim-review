<?php
$root = dirname(__DIR__);
$read = static fn(string $path): string => file_get_contents($root . '/' . $path) ?: '';
$recurring = $read('includes/class-recurring-updates.php');
$admin = $read('includes/class-admin-page.php');
$creator = $read('includes/class-post-creator.php');
$runner = $read('includes/class-job-runner.php');

function contest_route_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

contest_route_assert(!str_contains($recurring, 'La escritura de concursos o convocatorias permanece desactivada'), 'Se retiró el bloqueo informativo de escritura de concursos.');
contest_route_assert(str_contains($recurring, 'apply_structural_analysis') && str_contains($recurring, "content_type === 'contest'"), 'La aplicación estructural admite concursos.');
contest_route_assert(str_contains($recurring, 'build_contest_editorial_workflow_data'), 'Existe adaptador de datos para el workflow editorial de concursos.');
contest_route_assert(str_contains($recurring, "IDG_Workflow_Orchestrator::create(\$workflow_data, 'recurring')"), 'El concurso usa el orquestador recurrente existente.');
contest_route_assert(str_contains($admin, "in_array((string) (\$workflow['recurring_target_post_type'] ?? ''), ['evento', 'post'], true)"), 'La interfaz reconoce eventos y concursos recurrentes.');
contest_route_assert(str_contains($admin, 'is_recurring_contest_workflow') && str_contains($admin, 'Entrada de concurso'), 'La interfaz presenta el perfil editorial de concurso.');
contest_route_assert(str_contains($creator, '$is_contest = $target_post_type === \'post\';') && str_contains($creator, 'has_category(34, $post_before)'), 'El escritor final admite únicamente entradas de la categoría 34.');
contest_route_assert(str_contains($creator, 'target_post_fingerprint($post_id, $content_type)'), 'La escritura final verifica la identidad protegida del concurso.');
contest_route_assert(str_contains($creator, 'get_object_taxonomies($target_post_type)'), 'La escritura final conserva taxonomías según el tipo real de publicación.');
contest_route_assert(str_contains($runner, '$target_label = $target_post_type === \'post\' ? \'concurso o convocatoria\' : \'evento\';'), 'El runner conserva la acción legacy y generaliza el destino.');
contest_route_assert(substr_count($creator, 'wp_insert_post(') === 1, 'No se añadió creación de entradas al flujo recurrente.');
contest_route_assert(substr_count($creator, 'wp_update_post(') >= 1, 'La salida recurrente sigue actualizando la publicación existente.');

$complete_calls = 0;
foreach (glob($root . '/includes/*.php') ?: [] as $path) {
    $complete_calls += substr_count(file_get_contents($path) ?: '', '->complete(');
}
contest_route_assert($complete_calls === 8, 'No se añadieron llamadas a OpenAI.');

echo "PASS recurring contest editorial routing static\n";
