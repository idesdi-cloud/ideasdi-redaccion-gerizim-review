<?php
declare(strict_types=1);

/* Reutiliza exclusivamente los mocks WordPress locales del contrato RC1.7.8.2. */
require_once __DIR__ . '/rc1782-targeted-regression.php';

function rc17854_ok(bool $condition, string $label): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL RC17854: {$label}\n");
        exit(1);
    }
    echo "OK RC17854: {$label}\n";
}

/* Un nombre visible distinto sigue siendo válido solo por slug exacto + categoría. */
$GLOBALS['rc1782_terms'][55] = new WP_Term(55, 'post_tag', 'Nombre visible cambiado', 'lente-matriz');
$GLOBALS['rc1782_term_urls'][55] = 'https://ideasdi.com/tag/lente-matriz/';
$GLOBALS['rc1782_extra_tag_matrix'] = [
    ['tag' => 'Lente de matriz', 'tag_slug' => 'lente-matriz', 'category_slug' => 'modelado-3d', 'status' => 'Index'],
];
$slug_match = rc1782_workflow('RC17854-slug-match', rc1782_seo(rc1782_article(rc1782_box('ActionForge', 40)), rc1782_reel()));
$slug_match['tag_ids'] = [55];
$slug_match['primary_lens'] = 'Lente de matriz';
$slug_match['secondary_lenses'] = [];
$library = IDG_Internal_Links::library($slug_match);
rc17854_ok(($library['primary']['url'] ?? '') === 'https://ideasdi.com/tag/lente-matriz/' && ($library['primary']['source_name'] ?? '') === 'Nombre visible cambiado', 'slug exacto resuelve aunque el nombre WP difiera');
rc17854_ok(str_contains(IDG_Internal_Links::library_summary($slug_match), 'Diagnóstico técnico:'), 'diagnóstico de enlace está sanitizado en el campo existente de biblioteca');

/* Matriz noindex, categoría ajena y URL externa no pueden producir destino. */
$GLOBALS['rc1782_extra_tag_matrix'] = [['tag' => 'Lente de matriz', 'tag_slug' => 'lente-matriz', 'category_slug' => 'modelado-3d', 'status' => 'NoIndex']];
rc17854_ok(IDG_Internal_Links::library($slug_match)['editorial_resolution_status'] === 'unresolved', 'tag noindex queda excluido');
$GLOBALS['rc1782_extra_tag_matrix'] = [['tag' => 'Lente de matriz', 'tag_slug' => 'lente-matriz', 'category_slug' => 'diseno-interior', 'status' => 'Index']];
rc17854_ok(IDG_Internal_Links::library($slug_match)['editorial_resolution_status'] === 'unresolved', 'tag de categoría ajena queda excluido');
$GLOBALS['rc1782_extra_tag_matrix'] = [['tag' => 'Lente de matriz', 'tag_slug' => 'lente-matriz', 'category_slug' => 'modelado-3d', 'status' => 'Index']];
$GLOBALS['rc1782_term_urls'][55] = 'https://externo.example/tag/lente-matriz/';
rc17854_ok(IDG_Internal_Links::library($slug_match)['editorial_resolution_status'] === 'unresolved', 'URL externa no se acepta como enlace interno');
$GLOBALS['rc1782_term_urls'][55] = 'https://ideasdi.com/tag/lente-matriz/';

/* La UI puede ofrecer excepción por fallo SEO recuperable sin fallo editorial. */
$seo_only = rc1782_workflow('RC17854-seo-only', rc1782_seo($sky_article, rc1782_reel()));
$seo_only['official_source'] = 'https://superhivemarket.com/creators/caseysheep';
$seo_only['responsible_official_url'] = $seo_only['official_source'];
$seo_only['tag_ids'] = [];
$seo_only['primary_lens'] = '';
$seo_only['secondary_lenses'] = [];
$seo_only['seo_validation'] = ['ok' => false, 'errors' => ['No se detectó caja editorial.'], 'error_codes' => ['editorial_structure']];
unset($seo_only['editorial_validation'], $seo_only['editorial_requires_manual_review']);
rc17854_ok(IDG_Post_Creator::override_workflow_eligible($seo_only), 'fallo SEO recuperable es elegible aun sin rechazo editorial');
$editorial_only = $seo_only;
unset($editorial_only['seo_validation']);
$editorial_only['editorial_validation'] = ['ok' => false, 'errors' => ['Revisión editorial: no se detectó caja editorial.'], 'error_codes' => ['phase_editorial_structure']];
rc17854_ok(IDG_Post_Creator::override_workflow_eligible($editorial_only), 'fallo editorial recuperable conserva elegibilidad de interfaz');
rc17854_ok(!IDG_Post_Creator::override_workflow_eligible($seo_only + ['draft_post_id' => 44]), 'entrada previa nunca es elegible');
$unsafe = $seo_only;
$unsafe['seo_validation'] = ['ok' => false, 'errors' => ['El HTML final contiene un enlace externo no autorizado.'], 'error_codes' => ['external_link_integrity']];
rc17854_ok(!IDG_Post_Creator::override_workflow_eligible($unsafe), 'riesgo URL no recuperable no es elegible');

/* Servidor: confirmación completa, pending único e idempotencia, sin WordPress real. */
$writes = count($GLOBALS['rc1782_posts']);
$missing_confirmation = $seo_only;
$missing_confirmation['force_validation_override'] = true;
rc17854_ok(empty(IDG_Post_Creator::create_pending_post($missing_confirmation)['success']) && count($GLOBALS['rc1782_posts']) === $writes, 'sin confirmación no hay escritura');
$missing_nonce = rc1782_set_confirmation($seo_only);
$missing_nonce['force_confirmation_nonce'] = '';
rc17854_ok(empty(IDG_Post_Creator::create_pending_post($missing_nonce)['success']) && count($GLOBALS['rc1782_posts']) === $writes, 'nonce ausente no escribe');
$missing_hash = rc1782_set_confirmation($seo_only);
$missing_hash['force_confirmation_content_hash'] = '';
rc17854_ok(empty(IDG_Post_Creator::create_pending_post($missing_hash)['success']) && count($GLOBALS['rc1782_posts']) === $writes, 'hash ausente no escribe');
$confirmed = rc1782_set_confirmation($seo_only);
$created = IDG_Post_Creator::create_pending_post($confirmed);
rc17854_ok(!empty($created['success']) && ($GLOBALS['rc1782_posts'][$created['post_id']]['post_status'] ?? '') === 'pending' && !empty($created['validation']['overridden']), 'excepción SEO crea solo pending con auditoría de rechazo: ' . (string) ($created['message'] ?? ''));
$after_create = count($GLOBALS['rc1782_posts']);
rc17854_ok(empty(IDG_Post_Creator::create_pending_post($confirmed)['success']) && count($GLOBALS['rc1782_posts']) === $after_create, 'idempotencia impide duplicados en el mock local');


/* El diagnóstico usa el estado real de la fila de matriz y no el nombre WP. */
$GLOBALS['rc1782_extra_tag_matrix'] = [['tag'=>'Lente de matriz','tag_slug'=>'lente-matriz','category_slug'=>'modelado-3d','status'=>'Index / Transversal']];
$status_matrix = IDG_Internal_Links::library($slug_match);
rc17854_ok(($status_matrix['primary_tag_status'] ?? '') === 'Index / Transversal', 'estado mostrado corresponde a matriz indexable real');
$GLOBALS['rc1782_extra_tag_matrix'] = [['tag'=>'Lente de matriz','tag_slug'=>'lente-matriz','category_slug'=>'modelado-3d','status'=>'Index']];

/* Una etapa crítica prevalece sobre otra recuperable en la interfaz. */
$mixed = $seo_only;
$mixed['editorial_validation'] = ['ok'=>false,'errors'=>['El HTML final contiene un enlace externo no autorizado.'],'error_codes'=>['external_link_integrity']];
rc17854_ok(!IDG_Post_Creator::override_workflow_eligible($mixed), 'fallo crítico combinado impide mostrar excepción');
$incomplete = $seo_only;
$incomplete['editorial_validation'] = ['ok'=>false,'errors'=>[],'error_codes'=>[]];
rc17854_ok(!IDG_Post_Creator::override_workflow_eligible($incomplete), 'fallo de etapa sin diagnóstico no se ofrece como recuperable');
$both_recoverable = $seo_only;
$both_recoverable['editorial_validation'] = ['ok'=>false,'errors'=>['No se detectó caja editorial.'],'error_codes'=>['phase_editorial_structure']];
rc17854_ok(IDG_Post_Creator::override_workflow_eligible($both_recoverable), 'dos fallos recuperables mantienen elegibilidad');

/* Documento editorial canónico: fallo SEO-only sin rechazo editorial previo. */
require_once ABSPATH . 'includes/class-editorial-document.php';
$doc_seo_only = rc1782_workflow('RC17854-document-seo-only');
$doc_seo_only['editorial_document'] = IDG_Editorial_Document::from_markdown((string)$doc_seo_only['editorial_result'], $doc_seo_only);
$doc_seo_only['editorial_validation'] = ['ok'=>true,'errors'=>[],'error_codes'=>[]];
$doc_seo_only['seo_validation'] = ['ok'=>false,'errors'=>['Falta salida SEO final; la excepción mantiene revisión manual y no genera metadatos, Reel ni sello SEO.'],'error_codes'=>['missing_seo_result']];
unset($doc_seo_only['editorial_requires_manual_review']);
rc17854_ok(IDG_Post_Creator::override_workflow_eligible($doc_seo_only), 'UI permite documento con fallo solo SEO recuperable');
$doc_confirmed = rc1782_set_confirmation($doc_seo_only);
$doc_preflight = IDG_Post_Creator::preflight_public($doc_confirmed);
rc17854_ok(!empty($doc_preflight['write_allowed']) && empty($doc_preflight['ok'])
    && !empty($doc_preflight['validation']['overridden'])
    && in_array('missing_seo_result', (array)($doc_preflight['validation']['error_codes'] ?? []), true),
    'preflight de documento permite excepción SEO-only, manteniendo rechazo real');
$before_doc_posts = count($GLOBALS['rc1782_posts']);
$doc_created = IDG_Post_Creator::create_pending_post($doc_confirmed);
rc17854_ok(!empty($doc_created['success'])
    && count($GLOBALS['rc1782_posts']) === $before_doc_posts + 1
    && ($GLOBALS['rc1782_posts'][(int)$doc_created['post_id']]['post_status'] ?? '') === 'pending'
    && !empty($GLOBALS['rc1782_post_meta'][(int)$doc_created['post_id']]['_idg_validation_override'])
    && !empty($GLOBALS['rc1782_post_meta'][(int)$doc_created['post_id']]['_idg_manual_review_required']),
    'documento SEO-only crea un pending con auditoría de excepción');
rc17854_ok(empty(IDG_Post_Creator::create_pending_post($doc_confirmed)['success'])
    && count($GLOBALS['rc1782_posts']) === $before_doc_posts + 1,
    'documento SEO-only no genera duplicados');

echo "RC17854_GLOBAL_CONTRACTS_OK\n";
