<?php
declare(strict_types=1);

/* Exercises only the local reconciler; proposals are never written anywhere. */
require __DIR__ . '/rc1782-targeted-regression.php';
require_once ABSPATH . 'includes/class-editorial-document.php';

function rc1791_ok(bool $condition, string $label): void {
    if (!$condition) { fwrite(STDERR, "FAIL RC1791: {$label}\n"); exit(1); }
    echo "OK RC1791: {$label}\n";
}

$workflow = rc1782_workflow('rc1791-flex');
$base = rc1782_article(rc1782_box('ActionForge', 40));
$document = IDG_Editorial_Document::from_markdown($base, $workflow);
$unchanged = IDG_Editorial_Document::apply_editorial_response($document, "ARTÍCULO REVISADO\n" . IDG_Editorial_Document::markdown($document));
rc1791_ok(!empty($unchanged['ok']) && ($unchanged['reason'] ?? '') === 'unchanged_echo', 'idéntico conserva el documento sin cambios');
$section_one = "### Capas, poses y continuidad\nLa organización por capas separa partes del personaje y facilita cambios localizados durante una prueba. La cámara orbital permite comprobar el gesto desde varios ángulos sin perder la secuencia.\n\nLa línea temporal hace legible el orden de poses y pausas. Estas [pruebas de animación tridimensional](https://ideasdi.com/tag/animacion/) conectan observación, ajuste y continuidad dentro del navegador.";
$section_two = "### Exportación sin cerrar el flujo\nLos formatos FBX y GLB trasladan el resultado hacia Unreal, Unity, Godot o Blender. La exportación conserva la prueba como punto intermedio, no como destino rígido.\n\nActionForge funciona así como una estación ligera de preproducción. Su valor aparece en la rapidez para observar movimiento, corregir una pose y continuar el trabajo en otra herramienta.";
$proposal = str_replace($section_one . "\n\n" . $section_two, $section_two . "\n\n" . $section_one, $base);
$proposal = str_replace('### Capas, poses y continuidad', '### Observar el movimiento antes de exportar', $proposal);
$proposal = str_replace("La organización por capas separa partes del personaje y facilita cambios localizados durante una prueba. La cámara orbital permite comprobar el gesto desde varios ángulos sin perder la secuencia.\n\nLa línea temporal hace legible el orden de poses y pausas.", "La organización por capas separa partes del personaje y facilita cambios localizados durante una prueba.\n\nLa cámara orbital permite comprobar el gesto desde varios ángulos sin perder la secuencia. La línea temporal hace legible el orden de poses y pausas.", $proposal);
$proposal = str_replace("### Exportación sin cerrar el flujo\nLos formatos FBX", "### Exportación sin cerrar el flujo\nLos formatos FBX", $proposal);
$result = IDG_Editorial_Document::apply_editorial_response($document, "ARTÍCULO REVISADO\n{$proposal}\n\nDIAGNÓSTICO EDITORIAL INTERNO\n- H3 y estructura: corregido.\n\nNOTAS EDITORIALES INTERNAS\n- Sintético.");
$markdown = IDG_Editorial_Document::markdown($result['document']);
rc1791_ok(!empty($result['ok']) && ($result['reason'] ?? '') === 'complete_article_reconciled', 'acepta renombre y movimiento H3 con cuerpo, además de split/fusión de párrafos');
rc1791_ok(str_contains($markdown, '### Observar el movimiento antes de exportar') && substr_count($markdown, 'https://actionforge.app/') === 1 && substr_count($markdown, 'https://ideasdi.com/tag/animacion/') === 1, 'preserva anchors aprobados al reconciliar estructura');
rc1791_ok(str_starts_with($markdown, '# ActionForge lleva animación al navegador') && str_contains($markdown, "Caja editorial\n" . rc1782_box('ActionForge', 40)), 'H1 y caja íntegros');

$corrupt = str_replace('Caja editorial', 'Caja editorial\nCaja alterada', $proposal);
$rejected = IDG_Editorial_Document::apply_editorial_response($document, "ARTÍCULO REVISADO\n{$corrupt}");
rc1791_ok(empty($rejected['ok']) && ($rejected['reason'] ?? '') === 'protected_editorial_box_changed' && IDG_Editorial_Document::markdown($rejected['document']) === IDG_Editorial_Document::markdown($document), 'rechaza propuesta corrupta sin mutación parcial');
$broken_link = str_replace('https://ideasdi.com/tag/animacion/', 'https://ideasdi.com/tag/inventada/', $proposal);
$rejected_link = IDG_Editorial_Document::apply_editorial_response($document, "ARTÍCULO REVISADO\n{$broken_link}");
rc1791_ok(empty($rejected_link['ok']) && ($rejected_link['reason'] ?? '') === 'href_mutation' && IDG_Editorial_Document::markdown($rejected_link['document']) === IDG_Editorial_Document::markdown($document), 'rechaza cambio de enlace sin write');

$duplicate_paragraph = "La organización por capas separa partes del personaje y facilita cambios localizados durante una prueba. La cámara orbital permite comprobar el gesto desde varios ángulos sin perder la secuencia.";
$duplicate = str_replace('### Exportación sin cerrar el flujo', "### Exportación sin cerrar el flujo\n\n{$duplicate_paragraph}\n\n", $base);
$duplicate_document = IDG_Editorial_Document::from_markdown($duplicate, $workflow);
$duplicate_paragraph_blocks = array_values(array_filter(
    (array) ($duplicate_document['blocks'] ?? []),
    static fn(array $block): bool => ($block['type'] ?? '') === 'paragraph'
        && ($block['text'] ?? '') === $duplicate_paragraph
));
rc1791_ok(count($duplicate_paragraph_blocks) === 2, 'artículo propuesto contiene dos bloques párrafo iguales tras from_markdown');
$duplicate_result = IDG_Editorial_Document::apply_editorial_response($document, "ARTÍCULO REVISADO\n{$duplicate}");
$duplicate_ids = array_map(static fn(array $block): string => (string) ($block['id'] ?? ''), (array) ($duplicate_result['document']['blocks'] ?? []));
rc1791_ok(!empty($duplicate_result['ok']) && count($duplicate_ids) === count(array_unique($duplicate_ids)) && !in_array('', $duplicate_ids, true), 'contenido duplicado conserva IDs únicos sin rechazo ni mezcla');
$result_paragraph_blocks = array_values(array_filter(
    (array) ($duplicate_result['document']['blocks'] ?? []),
    static fn(array $block): bool => ($block['type'] ?? '') === 'paragraph'
        && ($block['text'] ?? '') === $duplicate_paragraph
));
rc1791_ok(count($result_paragraph_blocks) === 2 && $result_paragraph_blocks[0]['id'] !== $result_paragraph_blocks[1]['id'], 'documento resultante conserva dos párrafos iguales con IDs diferentes');
rc1791_ok(count($duplicate_ids) === count(array_filter($duplicate_ids, static fn(string $id): bool => $id !== '' && $id !== null)), 'todos los IDs del documento resultante no están vacíos');
rc1791_ok(!empty($duplicate_result['changed_ids']), 'reconciliación reporta IDs modificados');
rc1791_ok(str_starts_with(IDG_Editorial_Document::markdown($duplicate_result['document']), '# ActionForge lleva animación al navegador')
    && str_contains(IDG_Editorial_Document::markdown($duplicate_result['document']), "Caja editorial\n" . rc1782_box('ActionForge', 40))
    && substr_count(IDG_Editorial_Document::markdown($duplicate_result['document']), 'https://actionforge.app/') === 1
    && substr_count(IDG_Editorial_Document::markdown($duplicate_result['document']), 'https://ideasdi.com/tag/animacion/') === 1, 'reconciliación no pierde H1, caja ni enlaces');
$workflow2 = $workflow;
$workflow2['editorial_document'] = $duplicate_result['document'];
$manifest = IDG_Editorial_Document::prompt_manifest($workflow2, IDG_Editorial_Document::markdown($duplicate_result['document']), 'seo');
rc1791_ok(($manifest['ok'] ?? false) === true, 'manifest SEO valida el documento reconciliado');
rc1791_ok(($duplicate_result['document']['review']['state'] ?? '') === 'editorial_reviewed', 'documento reconciliado queda válido para manifest posterior');

echo "RC1791_FLEXIBLE_EDITORIAL_RECONCILIATION_SYNTHETIC_OK\n";
