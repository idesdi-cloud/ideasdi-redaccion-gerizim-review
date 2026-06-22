<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Investigación web controlada para Gerizim.
 * - No publica contenido en el borrador.
 * - Alimenta la ficha documental temporal y el reporte completo.
 * - Usa intensidad automática: baja, media o alta.
 */
final class IDG_Web_Research {
    private const MAX_SOURCE_CHARS = 55000;
    private const MAX_RESEARCH_CHARS = 30000;

    public static function run(string $workflow_id, array $workflow, IDG_OpenAI_Client $client): array {
        $fingerprint = self::fingerprint($workflow);
        if ($fingerprint !== '' && !empty($workflow['web_research_card']) && (string) ($workflow['web_research_hash'] ?? '') === $fingerprint) {
            return $workflow;
        }

        $intensity = self::determine_intensity($workflow);
        $workflow['web_research_intensity'] = $intensity['level'];
        $workflow['web_research_reason'] = $intensity['reason'];
        $workflow['web_research_context_size'] = self::context_size($intensity['level']);
        $workflow['web_research_started_at'] = current_time('mysql');
        $workflow['web_research_status'] = 'iniciada';
        $workflow['web_research_hash'] = $fingerprint;
        $workflow['web_research_tool_call_estimate_usd'] = self::tool_call_cost_estimate($intensity['level']);

        // Lectura directa de URL de fuente de información, si existe.
        $source_url = self::source_url($workflow);
        if ($source_url !== '') {
            $read = self::read_source_url($source_url);
            $workflow['source_information_url'] = $source_url;
            $workflow['source_url_status'] = $read['status'];
            $workflow['source_url_message'] = $read['message'];
            $workflow['source_url_read_at'] = current_time('mysql');
            $workflow['source_url_chars'] = (string) mb_strlen((string) ($read['text'] ?? ''));
            if (!empty($read['text'])) {
                $workflow['source_url_text'] = $read['text'];
            } else {
                $workflow['source_url_text'] = '';
                $workflow['temp_material_warnings'][] = 'No se pudo extraer texto suficiente desde la URL oficial o fuente complementaria: ' . $read['message'];
            }

            // Si la lectura directa falla, la investigación no puede quedarse en intensidad baja.
            // Se escala a media para intentar contraste por búsqueda web controlada, sin bloquear el flujo.
            $status_norm = mb_strtolower(function_exists('remove_accents') ? remove_accents((string) $read['status']) : (string) $read['status']);
            if ($intensity['level'] === 'baja' && $status_norm !== 'leida correctamente') {
                $intensity['level'] = 'media';
                $intensity['reason'] .= ' La lectura directa de URL no fue suficiente; se eleva a intensidad media para contraste.';
                $workflow['web_research_intensity'] = $intensity['level'];
                $workflow['web_research_reason'] = $intensity['reason'];
                $workflow['web_research_context_size'] = self::context_size($intensity['level']);
                $workflow['web_research_tool_call_estimate_usd'] = self::tool_call_cost_estimate($intensity['level']);
            }
        } else {
            $workflow['source_url_status'] = 'sin URL de fuente de información';
            $workflow['source_url_message'] = '';
            $workflow['source_url_text'] = '';
            $workflow['source_url_chars'] = '0';
        }

        $workflow = self::append_history_safe($workflow, 'web_research_started', 'Investigación web controlada iniciada con intensidad ' . $intensity['level'] . '.');
        if ($workflow_id !== '') {
            IDG_Job_Runner::save_workflow($workflow_id, $workflow);
        }

        $prompt = IDG_Prompt_Library::web_research_prompt(self::prompt_data($workflow), $intensity);
        $result = $client->complete_with_web_search($prompt, $intensity['level']);

        if (!$result['success']) {
            $workflow['web_research_status'] = 'falló';
            $workflow['web_research_error'] = $result['message'];
            $workflow['web_research_completed_at'] = current_time('mysql');
            $workflow['temp_material_warnings'][] = 'Investigación web controlada no completada: ' . $result['message'];
            $workflow = self::append_history_safe($workflow, 'web_research_failed', $result['message']);
            return $workflow;
        }

        $workflow['web_research_status'] = 'completada';
        $workflow['web_research_card'] = IDG_Temporary_Material::limit_text((string) ($result['content'] ?? ''), self::MAX_RESEARCH_CHARS);
        $workflow['web_research_sources'] = isset($result['sources']) && is_array($result['sources']) ? $result['sources'] : [];
        $workflow['web_research_usage'] = $result['usage'] ?? [];
        $workflow['web_research_completed_at'] = current_time('mysql');
        $workflow['web_research_error'] = '';
        $workflow = self::append_history_safe($workflow, 'web_research_completed', 'Investigación web controlada completada.');
        IDG_Usage_Estimator::record('web_research', (array) ($result['usage'] ?? []), $workflow_id, (string) ($result['model'] ?? ''));

        return $workflow;
    }

    public static function document_material(array $workflow): string {
        $parts = [];
        $manual = trim((string) ($workflow['temp_material_text'] ?? ''));
        if ($manual !== '') {
            $parts[] = "--- MATERIAL TEMPORAL / NOTA DE PRENSA APORTADA ---\n" . $manual;
        }
        $source_text = trim((string) ($workflow['source_url_text'] ?? ''));
        if ($source_text !== '') {
            $parts[] = "--- TEXTO EXTRAÍDO DE URL DE LA FUENTE DE INFORMACIÓN ---\nURL: " . self::source_url($workflow) . "\n" . $source_text;
        }
        $research = trim((string) ($workflow['web_research_card'] ?? ''));
        if ($research !== '') {
            $parts[] = "--- INVESTIGACIÓN WEB CONTROLADA INTERNA ---\n" . $research;
        }
        return trim(implode("\n\n", $parts));
    }

    public static function has_document_material(array $workflow): bool {
        return trim(self::document_material($workflow)) !== '';
    }


    public static function application_lines(array $workflow, string $article = ''): array {
        $lines = [];
        $level = trim((string) ($workflow['web_research_intensity'] ?? ''));
        $status = trim((string) ($workflow['web_research_status'] ?? ''));
        $lines[] = '- **Nivel visible antes del borrador:** ' . self::display($level !== '' ? $level : 'sin ejecutar');
        $lines[] = '- **Estado:** ' . self::display($status !== '' ? $status : 'no registrada');
        $reason = trim((string) ($workflow['web_research_reason'] ?? ''));
        if ($reason !== '') {
            $lines[] = '- **Motivo aplicado:** ' . self::display($reason);
        }

        $article_plain = self::plain($article);
        $signals = self::application_signals($workflow);
        $matched = [];
        $missing = [];
        foreach ($signals as $signal) {
            $plain = self::plain($signal);
            if ($plain === '' || mb_strlen($plain) < 4) {
                continue;
            }
            if ($article_plain !== '' && self::signal_applied_to_article($plain, $article_plain)) {
                $matched[] = $signal;
            } else {
                $missing[] = $signal;
            }
            if (count($matched) >= 8) {
                break;
            }
        }

        if (!empty($matched)) {
            $lines[] = '- **Datos de investigación visibles en el artículo:** ' . self::display(implode('; ', array_slice($matched, 0, 8))); 
        } else {
            $lines[] = '- **Datos de investigación visibles en el artículo:** _No se detectaron coincidencias directas en el texto disponible._';
        }

        if (!empty($missing)) {
            $lines[] = '- **Datos investigados a revisar si faltan en redacción:** ' . self::display(implode('; ', array_slice($missing, 0, 5)));
        }

        if (!empty($workflow['document_card'])) {
            $lines[] = '- **Ficha documental usada como insumo:** sí';
        }
        if (!empty($workflow['web_research_card'])) {
            $lines[] = '- **Investigación usada como insumo:** sí';
        }
        return $lines;
    }


    private static function signal_applied_to_article(string $signal_plain, string $article_plain): bool {
        if ($signal_plain === '' || $article_plain === '') {
            return false;
        }
        if (str_contains($article_plain, $signal_plain)) {
            return true;
        }
        $tokens = preg_split('/\s+/u', $signal_plain, -1, PREG_SPLIT_NO_EMPTY);
        $tokens = array_values(array_filter((array) $tokens, static function ($token) {
            $token = (string) $token;
            if (mb_strlen($token) < 5) return false;
            return !in_array($token, ['fuente', 'estado', 'investigacion', 'recomendacion', 'datos', 'diseno', 'proyecto', 'articulo', 'lectura'], true);
        }));
        if (empty($tokens)) {
            return false;
        }
        $hits = 0;
        foreach (array_slice(array_unique($tokens), 0, 8) as $token) {
            if (str_contains($article_plain, $token)) {
                $hits++;
            }
        }
        return $hits >= min(2, count($tokens));
    }

    private static function application_signals(array $workflow): array {
        $text = implode("
", [
            (string) ($workflow['web_research_card'] ?? ''),
            (string) ($workflow['document_card'] ?? ''),
        ]);
        $signals = [];
        foreach (preg_split('/
+/', $text) as $line) {
            $line = trim(preg_replace('/^[\-*•]\s*/u', '', (string) $line));
            $line = preg_replace('/\s*\([^)]*https?:\/\/[^)]*\)\s*/u', '', (string) $line);
            $line = trim(wp_strip_all_tags((string) $line));
            if ($line === '' || mb_strlen($line) < 8) {
                continue;
            }
            // Preferir entidades, fechas y datos breves verificables; evitar párrafos largos de recomendación.
            if (mb_strlen($line) > 160) {
                if (preg_match_all('/(?:[A-ZÁÉÍÓÚÑ][\p{L}0-9&\.\-]+(?:\s+[A-ZÁÉÍÓÚÑ][\p{L}0-9&\.\-]+){0,4}|\d{1,2}\s+de\s+[a-záéíóúñ]+\s+de\s+\d{4}|\d{4}|\d+[,.]?\d*\s?(?:m²|metros cuadrados|sq\.ft\.|km\/h|CV|hp|Nm))/u', $line, $m)) {
                    foreach ($m[0] as $item) {
                        $signals[] = trim((string) $item);
                    }
                }
                continue;
            }
            if (preg_match('/^(?:Hechos confirmados|Entidades|Datos útiles|Tono promocional|Dudas|Recomendación|Fuentes usadas)/iu', $line)) {
                continue;
            }
            $signals[] = $line;
        }
        $out = [];
        $seen = [];
        foreach ($signals as $signal) {
            $signal = trim($signal, " .;:-	

 ");
            $key = self::plain($signal);
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $signal;
            if (count($out) >= 16) {
                break;
            }
        }
        return $out;
    }

    private static function plain(string $text): string {
        $text = wp_strip_all_tags($text);
        $text = function_exists('remove_accents') ? remove_accents($text) : $text;
        $text = mb_strtolower((string) $text);
        $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text);
        $text = preg_replace('/\s+/u', ' ', (string) $text);
        return trim((string) $text);
    }

    public static function report_lines(array $workflow): array {
        $lines = [];
        $lines[] = '- **Ejecutada:** ' . self::display((string) ($workflow['web_research_status'] ?? 'no registrada'));
        $lines[] = '- **Intensidad:** ' . self::display((string) ($workflow['web_research_intensity'] ?? ''));
        $lines[] = '- **Motivo:** ' . self::display((string) ($workflow['web_research_reason'] ?? ''));
        $lines[] = '- **Contexto de búsqueda:** ' . self::display((string) ($workflow['web_research_context_size'] ?? ''));
        $lines[] = '- **Costo referencial herramienta:** ' . self::display((string) ($workflow['web_research_tool_call_estimate_usd'] ?? ''));
        $lines[] = '- **URL oficial o fuente complementaria:** ' . self::display((string) ($workflow['source_information_url'] ?? ''));
        $lines[] = '- **Lectura directa de URL:** ' . self::display((string) ($workflow['source_url_status'] ?? ''));
        if (!empty($workflow['source_url_message'])) {
            $lines[] = '- **Mensaje URL:** ' . self::display((string) $workflow['source_url_message']);
        }
        $lines[] = '- **Caracteres extraídos desde URL:** ' . self::display((string) ($workflow['source_url_chars'] ?? '0'));
        if (!empty($workflow['web_research_error'])) {
            $lines[] = '- **Error investigación:** ' . self::display((string) $workflow['web_research_error']);
        }
        $sources = isset($workflow['web_research_sources']) && is_array($workflow['web_research_sources']) ? $workflow['web_research_sources'] : [];
        if (!empty($sources)) {
            $lines[] = '### Fuentes detectadas por la investigación';
            foreach ($sources as $source) {
                $title = trim((string) ($source['title'] ?? 'Fuente'));
                $url = trim((string) ($source['url'] ?? ''));
                if ($url !== '') {
                    $lines[] = '- ' . $title . ' — ' . $url;
                }
            }
        }
        $card = trim((string) ($workflow['web_research_card'] ?? ''));
        if ($card !== '') {
            $lines[] = '### Ficha de investigación web controlada';
            $lines[] = $card;
        }
        return $lines;
    }

    private static function prompt_data(array $workflow): array {
        $category_name = '';
        if (!empty($workflow['category_id'])) {
            $term = get_term((int) $workflow['category_id'], 'category');
            if ($term && !is_wp_error($term)) {
                $category_name = (string) $term->name;
            }
        }
        $tag_names = [];
        if (!empty($workflow['tag_ids']) && is_array($workflow['tag_ids'])) {
            foreach ($workflow['tag_ids'] as $tag_id) {
                $tag = get_term((int) $tag_id, 'post_tag');
                if ($tag && !is_wp_error($tag)) {
                    $tag_names[] = (string) $tag->name;
                }
            }
        }
        return [
            'keyword' => (string) ($workflow['keyword'] ?? ''),
            'entity' => (string) ($workflow['entity'] ?? ''),
            'piece_type' => (string) ($workflow['piece_type'] ?? ''),
            'brief_fact' => (string) ($workflow['brief_fact'] ?? ''),
            'category_name' => $category_name,
            'tag_names' => $tag_names,
            'source_information_url' => (string) ($workflow['source_information_url'] ?? ''),
            'official_source' => (string) ($workflow['official_source'] ?? ''),
            'source_url_status' => (string) ($workflow['source_url_status'] ?? ''),
            'source_url_text' => IDG_Temporary_Material::limit_text((string) ($workflow['source_url_text'] ?? ''), 18000),
            'manual_material_present' => IDG_Temporary_Material::has_material($workflow) ? 'sí' : 'no',
            'manual_material_excerpt' => IDG_Temporary_Material::limit_text((string) ($workflow['temp_material_text'] ?? ''), 12000),
        ];
    }

    private static function determine_intensity(array $workflow): array {
        $has_manual = IDG_Temporary_Material::has_material($workflow);
        $has_source_url = self::source_url($workflow) !== '';
        $category = '';
        if (!empty($workflow['category_id'])) {
            $term = get_term((int) $workflow['category_id'], 'category');
            if ($term && !is_wp_error($term)) {
                $category = mb_strtolower(remove_accents((string) $term->name));
            }
        }
        $piece = mb_strtolower(remove_accents((string) ($workflow['piece_type'] ?? '')));
        $is_practical = str_contains($category, 'concurso') || str_contains($category, 'convocatoria') || str_contains($category, 'evento') || str_contains($piece, 'agenda') || str_contains($piece, 'convocatoria') || str_contains($piece, 'concurso');

        if (!$has_manual && !$has_source_url) {
            return ['level' => 'alta', 'reason' => 'No hay material temporal, archivo ni URL de fuente; la investigación construye la ficha documental desde el brief.'];
        }
        if ($has_source_url && !$has_manual) {
            return ['level' => $is_practical ? 'alta' : 'media', 'reason' => $is_practical ? 'Hay URL, pero la categoría requiere datos prácticos verificables.' : 'Hay URL de fuente, pero poco material pegado; se verifica y enriquece contexto.'];
        }
        if ($has_manual && $has_source_url) {
            return ['level' => $is_practical ? 'media' : 'baja', 'reason' => $is_practical ? 'Hay material y URL, pero concursos/eventos requieren confirmar fechas, requisitos o sede.' : 'Hay material y URL suficientes; se verifica nombres, responsable y datos clave.'];
        }
        return ['level' => $is_practical ? 'media' : 'baja', 'reason' => $is_practical ? 'Hay material, pero se verifica información práctica de convocatoria/evento.' : 'Hay material temporal; búsqueda breve para enriquecer y verificar.'];
    }

    private static function context_size(string $level): string {
        if ($level === 'alta') return 'high';
        if ($level === 'media') return 'medium';
        return 'low';
    }

    private static function tool_call_cost_estimate(string $level): string {
        if ($level === 'alta') return 'US$0.05–0.06 aprox. + tokens del modelo';
        if ($level === 'media') return 'US$0.03–0.04 aprox. + tokens del modelo';
        return 'US$0.01–0.02 aprox. + tokens del modelo';
    }

    private static function fingerprint(array $workflow): string {
        $pieces = [
            (string) ($workflow['keyword'] ?? ''),
            (string) ($workflow['entity'] ?? ''),
            (string) ($workflow['piece_type'] ?? ''),
            (string) ($workflow['brief_fact'] ?? ''),
            (string) ($workflow['category_id'] ?? ''),
            implode(',', isset($workflow['tag_ids']) && is_array($workflow['tag_ids']) ? array_map('intval', $workflow['tag_ids']) : []),
            (string) ($workflow['source_information_url'] ?? ''),
            (string) ($workflow['official_source'] ?? ''),
            IDG_Temporary_Material::hash((string) ($workflow['temp_material_text'] ?? '')),
        ];
        return hash('sha256', implode('|', $pieces));
    }

    private static function source_url(array $workflow): string {
        $url = trim((string) ($workflow['source_information_url'] ?? $workflow['temp_material_url'] ?? ''));
        return esc_url_raw($url);
    }

    private static function read_source_url(string $url): array {
        $url = esc_url_raw($url);
        if ($url === '' || !preg_match('/^https?:\/\//i', $url)) {
            return ['status' => 'URL inválida', 'message' => 'La URL de fuente no es válida.', 'text' => ''];
        }
        $response = wp_safe_remote_get($url, [
            'timeout' => 22,
            'redirection' => 3,
            'user-agent' => 'ideasDi-Gerizim/' . IDG_VERSION . '; ' . home_url('/'),
            'limit_response_size' => 2097152,
        ]);
        if (is_wp_error($response)) {
            return ['status' => 'falló', 'message' => $response->get_error_message(), 'text' => ''];
        }
        $status = (int) wp_remote_retrieve_response_code($response);
        if ($status < 200 || $status >= 300) {
            return ['status' => 'falló', 'message' => 'HTTP ' . $status, 'text' => ''];
        }
        $body = (string) wp_remote_retrieve_body($response);
        if ($body === '') {
            return ['status' => 'falló', 'message' => 'Respuesta vacía.', 'text' => ''];
        }
        $text = self::extract_text_from_html($body);
        if (mb_strlen($text) < 500) {
            return ['status' => 'lectura insuficiente', 'message' => 'La URL respondió, pero entregó poco texto útil.', 'text' => $text];
        }
        return ['status' => 'leída correctamente', 'message' => 'Texto útil extraído desde la URL.', 'text' => IDG_Temporary_Material::limit_text($text, self::MAX_SOURCE_CHARS)];
    }

    private static function extract_text_from_html(string $html): string {
        $html = wp_check_invalid_utf8($html);
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $html);
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', ' ', $html);
        $html = preg_replace('/<noscript\b[^>]*>.*?<\/noscript>/is', ' ', $html);
        $html = preg_replace('/<(nav|header|footer|aside|form|svg)\b[^>]*>.*?<\/\1>/is', ' ', (string) $html);
        $html = preg_replace('/<br\s*\/?>/i', "\n", (string) $html);
        $html = preg_replace('/<\/p\s*>/i', "\n\n", (string) $html);
        $html = preg_replace('/<\/h[1-6]\s*>/i', "\n\n", (string) $html);
        return IDG_Temporary_Material::limit_text(IDG_Temporary_Material::clean_text($html), self::MAX_SOURCE_CHARS);
    }

    private static function append_history_safe(array $workflow, string $event, string $message): array {
        $history = isset($workflow['history']) && is_array($workflow['history']) ? $workflow['history'] : [];
        $history[] = [
            'time' => current_time('mysql'),
            'event' => sanitize_key($event),
            'message' => sanitize_text_field($message),
        ];
        if (count($history) > 20) {
            $history = array_slice($history, -20);
        }
        $workflow['history'] = $history;
        return $workflow;
    }

    private static function display(string $value): string {
        $value = trim($value);
        return $value === '' ? '_Sin información._' : $value;
    }
}
