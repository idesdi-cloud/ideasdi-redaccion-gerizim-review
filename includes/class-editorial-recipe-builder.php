<?php
if (!defined('ABSPATH')) {
    exit;
}

final class IDG_Editorial_Recipe_Builder {
    private static ?array $data = null;

    public static function build(array $workflow): array {
        $keyword = trim((string) ($workflow['keyword'] ?? ''));
        $category_name = self::category_name($workflow);
        $tag_names = self::tag_names($workflow);
        $primary_tag = self::primary_tag_name($workflow, $tag_names);
        $secondary_tags = self::secondary_tag_names($workflow, $tag_names, $primary_tag);
        $angle = trim((string) ($workflow['editorial_angle'] ?? ''));

        $category = self::category_recipe($category_name);
        $tag = self::tag_recipe($primary_tag);
        $secondary = self::secondary_concepts($secondary_tags);

        $focus = (string) ($tag['filter'] ?? $category['focus'] ?? 'el enfoque editorial del caso');
        $concepts = self::merge_concepts((array) ($tag['concepts'] ?? []), $secondary, (array) ($category['concepts'] ?? []));
        $risk = (string) ($tag['risk'] ?? $category['risk'] ?? 'convertir el texto en ficha promocional');

        if ($angle !== '') {
            $angle_focus = self::focus_from_angle($angle);
            if ($angle_focus !== '') {
                $focus = $angle_focus;
            }
        }

        $subject = $keyword !== '' ? $keyword : 'el caso';
        $concept_text = self::concept_text(array_slice($concepts, 0, 3));
        $recipe = 'Leer ' . $subject . ' desde ' . $focus;
        if ($concept_text !== '') {
            $recipe .= ', observando ' . $concept_text;
        }
        $recipe .= ', sin ' . $risk . '.';
        $recipe = self::compact_recipe($recipe, 45);

        return [
            'recipe' => $recipe,
            'category_frame' => (string) ($category['frame'] ?? ''),
            'primary_tag' => $primary_tag,
            'secondary_tags' => $secondary_tags,
            'tag_filter' => (string) ($tag['filter'] ?? ''),
            'risk' => $risk,
            'technical_summary' => self::technical_summary($workflow, $primary_tag, $secondary_tags),
        ];
    }

    public static function recipe_text(array $workflow): string {
        $built = self::build($workflow);
        return trim((string) ($built['recipe'] ?? ''));
    }

    public static function technical_summary(array $workflow, string $primary_tag = '', array $secondary_tags = []): string {
        if ($primary_tag === '') {
            $tag_names = self::tag_names($workflow);
            $primary_tag = self::primary_tag_name($workflow, $tag_names);
            $secondary_tags = self::secondary_tag_names($workflow, $tag_names, $primary_tag);
        }
        $lines = [];
        $lines[] = 'Tag principal: ' . self::display($primary_tag);
        $lines[] = 'Tags secundarios: ' . self::display(implode(', ', $secondary_tags));
        if (class_exists('IDG_Internal_Links')) {
            $lines[] = 'Regla de enlace interno:';
            $lines[] = IDG_Internal_Links::library_summary($workflow);
        }
        return implode("\n", $lines);
    }

    public static function admin_category_presets($categories): array {
        $out = [];
        if (is_wp_error($categories)) {
            return $out;
        }
        foreach ($categories as $cat) {
            $category = self::category_recipe((string) $cat->name);
            if (!empty($category['frame'])) {
                $out[(string) $cat->term_id] = (string) $category['frame'];
            }
        }
        return $out;
    }

    public static function admin_tag_presets($tags): array {
        $out = [];
        if (is_wp_error($tags)) {
            return $out;
        }
        foreach ($tags as $tag) {
            $recipe = self::tag_recipe((string) $tag->name);
            if (!empty($recipe['filter'])) {
                $out[(string) $tag->term_id] = (string) $recipe['filter'];
            }
        }
        return $out;
    }

    private static function category_name(array $workflow): string {
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
        if (!empty($workflow['radar_tag_principal']) && !in_array((string) $workflow['radar_tag_principal'], $names, true)) {
            array_unshift($names, (string) $workflow['radar_tag_principal']);
        }
        return array_values(array_unique(array_filter($names)));
    }

    private static function primary_tag_name(array $workflow, array $tag_names): string {
        $radar = trim((string) ($workflow['radar_tag_principal'] ?? ''));
        if ($radar !== '') {
            foreach ($tag_names as $name) {
                if (self::same_name($name, $radar)) {
                    return $name;
                }
            }
            return $radar;
        }
        return (string) ($tag_names[0] ?? '');
    }

    private static function secondary_tag_names(array $workflow, array $tag_names, string $primary_tag): array {
        if (!empty($workflow['radar_tags_secundarios']) && is_array($workflow['radar_tags_secundarios'])) {
            return array_slice(array_values(array_filter(array_map('strval', $workflow['radar_tags_secundarios']))), 0, 2);
        }
        $out = [];
        foreach ($tag_names as $name) {
            if ($primary_tag !== '' && self::same_name($name, $primary_tag)) {
                continue;
            }
            $out[] = $name;
            if (count($out) >= 2) {
                break;
            }
        }
        return $out;
    }

    private static function category_recipe(string $name): array {
        foreach ((self::data()['categories'] ?? []) as $row) {
            foreach ((array) ($row['names'] ?? []) as $candidate) {
                if (self::same_name($candidate, $name)) {
                    return $row;
                }
            }
        }
        if (class_exists('IDG_Priority_Readings')) {
            $preset = IDG_Priority_Readings::preset_for_category_name($name);
            if ($preset !== '') {
                return ['frame' => self::clean_instruction($preset), 'focus' => self::clean_instruction($preset), 'concepts' => [], 'risk' => 'convertir la pieza en descripción genérica'];
            }
        }
        return ['frame' => '', 'focus' => 'el enfoque editorial del caso', 'concepts' => [], 'risk' => 'convertir el texto en ficha promocional'];
    }

    private static function tag_recipe(string $name): array {
        foreach ((self::data()['tags'] ?? []) as $row) {
            foreach ((array) ($row['names'] ?? []) as $candidate) {
                if (self::same_name($candidate, $name)) {
                    return $row;
                }
            }
        }
        if (class_exists('IDG_Priority_Readings')) {
            $preset = IDG_Priority_Readings::preset_for_tag_name($name);
            if ($preset !== '') {
                return ['filter' => self::clean_instruction($preset), 'concepts' => [], 'risk' => 'forzar el tag como tema principal'];
            }
        }
        return [];
    }

    private static function secondary_concepts(array $secondary_tags): array {
        $out = [];
        foreach (array_slice($secondary_tags, 0, 2) as $name) {
            $row = self::tag_recipe((string) $name);
            foreach ((array) ($row['concepts'] ?? []) as $concept) {
                $out[] = (string) $concept;
                break;
            }
        }
        return $out;
    }

    private static function focus_from_angle(string $angle): string {
        $plain = self::plain($angle);
        if (str_contains($plain, 'pantalla') || str_contains($plain, 'visual') || str_contains($plain, 'espacio publico') || str_contains($plain, 'urbana')) {
            return 'la relación entre pantalla, color y espacio público';
        }
        if (str_contains($plain, 'escucha') || str_contains($plain, 'audio') || str_contains($plain, 'sonido')) {
            return 'la experiencia de escucha y uso cotidiano';
        }
        if (str_contains($plain, 'paisaje') || str_contains($plain, 'implantacion') || str_contains($plain, 'terreno')) {
            return 'la relación entre implantación, paisaje y vida cotidiana';
        }
        if (str_contains($plain, 'convocatoria') || str_contains($plain, 'concurso') || str_contains($plain, 'portafolio')) {
            return 'su valor para el proceso creativo o portafolio';
        }
        return '';
    }

    private static function merge_concepts(array ...$groups): array {
        $out = [];
        $seen = [];
        foreach ($groups as $group) {
            foreach ($group as $concept) {
                $concept = trim((string) $concept);
                if ($concept === '') {
                    continue;
                }
                $key = self::plain($concept);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $out[] = $concept;
                if (count($out) >= 3) {
                    return $out;
                }
            }
        }
        return $out;
    }

    private static function concept_text(array $concepts): string {
        $concepts = array_values(array_filter(array_map('trim', $concepts)));
        if (empty($concepts)) {
            return '';
        }
        if (count($concepts) === 1) {
            return $concepts[0];
        }
        $last = array_pop($concepts);
        return implode(', ', $concepts) . ' y ' . $last;
    }

    private static function compact_recipe(string $recipe, int $max_words): string {
        $recipe = trim((string) preg_replace('/\s+/u', ' ', $recipe));
        $words = preg_split('/\s+/u', $recipe, -1, PREG_SPLIT_NO_EMPTY);
        if (count((array) $words) <= $max_words) {
            return $recipe;
        }
        $recipe = preg_replace('/, observando [^,\.]+/u', '', $recipe, 1);
        $recipe = trim((string) preg_replace('/\s+/u', ' ', (string) $recipe));
        return rtrim($recipe, '.') . '.';
    }

    private static function clean_instruction(string $text): string {
        $text = trim($text);
        $text = preg_replace('/\b(enlazar|indexar|noindex|no index|usar como tag|página SEO)[^.;]*(?:[.;]|$)/iu', '', $text);
        $text = preg_replace('/\s*;\s*/u', ', ', (string) $text);
        return trim((string) preg_replace('/\s+/u', ' ', (string) $text), " .\t\n\r\0\x0B") . '.';
    }

    private static function same_name(string $a, string $b): bool {
        return self::plain($a) === self::plain($b);
    }

    private static function plain(string $value): string {
        $value = function_exists('remove_accents') ? remove_accents($value) : $value;
        $value = mb_strtolower((string) $value);
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value);
        return trim((string) preg_replace('/\s+/u', ' ', (string) $value));
    }

    private static function display(string $value): string {
        $value = trim($value);
        return $value === '' ? '_Sin información._' : $value;
    }

    private static function data(): array {
        if (self::$data === null) {
            $path = defined('IDG_PLUGIN_DIR') ? IDG_PLUGIN_DIR . 'includes/data/editorial-recipes.php' : __DIR__ . '/data/editorial-recipes.php';
            self::$data = file_exists($path) ? (array) require $path : [];
        }
        return self::$data;
    }
}
