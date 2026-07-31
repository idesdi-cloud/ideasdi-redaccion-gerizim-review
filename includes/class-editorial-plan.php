<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plan editorial aplicado: convierte la receta base y la investigación
 * en una tesis auditable antes de redactar.
 */
final class IDG_Editorial_Plan {
    public static function parse(string $content, array $workflow, array $base): array {
        $sections = self::sections($content);
        $plan = [
            'thesis' => self::first_text($sections['thesis'] ?? []),
            'discipline' => self::first_text($sections['discipline'] ?? []),
            'identity' => self::first_text($sections['identity'] ?? []),
            'activated_concepts' => self::list_values($sections['activated_concepts'] ?? []),
            'semantic_expansions' => self::list_values($sections['semantic_expansions'] ?? []),
            'recommended_verbs' => self::list_values($sections['recommended_verbs'] ?? []),
            'conditioned_terms' => self::list_values($sections['conditioned_terms'] ?? []),
            'selected_axes' => self::list_values($sections['selected_axes'] ?? []),
            'perceptual_translations' => self::list_values($sections['perceptual'] ?? []),
            'discarded_axes' => self::list_values($sections['discarded_axes'] ?? []),
            'risks' => self::list_values($sections['risks'] ?? []),
            'link_strategy' => self::first_text($sections['links'] ?? []),
            'applied_recipe' => self::first_text($sections['applied_recipe'] ?? []),
            'raw' => trim($content),
            'source' => 'model',
        ];

        if ($plan['discipline'] === '') {
            $plan['discipline'] = (string) ($base['discipline'] ?? $base['territory'] ?? 'diseño');
        }
        if (count($plan['selected_axes']) < 2 || $plan['thesis'] === '' || $plan['applied_recipe'] === '') {
            $fallback = self::fallback($workflow, $base);
            foreach ($fallback as $key => $value) {
                if ($key === 'source') {
                    continue;
                }
                if (is_array($value)) {
                    if (empty($plan[$key])) {
                        $plan[$key] = $value;
                    }
                } elseif (trim((string) ($plan[$key] ?? '')) === '') {
                    $plan[$key] = $value;
                }
            }
            $plan['source'] = 'model+fallback';
        }
        return $plan;
    }

    public static function fallback(array $workflow, array $base): array {
        $keyword = trim((string) ($workflow['keyword'] ?? 'el caso'));
        $discipline = trim((string) ($base['discipline'] ?? $base['territory'] ?? 'diseño'));
        $axes = array_slice((array) ($base['available_axes'] ?? []), 0, 4);
        $experience = array_slice((array) ($base['experience_dimensions'] ?? []), 0, 3);
        $activated = array_slice((array) ($base['available_axes'] ?? []), 0, 6);
        $verbs = array_slice((array) ($base['natural_verbs'] ?? []), 0, 6);
        $conditioned = array_slice((array) ($base['conditional_terms'] ?? []), 0, 6);
        $entity = trim((string) ($workflow['entity'] ?? ''));
        $identity_required = !empty($base['identity_required']);

        $thesis = 'Explicar ' . $keyword . ' desde ' . $discipline . ', relacionando las decisiones documentadas con su efecto perceptivo y de uso.';
        if ($identity_required && $entity !== '') {
            $thesis = 'Explicar ' . $keyword . ' desde ' . $discipline . ' y mostrar cómo las decisiones documentadas expresan o transforman la identidad de ' . $entity . ', además de su efecto perceptivo y de uso.';
        }
        $identity = $identity_required
            ? ($entity !== '' ? 'Analizar la identidad de ' . $entity . ' únicamente mediante decisiones verificables de forma, material, proceso, interacción o uso.' : 'Analizar la identidad del autor o marca únicamente cuando exista evidencia verificable.')
            : 'No forzar identidad de autor u organizador; usarla solo cuando aporte contexto.';
        $translations = [];
        foreach ($experience as $item) {
            $translations[] = 'Decisión documentada → ' . $item . ' → consecuencia para la experiencia y el significado del proyecto.';
        }
        $links = class_exists('IDG_Internal_Links') ? IDG_Internal_Links::library_summary($workflow) : 'Integrar enlaces dentro de argumentos existentes; no crear párrafos de rescate.';
        $applied = 'Analizar ' . $keyword . ' desde ' . $discipline;
        if (!empty($axes)) {
            $applied .= ', priorizando ' . self::human_list($axes);
        }
        $applied .= ', y traducir cada decisión en percepción, uso y significado editorial';
        if ($identity_required) {
            $applied .= ', incluida la identidad del autor o marca';
        }
        $applied .= '.';

        $risks = array_slice((array) ($base['risks'] ?? []), 0, 5);
        $raw = implode("\n", [
            'TESIS EDITORIAL',
            $thesis,
            '',
            'LENTE DISCIPLINAR',
            $discipline,
            '',
            'IDENTIDAD DE AUTOR O MARCA',
            $identity,
            '',
            'CONCEPTOS ACTIVADOS',
            implode("\n", array_map(static fn($item) => '- ' . $item, $activated)),
            '',
            'EXPANSIONES SEMÁNTICAS DEL CASO',
            '- Sin expansión automática; la investigación puede aportar términos más precisos.',
            '',
            'VERBOS Y FORMULACIONES ÚTILES',
            implode("\n", array_map(static fn($item) => '- ' . $item, $verbs)),
            '',
            'TÉRMINOS CONDICIONADOS O DESCARTADOS',
            implode("\n", array_map(static fn($item) => '- ' . $item, $conditioned)),
            '',
            'EJES SELECCIONADOS',
            implode("\n", array_map(static fn($item) => '- ' . $item, $axes)),
            '',
            'TRADUCCIONES PERCEPTIVAS Y DE USO',
            implode("\n", array_map(static fn($item) => '- ' . $item, $translations)),
            '',
            'EJES DESCARTADOS',
            '- Sin descarte automático; revisar contra la ficha documental.',
            '',
            'RIESGOS EDITORIALES',
            implode("\n", array_map(static fn($item) => '- ' . $item, $risks)),
            '',
            'ESTRATEGIA DE ENLACES',
            $links,
            '',
            'RECETA APLICADA',
            $applied,
        ]);
        return [
            'thesis' => $thesis,
            'discipline' => $discipline,
            'identity' => $identity,
            'activated_concepts' => $activated,
            'semantic_expansions' => ['Sin expansión automática; la investigación puede aportar términos más precisos.'],
            'recommended_verbs' => $verbs,
            'conditioned_terms' => $conditioned,
            'selected_axes' => $axes,
            'perceptual_translations' => $translations,
            'discarded_axes' => ['Sin descarte automático; revisar contra la ficha documental.'],
            'risks' => $risks,
            'link_strategy' => $links,
            'applied_recipe' => $applied,
            'raw' => $raw,
            'source' => 'fallback',
        ];
    }

    public static function apply_to_workflow(array $workflow, array $plan, string $hash): array {
        $workflow['editorial_plan_raw'] = (string) ($plan['raw'] ?? '');
        $workflow['editorial_plan_hash'] = $hash;
        $workflow['editorial_plan_source'] = (string) ($plan['source'] ?? 'model');
        $workflow['editorial_plan_created_at'] = function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s');
        $workflow['editorial_thesis'] = (string) ($plan['thesis'] ?? '');
        $workflow['editorial_lens'] = (string) ($plan['discipline'] ?? '');
        $workflow['editorial_identity'] = (string) ($plan['identity'] ?? '');
        $workflow['editorial_activated_concepts'] = array_values((array) ($plan['activated_concepts'] ?? []));
        $workflow['editorial_semantic_expansions'] = array_values((array) ($plan['semantic_expansions'] ?? []));
        $workflow['editorial_recommended_verbs'] = array_values((array) ($plan['recommended_verbs'] ?? []));
        $workflow['editorial_conditioned_terms'] = array_values((array) ($plan['conditioned_terms'] ?? []));
        $workflow['editorial_selected_axes'] = array_values((array) ($plan['selected_axes'] ?? []));
        $workflow['editorial_perceptual_translations'] = array_values((array) ($plan['perceptual_translations'] ?? []));
        $workflow['editorial_discarded_axes'] = array_values((array) ($plan['discarded_axes'] ?? []));
        $workflow['editorial_plan_risks'] = array_values((array) ($plan['risks'] ?? []));
        $workflow['editorial_link_strategy'] = (string) ($plan['link_strategy'] ?? '');
        $workflow['editorial_recipe_applied'] = (string) ($plan['applied_recipe'] ?? '');
        return $workflow;
    }

    public static function from_workflow(array $workflow): array {
        return [
            'thesis' => (string) ($workflow['editorial_thesis'] ?? ''),
            'discipline' => (string) ($workflow['editorial_lens'] ?? ''),
            'identity' => (string) ($workflow['editorial_identity'] ?? ''),
            'activated_concepts' => array_values((array) ($workflow['editorial_activated_concepts'] ?? [])),
            'semantic_expansions' => array_values((array) ($workflow['editorial_semantic_expansions'] ?? [])),
            'recommended_verbs' => array_values((array) ($workflow['editorial_recommended_verbs'] ?? [])),
            'conditioned_terms' => array_values((array) ($workflow['editorial_conditioned_terms'] ?? [])),
            'selected_axes' => array_values((array) ($workflow['editorial_selected_axes'] ?? [])),
            'perceptual_translations' => array_values((array) ($workflow['editorial_perceptual_translations'] ?? [])),
            'discarded_axes' => array_values((array) ($workflow['editorial_discarded_axes'] ?? [])),
            'risks' => array_values((array) ($workflow['editorial_plan_risks'] ?? [])),
            'link_strategy' => (string) ($workflow['editorial_link_strategy'] ?? ''),
            'applied_recipe' => (string) ($workflow['editorial_recipe_applied'] ?? ''),
            'source' => (string) ($workflow['editorial_plan_source'] ?? ''),
        ];
    }

    public static function prompt_block(array $workflow): string {
        $plan = self::from_workflow($workflow);
        if (trim((string) $plan['applied_recipe']) === '') {
            return 'No hay plan editorial aplicado. Usa la receta base con cautela y no inventes ejes.';
        }
        $lines = [];
        $lines[] = 'Tesis editorial: ' . self::display((string) $plan['thesis']);
        $lines[] = 'Lente disciplinar: ' . self::display((string) $plan['discipline']);
        $lines[] = 'Identidad de autor/marca: ' . self::display((string) $plan['identity']);
        $lines[] = 'Conceptos activados: ' . self::display(implode('; ', (array) $plan['activated_concepts']));
        $lines[] = 'Expansiones semánticas detectadas en el caso: ' . self::display(implode('; ', (array) $plan['semantic_expansions']));
        $lines[] = 'Verbos y formulaciones útiles: ' . self::display(implode('; ', (array) $plan['recommended_verbs']));
        $lines[] = 'Términos condicionados o descartados: ' . self::display(implode('; ', (array) $plan['conditioned_terms']));
        $lines[] = 'Ejes seleccionados: ' . self::display(implode('; ', (array) $plan['selected_axes']));
        $lines[] = 'Traducciones perceptivas y de uso: ' . self::display(implode('; ', (array) $plan['perceptual_translations']));
        $lines[] = 'Ejes descartados: ' . self::display(implode('; ', (array) $plan['discarded_axes']));
        $lines[] = 'Riesgos: ' . self::display(implode('; ', (array) $plan['risks']));
        $lines[] = 'Estrategia de enlaces: ' . self::display((string) $plan['link_strategy']);
        $lines[] = 'Receta aplicada: ' . self::display((string) $plan['applied_recipe']);
        return implode("\n", $lines);
    }

    public static function hash(array $workflow, array $base): string {
        $parts = [
            (string) ($workflow['keyword'] ?? ''),
            (string) ($workflow['entity'] ?? ''),
            (string) ($workflow['brief_fact'] ?? ''),
            (string) ($workflow['editorial_angle'] ?? ''),
            (string) ($workflow['document_card_hash'] ?? ''),
            hash('sha256', (string) ($workflow['document_card'] ?? '')),
            hash('sha256', (string) ($workflow['web_research_card'] ?? '')),
            wp_json_encode($base),
            wp_json_encode($workflow['internal_links_structured'] ?? []),
        ];
        return hash('sha256', implode('|', $parts));
    }

    private static function sections(string $content): array {
        $map = [
            'tesis editorial' => 'thesis',
            'lente disciplinar' => 'discipline',
            'disciplina o lente' => 'discipline',
            'identidad de autor o marca' => 'identity',
            'identidad del autor o marca' => 'identity',
            'conceptos activados' => 'activated_concepts',
            'conceptos semanticos activados' => 'activated_concepts',
            'expansiones semanticas del caso' => 'semantic_expansions',
            'conceptos nuevos detectados' => 'semantic_expansions',
            'verbos y formulaciones utiles' => 'recommended_verbs',
            'verbos recomendados' => 'recommended_verbs',
            'terminos condicionados o descartados' => 'conditioned_terms',
            'terminos condicionados' => 'conditioned_terms',
            'ejes seleccionados' => 'selected_axes',
            'traducciones perceptivas y de uso' => 'perceptual',
            'traduccion perceptiva y de uso' => 'perceptual',
            'ejes descartados' => 'discarded_axes',
            'riesgos editoriales' => 'risks',
            'estrategia de enlaces' => 'links',
            'receta aplicada' => 'applied_recipe',
        ];
        $sections = [];
        $current = '';
        foreach (preg_split('/\R/u', str_replace(["\r\n", "\r"], "\n", $content)) as $line) {
            $trim = trim((string) $line);
            $heading = preg_replace('/^#{1,6}\s*/u', '', $trim);
            $heading = trim((string) preg_replace('/[:*]+$/u', '', (string) $heading));
            $key = self::plain($heading);
            if (isset($map[$key])) {
                $current = $map[$key];
                if (!isset($sections[$current])) $sections[$current] = [];
                continue;
            }
            if ($current !== '' && $trim !== '') {
                $sections[$current][] = $trim;
            }
        }
        return $sections;
    }

    private static function first_text(array $lines): string {
        $clean = [];
        foreach ($lines as $line) {
            $line = preg_replace('/^[-*]\s*/u', '', trim((string) $line));
            if ($line !== '') $clean[] = $line;
        }
        return trim(implode(' ', $clean));
    }

    private static function list_values(array $lines): array {
        $out = [];
        foreach ($lines as $line) {
            $line = trim((string) preg_replace('/^(?:[-*]|\d+[.)])\s*/u', '', (string) $line));
            if ($line === '') continue;
            foreach (preg_split('/\s*;\s*/u', $line, -1, PREG_SPLIT_NO_EMPTY) as $part) {
                $part = trim((string) $part);
                if ($part !== '') $out[] = $part;
            }
        }
        return array_values(array_unique($out));
    }

    private static function plain(string $text): string {
        $text = function_exists('remove_accents') ? remove_accents($text) : $text;
        $text = mb_strtolower(wp_strip_all_tags($text));
        $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', (string) $text);
        return trim((string) preg_replace('/\s+/u', ' ', (string) $text));
    }

    private static function human_list(array $items): string {
        $items = array_values(array_filter(array_map(static fn($v) => trim((string) $v), $items)));
        $count = count($items);
        if ($count === 0) return '';
        if ($count === 1) return $items[0];
        if ($count === 2) return $items[0] . ' y ' . $items[1];
        return implode(', ', array_slice($items, 0, -1)) . ' y ' . $items[$count - 1];
    }

    private static function display(string $value): string {
        $value = trim($value);
        return $value === '' ? 'Sin información' : $value;
    }
}
