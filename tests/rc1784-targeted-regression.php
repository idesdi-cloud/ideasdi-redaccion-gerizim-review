<?php
declare(strict_types=1);

/* Reutiliza el soporte WordPress local y las regresiones reales 199/217. */
require __DIR__ . '/rc1782-targeted-regression.php';

function rc1784_confirm(array $workflow): array { return rc1782_set_confirmation($workflow); }

/* Normalización temprana: dos espacios Markdown tras el rótulo no cambian prosa. */
$rc1784_box_prose = 'ActionForge reúne capas, poses, cámara y exportación para observar cambios de movimiento durante pruebas concretas. El flujo permite revisar timing, comparar decisiones y preparar transferencias hacia otras herramientas sin convertir la caja en promoción ni añadir enlaces externos innecesarios.';
$rc1784_box_markdown = "# ActionForge lleva animación al navegador\n## Movimiento y preproducción\nIntroducción factual.\n\n**Caja editorial**  \nActionForge reúne **capas**, poses, cámara y exportación para observar cambios de movimiento durante pruebas concretas. El flujo permite revisar timing, comparar decisiones y preparar transferencias hacia otras herramientas sin convertir la caja en promoción ni añadir enlaces externos innecesarios.\n\n### Desarrollo\nPárrafo normal.";
$rc1784_box_normalized = IDG_Workflow_Output_Parser::normalize_editorial_box_markdown($rc1784_box_markdown);
rc1782_ok(str_contains($rc1784_box_normalized, "Caja editorial\n{$rc1784_box_prose}") && !str_contains($rc1784_box_normalized, '**Caja editorial**') && !str_contains($rc1784_box_normalized, '**capas**'), 'normalización temprana de generate/editorial elimina formato de caja sin reescribir su texto');

/* Base→editorial→SEO: solo se reinsertan URLs ya aprobadas en un contexto único. */
$rc1784_source_article = rc1782_article(rc1782_box('ActionForge', 42));
$rc1784_editorial_without_links = str_replace(['[sitio oficial de la herramienta](https://actionforge.app/)', '[pruebas de animación tridimensional](https://ideasdi.com/tag/animacion/)'], ['sitio oficial de la herramienta', 'pruebas de animación tridimensional'], $rc1784_source_article);
$rc1784_links = IDG_Workflow_Output_Parser::preserve_approved_article_links($rc1784_source_article, $rc1784_editorial_without_links, rc1782_workflow('RC1784-links'));
rc1782_ok(!empty($rc1784_links['ok']) && substr_count($rc1784_links['content'], 'https://actionforge.app/') === 1 && substr_count($rc1784_links['content'], 'https://ideasdi.com/tag/animacion/') === 1, 'conserva enlaces aprobados de base a editorial sin duplicarlos');
$rc1784_seo_without_links = rc1782_seo(str_replace(['[sitio oficial de la herramienta](https://actionforge.app/)', '[pruebas de animación tridimensional](https://ideasdi.com/tag/animacion/)'], ['sitio oficial de la herramienta', 'pruebas de animación tridimensional'], (string) $rc1784_links['content']), rc1782_reel());
$rc1784_seo_links = IDG_Workflow_Output_Parser::preserve_approved_article_links((string) $rc1784_links['content'], $rc1784_seo_without_links, rc1782_workflow('RC1784-links'), true);
rc1782_ok(!empty($rc1784_seo_links['ok']) && substr_count($rc1784_seo_links['content'], 'https://actionforge.app/') === 1 && substr_count($rc1784_seo_links['content'], 'https://ideasdi.com/tag/animacion/') === 1 && !str_contains($rc1784_seo_links['content'], '[sitio oficial de la herramienta](https://actionforge.app/)\nINFORME SEO'), 'SEO restaura solo ARTÍCULO FINAL y el preflight conserva enlaces aprobados');
$rc1784_no_safe_context = str_replace('sitio oficial de la herramienta', 'referencia oficial', $rc1784_editorial_without_links);
$rc1784_link_failure = IDG_Workflow_Output_Parser::preserve_approved_article_links($rc1784_source_article, $rc1784_no_safe_context, rc1782_workflow('RC1784-link-failure'));
rc1782_ok(empty($rc1784_link_failure['ok']) && !empty($rc1784_link_failure['errors']) && !str_contains($rc1784_link_failure['content'], 'https://actionforge.app/'), 'sin contexto seguro el enlace perdido falla cerrado y no se inventa');

$rc1784_case_source = '[fuente](HTTPS://ActionForge.App/Case?Token=ABC#Frag)';
$rc1784_case_workflow = rc1782_workflow('RC1784-url-case');
$rc1784_case_workflow['responsible_official_url'] = 'https://actionforge.app/Case?Token=ABC#Frag';
$rc1784_case_wrong = '[fuente](https://actionforge.app/case?token=abc#frag)';
$rc1784_case_result = IDG_Workflow_Output_Parser::preserve_approved_article_links($rc1784_case_source, $rc1784_case_wrong, $rc1784_case_workflow);
rc1782_ok(empty($rc1784_case_result['ok']) && !empty($rc1784_case_result['errors']), 'URL similar solo por mayúsculas de ruta, query y fragmento no se considera igual');
$rc1784_case_safe = IDG_Workflow_Output_Parser::preserve_approved_article_links($rc1784_case_source, 'Texto [fuente].', $rc1784_case_workflow);
rc1782_ok(!empty($rc1784_case_safe['ok']) && str_contains($rc1784_case_safe['content'], '(HTTPS://ActionForge.App/Case?Token=ABC#Frag)'), 'URL aprobada se restaura conservando su forma original');

/* Brief222: enlaces ausentes y Reel defectuoso siguen siendo omitibles, no URLs ajenas. */
$brief222_article = str_replace('[sitio oficial de la herramienta](https://actionforge.app/)', 'sitio oficial de la herramienta', rc1782_article(rc1782_box('ActionForge', 42)));
$brief222 = rc1782_workflow('Brief222', rc1782_seo($brief222_article, rc1782_reel(true)));
$brief222_preflight = IDG_Post_Creator::preflight_public($brief222);
rc1782_ok(empty($brief222_preflight['ok']) && IDG_Post_Creator::override_eligible($brief222_preflight['validation']), 'Brief222 solo omite faltantes recuperables de enlace y Reel');
$brief222_created = IDG_Post_Creator::create_pending_post(rc1784_confirm($brief222));
rc1782_ok(!empty($brief222_created['success']) && ($GLOBALS['rc1782_posts'][$brief222_created['post_id']]['post_type'] ?? '') === 'post' && ($GLOBALS['rc1782_posts'][$brief222_created['post_id']]['post_status'] ?? '') === 'pending', 'Brief222 force crea post pending real');
$brief222_meta = $GLOBALS['rc1782_post_meta'][(int) $brief222_created['post_id']];
rc1782_ok(($brief222_meta['_idg_manual_review_required'] ?? '') === '1' && !empty($brief222_meta['_idg_validation_override']) && ($brief222_meta['_idg_source_phase'] ?? '') === 'seo', 'Brief222 audita revisión manual, payload y fase fuente');
$before = count($GLOBALS['rc1782_posts']);
rc1782_ok(empty(IDG_Post_Creator::create_pending_post(rc1784_confirm($brief222))['success']) && count($GLOBALS['rc1782_posts']) === $before, 'Brief222 evita doble creación por workflow');

foreach (['https://ideasdi.com/inventado/', 'https://malicious.invalid/'] as $bad_url) {
    $bad = rc1782_workflow('Brief222-' . md5($bad_url), rc1782_seo(str_replace('La organización por capas', '[enlace no autorizado](' . $bad_url . ') La organización por capas', rc1782_article(rc1782_box('ActionForge', 42))), rc1782_reel()));
    $preflight = IDG_Post_Creator::preflight_public($bad);
    rc1782_ok(!IDG_Post_Creator::override_eligible($preflight['validation']), 'URL no autorizada bloquea excepción ' . $bad_url);
}

/* Brief224: sin SEO se toma editorial antes que base y no se fabrica sello SEO. */
$brief224 = rc1782_workflow('Brief224', '');
$brief224['editorial_result'] = "# ActionForge lleva animación al navegador\n## Movimiento y preproducción\nPrimer párrafo factual sobre pruebas de movimiento y continuidad editorial.\n\n**Caja editorial**  \nActionForge reúne capas, poses, cámara y exportación para observar cambios de movimiento durante pruebas concretas. El flujo permite revisar timing, comparar decisiones y preparar transferencias hacia otras herramientas sin convertir la caja en promoción ni añadir enlaces externos innecesarios.\n\n### Desarrollo\nEl equipo conserva decisiones verificables durante el proceso editorial.";
$brief224['base_article'] = '# Base que no debe prevalecer';
$brief224_preflight = IDG_Post_Creator::preflight_public($brief224);
rc1782_ok(($brief224_preflight['prepared']['source_phase'] ?? '') === 'editorial' && str_contains($brief224_preflight['prepared']['content'], 'ActionForge reúne capas'), 'Brief224 prefiere editorial y normaliza Caja editorial Markdown');
rc1782_ok(IDG_Workflow_Policies::advance_block_reason('draft_force', $brief224) === '', 'política permite draft_force sin SEO cuando hay editorial');
$brief224_created = IDG_Post_Creator::create_pending_post(rc1784_confirm($brief224));
rc1782_ok(!empty($brief224_created['success']) && empty($brief224_created['seo_result']) && ($GLOBALS['rc1782_post_meta'][$brief224_created['post_id']]['_idg_source_phase'] ?? '') === 'editorial', 'Brief224 crea pending sin falsificar salida SEO');

$invalid_permission = rc1782_workflow('Brief224-permission', ''); $invalid_permission['editorial_result'] = $brief224['editorial_result'];
$GLOBALS['rc1782_can_edit'] = false; $writes = count($GLOBALS['rc1782_posts']);
rc1782_ok(empty(IDG_Post_Creator::create_pending_post(rc1784_confirm($invalid_permission))['success']) && count($GLOBALS['rc1782_posts']) === $writes, 'permiso inválido no escribe');
$GLOBALS['rc1782_can_edit'] = true;
$invalid_nonce = rc1784_confirm(array_merge($invalid_permission, ['workflow_id'=>'Brief224-nonce'])); $invalid_nonce['force_confirmation_nonce'] = 'invalid';
rc1782_ok(empty(IDG_Post_Creator::create_pending_post($invalid_nonce)['success']), 'nonce inválido no escribe');
$recurring_force = $brief224;
$recurring_force['workflow_id'] = 'Brief224-recurring';
$recurring_force['workflow_origin'] = 'recurring_update';
$recurring_force['recurring_target_post_id'] = 77;
$recurring_force = rc1784_confirm($recurring_force);
rc1782_ok(empty(IDG_Post_Creator::create_pending_post($recurring_force)['success']), 'destino recurrente rechaza draft_force y no reutiliza publicación');

$gutenberg_bad = IDG_Final_Guard::validate_gutenberg_blocks(json_encode([['blockName'=>'core/html','attrs'=>[],'innerBlocks'=>[],'innerHTML'=>'<script>bad</script>']]));
$gutenberg_codes = IDG_Final_Guard::classify_errors((array) ($gutenberg_bad['errors'] ?? []));
rc1782_ok(empty($gutenberg_bad['ok']) && !IDG_Post_Creator::override_eligible(['ok'=>false, 'errors'=>$gutenberg_bad['errors'], 'error_codes'=>$gutenberg_codes]), 'Gutenberg/integridad defectuosa no admite excepción');

echo "RC1784_TARGETED_FUNCTIONAL_OK\n";
