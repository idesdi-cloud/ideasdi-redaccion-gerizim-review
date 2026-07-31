<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Motor universal de recetas v2.
 *
 * La receta base no es una frase ensamblada con instrucciones completas.
 * Se construye desde territorio + lente + preguntas + riesgos y después
 * se refina, tras la investigación, mediante IDG_Editorial_Plan.
 */
final class IDG_Editorial_Recipe_Builder {
    private static ?array $data = null;

    public static function build(array $workflow): array {
        $keyword = trim((string) ($workflow['keyword'] ?? ''));
        $category_name = self::category_name($workflow);
        $is_event = self::is_event_workflow($workflow);
        $is_contest = self::is_contest_category($category_name);

        if (class_exists('IDG_Disciplinary_Library')) {
            $semantic = IDG_Disciplinary_Library::resolve($workflow);
            $territory = trim((string) ($semantic['territory'] ?? $category_name));
            $discipline = trim((string) ($semantic['discipline'] ?? $territory));
            $primary_tag = trim((string) ($semantic['primary_tag'] ?? ''));
            $role = trim((string) ($semantic['primary_role'] ?? 'lente disciplinar'));
            $secondary_tags = array_values((array) ($semantic['secondary_tags'] ?? []));
            $axes = array_values((array) ($semantic['decisions'] ?? []));
            $effects = array_values((array) ($semantic['effects'] ?? []));
            $uses = array_values((array) ($semantic['uses'] ?? []));
            $experience = self::merge_terms($effects, $uses);
            $questions = array_values((array) ($semantic['questions'] ?? []));
            $risks = array_values((array) ($semantic['risks'] ?? []));
            $verbs = array_values((array) ($semantic['verbs'] ?? []));
            $conditional = array_values((array) ($semantic['conditional_terms'] ?? []));
            $avoid_generic = array_values((array) ($semantic['avoid_generic'] ?? []));
            $recipe = IDG_Disciplinary_Library::compact_recipe($workflow);
            $semantic_prompt = IDG_Disciplinary_Library::prompt_block($workflow);
            $tag_roles = array_values((array) ($semantic['tag_classifications'] ?? []));
        } else {
            $tag_names = self::tag_names($workflow);
            $primary_tag = self::primary_tag_name($workflow, $tag_names);
            $secondary_tags = self::secondary_tag_names($workflow, $tag_names, $primary_tag);
            $category = self::category_recipe($category_name);
            $lens = self::tag_lens($primary_tag, $category_name);
            $territory = trim((string) ($category['territory'] ?? $category_name));
            $discipline = trim((string) ($lens['discipline'] ?? $category['discipline'] ?? $territory));
            $role = self::lens_role($primary_tag, $category_name, $lens, $is_event);
            $axes = self::merge_terms((array) ($lens['axes'] ?? []), (array) ($category['axes'] ?? []));
            $experience = self::merge_terms((array) ($lens['experience'] ?? []), (array) ($category['experience'] ?? []));
            $questions = self::merge_terms((array) ($lens['questions'] ?? []), (array) ($category['questions'] ?? []));
            $risks = self::merge_terms((array) ($lens['risks'] ?? []), (array) ($category['risks'] ?? []));
            $verbs = [];
            $conditional = [];
            $avoid_generic = [];
            $recipe = self::compose_base_recipe([
                'keyword' => $keyword,
                'territory' => $territory,
                'discipline' => $discipline,
                'role' => $role,
                'axes' => $axes,
                'experience' => $experience,
                'identity_required' => false,
                'identity_prompt' => '',
                'risks' => $risks,
                'angle' => '',
                'is_event' => $is_event,
                'is_contest' => $is_contest,
            ]);
            $semantic_prompt = '';
            $tag_roles = [];
            $semantic = [];
        }

        $category = self::category_recipe($category_name);
        $identity_required = !$is_event && !$is_contest && !empty($category['identity_required']);
        $identity_prompt = trim((string) ($category['identity_prompt'] ?? ''));

        return [
            'recipe' => $recipe,
            'base_recipe' => $recipe,
            'territory' => $territory,
            'category_name' => $category_name,
            'discipline' => $discipline,
            'primary_tag' => $primary_tag,
            'primary_tag_role' => $role,
            'secondary_tags' => $secondary_tags,
            'available_axes' => array_slice($axes, 0, 14),
            'experience_dimensions' => array_slice($experience, 0, 12),
            'questions' => array_slice($questions, 0, 10),
            'identity_required' => $identity_required,
            'identity_prompt' => $identity_prompt,
            'risks' => array_slice($risks, 0, 10),
            'is_event' => $is_event,
            'is_contest' => $is_contest,
            'natural_verbs' => array_slice($verbs, 0, 14),
            'conditional_terms' => array_slice($conditional, 0, 12),
            'avoid_generic' => array_slice($avoid_generic, 0, 12),
            'tag_roles' => $tag_roles,
            'semantic_library_version' => (string) ($semantic['version'] ?? ''),
            'semantic_library_open' => !empty($semantic['open_guidance']),
            'semantic_context' => $semantic,
            'semantic_prompt' => $semantic_prompt,
            'lens_guidance' => (string) ($semantic['guidance'] ?? ''),
            'anchor_candidates' => [],
            'technical_summary' => self::technical_summary($workflow, $primary_tag, $secondary_tags, $role),
        ];
    }

    public static function recipe_text(array $workflow): string {
        $built = self::build($workflow);
        return trim((string) ($built['recipe'] ?? ''));
    }

    public static function prompt_structure(array $workflow): string {
        $built = self::build($workflow);
        if (class_exists('IDG_Disciplinary_Library')) {
            $lines = [IDG_Disciplinary_Library::prompt_block($workflow)];
            $lines[] = 'Identidad de autor/marca: ' . (!empty($built['identity_required']) ? 'analizar cuando haya evidencia verificable' : 'usar solo cuando aporte contexto real');
            $lines[] = 'Regla de selección: activar únicamente conceptos respaldados, registrar términos nuevos más precisos y descartar los que no tengan evidencia.';
            return implode("
", $lines);
        }
        return 'Territorio editorial: ' . self::display((string) ($built['territory'] ?? '')) . "
" .
            'Lente disciplinar: ' . self::display((string) ($built['discipline'] ?? '')) . "
" .
            'Riesgos: ' . self::display(implode('; ', (array) ($built['risks'] ?? [])));
    }

    public static function technical_summary(array $workflow, string $primary_tag = '', array $secondary_tags = [], string $role = ''): string {
        $lines = [];
        if (class_exists('IDG_Disciplinary_Library')) {
            $semantic = IDG_Disciplinary_Library::resolve($workflow);
            $lines[] = 'Biblioteca disciplinar: ' . self::display((string) ($semantic['version'] ?? ''));
            $lines[] = 'Territorio: ' . self::display((string) ($semantic['territory'] ?? ''));
            $lines[] = 'Lente principal: ' . self::display((string) ($semantic['primary_label'] ?? $semantic['primary_tag'] ?? ''));
            $lines[] = 'Rol editorial del lente: ' . self::display((string) ($semantic['primary_role'] ?? ''));
            $lines[] = 'Roles de tags: ' . IDG_Disciplinary_Library::classification_text((array) ($semantic['tag_classifications'] ?? []));
            if (!empty($semantic['theme_label'])) {
                $lines[] = 'Tema secundario: ' . (string) $semantic['theme_label'];
            }
        } else {
            $lines[] = 'Lente principal: ' . self::display($primary_tag);
            $lines[] = 'Rol editorial del lente: ' . self::display($role);
            $lines[] = 'Tags secundarios: ' . self::display(implode(', ', $secondary_tags));
        }
        if (self::is_event_workflow($workflow)) {
            $lines[] = 'Perfil editorial: ' . self::display((string) ($workflow['editorial_context_name'] ?? 'Calendario de eventos'));
            $lines[] = 'Categoría editorial del evento: ' . self::display((string) ($workflow['event_editorial_category'] ?? ''));
            $lines[] = 'Taxonomías del evento: ' . self::display(self::event_taxonomy_text($workflow));
        }
        if (class_exists('IDG_Internal_Links')) {
            $lines[] = 'Regla de enlace interno:';
            $lines[] = IDG_Internal_Links::library_summary($workflow);
        }
        return implode("
", $lines);
    }

    public static function admin_category_presets($categories): array {
        $out = [];
        if (is_wp_error($categories)) return $out;
        foreach ($categories as $cat) {
            $out[(string) $cat->term_id] = class_exists('IDG_Disciplinary_Library')
                ? IDG_Disciplinary_Library::admin_category_preset((string) $cat->name)
                : ['summary' => self::category_admin_text(self::category_recipe((string) $cat->name), (string) $cat->name), 'priority' => 10, 'role' => 'territory'];
        }
        return $out;
    }

    public static function admin_tag_presets($tags): array {
        $out = [];
        if (is_wp_error($tags)) return $out;
        foreach ($tags as $tag) {
            if (class_exists('IDG_Disciplinary_Library')) {
                $out[(string) $tag->term_id] = IDG_Disciplinary_Library::admin_tag_preset((string) $tag->name);
            } else {
                $row = self::tag_lens((string) $tag->name, '');
                $out[(string) $tag->term_id] = ['summary' => trim((string) ($row['discipline'] ?? $row['guidance'] ?? '')), 'priority' => 50, 'role' => 'lens'];
            }
        }
        return $out;
    }

    private static function compose_base_recipe(array $context): string {
        $territory = trim((string) ($context['territory'] ?? 'diseño'));
        $discipline = trim((string) ($context['discipline'] ?? $territory));
        $axes = array_slice((array) ($context['axes'] ?? []), 0, 7);
        $experience = array_slice((array) ($context['experience'] ?? []), 0, 4);
        $risks = array_slice((array) ($context['risks'] ?? []), 0, 3);
        $angle = trim((string) ($context['angle'] ?? ''));

        $parts = [];
        $parts[] = 'Territorio: ' . self::sentence_fragment($territory) . '; lente principal: ' . self::sentence_fragment($discipline) . '.';
        if (!empty($axes)) {
            $parts[] = 'Explorar ' . self::human_list($axes) . ' y seleccionar únicamente los ejes respaldados por la investigación.';
        }
        if (!empty($experience)) {
            $parts[] = 'Traducir las decisiones verificadas en ' . self::human_list($experience) . ', explicando su efecto perceptivo y de uso.';
        }
        if (!empty($context['identity_required'])) {
            $parts[] = 'Relacionar esas decisiones con la identidad del diseñador, estudio o marca, sin atribuir intenciones no documentadas.';
        }
        if ($angle !== '') {
            $parts[] = 'Ángulo aportado por el editor: ' . rtrim($angle, '. ') . '.';
        }
        if (!empty($risks)) {
            $parts[] = 'Evitar ' . self::human_list($risks) . '.';
        }
        return trim(implode(' ', $parts));
    }

    private static function category_admin_text(array $row, string $fallback): string {
        $territory = trim((string) ($row['territory'] ?? $fallback));
        $axes = array_slice((array) ($row['axes'] ?? []), 0, 5);
        return $territory . (empty($axes) ? '' : ': ' . implode(', ', $axes));
    }

    private static function category_name(array $workflow): string {
        if (self::is_event_workflow($workflow)) {
            return 'Calendario de eventos';
        }
        if (!empty($workflow['editorial_context_name'])) {
            return (string) $workflow['editorial_context_name'];
        }
        if (!empty($workflow['category_name'])) {
            return (string) $workflow['category_name'];
        }
        if (!empty($workflow['category_id'])) {
            $term = get_term((int) $workflow['category_id'], 'category');
            if ($term && !is_wp_error($term)) {
                return (string) $term->name;
            }
        }
        return '';
    }

    private static function tag_names(array $workflow): array {
        if (!empty($workflow['tag_names']) && is_array($workflow['tag_names'])) {
            return array_values(array_filter(array_map('strval', $workflow['tag_names'])));
        }
        $names = [];
        if (!empty($workflow['tag_ids']) && is_array($workflow['tag_ids'])) {
            foreach ($workflow['tag_ids'] as $tag_id) {
                $term = get_term((int) $tag_id, 'post_tag');
                if ($term && !is_wp_error($term)) {
                    $names[] = (string) $term->name;
                }
            }
        }
        if (!empty($workflow['radar_tag_principal']) && !self::contains_name($names, (string) $workflow['radar_tag_principal'])) {
            array_unshift($names, (string) $workflow['radar_tag_principal']);
        }
        return array_values(array_unique(array_filter($names)));
    }

    private static function primary_tag_name(array $workflow, array $tag_names): string {
        $radar = trim((string) ($workflow['radar_tag_principal'] ?? ''));
        if ($radar !== '') {
            foreach ($tag_names as $name) {
                if (self::same_name((string) $name, $radar)) {
                    return (string) $name;
                }
            }
            return $radar;
        }
        // Preferir el primer tag que no sea operativo/noindex como lente editorial.
        $category = self::category_name($workflow);
        foreach ($tag_names as $name) {
            if (!class_exists('IDG_Priority_Readings') || !IDG_Priority_Readings::is_noindex_tag_name((string) $name, $category)) {
                return (string) $name;
            }
        }
        return (string) ($tag_names[0] ?? '');
    }

    private static function secondary_tag_names(array $workflow, array $tag_names, string $primary_tag): array {
        if (!empty($workflow['radar_tags_secundarios']) && is_array($workflow['radar_tags_secundarios'])) {
            return array_slice(array_values(array_filter(array_map('strval', $workflow['radar_tags_secundarios']))), 0, 3);
        }
        $out = [];
        foreach ($tag_names as $name) {
            if ($primary_tag !== '' && self::same_name((string) $name, $primary_tag)) {
                continue;
            }
            $out[] = (string) $name;
            if (count($out) >= 3) {
                break;
            }
        }
        return $out;
    }

    private static function category_recipe(string $name): array {
        foreach ((self::data()['categories'] ?? []) as $row) {
            foreach ((array) ($row['names'] ?? []) as $candidate) {
                if (self::same_name((string) $candidate, $name)) {
                    return $row;
                }
            }
        }
        $fallback = class_exists('IDG_Priority_Readings') ? IDG_Priority_Readings::preset_for_category_name($name) : '';
        return [
            'territory' => $name !== '' ? $name : 'diseño',
            'discipline' => $name !== '' ? $name : 'diseño',
            'axes' => self::extract_axes($fallback),
            'experience' => ['percepción', 'uso cotidiano', 'relación con el contexto'],
            'questions' => ['¿Qué decisiones de diseño definen el caso?', '¿Cómo afectan la percepción y el uso?'],
            'identity_required' => !self::is_contest_category($name) && !self::is_event_category($name),
            'identity_prompt' => 'relacionar las decisiones verificadas con la identidad del autor o marca',
            'risks' => ['convertir la pieza en descripción genérica'],
        ];
    }

    private static function event_lens(string $name): array {
        foreach ((self::data()['event_lenses'] ?? []) as $candidate => $row) {
            if (self::same_name((string) $candidate, $name)) {
                return $row + ['guidance' => 'Usar la categoría editorial del evento como lente temática sin crear una categoría WordPress.'];
            }
        }
        return [
            'discipline' => $name !== '' ? $name : 'agenda editorial de diseño',
            'axes' => [],
            'experience' => [],
            'guidance' => 'Usar la categoría editorial del evento como lente temática.',
        ];
    }

    private static function tag_lens(string $name, string $category_name): array {
        if ($name === '') {
            return [];
        }
        foreach ((self::data()['tags'] ?? []) as $row) {
            foreach ((array) ($row['names'] ?? []) as $candidate) {
                if (self::same_name((string) $candidate, $name)) {
                    return $row + ['guidance' => 'Lente estructurado: ' . (string) ($row['discipline'] ?? $name) . '.'];
                }
            }
        }
        $matrix = class_exists('IDG_Priority_Readings') ? IDG_Priority_Readings::matrix_row_for_public($name, $category_name) : [];
        $reading = trim((string) ($matrix['reading'] ?? ''));
        if ($reading === '' && class_exists('IDG_Priority_Readings')) {
            $reading = IDG_Priority_Readings::preset_for_tag_name($name, $category_name);
        }
        return [
            'discipline' => $name,
            'axes' => self::extract_axes($reading),
            'experience' => [],
            'questions' => [],
            'risks' => self::risks_from_reading($reading),
            'guidance' => self::clean_instruction($reading),
            'status' => (string) ($matrix['status'] ?? ''),
        ];
    }

    private static function lens_role(string $tag, string $category, array $lens, bool $is_event): string {
        if ($is_event) {
            return 'lente temática del evento';
        }
        if (!empty($lens['role'])) {
            return (string) $lens['role'];
        }
        if ($tag === '') {
            return 'territorio de categoría';
        }
        if (class_exists('IDG_Priority_Readings') && IDG_Priority_Readings::is_noindex_tag_name($tag, $category)) {
            return 'contextual / operativo';
        }
        return 'lente disciplinar';
    }

    private static function secondary_axes(array $tags, string $category): array {
        $axes = [];
        foreach ($tags as $tag) {
            $lens = self::tag_lens((string) $tag, $category);
            foreach (array_slice((array) ($lens['axes'] ?? []), 0, 2) as $axis) {
                $axes[] = (string) $axis;
            }
        }
        return $axes;
    }

    private static function extract_axes(string $reading): array {
        $text = self::clean_instruction($reading);
        if ($text === '') {
            return [];
        }
        $text = preg_replace('/\b(?:cuando|siempre que|solo cuando|sin|evitando|para evitar)\b.*$/iu', '', $text);
        $text = str_replace([';', ':'], ',', (string) $text);
        $text = preg_replace('/\s+y\s+/iu', ', ', (string) $text);
        $parts = preg_split('/\s*,\s*/u', (string) $text, -1, PREG_SPLIT_NO_EMPTY);
        $out = [];
        foreach ((array) $parts as $part) {
            $part = trim((string) preg_replace('/^(?:el|la|los|las|un|una)\s+/iu', '', (string) $part), " .\t\n\r\0\x0B");
            if ($part === '' || mb_strlen($part) > 80) {
                continue;
            }
            $out[] = $part;
        }
        return self::merge_terms($out);
    }

    private static function risks_from_reading(string $reading): array {
        $plain = self::plain($reading);
        if (str_contains($plain, 'evitar')) {
            $pos = mb_strpos($plain, 'evitar');
            $risk = trim(mb_substr($reading, (int) $pos + 6), " .\t\n\r\0\x0B");
            if ($risk !== '') {
                return [$risk];
            }
        }
        if (str_contains($plain, 'no index') || str_contains($plain, 'operativo')) {
            return ['forzar un tag operativo como tema principal'];
        }
        return [];
    }

    private static function merge_terms(array ...$groups): array {
        $out = [];
        $seen = [];
        foreach ($groups as $group) {
            foreach ($group as $term) {
                $term = trim((string) $term, " .\t\n\r\0\x0B");
                if ($term === '') {
                    continue;
                }
                $key = self::plain($term);
                if ($key === '' || isset($seen[$key])) {
                    continue;
                }
                // Evitar redundancias simples entre singular/plural o frases contenidas.
                $redundant = false;
                foreach (array_keys($seen) as $existing) {
                    if (mb_strlen($key) >= 5 && mb_strlen($existing) >= 5 && (str_contains($key, $existing) || str_contains($existing, $key))) {
                        $redundant = true;
                        break;
                    }
                }
                if ($redundant) {
                    continue;
                }
                $seen[$key] = true;
                $out[] = $term;
            }
        }
        return $out;
    }

    private static function clean_instruction(string $text): string {
        $text = trim(wp_strip_all_tags($text));
        $text = preg_replace('/^(?:leer|analizar|usar|priorizar|observar|explorar)(?:\s+(?:el|la|los|las|un|una|como))?\s*/iu', '', (string) $text);
        $text = preg_replace('/^(?:desde|a partir de|con foco en)\s+/iu', '', (string) $text);
        return trim((string) $text, " .\t\n\r\0\x0B");
    }

    private static function human_list(array $items): string {
        $items = array_values(array_filter(array_map(static fn($v) => trim((string) $v), $items)));
        $count = count($items);
        if ($count === 0) return '';
        if ($count === 1) return $items[0];
        if ($count === 2) return $items[0] . ' y ' . $items[1];
        return implode(', ', array_slice($items, 0, -1)) . ' y ' . $items[$count - 1];
    }

    private static function sentence_fragment(string $text): string {
        return trim(self::clean_instruction($text));
    }

    private static function contains_name(array $names, string $needle): bool {
        foreach ($names as $name) {
            if (self::same_name((string) $name, $needle)) {
                return true;
            }
        }
        return false;
    }

    private static function same_name(string $a, string $b): bool {
        return self::plain($a) === self::plain($b);
    }

    private static function plain(string $value): string {
        $value = function_exists('remove_accents') ? remove_accents($value) : $value;
        $value = mb_strtolower(wp_strip_all_tags($value));
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', (string) $value);
        return trim((string) preg_replace('/\s+/u', ' ', (string) $value));
    }

    private static function is_contest_category(string $name): bool {
        $plain = self::plain($name);
        return str_contains($plain, 'concurso') || str_contains($plain, 'convocatoria');
    }

    private static function is_event_category(string $name): bool {
        $plain = self::plain($name);
        return str_contains($plain, 'evento') || str_contains($plain, 'agenda') || str_contains($plain, 'calendario');
    }

    private static function is_event_workflow(array $workflow): bool {
        return (string) ($workflow['editorial_context'] ?? '') === 'event_calendar'
            || ((string) ($workflow['workflow_origin'] ?? '') === 'recurring_update' && (string) ($workflow['recurring_target_post_type'] ?? '') === 'evento');
    }

    private static function event_taxonomy_text(array $workflow): string {
        $parts = [];
        foreach ((array) ($workflow['event_taxonomy_context'] ?? []) as $row) {
            if (!is_array($row)) continue;
            $names = [];
            foreach ((array) ($row['terms'] ?? []) as $term) {
                if (is_array($term) && trim((string) ($term['name'] ?? '')) !== '') {
                    $names[] = trim((string) $term['name']);
                }
            }
            if (!empty($names)) {
                $parts[] = trim((string) ($row['label'] ?? $row['taxonomy'] ?? 'Taxonomía')) . ': ' . implode(', ', array_values(array_unique($names)));
            }
        }
        return implode(' · ', $parts);
    }

    private static function display(string $value): string {
        $value = trim($value);
        return $value === '' ? 'Sin información' : $value;
    }

    private static function data(): array {
        if (self::$data !== null) {
            return self::$data;
        }
        $file = IDG_PLUGIN_DIR . 'includes/data/editorial-recipes.php';
        $data = is_readable($file) ? require $file : [];
        self::$data = is_array($data) ? $data : [];
        return self::$data;
    }
}
