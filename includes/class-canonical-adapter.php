<?php
/** Isolated read-only adapter. No runtime registration or policy enforcement. */
final class IDG_Canonical_Adapter {
    private const CATEGORIES = [
        'product' => ['Diseño de producto'],
        'architecture_interiors' => ['Arquitectura e interiores', 'Arquitectura y diseño interior', 'Interior & Arquitectura'],
        'fashion' => ['Moda'],
        'mobility' => ['Movilidad y transporte', 'Movilidad', 'Transporte'],
        'digital_3d' => ['Diseño digital y 3D', 'Diseño digital'],
        'contests_calls' => ['Concursos y convocatorias', 'Concursos de diseño'],
    ];
    private const LENSES = [
        'automotive' => ['Diseño automotriz', 'Automotriz', 'Automóvil'],
        'furniture' => ['Mobiliario'],
        'lighting' => ['Iluminación', 'Iluminación natural'],
        'materiality' => ['Materialidad', 'Materiales'],
        'sustainability' => ['Diseño sostenible', 'Sostenibilidad'],
        'generative_ai' => ['IA generativa'],
    ];

    public static function projection(): array {
        return require __DIR__ . '/data/editorial-canonical.php';
    }

    private static function normalize($value): string {
        if (!is_string($value)) {
            return '';
        }
        // Explicit UTF-8 folding avoids depending on WordPress, locale or mbstring.
        $value = strtr($value, [
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u', 'Ñ' => 'n',
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            "\u{0301}" => '', "\u{0308}" => '', "\u{0303}" => '',
        ]);
        return strtolower(trim(preg_replace('/\s+/u', ' ', $value) ?? ''));
    }

    private static function match_id($value, array $aliases): ?string {
        $value = self::normalize($value);
        foreach ($aliases as $id => $names) {
            foreach (array_merge([$id], $names) as $name) {
                if ($value === self::normalize($name)) {
                    return $id;
                }
            }
        }
        return null;
    }

    private static function values($value): array {
        return is_array($value) ? array_values($value) : [$value];
    }

    private static function present($value): bool {
        return $value !== null && $value !== [] && $value !== false
            && (!is_string($value) || trim($value) !== '');
    }

    public static function resolve(array $workflow): array {
        $projection = self::projection();
        $surface = (($workflow['editorial_context'] ?? '') === 'event_calendar'
            || ($workflow['recurring_target_post_type'] ?? '') === 'evento') ? 'calendar_event' : 'article';
        $category = null;
        if ($surface === 'article') {
            if (($workflow['editorial_context'] ?? '') === 'contest_call') {
                $category = 'contests_calls';
            } else {
                foreach (['category_name', 'editorial_context_name'] as $field) {
                    $category = self::match_id($workflow[$field] ?? null, self::CATEGORIES);
                    if ($category !== null) {
                        break;
                    }
                }
                $id = $workflow['category_id'] ?? null;
                if ($category === null && function_exists('get_term')
                    && filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false) {
                    $term = get_term((int) $id, 'category');
                    if (is_object($term) && (!function_exists('is_wp_error') || !is_wp_error($term))
                        && isset($term->name) && is_string($term->name)) {
                        $category = self::match_id($term->name, self::CATEGORIES);
                    }
                }
            }
        }
        $primary = null;
        foreach (['primary_lens', 'radar_lente_sugerida', 'lens_suggested', 'radar_tag_principal', 'tag_names'] as $field) {
            foreach (self::values($workflow[$field] ?? null) as $value) {
                $primary = self::match_id($value, self::LENSES);
                if ($primary !== null) {
                    break 2;
                }
            }
        }
        $secondary = [];
        foreach (['secondary_lenses', 'radar_tags_secundarios', 'tag_names'] as $field) {
            foreach (self::values($workflow[$field] ?? null) as $value) {
                $lens = self::match_id($value, self::LENSES);
                if ($lens !== null && $lens !== $primary && !in_array($lens, $secondary, true)) {
                    $secondary[] = $lens;
                }
            }
        }
        $context = [];
        foreach (['brief_fact' => 'brief', 'editorial_angle' => 'angle', 'entity' => 'responsible_entity', 'radar_restricciones_editoriales' => 'article_specific_constraints'] as $legacy => $canonical) {
            if (self::present($workflow[$legacy] ?? null)) {
                $context[$canonical] = $workflow[$legacy];
            }
        }
        $provided = is_array($workflow['piece_context'] ?? null) ? $workflow['piece_context'] : [];
        foreach ($projection['resolution']['piece_context']['allowed_fields'] as $field) {
            if (self::present($provided[$field] ?? null)) {
                $context[$field] = $provided[$field];
            }
        }
        $url = $workflow['responsible_official_url'] ?? null;
        return [
            'knowledge_id' => $projection['knowledge_id'],
            'schema_version' => $projection['schema_version'],
            'canonical_version' => $projection['canonical_version'],
            'canonical_sha256' => $projection['canonical_sha256'],
            'projection' => $projection,
            'surface' => $surface,
            'category' => $category,
            'primary_lens' => $primary,
            'secondary_lenses' => $secondary,
            'responsible_official_url' => self::present($url) ? $url : ($workflow['official_source'] ?? ''),
            'piece_context' => $context,
        ];
    }
}
