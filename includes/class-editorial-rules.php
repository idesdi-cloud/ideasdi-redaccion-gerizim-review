<?php
if (!defined('ABSPATH')) {
    exit;
}

final class IDG_Editorial_Rules {
    public static function defaults(): array {
        return [
            'rules_version_name' => 'Reglas ideasDi base RC1.4',
            'h1_max_chars' => 68,
            'h2_max_chars' => 100,
            'h1_keyword_policy' => 'flexible',
            'editorial_box_min_words' => 40,
            'editorial_box_max_words' => 55,
            'min_paragraphs_per_h3' => 2,
            'reel_vo_words' => 14,
            'reel_scenes' => 6,
            'reel_overlays_per_scene' => 3,
            'reel_overlay_max_chars' => 40,
            'reel_cta' => 'Conoce más de este proyecto en ideasDi.com',
            'structure_rules' => 'Mantener H1, H2, introducción de dos párrafos, caja editorial después de la introducción y desarrollo por H3 con mínimo dos párrafos breves. Si un H3 queda con un solo párrafo, agruparlo o ampliar el desarrollo.',
            'editorial_box_rules' => 'La caja editorial debe responder qué es, quién lo impulsa y qué aporta. No debe contener enlaces, negritas ni tono promocional. Debe aparecer después de los dos párrafos de introducción.',
            'internal_link_rules' => 'El enlace interno debe integrarse dentro de un párrafo de análisis, nunca como línea suelta, botón, “ver más” ni cierre genérico. El anchor debe tener entre 3 y 8 palabras, no usar la keyword principal exacta y ampliar una idea del bloque.',
            'external_link_rules' => 'El enlace externo debe apuntar a la URL del responsable configurado y entrar de forma contextual. No debe enlazar una entidad distinta ni quedar como enlace suelto al final del artículo.',
            'reel_rules' => 'El paquete reel debe incluir VO 1 a VO 6. VO 1 a VO 5 deben tener exactamente 14 palabras. VO 6 debe incluir el CTA fijo. Debe haber 6 escenas con 3 overlays cada una.',
            'forbidden_phrases' => 'Conclusión\nFuente oficial:\nEn este artículo\nLa lectura editorial\nBajada al lifestyle\nSi quieres',
            'category_rules' => '',
            'last_updated_at' => '',
            'last_updated_by' => '',
            'plugin_version' => IDG_VERSION,
        ];
    }

    public static function get(): array {
        $saved = get_option(defined('IDG_EDITORIAL_RULES_OPTION_KEY') ? IDG_EDITORIAL_RULES_OPTION_KEY : 'idg_editorial_rules', []);
        if (!is_array($saved)) {
            $saved = [];
        }
        return array_merge(self::defaults(), $saved);
    }

    public static function int_rule(string $key): int {
        $rules = self::get();
        return max(0, (int) ($rules[$key] ?? self::defaults()[$key] ?? 0));
    }

    public static function text_rule(string $key): string {
        $rules = self::get();
        return trim((string) ($rules[$key] ?? self::defaults()[$key] ?? ''));
    }

    public static function version_label(): string {
        $rules = self::get();
        $label = trim((string) ($rules['rules_version_name'] ?? ''));
        return $label !== '' ? $label : 'Reglas ideasDi';
    }

    public static function prompt_block(string $context = 'general'): string {
        $r = self::get();
        $lines = [];
        $lines[] = 'REGLAS EDITORIALES ACTIVAS DESDE WORDPRESS — ' . self::version_label();
        $lines[] = 'Estas reglas no modifican archivos del plugin; sobrescriben el comportamiento en tiempo de ejecución.';
        $lines[] = '- H1 máximo: ' . (int) $r['h1_max_chars'] . ' caracteres.';
        $lines[] = '- Política keyword en H1: ' . (string) ($r['h1_keyword_policy'] ?? 'flexible') . ' (exacta, flexible o aviso).';
        $lines[] = '- H2 máximo: ' . (int) $r['h2_max_chars'] . ' caracteres.';
        $lines[] = '- Caja editorial: ' . (int) $r['editorial_box_min_words'] . '–' . (int) $r['editorial_box_max_words'] . ' palabras.';
        $lines[] = '- Mínimo de párrafos por H3: ' . (int) $r['min_paragraphs_per_h3'] . '.';
        $lines[] = '- Reel: ' . (int) $r['reel_scenes'] . ' escenas, ' . (int) $r['reel_overlays_per_scene'] . ' overlays por escena, VO 1–5 de ' . (int) $r['reel_vo_words'] . ' palabras.';
        $lines[] = '- CTA fijo reel: ' . (string) $r['reel_cta'];
        foreach (['structure_rules','editorial_box_rules','internal_link_rules','external_link_rules','reel_rules','forbidden_phrases','category_rules'] as $key) {
            $value = trim((string) ($r[$key] ?? ''));
            if ($value !== '') {
                $lines[] = strtoupper(str_replace('_', ' ', $key)) . ':';
                $lines[] = $value;
            }
        }
        return "\n\n" . implode("\n", $lines) . "\n";
    }

    public static function save_from_request(array $post): array {
        $current = self::get();
        $defaults = self::defaults();
        if (!empty($post['reset_editorial_rules'])) {
            $new = $defaults;
        } elseif (!empty($post['import_editorial_rules'])) {
            $json = wp_unslash((string) ($post['editorial_rules_import_json'] ?? ''));
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                $new = array_merge($defaults, array_intersect_key($decoded, $defaults));
            } else {
                $new = $current;
            }
        } else {
            $new = $current;
            foreach (['rules_version_name','structure_rules','editorial_box_rules','internal_link_rules','external_link_rules','reel_rules','forbidden_phrases','category_rules'] as $key) {
                $new[$key] = isset($post[$key]) ? IDG_Sanitizer::textarea((string) wp_unslash($post[$key])) : '';
            }
            foreach (['h1_max_chars','h2_max_chars','editorial_box_min_words','editorial_box_max_words','min_paragraphs_per_h3','reel_vo_words','reel_scenes','reel_overlays_per_scene','reel_overlay_max_chars'] as $key) {
                $new[$key] = isset($post[$key]) ? max(0, absint($post[$key])) : (int) ($defaults[$key] ?? 0);
            }
            $new['reel_cta'] = isset($post['reel_cta']) ? IDG_Sanitizer::text((string) wp_unslash($post['reel_cta'])) : (string) $defaults['reel_cta'];
            $policy = isset($post['h1_keyword_policy']) ? sanitize_key((string) wp_unslash($post['h1_keyword_policy'])) : (string) $defaults['h1_keyword_policy'];
            $new['h1_keyword_policy'] = in_array($policy, ['exact','flexible','warning'], true) ? $policy : 'flexible';
        }
        $new['last_updated_at'] = current_time('mysql');
        $new['last_updated_by'] = get_current_user_id();
        $new['plugin_version'] = IDG_VERSION;
        update_option(defined('IDG_EDITORIAL_RULES_OPTION_KEY') ? IDG_EDITORIAL_RULES_OPTION_KEY : 'idg_editorial_rules', $new, false);
        self::append_history($new, !empty($post['reset_editorial_rules']) ? 'reset' : (!empty($post['import_editorial_rules']) ? 'import' : 'save'));
        return $new;
    }

    public static function append_history(array $rules, string $action): void {
        $key = defined('IDG_EDITORIAL_RULES_HISTORY_OPTION_KEY') ? IDG_EDITORIAL_RULES_HISTORY_OPTION_KEY : 'idg_editorial_rules_history';
        $history = get_option($key, []);
        if (!is_array($history)) {
            $history = [];
        }
        array_unshift($history, [
            'time' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'action' => $action,
            'version_name' => (string) ($rules['rules_version_name'] ?? ''),
            'plugin_version' => IDG_VERSION,
            'rules' => $rules,
        ]);
        $history = array_slice($history, 0, 12);
        update_option($key, $history, false);
    }

    public static function history(): array {
        $history = get_option(defined('IDG_EDITORIAL_RULES_HISTORY_OPTION_KEY') ? IDG_EDITORIAL_RULES_HISTORY_OPTION_KEY : 'idg_editorial_rules_history', []);
        return is_array($history) ? $history : [];
    }

    public static function export_json(): string {
        $rules = self::get();
        unset($rules['last_updated_by']);
        return wp_json_encode($rules, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function summary_lines(): array {
        $r = self::get();
        return [
            'Versión de reglas' => self::version_label(),
            'Última actualización' => (string) ($r['last_updated_at'] ?? ''),
            'Plugin al guardar reglas' => (string) ($r['plugin_version'] ?? ''),
            'H1 máximo' => (string) (int) $r['h1_max_chars'],
            'Keyword en H1' => (string) ($r['h1_keyword_policy'] ?? 'flexible'),
            'Caja editorial' => (int) $r['editorial_box_min_words'] . '–' . (int) $r['editorial_box_max_words'] . ' palabras',
            'H3 mínimo' => (string) (int) $r['min_paragraphs_per_h3'] . ' párrafos',
            'Reel VO 1–5' => (string) (int) $r['reel_vo_words'] . ' palabras',
            'Reel overlays' => (int) $r['reel_scenes'] . ' × ' . (int) $r['reel_overlays_per_scene'],
        ];
    }
}
