<?php
declare(strict_types=1);

/* Local synthetic contract: no network, WordPress, or historical fixture writes. */
require __DIR__ . '/rc1782-targeted-regression.php';
require_once ABSPATH . 'includes/class-editorial-document.php';
require_once ABSPATH . 'includes/class-editorial-plan.php';

function rc1790_ok(bool $condition, string $label): void {
    if (!$condition) { fwrite(STDERR, "FAIL RC1790: {$label}\n"); exit(1); }
    echo "OK RC1790: {$label}\n";
}

$workflow = rc1782_workflow('rc1790-links');
$plan = IDG_Editorial_Plan::fallback($workflow, ['discipline' => 'diseño digital']);
$workflow = IDG_Editorial_Plan::apply_to_workflow($workflow, $plan, 'synthetic-plan-hash');
$data = IDG_Workflow_Prompt_Data::prepare($workflow, $workflow['base_article'], 'generate');
$internal = (array) ($data['internal_links_resolved'][0] ?? []);
rc1790_ok(($internal['url'] ?? '') === 'https://ideasdi.com/tag/animacion/' && ($internal['source_type'] ?? '') === 'tag', 'P2 transmite solo el tag canónico elegible');
rc1790_ok(str_contains((string) $workflow['editorial_link_strategy'], 'Página tag') && str_contains(IDG_Prompt_Library::generate_prompt($data), 'destinos ya resueltos por el plan'), 'plan y generación comparten estrategia y destinos');

$document = IDG_Editorial_Document::from_markdown($workflow['base_article'], $workflow);
$reviewed = IDG_Editorial_Document::apply_editorial_response($document, "ARTÍCULO REVISADO\n" . IDG_Editorial_Document::markdown($document));
rc1790_ok(!empty($reviewed['ok']) && in_array(($reviewed['reason'] ?? ''), ['unchanged_echo', 'complete_article_reconciled'], true), 'editorial conserva los enlaces ya integrados');
$seo = IDG_Editorial_Document::apply_seo_response($reviewed['document'], "META DESCRIPTION\nResumen sintético de ActionForge y sus decisiones de animación en navegador.\n\nINFORME SEO INTERNO\n- Sin duplicar destinos.\n\nCOPY PARA REDES\nActionForge y animación.\n\nPAQUETE REEL\n" . rc1782_reel() . "\n\nRETROALIMENTACIÓN GERIZIM\n- Enlaces conservados.", $workflow);
$rendered = IDG_Editorial_Document::gutenberg($seo['document']);
rc1790_ok(!empty($seo['ok']) && substr_count($rendered['html'], 'https://actionforge.app/') === 1 && substr_count($rendered['html'], 'https://ideasdi.com/tag/animacion/') === 1, 'generación, editorial y SEO preservan destinos sin duplicados en Gutenberg');

$unresolved = $workflow; $unresolved['tag_ids'] = []; $unresolved['primary_lens'] = 'Inexistente'; $unresolved['secondary_lenses'] = [];
$library = IDG_Internal_Links::library($unresolved);
rc1790_ok(($library['editorial_resolution_status'] ?? '') === 'unresolved' && ($library['resolution_reason'] ?? '') !== '' && empty(IDG_Internal_Links::normalize($unresolved)), 'P2 unresolved diagnostica temprano sin inventar URL');
$event = $unresolved; $event['workflow_origin'] = 'recurring_update'; $event['recurring_target_post_type'] = 'evento'; $event['internal_links_structured'] = [['url' => 'https://ideasdi.com/eventos/', 'type' => 'event_archive', 'label' => 'Agenda']];
rc1790_ok((IDG_Internal_Links::normalize($event)[0]['url'] ?? '') === 'https://ideasdi.com/eventos/', 'eventos conserva su navegación propia sin forzar P2');

echo "RC1790_LINK_CONTRACT_SYNTHETIC_OK\n";
