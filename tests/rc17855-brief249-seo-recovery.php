<?php
declare(strict_types=1);

/* Sanitized local replay of brief 249; it never invokes a network model. */
require __DIR__ . '/rc1782-targeted-regression.php';
require_once ABSPATH . 'includes/class-editorial-document.php';
require_once ABSPATH . 'includes/class-radar-importer.php';
if (!function_exists('wp_check_invalid_utf8')) {
    function wp_check_invalid_utf8(string $value): string { return $value; }
}
if (!function_exists('get_terms')) {
    function get_terms(array $args = []): array { return []; }
}
if (!function_exists('get_transient')) {
    function get_transient(string $key) { return false; }
}
if (!function_exists('delete_transient')) {
    function delete_transient(string $key): bool { return true; }
}
require_once ABSPATH . 'includes/class-admin-page.php';

function rc17855_ok(bool $condition, string $label): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL RC17855: {$label}\n");
        exit(1);
    }
    echo "OK RC17855: {$label}\n";
}

$GLOBALS['rc1782_terms'][1248] = new WP_Term(1248, 'post_tag', 'Software 3D', 'software-3d');
$GLOBALS['rc1782_terms'][1590] = new WP_Term(1590, 'post_tag', 'Render', 'render');
$GLOBALS['rc1782_term_urls'][1590] = 'https://ideasdi.com/tag/render/';
/* The editor-selected Software 3D has no demonstrated matrix row here; only Render is indexable. */
$GLOBALS['rc1782_include_software_3d_matrix'] = false;
$GLOBALS['rc1782_extra_tag_matrix'] = [['tag'=>'Render', 'tag_slug'=>'render', 'category_slug'=>'modelado-3d', 'status'=>'Index']];

$workflow = rc1782_workflow('idg-sanitized-brief249');
$workflow['radar_brief_id'] = 249;
$workflow['entity'] = 'Estudio de prueba';
$workflow['keyword'] = 'visualización digital';
$workflow['category_id'] = 1232;
$workflow['tag_ids'] = [1248, 1590];
$workflow['primary_lens'] = 'Software 3D';
$workflow['secondary_lenses'] = ['Render'];
$workflow['tag_names'] = ['Software 3D', 'Render'];
$workflow['radar_lente_sugerida'] = 'Animación';
$workflow['responsible_official_url'] = 'https://example.test/estudio/';
$workflow['canonical_context'] = ['responsible_official_url' => $workflow['responsible_official_url']];
/* Phase 1 identity mapping: Radar's responsible URL is identity; fuente_oficial remains documentary. */
$radar_payload = [
    'sistema' => 'radar-editorial-ideasdi', 'destino' => 'gerizim-wp', 'brief' => [
        'id' => 249, 'keyword_principal' => 'prueba', 'categoria_wp' => 'Diseño digital y 3D',
        'tipo_pieza' => 'Actualidad', 'responsable' => 'Estudio de prueba',
        'responsable_url' => 'https://example.test/responsable/', 'fuente_oficial' => 'https://research.test/fuente/',
    ],
    'contenido_editorial' => ['hecho_base' => 'Hecho.', 'angulo_editorial' => 'Ángulo.'],
];
$identity_import = IDG_Radar_Importer::import_from_json_string(json_encode($radar_payload), []);
rc17855_ok(!empty($identity_import['success'])
    , 'Radar acepta el brief mínimo de identidad');
rc17855_ok(($identity_import['workflow']['responsible_official_url'] ?? '') === 'https://example.test/responsable/', 'Radar copia la URL responsable al campo canónico');
rc17855_ok(($identity_import['workflow']['source_information_url'] ?? '') === 'https://research.test/fuente/', 'Radar conserva separada la fuente documental');
$malformed_payload = $radar_payload;
$malformed_payload['brief']['responsable_url'] = 'javascript:alert(1)';
$malformed_import = IDG_Radar_Importer::import_from_json_string(json_encode($malformed_payload), []);
rc17855_ok(empty($malformed_import['success']) && ($malformed_import['reason'] ?? '') === 'responsible_url_invalid', 'una URL responsable no HTTP(S) se rechaza antes de escribir');
$conflict_import = IDG_Radar_Importer::import_from_json_string(json_encode($radar_payload), ['responsible_official_url' => 'https://other.test/responsable/']);
rc17855_ok(empty($conflict_import['success']) && ($conflict_import['reason'] ?? '') === 'responsible_url_conflict', 'una URL responsable explícita en conflicto se rechaza sin sobrescribir');
$clear_workflow = ['responsible_official_url' => 'https://example.test/old/', 'official_source' => 'https://example.test/old/', 'radar_reimport_allowed' => true];
$clear_payload = $radar_payload;
$clear_payload['brief']['responsable_url'] = '';
$clear_import = IDG_Radar_Importer::import_from_json_string(json_encode($clear_payload), $clear_workflow);
rc17855_ok(!empty($clear_import['success']) && ($clear_import['workflow']['responsible_official_url'] ?? 'x') === '' && ($clear_import['workflow']['official_source'] ?? 'x') === '', 'responsable_url vacío limpia identidad y fuente sincronizadamente');
$missing_payload = $radar_payload;
unset($missing_payload['brief']['responsable_url']);
$missing_import = IDG_Radar_Importer::import_from_json_string(json_encode($missing_payload), $clear_workflow);
rc17855_ok(!empty($missing_import['success']) && ($missing_import['workflow']['responsible_official_url'] ?? '') === $clear_workflow['responsible_official_url'], 'responsable_url ausente conserva identidad existente sin inferir fuente');
$update_import = IDG_Radar_Importer::import_from_json_string(json_encode($radar_payload), []);
rc17855_ok(!empty($update_import['success']) && ($update_import['workflow']['official_source'] ?? '') === ($update_import['workflow']['responsible_official_url'] ?? ''), 'actualización válida sincroniza ambos campos');
$_GET['message'] = 'responsible_url_invalid';
$render_notice = new ReflectionMethod('IDG_Workflow_Admin_Controller', 'render_notice');
$render_notice->setAccessible(true);
ob_start();
$render_notice->invoke(null, []);
$invalid_notice = (string) ob_get_clean();
rc17855_ok(str_contains($invalid_notice, 'notice-error') && str_contains($invalid_notice, 'URL HTTP(S) válida') && str_contains($invalid_notice, 'No se guardaron cambios'), 'la UI muestra el aviso seguro de URL responsable inválida');
$_GET['message'] = 'responsible_url_conflict';
ob_start();
$render_notice->invoke(null, []);
$conflict_notice = (string) ob_get_clean();
rc17855_ok(str_contains($conflict_notice, 'notice-error') && str_contains($conflict_notice, 'difiere de la identidad responsable registrada') && str_contains($conflict_notice, 'No se guardaron cambios'), 'la UI muestra el aviso seguro de conflicto de URL responsable');
unset($_GET['message']);
rc17855_ok((IDG_Internal_Links::normalize($workflow)[0]['url'] ?? '') === 'https://ideasdi.com/tag/render/', 'los tags elegidos por editor prevalecen sobre Radar; Software 3D sin matriz no se asume indexable');

$article = "# Visualización digital para una prueba\n\n[El estudio responsable](https://example.test/estudio/) presentó una demostración de visualización digital.\n\nCaja editorial\n" . rc1782_box('Prueba', 40) . "\n\n## Decisiones de imagen\nEl equipo comparó iluminación, materiales y encuadre antes de publicar la demostración.";
$document = IDG_Editorial_Document::from_markdown($article, $workflow);
$components = "META DESCRIPTION\nUna demostración de visualización digital explica decisiones de imagen, materiales y encuadre.\n\nINFORME SEO INTERNO\n- Cinco componentes SEO recibidos y conservados.\n\nCOPY PARA REDES\nUna prueba de visualización digital.\n\nPAQUETE REEL\n" . rc1782_reel() . "\n\nRETROALIMENTACIÓN GERIZIM\n- Revisar el enlace interno con criterio editorial.";
$applied = IDG_Editorial_Document::apply_seo_response($document, $components, $workflow);
rc17855_ok(!empty($applied['ok']) && count((array) $applied['received_components']) === 5, 'la respuesta SEO conserva sus cinco componentes independientes');
rc17855_ok(($applied['document']['seo']['meta_description'] ?? '') !== '' && !empty($applied['document']['reel']['valid']), 'metadatos y Reel válidos permanecen sellados');

$failed = IDG_Editorial_Document::complete_missing_authorized_links($applied['document'], $workflow);
rc17855_ok(empty($failed['ok']) && ($failed['reason'] ?? '') === 'missing_unique_authorized_anchor', 'la causa real es falta de ancla contextual única, no una hipótesis sobre el tag');
rc17855_ok(IDG_Editorial_Document::markdown($failed['document']) === IDG_Editorial_Document::markdown($applied['document']), 'la reconciliación ambigua conserva el texto byte a byte');

$pending = $workflow;
$pending['editorial_document'] = $failed['document'];
$pending['seo_result'] = IDG_Editorial_Document::projection($failed['document']);
$preflight = IDG_Post_Creator::preflight_public($pending);
rc17855_ok(empty($preflight['ok']) && !empty($preflight['validation']['error_codes']), 'el preflight real se mantiene rechazado y diagnosticable');
rc17855_ok(in_array('recoverable_link_missing', (array) $preflight['validation']['error_codes'], true), 'la falta segura de enlace queda tipada por Final Guard sin aprobar SEO');

/* The actual phase receives one local mocked response and persists its rejected preflight. */
$replay = $workflow;
$replay['editorial_document'] = $document;
$replay['editorial_validation'] = ['ok' => true, 'errors' => [], 'error_codes' => []];
$GLOBALS['rc1782_http_calls'] = [];
$GLOBALS['rc1782_http_queue'] = [rc1782_response($components)];
(new IDG_Workflow_Redaction_Pipeline())->execute((string) $replay['workflow_id'], IDG_Workflow_Policies::ACTION_SEO, $replay, new IDG_OpenAI_Client('local-only', 'local-mock'));
$persisted = (array) get_option((string) $replay['workflow_id'], []);
rc17855_ok(count($GLOBALS['rc1782_http_calls']) === 1, 'el flujo usa solo la respuesta SEO recibida; no solicita reparación');
rc17855_ok(empty($persisted['seo_validation']['ok'])
    && is_string($persisted['seo_response_diagnostics']['authorized_links'] ?? null)
    && ($persisted['seo_response_diagnostics']['authorized_links'] ?? '') === 'missing_unique_authorized_anchor'
    && (($persisted['seo_response_diagnostics']['authorized_links_detail']['reason'] ?? '') === 'missing_unique_authorized_anchor')
    && (($persisted['seo_response_diagnostics']['authorized_links_detail']['status'] ?? '') === 'unreconciled')
    && !empty($persisted['seo_response_diagnostics']['authorized_links_detail']['editor_message']), 'run_seo preserva el campo legacy string para Bridge y el detalle enriquecido separado');
rc17855_ok(($persisted['editorial_document']['seo']['meta_description'] ?? '') !== '' && !empty($persisted['editorial_document']['reel']['valid']), 'run_seo conserva componentes independientes después del fallo de enlace');

/* La UI usa únicamente la validación persistida: una excepción exige que cada fallo sea omitible. */
$only_recoverable = $persisted;
$only_recoverable['seo_validation'] = ['ok' => false, 'errors' => ['P2 exige exactamente un enlace al tag canónico resuelto; detectados: 0.'], 'error_codes' => ['recoverable_link_missing']];
$only_recoverable['editorial_validation'] = ['ok' => true, 'errors' => [], 'error_codes' => []];
rc17855_ok(IDG_Post_Creator::override_workflow_eligible($only_recoverable), 'un workflow persistido con un único fallo recuperable puede ofrecer la excepción');
$critical_mix = $only_recoverable;
$critical_mix['editorial_validation'] = ['ok' => false, 'errors' => ['El HTML final contiene un enlace externo no autorizado.'], 'error_codes' => ['external_link_integrity']];
rc17855_ok(!IDG_Post_Creator::override_workflow_eligible($critical_mix), 'un error crítico junto al recuperable no ofrece excepción');
$already_created = $only_recoverable;
$already_created['draft_post_id'] = 901;
rc17855_ok(!IDG_Post_Creator::override_workflow_eligible($already_created), 'una entrada existente no ofrece una segunda creación pending');
$recurring = $only_recoverable;
$recurring['workflow_origin'] = 'recurring_update';
$recurring['recurring_target_post_id'] = 902;
rc17855_ok(!IDG_Post_Creator::override_workflow_eligible($recurring), 'un workflow recurrente no ofrece excepción para crear entrada nueva');
$duplicate_pending = $workflow;
$duplicate_pending['editorial_document'] = IDG_Editorial_Document::from_markdown($article . "\n\n[Primera referencia](https://ideasdi.com/tag/render/) y [segunda referencia](https://ideasdi.com/tag/render/) permanecen para comprobar el rechazo.", $workflow);
$duplicate_pending['seo_result'] = IDG_Editorial_Document::projection($duplicate_pending['editorial_document']);
$duplicate_pending['seo_validation'] = IDG_Post_Creator::preflight_public($duplicate_pending)['validation'];
$duplicate_pending['editorial_validation'] = ['ok' => true, 'errors' => [], 'error_codes' => []];
rc17855_ok(!IDG_Post_Creator::override_workflow_eligible($duplicate_pending), 'enlaces duplicados conservan su bloqueo y no se transforman en excepción');
$brief249_codes = (array) ($persisted['seo_validation']['error_codes'] ?? []);
$omittable_codes = ['editorial_structure', 'reel_contract', 'p2_unresolved', 'recoverable_link_missing', 'missing_seo_result'];
$brief249_has_non_omittable = (bool) array_filter($brief249_codes, static fn($code): bool => !in_array((string) $code, $omittable_codes, true));
rc17855_ok($brief249_has_non_omittable
    ? !IDG_Post_Creator::override_workflow_eligible($persisted)
    : IDG_Post_Creator::override_workflow_eligible($persisted), 'la UI refleja los códigos reales persistidos del brief 249 sin falsear elegibilidad');

/* An anchor may be a natural contextual phrase, never the literal tag name alone. */
$natural = str_replace('El equipo comparó iluminación, materiales y encuadre antes de publicar la demostración.', 'El equipo revisó ajustes de render para comparar iluminación, materiales y encuadre antes de publicar la demostración.', $article);
$natural_document = IDG_Editorial_Document::from_markdown($natural, $workflow);
$natural_links = IDG_Editorial_Document::complete_missing_authorized_links($natural_document, $workflow);
$natural_markdown = IDG_Editorial_Document::markdown($natural_links['document']);
$natural_anchor = '';
preg_match('/\[([^\]]+)\]\(https:\/\/ideasdi\.com\/tag\/render\/\)/u', $natural_markdown, $natural_match);
$natural_anchor = (string) ($natural_match[1] ?? '');
rc17855_ok(!empty($natural_links['ok']) && mb_strtolower($natural_anchor) !== 'render' && str_contains(mb_strtolower($natural_anchor), 'render'), 'solo envuelve una frase natural; no fuerza el nombre literal Render');
$reconciled_replay = $workflow;
$reconciled_replay['workflow_id'] = 'idg-sanitized-brief249-reconciled';
$reconciled_replay['editorial_document'] = $natural_document;
$reconciled_replay['editorial_validation'] = ['ok' => true, 'errors' => [], 'error_codes' => []];
$GLOBALS['rc1782_http_calls'] = [];
$GLOBALS['rc1782_http_queue'] = [rc1782_response($components)];
(new IDG_Workflow_Redaction_Pipeline())->execute((string) $reconciled_replay['workflow_id'], IDG_Workflow_Policies::ACTION_SEO, $reconciled_replay, new IDG_OpenAI_Client('local-only', 'local-mock'));
$reconciled = (array) get_option((string) $reconciled_replay['workflow_id'], []);
rc17855_ok(($reconciled['seo_response_diagnostics']['authorized_links'] ?? null) === '' && !isset($reconciled['seo_response_diagnostics']['authorized_links_detail']), 'la reconciliación correcta no escribe un legacy incident ni detalle de fallo');

/* Negative link-integrity cases remain blocking. */
$duplicate = IDG_Editorial_Document::from_markdown($article . "\n\n[Primera referencia](https://ideasdi.com/tag/render/) y [segunda referencia](https://ideasdi.com/tag/render/) permanecen para comprobar el rechazo.", $workflow);
$duplicate_result = IDG_Editorial_Document::complete_missing_authorized_links($duplicate, $workflow);
rc17855_ok(empty($duplicate_result['ok']) && ($duplicate_result['reason'] ?? '') === 'duplicate_authorized_link', 'un enlace autorizado duplicado nunca se corrige a ciegas');
$foreign = IDG_Editorial_Document::from_markdown(str_replace('demostración de visualización digital.', '[demostración](https://evil.example/otro) de visualización digital.', $article), $workflow);
$foreign_pending = $workflow; $foreign_pending['editorial_document'] = $foreign; $foreign_pending['seo_result'] = IDG_Editorial_Document::projection($foreign);
$foreign_preflight = IDG_Post_Creator::preflight_public($foreign_pending);
rc17855_ok(in_array('external_link_integrity', (array) $foreign_preflight['validation']['error_codes'], true), 'URL externa ajena bloquea cualquier excepción');

$pipeline = file_get_contents(ABSPATH . 'includes/class-workflow-redaction-pipeline.php');
rc17855_ok(is_string($pipeline) && str_contains($pipeline, "'missing_unique_authorized_anchor'") && str_contains($pipeline, "'status' => 'unreconciled'"), 'el flujo persiste reason tipado y no abandona el diagnóstico');
rc17855_ok(str_contains($pipeline, '&& !$link_reconciliation_failed'), 'la reconciliación ambigua no dispara una segunda llamada al modelo');
rc17855_ok(!str_contains($pipeline, "Enlaces autorizados no reconciliables.']); return;"), 'el flujo ya no retorna antes de guardar seo_validation');
$admin = file_get_contents(ABSPATH . 'includes/class-admin-page.php');
rc17855_ok(is_string($admin)
    && str_contains($admin, "['authorized_links_detail']")
    && str_contains($admin, "=== 'unreconciled'")
    && str_contains($admin, 'esc_html((string) ($authorized_links_detail'), 'la zona SEO muestra solo el detalle de fallo con escaping');

echo "RC17855_BRIEF249_SEO_RECOVERY_OK\n";
