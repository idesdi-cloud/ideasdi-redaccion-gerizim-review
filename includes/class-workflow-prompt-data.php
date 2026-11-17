<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Construye el mismo payload histórico de prompts fuera del Job_Runner.
 */
final class IDG_Workflow_Prompt_Data {
    public static function prepare(array $workflow, string $article, string $phase = 'generic'): array {
        // P2 se resuelve una sola vez contra el workflow completo antes de reducirlo
        // para el prompt. El formateador recibe esta decisión, no vuelve a inferirla.
        $resolved_internal_links = class_exists('IDG_Internal_Links')
            ? IDG_Internal_Links::normalize($workflow)
            : [];
        $category_name = '';
        if (!empty($workflow['editorial_context_name'])) {
            $category_name = (string) $workflow['editorial_context_name'];
        } elseif ((string) ($workflow['workflow_origin'] ?? '') === 'recurring_update' && (string) ($workflow['recurring_target_post_type'] ?? '') === 'evento') {
            $category_name = 'Calendario de eventos';
        } elseif ((string) ($workflow['workflow_origin'] ?? '') === 'recurring_update' && (string) ($workflow['recurring_target_post_type'] ?? '') === 'post') {
            $category_name = 'Concursos y convocatorias';
        } elseif (!empty($workflow['category_id'])) {
            $term = get_term((int) $workflow['category_id'], 'category');
            if ($term && !is_wp_error($term)) {
                $category_name = $term->name;
            }
        }

        $tag_names = [];
        if (!empty($workflow['tag_ids']) && is_array($workflow['tag_ids'])) {
            foreach ($workflow['tag_ids'] as $tag_id) {
                $tag = get_term((int) $tag_id, 'post_tag');
                if ($tag && !is_wp_error($tag)) {
                    $tag_names[] = $tag->name;
                }
            }
        }
        if (empty($tag_names) && !empty($workflow['tag_names']) && is_array($workflow['tag_names'])) {
            $tag_names = array_values(array_filter(array_map('strval', $workflow['tag_names'])));
        }

        $base_structure = class_exists('IDG_Editorial_Recipe_Builder') ? IDG_Editorial_Recipe_Builder::build($workflow) : [];
        $auto_priority = (string) ($base_structure['recipe'] ?? (class_exists('IDG_Priority_Readings') ? IDG_Priority_Readings::build_suggestion((int) ($workflow['category_id'] ?? 0), isset($workflow['tag_ids']) && is_array($workflow['tag_ids']) ? $workflow['tag_ids'] : []) : ''));
        $recipe_base = trim((string) ($workflow['recipe_base'] ?? $workflow['priority_readings'] ?? $workflow['editorial_recipe'] ?? ''));
        if ($recipe_base === '') {
            $recipe_base = $auto_priority;
        }
        $priority_readings = trim((string) ($workflow['editorial_recipe_applied'] ?? $recipe_base));
        $editorial_angle = trim((string) ($workflow['editorial_angle'] ?? ''));
        if ($editorial_angle === '') {
            $editorial_angle = 'Usar el hecho base y la investigación como prioridad factual. La categoría delimita el territorio, el lente filtra la mirada y el plan editorial aplicado selecciona únicamente los ejes pertinentes.';
        }

        $editorial_signals = $phase === 'editorial' ? self::editorial_review_signals($article) : '';
        $objective_checks = $phase === 'editorial' ? self::editorial_objective_checks($article, $workflow) : [];
        $shadow_decision = $phase === 'editorial'
            ? self::editorial_shadow_decision($article, $workflow, $objective_checks, $editorial_signals)
            : [];
        $manifest = class_exists('IDG_Editorial_Document')
            ? IDG_Editorial_Document::prompt_manifest($workflow, $article, $phase)
            : ['ok' => true, 'text' => ''];
        return [
            'canonical_context' => class_exists('IDG_Canonical_Context') ? IDG_Canonical_Context::resolve($workflow) : [],
            'canonical_guidance' => class_exists('IDG_Canonical_Context') ? IDG_Canonical_Context::prompt_block($workflow) : '',
            'primary_lens' => (string) ($workflow['primary_lens'] ?? ''),
            'secondary_lenses' => (array) ($workflow['secondary_lenses'] ?? []),
            'responsible_official_url' => (string) ($workflow['responsible_official_url'] ?? ''),
            'keyword' => $workflow['keyword'] ?? '',
            'entity' => $workflow['entity'] ?? '',
            'piece_type' => $workflow['piece_type'] ?? '',
            'brief_fact' => $workflow['brief_fact'] ?? '',
            'editorial_angle' => $editorial_angle,
            'priority_readings' => $priority_readings,
            'recipe_base' => $recipe_base,
            'recipe_base_structure' => class_exists('IDG_Editorial_Recipe_Builder') ? IDG_Editorial_Recipe_Builder::prompt_structure($workflow) : '',
            'semantic_library_context' => class_exists('IDG_Disciplinary_Library') ? IDG_Disciplinary_Library::prompt_block($workflow) : '',
            'editorial_plan' => class_exists('IDG_Editorial_Plan') ? IDG_Editorial_Plan::prompt_block($workflow) : '',
            'identity_required' => !empty($base_structure['identity_required']),
            'category_name' => $category_name,
            'editorial_context' => (string) ($workflow['editorial_context'] ?? (((string) ($workflow['workflow_origin'] ?? '') === 'recurring_update') ? (((string) ($workflow['recurring_target_post_type'] ?? '') === 'evento') ? 'event_calendar' : 'contest_call') : '')),
            'editorial_context_name' => (string) ($workflow['editorial_context_name'] ?? (((string) ($workflow['workflow_origin'] ?? '') === 'recurring_update') ? (((string) ($workflow['recurring_target_post_type'] ?? '') === 'evento') ? 'Calendario de eventos' : 'Concursos y convocatorias') : '')),
            'wordpress_content_type' => (string) ($workflow['wordpress_content_type'] ?? (((string) ($workflow['recurring_target_post_type'] ?? '') === 'evento') ? 'Evento' : (((string) ($workflow['workflow_origin'] ?? '') === 'recurring_update') ? 'Entrada de concurso' : 'Entrada'))),
            'event_taxonomy_context' => isset($workflow['event_taxonomy_context']) && is_array($workflow['event_taxonomy_context']) ? $workflow['event_taxonomy_context'] : [],
            'event_editorial_category' => (string) ($workflow['event_editorial_category'] ?? ''),
            'tag_names' => $tag_names,
            'official_source' => $workflow['official_source'] ?? '',
            'internal_links' => $workflow['internal_links'] ?? '',
            'internal_links_structured' => $workflow['internal_links_structured'] ?? [],
            'internal_links_resolved' => $resolved_internal_links,
            'editor_notes' => $workflow['editor_notes'] ?? '',
            'sponsor_client' => $workflow['sponsor_client'] ?? '',
            'sponsored_topic' => $workflow['sponsored_topic'] ?? '',
            'sponsored_brief' => $workflow['sponsored_brief'] ?? '',
            'sponsored_must_include' => $workflow['sponsored_must_include'] ?? '',
            'sponsored_avoid' => $workflow['sponsored_avoid'] ?? '',
            'sponsored_required_link' => $workflow['sponsored_required_link'] ?? '',
            'sponsored_anchor' => $workflow['sponsored_anchor'] ?? '',
            'sponsored_link_rel' => $workflow['sponsored_link_rel'] ?? '',
            'sponsored_visible_label' => $workflow['sponsored_visible_label'] ?? '',
            'sponsored_restrictions' => $workflow['sponsored_restrictions'] ?? '',
            'document_card' => $workflow['document_card'] ?? '',
            'assignment_card' => $workflow['assignment_card'] ?? '',
            'web_research_status' => $workflow['web_research_status'] ?? '',
            'web_research_intensity' => $workflow['web_research_intensity'] ?? '',
            'web_research_reason' => $workflow['web_research_reason'] ?? '',
            'web_research_card' => $workflow['web_research_card'] ?? '',
            'source_url_status' => $workflow['source_url_status'] ?? '',
            'source_information_url' => $workflow['source_information_url'] ?? '',
            'temporary_material_excerpt' => self::material_excerpt_for_phase($workflow, $phase),
            'temporary_material_mode' => self::material_mode_for_phase($workflow, $phase),
            'article' => $article,
            'editorial_review_signals' => $editorial_signals,
            'editorial_objective_checks' => $objective_checks,
            'editorial_shadow_decision' => $shadow_decision,
            'editorial_document_manifest' => (string) ($manifest['text'] ?? ''),
            'editorial_document_manifest_ok' => !empty($manifest['ok']),
            'editorial_document_manifest_reason' => (string) ($manifest['reason'] ?? ''),
        ];
    }

    /**
     * Small deterministic hints for the *existing* editorial call. These are
     * candidates for scrutiny, not facts or instructions to change the text.
     * Never infer credibility from a lexical match and never mutate prose.
     */
    /**
     * P1Q1: bounded objective pre-review, advisory only.
     * No automatic edits, vetoes, pass/fail declarations for editorial quality,
     * call reduction, or external data fetching. The brief is an *internal*
     * comparator for explicit numerical claims, not a new source of facts.
     */
    public static function editorial_objective_checks(string $article, array $workflow = []): array {
        $issues = [];
        $add = static function (string $code, string $message) use (&$issues): void {
            if (count($issues) < 8 && !isset($issues[$code])) {
                $issues[$code] = ['code' => $code, 'message' => $message];
            }
        };
        preg_match_all('/^#\s+([^\r\n]+)$/mu', $article, $h1);
        preg_match_all('/^##\s+([^\r\n]+)$/mu', $article, $h2);
        if (count($h1[1]) !== 1) {
            $add('h1_count', 'H1: se detectaron ' . count($h1[1]) . ' encabezados de nivel 1; revisar la estructura.');
        }
        if (!empty($h1[1][0]) && mb_strlen(trim($h1[1][0]), 'UTF-8') > 68) {
            $add('h1_length', 'H1 supera 68 caracteres; proponer corrección solo si es fiel a los hechos.');
        }
        if (!empty($h1[1][0]) && !empty($h2[1][0])) {
            $normal = static function (string $s): string {
                $s = mb_strtolower(trim($s), 'UTF-8');
                return trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s));
            };
            if ($normal($h1[1][0]) === $normal($h2[1][0])) {
                $add('h1_h2_identical', 'H1 y H2 tienen el mismo texto normalizado; evitar duplicación de función.');
            }
        }

        // Check actual Markdown and HTML anchors; never modify or replace their URLs here.
        $anchors = [];
        if (preg_match_all('/\[([^\]\r\n]+)\]\((https?:\/\/[^\s)]+)\)/iu', $article, $m, PREG_SET_ORDER)) {
            foreach ($m as $a) $anchors[] = [$a[1], $a[2]];
        }
        if (preg_match_all('/<a\s+[^>]*href\s*=\s*[\'\"](https?:\/\/[^\'\"]+)[\'\"][^>]*>(.*?)<\/a>/isu', $article, $m, PREG_SET_ORDER)) {
            foreach ($m as $a) $anchors[] = [strip_tags($a[2]), $a[1]];
        }
        foreach ($anchors as [$label, $url]) {
            $host = mb_strtolower((string) parse_url($url, PHP_URL_HOST), 'UTF-8');
            $path = (string) parse_url($url, PHP_URL_PATH);
            if (!in_array($host, ['ideasdi.com', 'www.ideasdi.com'], true) || strpos($path, '/tag/') !== 0) continue;
            $words = preg_split('/\s+/u', trim(strip_tags((string) $label)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if (count($words) < 3 || count($words) > 8) {
                $add('internal_anchor_length', 'Ancla interna a etiqueta con ' . count($words) . ' palabra(s), fuera de 3–8: «' . mb_substr(trim((string) $label), 0, 65, 'UTF-8') . '».');
            }
            $keyword = trim((string) ($workflow['keyword'] ?? ''));
            if ($keyword !== '' && mb_strtolower(trim((string) $label), 'UTF-8') === mb_strtolower($keyword, 'UTF-8')) {
                $add('internal_anchor_keyword', 'El ancla interna coincide literalmente con la keyword; usar frase contextual.');
            }
        }

        // Only compare numerals tied to a narrowly identified metric. A phase
        // figure is NOT a total: do not flag 232 as a contradiction of 840.
        $brief = (string) ($workflow['brief_fact'] ?? '');
        if (preg_match('/\b(\d{2,5})\s+residencias(?:\s+de\s+marca)?\b/iu', $brief, $baseline)) {
            $expected = (int) $baseline[1];
            if (preg_match_all('/\b(\d{2,5})\s+residencias(?:\s+de\s+marca)?\b/iu', $article, $observed, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                foreach ($observed as $one) {
                    $n = (int) $one[1][0];
                    if ($n === $expected) continue;
                    $pos = $one[0][1];
                    $prefix = mb_strtolower(substr($article, max(0, $pos-85), min(85, $pos)), 'UTF-8');
                    $clauses = preg_split('/[.!?\n]/u', $prefix) ?: [];
                    $context = (string) end($clauses);
                    // Distinguish phase counts from global totals before flagging.
                    if (preg_match('/(?:primera\s+fase|fase\s+inicial|primera\s+etapa)[^.!?\n]{0,85}$/iu', $context)) continue;
                    $add('residence_total_conflict', 'Posible diferencia en total de residencias: brief ' . $expected . ', artículo ' . $n . '. Verificar mismo alcance antes de corregir.');
                    break;
                }
            }
        }
        if (preg_match('/\b(?:primera\s+fase|fase\s+inicial)[^.!?\n]{0,100}?\b(\d{2,5})\s+(?:viviendas|residencias)\b/iu', $brief, $baseline_phase)) {
            $expected_phase = (int) $baseline_phase[1];
            if (preg_match_all('/\b(?:primera\s+fase|fase\s+inicial)[^.!?\n]{0,100}?\b(\d{2,5})\s+(?:viviendas|residencias)\b/iu', $article, $observed_phase, PREG_SET_ORDER)) {
                foreach ($observed_phase as $one) {
                    $n = (int) $one[1];
                    if ($n !== $expected_phase) {
                        $add('phase_count_conflict', 'Posible diferencia en cifra de primera fase: brief ' . $expected_phase . ', artículo ' . $n . '. Contrastar el mismo programa.');
                        break;
                    }
                }
            }
        }
        if (preg_match('/\b(?:entregas?|entrega\s+inicial)[^.!?\n]{0,100}?\b(20\d{2})\b/iu', $brief, $baseline_year)) {
            $expected_year = (int) $baseline_year[1];
            if (preg_match_all('/\b(?:entregas?|entrega\s+inicial)[^.!?\n]{0,100}?\b(20\d{2})\b/iu', $article, $observed_year, PREG_SET_ORDER)) {
                foreach ($observed_year as $one) {
                    if ((int) $one[1] !== $expected_year) {
                        $add('delivery_year_conflict', 'Posible diferencia en horizonte de entrega: brief ' . $expected_year . ', artículo ' . $one[1] . '. Verificar contexto y fuente.');
                        break;
                    }
                }
            }
        }
        $items = array_values($issues);
        $message = $items
            ? '- ' . implode("\n- ", array_map(static fn(array $issue): string => '[' . $issue['code'] . '] ' . $issue['message'], $items))
            : '- Sin incidencias en estos controles objetivos limitados; NO equivale a aprobación editorial.';
        return [
            'version' => 'P1Q1-objective-v1',
            'status' => $items ? 'attention' : 'no_flags',
            'issue_count' => count($items),
            'issues' => $items,
            'text' => $message,
            'mode' => 'advisory_only_review_always_runs',
        ];
    }

    /**
     * P1Q2 shadow-only route suggestion. This method NEVER authorizes skipping
     * the editorial call, changes to article content, publication, or approval.
     * Both objective checks and textual hints are low-coverage advisory signals.
     * A no-flags result is a hypothesis for future evaluation, not a quality score.
     */
    public static function editorial_shadow_decision(string $article, array $workflow, array $checks, string $signals): array {
        $reasons = [];
        $objective = max(0, (int) ($checks['issue_count'] ?? 0));
        $valid_checks = ($checks['version'] ?? '') === 'P1Q1-objective-v1'
            && in_array((string) ($checks['status'] ?? ''), ['attention', 'no_flags'], true);
        $no_lexical = strpos($signals, '- Sin alertas léxicas concluyentes.') === 0;
        $lexical_count = $no_lexical ? 0 : count(array_filter(
            preg_split('/\\R/u', trim($signals)) ?: [],
            static fn(string $line): bool => strpos(trim($line), '- ') === 0
        ));
        // Fallback to manual-review hypothesis if available evidence is incomplete.
        $uncertain = !$valid_checks || trim($signals) === '' || trim($article) === ''
            || trim((string) ($workflow['brief_fact'] ?? '')) === '';
        if ($uncertain) {
            $route = 'review_due_uncertainty';
            $reasons[] = 'Evidencia de prelectura o hecho base incompletos; no inferir calidad ni omisión.';
        } elseif ($objective > 0) {
            $route = 'review_due_objective_flags';
            $reasons[] = $objective . ' incidencia(s) objetiva(s) candidata(s) a contraste.';
            if ($lexical_count > 0) $reasons[] = $lexical_count . ' señal(es) editorial(es) adicional(es).';
        } elseif ($lexical_count > 0) {
            $route = 'review_due_editorial_signals';
            $reasons[] = $lexical_count . ' señal(es) editorial(es) por contrastar; ninguna es prueba de error.';
        } else {
            $route = 'omission_candidate_observation_only';
            $reasons[] = 'Sin alertas dentro de una cobertura limitada; no acredita calidad editorial.';
            $reasons[] = 'Solo candidato a comparación histórica, jamás permiso para omitir.';
        }
        return [
            'version' => 'P1Q2-shadow-v1',
            'mode' => 'observation_only',
            'route' => $route,
            'objective_count' => $objective,
            'lexical_count' => $lexical_count,
            'reasons' => array_slice($reasons, 0, 3),
            'editorial_call_required' => true,
            'editorial_call_skipped' => false,
            'predicted_avoidable_calls' => $route === 'omission_candidate_observation_only' ? 1 : 0,
            'savings_are_hypothetical' => true,
            'quality_approved' => false,
        ];
    }

    public static function editorial_review_signals(string $article): string {
        $lines = preg_split('/\R/u', $article) ?: [];
        $headings = [];
        $paragraphs = [];
        $buffer = [];
        $flush = static function () use (&$buffer, &$paragraphs): void {
            $str = trim(implode(' ', $buffer));
            if ($str !== '' && $str !== 'Caja editorial') $paragraphs[] = $str;
            $buffer = [];
        };
        $inside_box = false;
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === 'Caja editorial') { $flush(); $inside_box = true; continue; }
            if (preg_match('/^#{1,3}\s+(.+)$/u', $line, $m)) {
                $flush(); $inside_box = false;
                $headings[] = trim($m[1]);
                continue;
            }
            if ($line === '') { $flush(); $inside_box = false; continue; }
            if (!$inside_box) $buffer[] = $line;
        }
        $flush();
        $tokens = static function (string $t): array {
            $t = mb_strtolower(strip_tags($t), 'UTF-8');
            $words = preg_split('/[^\p{L}\p{N}]+/u', $t, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $common = ['maserati','island','este','esta','estos','estas','entre','desde','sobre','donde','para','como','tiene','forma','parte','antes','cada','mismo','misma','tambien','también','proyecto','marca','vida','lugar','conjunto','residencial','diseño','dentro','fuera','hacia','según','segun','toda','todos','todas'];
            return array_values(array_unique(array_filter($words, static fn($v) => mb_strlen($v,'UTF-8') >= 4 && !in_array($v, $common, true))));
        };
        $signals = [];
        if (count($headings) >= 3) {
            $lead = array_values(array_unique(array_merge($tokens($headings[0]), $tokens($headings[1]))));
            $h3 = array_values(array_intersect($lead, $tokens($headings[2])));
            if ($h3) {
                $signals[] = 'Posible reiteración de foco entre apertura de encabezados y primer H3 (' . implode(', ', array_slice($h3, 0, 3)) . '): comprobar qué dato nuevo introduce cada nivel.';
            }
        }
        if (count($paragraphs) >= 4) {
            $opening = array_values(array_unique(array_merge($tokens($paragraphs[0]), $tokens($paragraphs[1]))));
            $ending = $tokens($paragraphs[count($paragraphs) - 1]);
            $overlap = array_values(array_intersect($opening, $ending));
            if (count($overlap) >= 4) {
                $signals[] = 'Apertura y cierre comparten términos (' . implode(', ', array_slice($overlap, 0, 5)) . '): verificar si el cierre aporta una consecuencia nueva; no eliminar datos únicos.';
            }
        }
        if (preg_match('/\b(más lent[oa]s?|casi ceremonial|garantiza|asegura|sin aislar|sin esfuerzo|redefine la experiencia|sensación de bienestar)\b/iu', $article, $m)) {
            $signals[] = 'Expresión de efecto o experiencia por contrastar: «' . mb_substr($m[0], 0, 60, 'UTF-8') . '». No asumir falsedad; buscar respaldo documental antes de conservarla o matizarla.';
        }
        if (preg_match('/\b(el dato más singular|la escala también ordena la lectura|lo interesante|la lectura del conjunto|lo que queda por ver)\b/iu', $article, $m)) {
            $signals[] = 'Posible frase metadiscursiva: «' . mb_substr($m[0], 0, 60, 'UTF-8') . '». Comprobar si aporta información verificable o solo comenta el relato.';
        }
        if (!$signals) return '- Sin alertas léxicas concluyentes. Realiza revisión editorial normal; ausencia de señales no acredita calidad.';
        return '- ' . implode("\n- ", array_slice($signals, 0, 4));
    }

    public static function document_material_text(array $workflow): string {
        if (class_exists('IDG_Web_Research')) {
            return IDG_Web_Research::document_material($workflow);
        }
        return (string) ($workflow['temp_material_text'] ?? '');
    }

    private static function material_excerpt_for_phase(array $workflow, string $phase): string {
        $combined = self::document_material_text($workflow);
        if (trim($combined) === '') {
            return '';
        }
        if ($phase === 'generate') {
            return IDG_Temporary_Material::limit_text($combined, IDG_Temporary_Material::MAX_PROMPT_CHARS);
        }
        // Para revisión editorial y SEO usamos la ficha documental, no la nota completa,
        // para evitar reescrituras promocionales o pérdida de la voz editorial.
        return '';
    }

    private static function material_mode_for_phase(array $workflow, string $phase): string {
        $has_combined = trim(self::document_material_text($workflow)) !== '';
        $research_status = trim((string) ($workflow['web_research_status'] ?? ''));
        if (!$has_combined) {
            return 'Sin material documental activo. La investigación web controlada puede construir una ficha desde el brief.';
        }
        if ($phase === 'generate') {
            return 'Uso alto: brief + ficha documental temporal + extracto controlado del material/documentación/investigación web para generar el artículo base. Investigación web: ' . ($research_status !== '' ? $research_status : 'no registrada') . '.';
        }
        if ($phase === 'editorial') {
            return 'Uso medio: solo ficha documental temporal para verificar datos, conservar precisión y neutralizar tono promocional.';
        }
        if ($phase === 'seo') {
            return 'Uso bajo: solo ficha documental temporal para validar precisión; no reescribir desde documentación o búsqueda.';
        }
        if ($phase === 'material') {
            return 'Creación de ficha documental temporal desde material, URL e investigación web controlada.';
        }
        return 'Material documental activo.';
    }
}
