<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Resuelve categoría, roles de tags y familia disciplinar.
 * La biblioteca es una guía abierta: no limita vocabulario y la evidencia
 * encontrada por el modelo prevalece sobre cualquier término sugerido.
 */
final class IDG_Disciplinary_Library {
    private static ?array $data = null;

    public static function resolve(array $workflow): array {
        $category = self::category_name($workflow);
        $is_event = self::is_event_workflow($workflow);
        if ($is_event) {
            $category = 'Calendario de eventos';
        }
        $tags = self::tag_names($workflow);
        $entity = trim((string) ($workflow['entity'] ?? ''));
        $suggested = trim((string) ($workflow['radar_lente_sugerida'] ?? $workflow['lens_suggested'] ?? ''));
        $classifications = [];

        foreach ($tags as $tag) {
            $classifications[] = self::classify_tag((string) $tag, $category, $entity);
        }
        if ($suggested !== '' && !self::contains_tag($tags, $suggested)) {
            $suggested_row = self::classify_tag($suggested, $category, $entity);
            $suggested_row['suggested'] = true;
            $classifications[] = $suggested_row;
        }

        $primary_family = '';
        $primary_tag = '';
        $primary_role = 'territorio de categoría';
        $event_type = '';
        $theme_family = '';

        if ($is_event) {
            $event_type = self::detect_event_family($workflow, $classifications);
            $primary_family = $event_type !== '' ? $event_type : 'event.general';
            $primary_tag = self::family_label($primary_family);
            $primary_role = 'tipología de evento';
            $theme_family = self::event_theme_family($workflow, $classifications);
        } else {
            $winner = self::choose_primary($classifications);
            if (!empty($winner['family'])) {
                $primary_family = (string) $winner['family'];
                $primary_tag = (string) ($winner['tag'] ?? self::family_label($primary_family));
                $primary_role = self::role_label((string) ($winner['role'] ?? 'lens'));
            }
            if ($primary_family === '') {
                $primary_family = self::category_default($category);
                $primary_tag = self::family_label($primary_family);
            }
        }

        if ($primary_family === '') {
            $primary_family = 'product.general';
        }
        $profile = self::family($primary_family);
        $theme_profile = $theme_family !== '' ? self::family($theme_family) : [];
        $modifiers = self::modifier_profiles($classifications);
        $secondary_tags = [];
        foreach ($classifications as $row) {
            $tag = trim((string) ($row['tag'] ?? ''));
            if ($tag === '' || self::same($tag, $primary_tag)) {
                continue;
            }
            $secondary_tags[] = $tag . ' (' . self::role_label((string) ($row['role'] ?? 'unknown')) . ')';
        }

        $decisions = self::merge(
            (array) ($profile['decisions'] ?? []),
            array_slice((array) ($theme_profile['decisions'] ?? []), 0, 4),
            self::modifier_terms($modifiers, 'terms')
        );
        $effects = self::merge((array) ($profile['effects'] ?? []), array_slice((array) ($theme_profile['effects'] ?? []), 0, 3));
        $uses = self::merge((array) ($profile['uses'] ?? []), array_slice((array) ($theme_profile['uses'] ?? []), 0, 3));
        $verbs = self::merge((array) ($profile['verbs'] ?? []), array_slice((array) ($theme_profile['verbs'] ?? []), 0, 3), self::modifier_terms($modifiers, 'verbs'));
        $conditional = self::merge((array) ($profile['conditional'] ?? []), (array) ($theme_profile['conditional'] ?? []), self::modifier_terms($modifiers, 'conditional'));
        $questions = self::merge((array) ($profile['questions'] ?? []), array_slice((array) ($theme_profile['questions'] ?? []), 0, 2));
        $risks = self::merge((array) ($profile['risks'] ?? []), (array) ($theme_profile['risks'] ?? []), self::modifier_terms($modifiers, 'risks'));
        $avoid = self::merge((array) ($profile['avoid_generic'] ?? []), (array) ($theme_profile['avoid_generic'] ?? []));

        return [
            'version' => 'disciplinary-library-v1.0.0-RC1.5.2',
            'open_guidance' => true,
            'category' => $category,
            'territory' => self::territory_label($category),
            'primary_tag' => $primary_tag,
            'primary_role' => $primary_role,
            'primary_family' => $primary_family,
            'primary_label' => (string) ($profile['label'] ?? self::family_label($primary_family)),
            'discipline' => (string) ($profile['discipline'] ?? self::territory_label($category)),
            'event_type_family' => $event_type,
            'theme_family' => $theme_family,
            'theme_label' => (string) ($theme_profile['label'] ?? ''),
            'tag_classifications' => $classifications,
            'secondary_tags' => $secondary_tags,
            'modifiers' => array_values(array_map(static fn(array $row): string => (string) ($row['label'] ?? ''), $modifiers)),
            'decisions' => array_slice($decisions, 0, 14),
            'effects' => array_slice($effects, 0, 10),
            'uses' => array_slice($uses, 0, 10),
            'verbs' => array_slice($verbs, 0, 14),
            'conditional_terms' => array_slice($conditional, 0, 12),
            'questions' => array_slice($questions, 0, 8),
            'risks' => array_slice($risks, 0, 10),
            'avoid_generic' => array_slice($avoid, 0, 12),
            'guidance' => 'Referencias semánticas sugeridas y no exhaustivas. No insertarlas por obligación. La investigación puede añadir términos más precisos; la evidencia del caso prevalece sobre la biblioteca.',
        ];
    }

    public static function prompt_block(array $workflow): string {
        $ctx = self::resolve($workflow);
        $lines = [];
        $lines[] = 'Biblioteca disciplinar: ' . (string) ($ctx['version'] ?? 'v1');
        $lines[] = 'Principio: referencias abiertas, no exhaustivas y no obligatorias. La evidencia y los términos más precisos del caso prevalecen.';
        $lines[] = 'Territorio: ' . self::display((string) ($ctx['territory'] ?? ''));
        $lines[] = 'Lente aplicada: ' . self::display((string) ($ctx['primary_label'] ?? '')) . ' · rol: ' . self::display((string) ($ctx['primary_role'] ?? ''));
        if (!empty($ctx['theme_label'])) {
            $lines[] = 'Tema disciplinar secundario: ' . (string) $ctx['theme_label'];
        }
        $lines[] = 'Roles de tags: ' . self::classification_text((array) ($ctx['tag_classifications'] ?? []));
        $lines[] = 'Decisiones posibles, solo si la fuente las respalda: ' . self::display(implode('; ', (array) ($ctx['decisions'] ?? [])));
        $lines[] = 'Efectos y usos posibles: ' . self::display(implode('; ', self::merge((array) ($ctx['effects'] ?? []), (array) ($ctx['uses'] ?? []))));
        $lines[] = 'Verbos disciplinares sugeridos: ' . self::display(implode('; ', (array) ($ctx['verbs'] ?? [])));
        $lines[] = 'Términos condicionados que requieren evidencia: ' . self::display(implode('; ', (array) ($ctx['conditional_terms'] ?? [])));
        $lines[] = 'Abstracciones o muletillas a revisar: ' . self::display(implode('; ', (array) ($ctx['avoid_generic'] ?? [])));
        $lines[] = 'Preguntas orientativas: ' . self::display(implode(' ', (array) ($ctx['questions'] ?? [])));
        return implode("\n", $lines);
    }

    public static function compact_recipe(array $workflow): string {
        $ctx = self::resolve($workflow);
        $discipline = (string) ($ctx['discipline'] ?? 'diseño');
        $questions = array_slice((array) ($ctx['questions'] ?? []), 0, 2);
        $risks = array_slice((array) ($ctx['risks'] ?? []), 0, 2);
        $parts = [];
        if (self::is_event_workflow($workflow)) {
            $event_label = mb_strtolower((string) ($ctx['primary_label'] ?? 'evento de diseño'));
            $parts[] = 'Tratar la pieza como ' . $event_label . '.';
            $theme_label = trim((string) ($ctx['theme_label'] ?? ''));
            if ($theme_label !== '' && !self::same($theme_label, $event_label)) {
                $parts[] = 'Aplicar una lectura temática de ' . mb_strtolower($theme_label) . ' con términos propios de esa disciplina.';
            }
            $parts[] = 'Priorizar datos y contenidos confirmados de esta edición, diferenciándolos del contexto general del evento.';
        } else {
            $parts[] = 'Leer el caso desde ' . $discipline . '.';
            if (!empty($questions)) {
                $parts[] = 'Orientar la investigación con estas preguntas: ' . implode(' ', $questions);
            }
        }
        $parts[] = 'La biblioteca es una guía abierta: usar solo conceptos respaldados y añadir términos más precisos cuando la documentación lo permita.';
        if (!empty($risks)) {
            $parts[] = 'Evitar ' . self::human_list($risks) . '.';
        }
        return trim(implode(' ', $parts));
    }

    public static function classification_text(array $rows): string {
        $parts = [];
        foreach ($rows as $row) {
            $tag = trim((string) ($row['tag'] ?? ''));
            if ($tag === '') continue;
            $parts[] = $tag . ' → ' . self::role_label((string) ($row['role'] ?? 'unknown'));
        }
        return empty($parts) ? 'Sin tags clasificables; se usa la familia de categoría.' : implode(' · ', $parts);
    }

    public static function admin_category_preset(string $category): array {
        $canonical = self::canonical_category($category);
        $family_key = self::category_default($canonical);
        $profile = self::family($family_key);
        $discipline = (string) ($profile['discipline'] ?? self::territory_label($canonical));
        return [
            'label' => $canonical,
            'role' => 'territory',
            'role_label' => self::role_label('territory'),
            'family' => $family_key,
            'discipline' => $discipline,
            'priority' => 10,
            'summary' => 'Leer el caso desde ' . $discipline . '. La investigación determinará la lente específica y los conceptos respaldados.',
        ];
    }

    public static function admin_tag_preset(string $tag): array {
        $best = [];
        $best_priority = -1;
        foreach ((array) (self::data()['tag_registry'] ?? []) as $row) {
            $matches = false;
            foreach ((array) ($row['names'] ?? []) as $name) {
                if (self::same((string) $name, $tag)) {
                    $matches = true;
                    break;
                }
            }
            if (!$matches) continue;
            $priority = self::role_priority((string) ($row['role'] ?? 'unknown'));
            if ($priority > $best_priority) {
                $best_priority = $priority;
                $best = $row;
            }
        }
        $role = (string) ($best['role'] ?? 'unclassified');
        $family_key = (string) ($best['family'] ?? '');
        $profile = $family_key !== '' ? self::family($family_key) : [];
        $discipline = (string) ($profile['discipline'] ?? '');
        $label = (string) ($profile['label'] ?? $tag);
        $summary = '';
        if (in_array($role, ['lens', 'format_lens', 'typology', 'theme'], true) && $discipline !== '') {
            $summary = 'Leer el caso desde ' . $discipline . '. La biblioteca es una guía abierta y la evidencia puede aportar términos más precisos.';
        } elseif ($role === 'modifier') {
            $summary = 'Usar ' . $tag . ' solo como modificador cuando la documentación lo respalde.';
        }
        return [
            'label' => $label,
            'tag' => $tag,
            'role' => $role,
            'role_label' => self::role_label($role),
            'family' => $family_key,
            'discipline' => $discipline,
            'priority' => max(0, $best_priority),
            'summary' => $summary,
        ];
    }

    public static function inventory_coverage(): array {
        $out = [];
        foreach ((array) (self::data()['tag_registry'] ?? []) as $row) {
            foreach ((array) ($row['names'] ?? []) as $name) {
                $out[] = [
                    'category' => (string) ($row['category'] ?? '*'),
                    'tag' => (string) $name,
                    'role' => (string) ($row['role'] ?? ''),
                    'family' => (string) ($row['family'] ?? ''),
                    'modifier' => (string) ($row['modifier'] ?? ''),
                ];
            }
        }
        return $out;
    }

    private static function classify_tag(string $tag, string $category, string $entity): array {
        if ($tag === '') {
            return ['tag' => '', 'role' => 'unknown', 'family' => '', 'modifier' => '', 'priority' => 0];
        }
        if ($entity !== '' && (self::same($tag, $entity) || self::entity_contains($entity, $tag))) {
            return ['tag' => $tag, 'role' => 'entity', 'family' => '', 'modifier' => '', 'priority' => 0];
        }
        $match = self::registry_match($tag, $category);
        if (!empty($match)) {
            $role = (string) ($match['role'] ?? 'unknown');
            return [
                'tag' => $tag,
                'role' => $role,
                'family' => (string) ($match['family'] ?? ''),
                'modifier' => (string) ($match['modifier'] ?? ''),
                'priority' => self::role_priority($role),
            ];
        }
        if (self::same($tag, $category) || self::same($tag, self::territory_label($category))) {
            return ['tag' => $tag, 'role' => 'territory', 'family' => self::category_default($category), 'modifier' => '', 'priority' => 10];
        }
        if (class_exists('IDG_Priority_Readings') && IDG_Priority_Readings::is_noindex_tag_name($tag, $category)) {
            return ['tag' => $tag, 'role' => 'operational', 'family' => '', 'modifier' => '', 'priority' => 0];
        }
        return ['tag' => $tag, 'role' => 'unclassified', 'family' => '', 'modifier' => '', 'priority' => 5];
    }

    private static function choose_primary(array $rows): array {
        $best = [];
        $best_priority = -1;
        foreach ($rows as $row) {
            $priority = (int) ($row['priority'] ?? 0);
            if (!empty($row['suggested']) && in_array((string) ($row['role'] ?? ''), ['lens', 'format_lens'], true)) {
                $priority += 15;
            }
            if (trim((string) ($row['family'] ?? '')) === '') continue;
            if ($priority > $best_priority) {
                $best_priority = $priority;
                $best = $row;
            }
        }
        return $best;
    }

    private static function detect_event_family(array $workflow, array $rows): string {
        foreach ($rows as $row) {
            if ((string) ($row['role'] ?? '') === 'format_lens' && str_starts_with((string) ($row['family'] ?? ''), 'event.')) {
                return (string) $row['family'];
            }
        }
        $haystack = self::plain(implode(' ', [
            (string) ($workflow['keyword'] ?? ''),
            (string) ($workflow['recurring_target_title'] ?? ''),
            (string) ($workflow['brief_fact'] ?? ''),
        ]));
        foreach ((array) (self::data()['event_type_rules'] ?? []) as $family => $patterns) {
            foreach ((array) $patterns as $pattern) {
                if ($pattern !== '' && str_contains($haystack, self::plain((string) $pattern))) {
                    return (string) $family;
                }
            }
        }
        return 'event.general';
    }

    private static function event_theme_family(array $workflow, array $rows): string {
        $editorial = trim((string) ($workflow['event_editorial_category'] ?? ''));
        if ($editorial !== '') {
            $match = self::registry_match($editorial, 'Calendario de eventos');
            if (!empty($match['family']) && !str_starts_with((string) $match['family'], 'event.')) {
                return (string) $match['family'];
            }
            $default = self::category_default($editorial);
            if ($default !== '') return $default;
        }
        foreach ($rows as $row) {
            if ((string) ($row['role'] ?? '') === 'theme' && !empty($row['family'])) {
                return (string) $row['family'];
            }
        }
        return '';
    }

    private static function registry_match(string $tag, string $category): array {
        $wildcard = [];
        foreach ((array) (self::data()['tag_registry'] ?? []) as $row) {
            $row_category = (string) ($row['category'] ?? '*');
            $matches_name = false;
            foreach ((array) ($row['names'] ?? []) as $name) {
                if (self::same((string) $name, $tag)) {
                    $matches_name = true;
                    break;
                }
            }
            if (!$matches_name) continue;
            if ($row_category === '*') {
                $wildcard = $row;
                continue;
            }
            if (self::category_same($row_category, $category)) {
                return $row;
            }
        }
        return $wildcard;
    }

    private static function modifier_profiles(array $rows): array {
        $out = [];
        $seen = [];
        foreach ($rows as $row) {
            $key = trim((string) ($row['modifier'] ?? ''));
            if ($key === '' || isset($seen[$key])) continue;
            $profile = (array) (self::data()['modifiers'][$key] ?? []);
            if (empty($profile)) continue;
            $seen[$key] = true;
            $out[] = $profile;
        }
        return $out;
    }

    private static function modifier_terms(array $profiles, string $key): array {
        $out = [];
        foreach ($profiles as $profile) {
            $out = self::merge($out, (array) ($profile[$key] ?? []));
        }
        return $out;
    }

    private static function family(string $key): array {
        return (array) (self::data()['families'][$key] ?? []);
    }

    private static function family_label(string $key): string {
        return (string) (self::family($key)['label'] ?? $key);
    }

    private static function category_default(string $category): string {
        foreach ((array) (self::data()['category_defaults'] ?? []) as $name => $family) {
            if (self::category_same((string) $name, $category)) return (string) $family;
        }
        return '';
    }

    private static function territory_label(string $category): string {
        $map = [
            'Diseño de producto' => 'diseño de producto',
            'Arquitectura e interiores' => 'arquitectura e interiores',
            'Moda' => 'moda',
            'Movilidad y transporte' => 'movilidad y transporte',
            'Diseño digital y 3D' => 'diseño digital y 3D',
            'Concursos y convocatorias' => 'concursos y convocatorias',
            'Calendario de eventos' => 'calendario de eventos',
        ];
        foreach ($map as $name => $label) {
            if (self::category_same($name, $category)) return $label;
        }
        return $category !== '' ? mb_strtolower($category) : 'diseño';
    }

    private static function role_priority(string $role): int {
        return [
            'lens' => 100,
            'format_lens' => 95,
            'typology' => 80,
            'theme' => 70,
            'context' => 35,
            'modifier' => 30,
            'territory' => 10,
            'unclassified' => 5,
            'operational' => 0,
            'entity' => 0,
        ][$role] ?? 0;
    }

    private static function role_label(string $role): string {
        return [
            'lens' => 'lente disciplinar',
            'format_lens' => 'formato con función de lente',
            'typology' => 'tipología',
            'theme' => 'tema disciplinar',
            'context' => 'contexto',
            'modifier' => 'modificador condicionado',
            'territory' => 'territorio general',
            'operational' => 'operativo / SEO',
            'entity' => 'entidad',
            'unclassified' => 'sin clasificar; no actúa como lente',
        ][$role] ?? $role;
    }

    private static function category_name(array $workflow): string {
        if (self::is_event_workflow($workflow)) return 'Calendario de eventos';
        if (!empty($workflow['category_name'])) return self::canonical_category((string) $workflow['category_name']);
        if (!empty($workflow['editorial_context_name'])) return self::canonical_category((string) $workflow['editorial_context_name']);
        if (!empty($workflow['category_id'])) {
            $term = get_term((int) $workflow['category_id'], 'category');
            if ($term && !is_wp_error($term)) return self::canonical_category((string) $term->name);
        }
        return '';
    }

    private static function canonical_category(string $name): string {
        $plain = self::plain($name);
        if (str_contains($plain, 'producto')) return 'Diseño de producto';
        if (str_contains($plain, 'arquitect') || str_contains($plain, 'interior')) return 'Arquitectura e interiores';
        if ($plain === 'moda' || str_contains($plain, 'diseno de moda')) return 'Moda';
        if (str_contains($plain, 'movilidad') || str_contains($plain, 'transporte')) return 'Movilidad y transporte';
        if (str_contains($plain, 'digital') || str_contains($plain, '3d')) return 'Diseño digital y 3D';
        if (str_contains($plain, 'concurso') || str_contains($plain, 'convocatoria')) return 'Concursos y convocatorias';
        if (str_contains($plain, 'evento') || str_contains($plain, 'agenda') || str_contains($plain, 'calendario')) return 'Calendario de eventos';
        return $name;
    }

    private static function tag_names(array $workflow): array {
        $names = [];
        if (!empty($workflow['tag_names']) && is_array($workflow['tag_names'])) {
            $names = array_values(array_filter(array_map('strval', $workflow['tag_names'])));
        } elseif (!empty($workflow['tag_ids']) && is_array($workflow['tag_ids'])) {
            foreach ($workflow['tag_ids'] as $id) {
                $term = get_term((int) $id, 'post_tag');
                if ($term && !is_wp_error($term)) $names[] = (string) $term->name;
            }
        }
        if (!empty($workflow['radar_tag_principal'])) array_unshift($names, (string) $workflow['radar_tag_principal']);
        foreach ((array) ($workflow['radar_tags_secundarios'] ?? []) as $tag) $names[] = (string) $tag;
        if (self::is_event_workflow($workflow) && !empty($workflow['event_editorial_category'])) $names[] = (string) $workflow['event_editorial_category'];
        $out = [];
        foreach ($names as $name) {
            $name = trim((string) $name);
            if ($name === '' || self::contains_tag($out, $name)) continue;
            $out[] = $name;
        }
        return $out;
    }

    private static function contains_tag(array $names, string $needle): bool {
        foreach ($names as $name) if (self::same((string) $name, $needle)) return true;
        return false;
    }

    private static function entity_contains(string $entity, string $tag): bool {
        $e = self::plain($entity);
        $t = self::plain($tag);
        return $t !== '' && mb_strlen($t) >= 4 && (str_contains($e, $t) || str_contains($t, $e));
    }

    private static function category_same(string $a, string $b): bool {
        return self::plain(self::canonical_category($a)) === self::plain(self::canonical_category($b));
    }

    private static function same(string $a, string $b): bool {
        return self::plain($a) === self::plain($b);
    }

    private static function plain(string $text): string {
        $text = function_exists('remove_accents') ? remove_accents($text) : $text;
        $text = mb_strtolower(wp_strip_all_tags($text));
        $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', (string) $text);
        return trim((string) preg_replace('/\s+/u', ' ', (string) $text));
    }

    private static function merge(array ...$groups): array {
        $out = [];
        $seen = [];
        foreach ($groups as $group) {
            foreach ($group as $value) {
                $value = trim((string) $value);
                $key = self::plain($value);
                if ($value === '' || $key === '' || isset($seen[$key])) continue;
                $seen[$key] = true;
                $out[] = $value;
            }
        }
        return $out;
    }

    private static function human_list(array $items): string {
        $items = array_values(array_filter(array_map(static fn($v) => trim((string) $v), $items)));
        if (count($items) <= 1) return (string) ($items[0] ?? '');
        if (count($items) === 2) return $items[0] . ' y ' . $items[1];
        return implode(', ', array_slice($items, 0, -1)) . ' y ' . $items[count($items) - 1];
    }

    private static function display(string $value): string {
        return trim($value) !== '' ? trim($value) : 'Sin información';
    }

    private static function is_event_workflow(array $workflow): bool {
        return (string) ($workflow['editorial_context'] ?? '') === 'event_calendar'
            || ((string) ($workflow['workflow_origin'] ?? '') === 'recurring_update' && (string) ($workflow['recurring_target_post_type'] ?? '') === 'evento');
    }

    private static function data(): array {
        if (self::$data !== null) return self::$data;
        $file = IDG_PLUGIN_DIR . 'includes/data/disciplinary-library.php';
        $data = is_readable($file) ? require $file : [];
        self::$data = is_array($data) ? $data : [];
        return self::$data;
    }
}
