<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Documento editorial canónico persistido dentro del workflow (wp_options).
 * Las cadenas legacy son proyecciones: este objeto no crea tablas ni migra
 * silenciosamente expedientes históricos.
 */
final class IDG_Editorial_Document {
    public const SCHEMA_VERSION = 1;

    public static function exists(array $workflow): bool {
        return isset($workflow['editorial_document']) && is_array($workflow['editorial_document'])
            && (int) ($workflow['editorial_document']['schema_version'] ?? 0) === self::SCHEMA_VERSION;
    }

    public static function from_markdown(string $markdown, array $workflow, ?array $previous = null): array {
        $markdown = trim(str_replace(["\r\n", "\r"], "\n", $markdown));
        // Also protect direct canonical imports: structural variants must not
        // become a regular H3 when a caller bypasses the generation adapter.
        $markdown = IDG_Workflow_Output_Parser::normalize_editorial_box_markdown($markdown);
        $lines = preg_split('/\n/', $markdown);
        $blocks = []; $paragraph = []; $list = []; $box_next = false; $ordinal = 0;
        $flush_paragraph = static function () use (&$paragraph, &$blocks, &$ordinal): void {
            $text = trim(implode("\n", $paragraph)); $paragraph = [];
            if ($text !== '') $blocks[] = ['id' => self::stable_id('paragraph', $text, $ordinal++), 'type' => 'paragraph', 'text' => $text];
        };
        $flush_list = static function () use (&$list, &$blocks, &$ordinal): void {
            if ($list) { $blocks[] = ['id' => self::stable_id('list', implode('|', $list), $ordinal++), 'type' => 'list', 'items' => $list]; $list = []; }
        };
        foreach ((array) $lines as $line) {
            $trim = trim((string) $line);
            if ($trim === '') { $flush_paragraph(); $flush_list(); continue; }
            if (preg_match('/^(#{1,3})\s+(.+)$/u', $trim, $m)) {
                $flush_paragraph(); $flush_list(); $level = strlen($m[1]);
                $blocks[] = ['id' => self::stable_id('heading', $m[2], $ordinal++), 'type' => 'heading', 'level' => $level, 'text' => trim($m[2])];
                continue;
            }
            if (preg_match('/^(?:\*\*)?Caja editorial:?\s*(?:\*\*)?$/iu', $trim)) { $flush_paragraph(); $flush_list(); $box_next = true; continue; }
            if ($box_next) { $blocks[] = ['id' => self::stable_id('editorial_box', $trim, $ordinal++), 'type' => 'editorial_box', 'text' => $trim]; $box_next = false; continue; }
            if (preg_match('/^(?:[-*+] |\d+\. )(.+)$/u', $trim, $m)) { $flush_paragraph(); $list[] = trim($m[1]); continue; }
            $flush_list(); $paragraph[] = $trim;
        }
        $flush_paragraph(); $flush_list();
        // A canonical document always exposes one H1; legacy validation remains
        // responsible for rejecting malformed model output.
        $doc = [
            'schema_version' => self::SCHEMA_VERSION,
            'blocks' => $blocks,
            'seo' => ['title' => '%%title%%', 'keyword' => (string) ($workflow['keyword'] ?? ''), 'meta_description' => '', 'report' => ''],
            'links' => self::authorized_links($workflow),
            'social_copy' => '', 'reel' => [],
            'feedback_notes' => '',
            'origin' => ['workflow_origin' => (string) ($workflow['workflow_origin'] ?? 'manual'), 'radar_brief_id' => (int) ($workflow['radar_brief_id'] ?? 0)],
            'review' => ['state' => 'generated', 'validation' => []],
        ];
        if (is_array($previous)) $doc = self::preserve_ids($doc, $previous);
        return self::seal($doc);
    }

    /** Only recognized blocks may be updated; unexpected editorial responses fail closed. */
    public static function apply_editorial_response(array $document, string $response): array {
        $parsed = IDG_Workflow_Output_Parser::parse_editorial($response);
        $article = trim((string) ($parsed['article'] ?? ''));
        if ($article !== '' && hash_equals(self::markdown($document), $article)) {
            $document['review'] = ['state' => 'editorial_reviewed', 'validation' => [], 'changed_ids' => []];
            return ['ok' => true, 'document' => self::seal($document), 'changed_ids' => [], 'reason' => 'unchanged_echo'];
        }
        if ($article !== '') {
            $reconciled = self::reconcile_complete_article($document, $article, 'editorial', []);
            if (!empty($reconciled['ok'])) return $reconciled;
            // A complete proposal that cannot be safely reconciled must retain
            // its specific diagnostic and the original document. PATCH remains
            // available only when the response actually supplies one.
            if (!preg_match('/^PATCH\s+/m', $response)) return $reconciled;
        }
        return self::apply_patches($document, $response, 'editorial');
    }

    public static function apply_seo_response(array $document, string $response, array $workflow): array {
        $original = $document;
        $sections = self::seo_sections(self::without_patches($response));
        // "Received" describes presence, not acceptance by Final Guard.
        $received_components = [];
        foreach (['meta_description', 'seo_report', 'social_copy', 'reel_package', 'feedback_notes'] as $component) {
            if (trim((string) ($sections[$component] ?? '')) !== '') $received_components[] = $component;
        }
        $has_components = !empty($received_components);
        $patch = ['received' => (bool) preg_match('/^PATCH\s+/m', $response), 'applied' => false, 'reason' => 'not_proposed'];
        $article = trim((string) ($sections['article'] ?? ''));
        $changed = []; $prose = ['received' => $article !== '', 'applied' => false, 'reason' => 'not_proposed'];
        if ($article !== '' && !hash_equals(self::markdown($document), $article)) {
            $reconciled = self::reconcile_complete_article($document, $article, 'seo', $workflow);
            if (!empty($reconciled['ok'])) {
                $document = $reconciled['document']; $changed = $reconciled['changed_ids'];
                $prose = ['received' => true, 'applied' => true, 'reason' => (string) ($reconciled['reason'] ?? 'complete_article_reconciled')];
            } else {
                // SEO components are independent from an unsafe historic full-article echo.
                // The persisted document remains authoritative and is never overwritten here.
                $prose = ['received' => true, 'applied' => false, 'reason' => (string) ($reconciled['reason'] ?? 'unsafe_seo_article_replacement')];
            }
        } elseif ($article !== '') {
            $prose = ['received' => true, 'applied' => false, 'reason' => 'unchanged_echo'];
        }
        if ($patch['received']) {
            $patches = self::apply_patches($document, $response, 'seo', $workflow);
            $patch['applied'] = !empty($patches['ok']);
            $patch['reason'] = (string) ($patches['reason'] ?? 'invalid_patch');
            if ($patch['applied']) {
                $document = $patches['document'];
                $changed = array_values(array_unique(array_merge($changed, (array) ($patches['changed_ids'] ?? []))));
                $prose = ['received' => true, 'applied' => true, 'reason' => 'patches'];
            } elseif (!$prose['received']) {
                $prose = ['received' => true, 'applied' => false, 'reason' => $patch['reason']];
            }
        }
        // A malicious or stale proposal with no independently usable SEO
        // component remains a hard failure. Partial delivery is accepted only
        // when there is actual independent data to preserve.
        if (!$has_components && (!empty($patch['received']) && empty($patch['applied'])
                || !empty($prose['received']) && empty($prose['applied']))) {
            $reason = !empty($patch['received']) && empty($patch['applied']) ? $patch['reason'] : $prose['reason'];
            return ['ok' => false, 'document' => $original, 'changed_ids' => [], 'reason' => (string) $reason,
                'prose' => $prose, 'patch' => $patch, 'received_components' => $received_components];
        }
        $document['seo'] = array_merge((array) ($document['seo'] ?? []), [
            'title' => '%%title%%', 'keyword' => trim((string) ($workflow['keyword'] ?? '')),
            'meta_description' => trim((string) ($sections['meta_description'] ?? '')),
            'report' => trim((string) ($sections['seo_report'] ?? '')),
        ]);
        $document['social_copy'] = trim((string) ($sections['social_copy'] ?? ''));
        $document['feedback_notes'] = trim((string) ($sections['feedback_notes'] ?? ''));
        $reel = class_exists('IDG_Reel_Contract') ? IDG_Reel_Contract::inspect((string) ($sections['reel_package'] ?? '')) : ['valid' => false, 'normalized' => ''];
        $document['reel'] = ['normalized' => (string) ($reel['normalized'] ?? ''), 'valid' => !empty($reel['valid'])];
        $document['links'] = self::authorized_links($workflow);
        $document['review'] = ['state' => 'seo_reviewed', 'validation' => []];
        return ['ok' => true, 'document' => self::seal($document), 'changed_ids' => $changed, 'reason' => '', 'prose' => $prose, 'patch' => $patch, 'received_components' => $received_components, 'reel_validated' => !empty($reel['valid'])];
    }

    /** Applies only a separately validated Reel; it never re-enters article reconciliation. */
    public static function apply_reel_package(array $document, string $reel_package): array {
        $reel = class_exists('IDG_Reel_Contract') ? IDG_Reel_Contract::inspect($reel_package) : ['valid' => false, 'normalized' => ''];
        if (empty($reel['valid'])) return ['ok' => false, 'document' => $document, 'reason' => 'invalid_reel_package'];
        $document['reel'] = ['normalized' => (string) ($reel['normalized'] ?? ''), 'valid' => true];
        return ['ok' => true, 'document' => self::seal($document), 'reason' => 'reel_only'];
    }

    /** Independently re-seals only a well-formed SEO meta description. */
    public static function apply_meta_description(array $document, string $description): array {
        $description = trim($description);
        $length = mb_strlen($description);
        if ($length < 106 || $length > 150 || preg_match('/[\r\n<>]/u', $description)
            || preg_match('/https?:\/\//iu', $description)) {
            return ['ok' => false, 'document' => $document, 'reason' => 'invalid_meta_description'];
        }
        $document['seo']['meta_description'] = $description;
        return ['ok' => true, 'document' => self::seal($document), 'reason' => 'meta_only'];
    }

    /**
     * No model request for marginal metadata overflow when an objectively
     * redundant, nonfactual phrase can be dropped while preserving keyword.
     * Larger or ambiguous edits remain the responsibility of localized repair.
     */
    public static function compact_meta_if_safe(array $document, array $workflow): array {
        $meta=trim((string)(($document['seo']??[])['meta_description']??''));
        $length=mb_strlen($meta);
        if ($length>=106 && $length<=150) return ['applied'=>false,'document'=>$document,'reason'=>'already_valid'];
        if ($length<151 || $length>165) return ['applied'=>false,'document'=>$document,'reason'=>'not_marginal'];
        $keyword=trim((string)($workflow['keyword']??''));
        // Changes are limited to redundant editorial scaffolding; do not
        // drop dates, figures, negations, suppliers or technical qualifiers.
        $redundant=[' de la marca',' de este proyecto',' en esta propuesta',' en el proyecto'];
        foreach ($redundant as $piece) {
            if (substr_count($meta,$piece)!==1) continue;
            $next=str_replace($piece,'',$meta);
            if ($keyword!=='' && mb_stripos($next,$keyword)===false) continue;
            $applied=self::apply_meta_description($document,$next);
            if (!empty($applied['ok'])) return ['applied'=>true,'document'=>$applied['document'],'reason'=>'redundant_nonfactual_phrase_removed'];
        }
        return ['applied'=>false,'document'=>$document,'reason'=>'requires_localized_repair'];
    }

    /** Completes only missing, already-authorized links by wrapping existing prose. */
    public static function complete_missing_authorized_links(array $document, array $workflow): array {
        $curation = self::curate_documentary_source_mentions($document, $workflow);
        if (empty($curation['ok'])) return ['ok' => false, 'document' => $document, 'reason' => (string) ($curation['reason'] ?? 'documentary_reference_ambiguous')];
        $document = $curation['document'];
        $required = [];
        $official = self::responsible_url($workflow);
        if ($official !== '') $required[] = ['url'=>$official, 'internal'=>false];
        if (class_exists('IDG_Internal_Links')) foreach ((array) IDG_Internal_Links::normalize($workflow) as $link) {
            $url = esc_url_raw((string) ($link['url'] ?? ''));
            if ($url !== '') $required[] = ['url'=>$url, 'internal'=>true];
        }
        if (!$required) return ['ok'=>false, 'document'=>$document, 'reason'=>'authorized_links_unresolved'];
        $existing = self::links(self::markdown($document));
        $remaining = []; $applied = [];
        // Each destination is independent: failure of one anchor may never
        // discard a different, already-authorized successful insertion.
        foreach ($required as $need) {
            $url = (string) $need['url'];
            $present = array_values(array_filter($existing, static fn(array $link): bool => $link['url'] === $url));
            if (count($present) > 1) { $remaining[] = ['url'=>$url,'internal'=>!empty($need['internal']),'reason'=>'duplicate_authorized_link']; continue; }
            if ($present) {
                if (empty($need['internal']) && self::ends_in_broken_connector((string) ($present[0]['anchor'] ?? ''))) {
                    $adjusted = self::repair_existing_official_anchor($document, $workflow, $need);
                    if ($adjusted !== null) { $document = $adjusted; $existing = self::links(self::markdown($document)); }
                    else $remaining[] = ['url'=>$url,'internal'=>false,'reason'=>'malformed_official_anchor'];
                }
                if (!empty($need['internal']) && !self::valid_internal_anchor((string) ($present[0]['anchor'] ?? ''), $workflow, $url)) {
                    $repaired = self::repair_existing_internal_anchor($document, $workflow, $need);
                    if ($repaired === null) $remaining[] = ['url'=>$url,'internal'=>true,'reason'=>'invalid_internal_anchor'];
                    else { $document = $repaired; $existing = self::links(self::markdown($document)); $applied[]=$url; }
                }
                continue;
            }
            $match = self::contextual_anchor($document, $workflow, $need);
            if ($match !== null) {
                [$index, $anchor] = $match;
                $text = self::block_text((array) $document['blocks'][$index]);
                if (substr_count($text, $anchor) !== 1) { $remaining[] = ['url'=>$url,'internal'=>!empty($need['internal']),'reason'=>'ambiguous_anchor']; continue; }
                $document['blocks'][$index]['text'] = str_replace($anchor, '['.$anchor.']('.$url.')', $text);
                $existing[] = ['anchor'=>$anchor, 'url'=>$url];
                $applied[] = $url;
                continue;
            }
            // A generic corporate apposition is a verifiable identity, not a
            // fabricated factual sentence. The source must match the brand.
            $appended = empty($need['internal']) ? self::appose_official_identity($document, $workflow, $url) : null;
            if ($appended !== null) {
                $document = $appended;
                $existing = self::links(self::markdown($document));
                $applied[] = $url;
                continue;
            }
            $remaining[] = ['url'=>$url,'internal'=>!empty($need['internal']),'reason'=>'missing_contextual_anchor'];
        }
        $document = self::seal($document);
        return ['ok'=>empty($remaining),'document'=>$document,
            'reason'=>empty($remaining)?'deterministic_authorized_links':'missing_unique_authorized_anchor',
            'applied'=>$applied,'remaining'=>$remaining];
    }

    /**
     * Remove explicitly known secondary-source citations from *publishable*
     * paragraphs only. This does not modify research evidence, attribution,
     * official-URL resolution, P2, headings, the factual box or other content.
     * For a non-mechanical citation or extra external URL, fail closed.
     */
    public static function curate_documentary_source_mentions(array $document, array $workflow): array {
        $original = $document;
        $official = self::responsible_url($workflow);
        if ($official === '' || mb_strtolower(trim((string) ($workflow['piece_type'] ?? ''))) !== 'actualidad') {
            return ['ok' => true, 'document' => $document, 'removed' => 0, 'changed_ids' => []];
        }
        $documentary = [];
        foreach (['official_source', 'source_information_url'] as $key) {
            $url = trim((string) ($workflow[$key] ?? ''));
            if (!filter_var($url, FILTER_VALIDATE_URL)) continue;
            $url = esc_url_raw($url);
            if ($url !== '' && rtrim($url, '/') !== rtrim($official, '/')) $documentary[$url] = true;
        }
        if (!$documentary) return ['ok' => true, 'document' => $document, 'removed' => 0, 'changed_ids' => []];

        $removed = 0; $changed_ids = []; $media_names = [];
        foreach ((array) ($document['blocks'] ?? []) as $index => $block) {
            $before = self::block_text((array) $block);
            $text = $before;
            foreach (self::links($text) as $link) {
                $url = (string) ($link['url'] ?? '');
                if (!isset($documentary[$url])) continue;
                if (($block['type'] ?? '') !== 'paragraph') {
                    return ['ok' => false, 'document' => $original, 'reason' => 'documentary_reference_in_protected_block'];
                }
                $anchor = (string) ($link['anchor'] ?? '');
                $markup = '[' . $anchor . '](' . $url . ')';
                if ($anchor === '' || substr_count($text, $markup) !== 1) {
                    return ['ok' => false, 'document' => $original, 'reason' => 'ambiguous_documentary_reference'];
                }
                // In both recognized patterns the entire phrase is a citation
                // mechanism with no project fact. Never remove a factual claim.
                $escaped = preg_quote($markup, '/');
                $patterns = [
                    '/,\s*mientras\s+que\s+la\s+cobertura\s+de\s+referencia\s+puede\s+consultarse\s+en\s+(?:la|el)\s+' . $escaped . '(?=[.!?;]|$)/iu',
                    '/(?:(?<=^)|(?<=[.!?] ))(?:Para\s+(?:m[aá]s|ampliar)\s+informaci[oó]n,?\s+)?(?:consulta(?:r)?|visita(?:r)?)\s+(?:(?:la|el)\s+)?' . $escaped . '\s*[.!?](?=\s|$)/iu',
                ];
                $candidate = $text; $count = 0;
                foreach ($patterns as $pattern) {
                    $proposed = preg_replace($pattern, '', $text, 1, $matches);
                    if ($proposed !== null && $matches === 1) {
                        $candidate = $proposed; $count = 1; break;
                    }
                }
                if ($count !== 1 || trim($candidate) === '') {
                    return ['ok' => false, 'document' => $original, 'reason' => 'documentary_reference_needs_editorial_review'];
                }
                if (preg_match('/^(?:nota|art[íi]culo|rese[ñn]a|cobertura)\s+de\s+(.+)$/iu', trim($anchor), $m)) {
                    $media_names[] = trim($m[1]);
                }
                $text = $candidate; $removed++;
            }
            if ($text !== $before) {
                $document['blocks'][$index]['text'] = $text;
                $changed_ids[] = (string) ($block['id'] ?? '');
            }
        }
        $visible = self::markdown($document);
        foreach ($media_names as $name) {
            if ($name !== '' && mb_stripos($visible, $name) !== false) {
                return ['ok' => false, 'document' => $original, 'reason' => 'documentary_media_mention_remains'];
            }
        }
        foreach (self::links($visible) as $link) {
            if (isset($documentary[(string) ($link['url'] ?? '')])) {
                return ['ok' => false, 'document' => $original, 'reason' => 'documentary_reference_remains'];
            }
        }
        if ($removed > 0) {
            $document['review']['changed_ids'] = array_values(array_unique(array_merge(
                (array) ($document['review']['changed_ids'] ?? []), $changed_ids
            )));
            $document = self::seal($document);
        }
        return ['ok' => true, 'document' => $document, 'removed' => $removed, 'changed_ids' => array_values(array_filter($changed_ids))];
    }

    /** Replaces the SEO link-count claim using the exact typed Gutenberg HTML. */
    public static function reconcile_seo_report_links(array $document, array $workflow): array {
        if (!class_exists('IDG_Post_Creator')) return $document;
        $html = (string) (self::gutenberg($document)['html'] ?? '');
        $report = IDG_Post_Creator::repair_seo_report_link_counts((string) (($document['seo'] ?? [])['report'] ?? ''), $html, $workflow);
        // Model recommendations may be stale after deterministic link repair.
        // Remove only already-fulfilled link-insertion requests; retain other
        // editorial suggestions and their original wording.
        $audit = IDG_Internal_Links::audit_html($html, $workflow);
        $internal_done = (int) ($audit['internal_count'] ?? 0) === 1 && empty($audit['errors']);
        $official = self::responsible_url($workflow);
        $external_done = false;
        if ($official !== '') foreach ((array) ($audit['anchors'] ?? []) as $link) {
            if (empty($link['internal']) && IDG_Internal_Links::equivalent_url((string) ($link['url'] ?? ''), $official)) {
                $external_done = true;
                break;
            }
        }
        $report = self::remove_fulfilled_link_suggestions($report, $internal_done, $external_done);
        if ($report === (string) (($document['seo'] ?? [])['report'] ?? '')) return $document;
        $document['seo']['report'] = $report;
        return self::seal($document);
    }

    /** Retain only outstanding model suggestions; no fabricated replacements. */
    private static function remove_fulfilled_link_suggestions(string $report, bool $internal_done, bool $external_done): string {
        if (!$internal_done && !$external_done) return $report;
        $lines = preg_split('/\R/u', $report) ?: [];
        $result = []; $suggestions = 0; $header_index = null; $in_list = false;
        foreach ($lines as $line) {
            if (preg_match('/^\s*-\s*\d+\s+mejoras?\s+sugeridas?\s*:/iu', $line)) {
                $header_index = count($result); $result[] = $line; $in_list = true; continue;
            }
            if ($in_list && preg_match('/^(\s*)\d+[.)]\s*(.+)$/u', $line, $m)) {
                $text = trim($m[2]);
                $insert_request = (bool) preg_match('/\b(?:integrar|insertar|a[nñ]adir|colocar|agregar|incorporar)\b/iu', $text);
                $internal_request = (bool) preg_match('/\benlace\s+interno\b/iu', $text);
                $external_request = (bool) preg_match('/\benlace\s+externo\b/iu', $text);
                if ($insert_request && (($internal_done && $internal_request) || ($external_done && $external_request))) continue;
                $result[] = $m[1] . (++$suggestions) . '. ' . $text;
                continue;
            }
            if ($in_list && trim($line) !== '' && !preg_match('/^\s*\d+[.)]\s/u', $line)) $in_list = false;
            $result[] = $line;
        }
        if ($header_index !== null) {
            if ($suggestions === 0) unset($result[$header_index]);
            else $result[$header_index] = '- ' . $suggestions . ' mejora' . ($suggestions !== 1 ? 's' : '') . ' sugerida' . ($suggestions !== 1 ? 's' : '') . ':';
        }
        return trim(implode("\n", array_values($result)));
    }

    /**
     * Replace model claims about P2 with counts from actual Gutenberg HTML.
     * This never changes article prose or claims that Final Guard approved SEO.
     */
    public static function reconcile_seo_feedback_links(array $document, array $workflow): array {
        if (!class_exists('IDG_Internal_Links')) return $document;
        $audit = IDG_Internal_Links::audit_html((string) (self::gutenberg($document)['html'] ?? ''), $workflow);
        $canonical_count = (int) ($audit['expected_count'] ?? 0);
        $official = self::responsible_url($workflow);
        $official_count = 0;
        foreach ((array) ($audit['anchors'] ?? []) as $link) {
            if ($official !== '' && empty($link['internal'])
                && IDG_Internal_Links::equivalent_url((string) ($link['url'] ?? ''), $official)) $official_count++;
        }
        $lines = preg_split('/\\R/u', trim((string) ($document['feedback_notes'] ?? ''))) ?: [];
        $retained = [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '' || preg_match('/verificaci[oó]n t[eé]cnica de enlaces/iu', $line)) continue;
            // Model-provided "resolved/inserted" assertions cannot overrule
            // the physical anchor count. Preserve actual editorial feedback.
            $about_internal = preg_match('/\\benlace(?:s)?\\b.*?\\bintern[oa]s?\\b/iu', $line) === 1;
            if ($about_internal && preg_match('/resuelt|asumid|integrad|aplicad|conservad|completad|verificad|validado/iu', $line)) continue;
            if ($about_internal && $canonical_count === 1
                && preg_match('/pendiente|ausente|faltante|no se (?:añadi[oó]|agreg[oó]|integr[oó])/iu', $line)) continue;
            $retained[] = $line;
        }
        $retained[] = '- Verificación técnica de enlaces: ' . $canonical_count . ' interno' . ($canonical_count === 1 ? '' : 's')
            . ' canónico' . ($canonical_count === 1 ? '' : 's') . ' detectado' . ($canonical_count === 1 ? '' : 's') . '; '
            . $official_count . ' externo' . ($official_count === 1 ? '' : 's') . ' corporativo' . ($official_count === 1 ? '' : 's')
            . ' detectado' . ($official_count === 1 ? '' : 's') . '. La aprobación depende del preflight.';
        $updated = trim(implode("\n", $retained));
        if ($updated === (string) ($document['feedback_notes'] ?? '')) return $document;
        $document['feedback_notes'] = $updated;
        return self::seal($document);
    }

    /** Keeps the sealed document's validation claim derived from final preflight. */
    public static function normalize_seo_report_validation(array $document, bool $approved, bool $reel_valid): array {
        $report = trim((string) (($document['seo'] ?? [])['report'] ?? ''));
        $status = $approved ? 'preflight Gutenberg aprobado' : 'preflight Gutenberg requiere intervención';
        $reel = $reel_valid ? 'Reel validado' : 'Reel requiere intervención';
        $line = '- Validaciones verificadas por plugin: ' . $status . '; ' . $reel . '.';
        $report = (string) preg_replace('/^[ \t]*[-*][ \t]*Validaciones(?:[ \t]+verificadas[ \t]+por[ \t]+plugin)?[ \t]*:.*$/imu', '', $report);
        $report = (string) preg_replace('/^Declaraciones del modelo \(no equivalen a verificación del plugin\):\s*/imu', '', $report);
        $report = trim($report);
        $document['seo']['report'] = trim('Declaraciones del modelo (no equivalen a verificación del plugin):' . "\n" . ($report !== '' ? $report . "\n\n" : '') . $line);
        return self::seal($document);
    }

    public static function markdown(array $document): string {
        $out = [];
        foreach ((array) ($document['blocks'] ?? []) as $block) {
            $type = (string) ($block['type'] ?? '');
            if ($type === 'heading') $out[] = str_repeat('#', max(1, min(3, (int) ($block['level'] ?? 2)))) . ' ' . trim((string) ($block['text'] ?? ''));
            elseif ($type === 'editorial_box') { $out[] = 'Caja editorial'; $out[] = trim((string) ($block['text'] ?? '')); }
            elseif ($type === 'list') foreach ((array) ($block['items'] ?? []) as $item) $out[] = '- ' . trim((string) $item);
            elseif ($type === 'paragraph') $out[] = trim((string) ($block['text'] ?? ''));
            $out[] = '';
        }
        return trim(implode("\n", $out));
    }

    public static function projection(array $document): string {
        $seo = (array) ($document['seo'] ?? []);
        return self::markdown($document) . "\n\nTÍTULO SEO\n%%title%%\n\nMETA DESCRIPTION\n" . (string) ($seo['meta_description'] ?? '')
            . "\n\nINFORME SEO INTERNO\n" . (string) ($seo['report'] ?? '') . "\n\nCOPY PARA REDES\n" . (string) ($document['social_copy'] ?? '')
            . "\n\nPAQUETE REEL\n" . self::reel_text((array) ($document['reel'] ?? []))
            . "\n\nRETROALIMENTACIÓN GERIZIM\n" . (string) ($document['feedback_notes'] ?? '');
    }

    /** Typed Gutenberg serializer: it never adds links, copy or prose. */
    public static function gutenberg(array $document): array {
        $blocks = []; $html = [];
        $title = '';
        foreach ((array) ($document['blocks'] ?? []) as $block) {
            $type = (string) ($block['type'] ?? ''); $text = self::inline_html(self::block_text($block));
            if ($type === 'heading') { $level=max(1,min(3,(int)($block['level']??2))); $tag='h'.$level; $inner="<$tag>$text</$tag>"; if ($level === 1) { if ($title === '') $title = wp_strip_all_tags($text); $html[]=$inner; } else { $blocks[]=self::wp_block('core/heading',['level'=>$level],$inner); $html[]=$inner; } }
            elseif ($type === 'editorial_box') { $inner='<p class="featured-snippet-box">'.$text.'</p>'; $blocks[]=self::wp_block('core/paragraph',['className'=>'featured-snippet-box'],$inner); $html[]=$inner; }
            elseif ($type === 'list') { $items=[]; foreach((array)($block['items']??[]) as $item) $items[]='<li>'.self::inline_html((string)$item).'</li>'; $inner='<ul>'.implode('',$items).'</ul>'; $blocks[]=self::wp_block('core/list',[],$inner); $html[]=$inner; }
            elseif ($type === 'paragraph') { $inner='<p>'.$text.'</p>'; $blocks[]=self::wp_block('core/paragraph',[],$inner); $html[]=$inner; }
        }
        return ['title' => $title, 'post_content' => serialize_blocks($blocks), 'html' => implode("\n", $html)];
    }

    public static function hash(array $document): string { return (string) ($document['hash'] ?? self::seal($document)['hash']); }
    /** Small current-state contract for model patch responses. */
    public static function prompt_manifest(array $workflow, string $article, string $phase): array {
        if (!in_array($phase, ['editorial', 'seo'], true) || !self::exists($workflow)) return ['ok' => true, 'text' => ''];
        $document = $workflow['editorial_document'];
        if (!hash_equals(self::markdown($document), $article)) return ['ok' => false, 'text' => '', 'reason' => 'stale_article'];
        $rows = []; $ids = [];
        foreach ((array) ($document['blocks'] ?? []) as $ordinal => $block) {
            $id = (string) ($block['id'] ?? ''); $type = (string) ($block['type'] ?? '');
            if ($id === '' || isset($ids[$id]) || !in_array($type, ['heading', 'paragraph', 'editorial_box', 'list'], true)) return ['ok' => false, 'text' => '', 'reason' => 'malformed_or_duplicate_blocks'];
            if ($type === 'heading' && !in_array((int) ($block['level'] ?? 0), [1, 2, 3], true)) return ['ok' => false, 'text' => '', 'reason' => 'malformed_or_duplicate_blocks'];
            $text = self::block_text($block);
            if ($text === '') return ['ok' => false, 'text' => '', 'reason' => 'malformed_or_duplicate_blocks'];
            $ids[$id] = true;
            $preview = preg_replace('/\s+/u', ' ', $text);
            if (mb_strlen($preview) > 160) $preview = mb_substr($preview, 0, 157) . '...';
            $protected = $phase === 'seo' && ($type === 'heading' || $type === 'editorial_box') ? ' PROTEGIDO: no editar' : ' editable';
            $level = $type === 'heading' ? ' nivel H' . (int) $block['level'] : '';
            $rows[] = sprintf('- #%d | %s | %s%s | SHA256 %s | vista: %s%s', $ordinal + 1, $id, $type, $level, hash('sha256', $text), $preview, $protected);
        }
        if (!$rows) return ['ok' => false, 'text' => '', 'reason' => 'malformed_or_duplicate_blocks'];
        return ['ok' => true, 'text' => "CONTRATO DE PARCHES DEL DOCUMENTO ACTUAL\nCada hash corresponde exactamente al texto actual del bloque (listas: elementos unidos con |). No expongas IDs, hashes ni este contrato dentro del artículo.\n" . implode("\n", $rows)];
    }
    private static function seal(array $doc): array { $revision = max(0, (int) ($doc['revision'] ?? 0)) + 1; unset($doc['hash'], $doc['revision']); self::ksort_recursive($doc); $doc['hash'] = hash('sha256', (string) wp_json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); $doc['revision'] = $revision; return $doc; }
    private static function stable_id(string $type, string $text, int $ordinal): string { return $type . '-' . substr(hash('sha256', $type . '|' . $ordinal . '|' . preg_replace('/\s+/u', ' ', $text)), 0, 16); }
    private static function block_text(array $b): string { return (string) ($b['text'] ?? implode('|', (array) ($b['items'] ?? []))); }
    private static function same_shape(array $a, array $b): bool { if (count((array) ($a['blocks'] ?? [])) !== count((array) ($b['blocks'] ?? []))) return false; foreach ($a['blocks'] as $i => $x) { $y = $b['blocks'][$i] ?? []; if (($x['type'] ?? '') !== ($y['type'] ?? '') || ($x['level'] ?? 0) !== ($y['level'] ?? 0)) return false; } return true; }
    private static function preserve_ids(array $doc, array $previous): array { if (self::same_shape($previous, $doc)) foreach ($doc['blocks'] as $i => $b) $doc['blocks'][$i]['id'] = $previous['blocks'][$i]['id']; return $doc; }
    private static function authorized_links(array $w): array { $links = []; if (class_exists('IDG_Internal_Links')) foreach ((array) IDG_Internal_Links::normalize($w) as $l) if (!empty($l['url'])) $links[] = ['url' => esc_url_raw((string) $l['url']), 'anchor' => '', 'kind' => 'internal']; $url = self::responsible_url($w); if ($url !== '') $links[] = ['url' => $url, 'anchor' => '', 'kind' => 'official']; return $links; }
    /** An article needs an explicit, validated corporate identity. Documentary URLs never fill this role. */
    private static function responsible_url(array $workflow): string { $canonical = (array) ($workflow['canonical_context'] ?? []); foreach ([$canonical['responsible_official_url'] ?? '', $workflow['responsible_official_url'] ?? ''] as $url) { $url = trim((string) $url); if (filter_var($url, FILTER_VALIDATE_URL) !== false && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) return esc_url_raw($url); } return ''; }
    private static function seo_sections(string $raw): array { $s = ['article' => '', 'meta_description' => '', 'seo_report' => '', 'social_copy' => '', 'reel_package' => '', 'feedback_notes' => '']; $map = ['ART[ÍI]CULO FINAL' => 'article', 'T[ÍI]TULO SEO' => 'title', 'META DESCRIPTION' => 'meta_description', 'INFORME SEO INTERNO' => 'seo_report', 'COPY PARA REDES' => 'social_copy', 'PAQUETE REEL' => 'reel_package', 'RETROALIMENTACI[ÓO]N GERIZIM' => 'feedback_notes']; $matches=[]; foreach ($map as $p=>$k) if (preg_match('/^\s*(?:#{1,6}\s*)?(?:\*\*)?\s*'.$p.'\s*(?:\*\*)?\s*:?\s*$/imu',$raw,$m,PREG_OFFSET_CAPTURE)) $matches[]=['key'=>$k,'start'=>$m[0][1],'body'=>$m[0][1]+strlen($m[0][0])]; usort($matches, fn($a,$b)=>$a['start']<=>$b['start']); if ($matches && $matches[0]['start'] > 0 && $matches[0]['key'] !== 'article') $s['article'] = trim(substr($raw, 0, $matches[0]['start'])); foreach($matches as $i=>$m) $s[$m['key']]=trim(substr($raw,$m['body'],($matches[$i+1]['start']??strlen($raw))-$m['body'])); return $s; }
    /** Finds one deterministic, contextual prose span; never manufactures prose or uses the box. */
    private static function contextual_anchor(array $document, array $workflow, array $need): ?array {
        if (empty($need['internal'])) return self::official_identity_anchor($document, $workflow);
        $tokens = self::internal_anchor_tokens($workflow, (string) $need['url']);
        if (!$tokens) return null;
        $keyword = self::plain((string) ($workflow['keyword'] ?? ''));
        $matches = [];
        foreach ((array) ($document['blocks'] ?? []) as $index => $block) {
            if (($block['type'] ?? '') !== 'paragraph') continue;
            $text = self::block_text((array) $block);
            if (self::links($text)) continue;
            foreach (self::phrases($text, $tokens, !empty($need['internal'])) as $phrase) {
                if ($keyword !== '' && self::plain($phrase) === $keyword) continue;
                $matches[] = [$index, $phrase];
            }
        }
        // A fixed five-word window avoids manufacturing several equally-long
        // generated alternatives around the same unequivocal mention.
        if (!$matches) return self::contextual_dron_singular_anchor($document, $workflow, $need)
            ?? self::contextual_category_anchor($document, $workflow, $need);
        // Prefer complete articulated nominal phrases (e.g. "la lectura del
        // calzado") to looser generic ones ("innovación de calzado"). Then
        // use article order, not the arbitrary number of characters.
        $quality = static fn(array $m): int => preg_match('/\b(?:del|de\s+la|de\s+los|de\s+las)\b/iu', $m[1]) ? 0
            : (preg_match('/\b(?:de|para|con|en)\b/iu', $m[1]) ? 1 : 2);
        usort($matches, static fn(array $a, array $b): int => $quality($a) <=> $quality($b)
            ?: $a[0] <=> $b[0] ?: strlen($a[1]) <=> strlen($b[1]) ?: strcmp($a[1], $b[1]));
        $best = $matches[0];
        $same = array_filter($matches, static fn(array $m): bool => $quality($m) === $quality($best) && $m[0] === $best[0]);
        return count($same) === 1 && substr_count(self::block_text((array) $document['blocks'][$best[0]]), $best[1]) === 1 ? $best : null;
    }
    /**
     * P2: the verified Drones tag may occur as singular "dron" in Spanish prose.
     * Match only an already-written, complete nominal span, never the protected
     * box, a linked paragraph or a generic "aircraft" synonym. More than one
     * candidate is ambiguous, so no automatic link is inserted.
     */
    private static function contextual_dron_singular_anchor(array $document, array $workflow, array $need): ?array {
        $url = (string) ($need['url'] ?? '');
        $canonical = [];
        foreach ((array) IDG_Internal_Links::normalize($workflow) as $link) {
            if ((string) ($link['url'] ?? '') === $url) { $canonical = $link; break; }
        }
        if ((string) ($canonical['source_type'] ?? '') !== 'tag'
            || self::plain((string) ($canonical['source_name'] ?? '')) !== 'drones') return null;
        $matches = [];
        foreach ((array) ($document['blocks'] ?? []) as $index => $block) {
            if (($block['type'] ?? '') !== 'paragraph') continue;
            $text = self::block_text((array) $block);
            if (self::links($text) || str_contains($text, '](')) continue;
            // e.g. "el uso del dron", "la configuración del dron": existing prose only.
            $pattern = '/(?<![\\p{L}\\p{N}])((?:el|la|los|las|un|una)\\s+[\\p{L}\\p{N}]+(?:\\s+[\\p{L}\\p{N}]+)?\\s+(?:del|de\\s+la|de\\s+los|de\\s+las)\\s+dron)(?![\\p{L}\\p{N}])/iu';
            if (!preg_match_all($pattern, $text, $found)) continue;
            foreach ((array) ($found[1] ?? []) as $span) {
                if (!self::valid_internal_anchor((string) $span, $workflow, $url)
                    || substr_count($text, (string) $span) !== 1) continue;
                $matches[] = [$index, (string) $span];
            }
        }
        return count($matches) === 1 ? $matches[0] : null;
    }

    /**
     * A responsible already named in the brief may be apposed to an existing
     * unambiguous "la marca" reference. This inserts the *identity only*, not
     * an unsupported new claim or a fabricated corporate destination.
     */
    private static function appose_official_identity(array $document, array $workflow, string $url): ?array {
        $name = trim((string) ($workflow['entity'] ?? ''));
        if ($name === '' || $url !== self::responsible_url($workflow)) return null;
        // The brief explicitly authorizes the destination. Attaching that
        // already-named responsible to an unambiguous "la marca" does not
        // depend on the registered domain containing the literal brand name.
        if (self::suspicious_responsible_subdomain($url,$name)) return null;
        $candidates = [];
        foreach ((array) ($document['blocks'] ?? []) as $i => $block) {
            if (($block['type'] ?? '') !== 'paragraph') continue;
            $text = self::block_text((array) $block);
            if (self::links($text)) continue;
            // Only a clear singular corporate reference can receive the
            // already verified brand; do not attach it to "un estudio", etc.
            if (!preg_match_all('/(?<![\p{L}\p{N}])(la marca|la firma)(?![\p{L}\p{N}])/iu', $text, $hits, PREG_OFFSET_CAPTURE)) continue;
            foreach ((array) ($hits[1] ?? []) as $hit) $candidates[] = [$i,(string)$hit[0]];
        }
        if (count($candidates)!==1) return null;
        [$index,$phrase]=$candidates[0];
        $text = self::block_text((array) $document['blocks'][$index]);
        $replacement=$phrase.' ['.$name.']('.$url.')';
        $modified=preg_replace('/'.preg_quote($phrase,'/').'/u',$replacement,$text,1,$count);
        if ($count!==1 || $modified===null) return null;
        $document['blocks'][$index]['text']=$modified;
        return $document;
    }

    /** Reusable, category-backed object vocabulary when the literal tag
     * is absent. Only already-written nouns in vetted mobility articles can
     * be linked; no tag is fabricated, and prose/URLs are immutable. */
    private static function contextual_category_anchor(array $document, array $workflow, array $need): ?array {
        if (empty($need['internal']) || (int)($workflow['category_id']??0)!==1236) return null;
        $resolved = [];
        foreach ((array) IDG_Internal_Links::normalize($workflow) as $entry) {
            if ((string)($entry['url']??'')===(string)($need['url']??'')) { $resolved=$entry; break; }
        }
        if (($resolved['source_type']??'')!=='tag') return null;
        $name = self::plain((string)($resolved['source_name']??''));
        if (!in_array($name, ['vehículos eléctricos','vehiculos electricos','diseño automotriz','diseno automotriz'],true)) return null;
        $matches=[];
        foreach ((array)($document['blocks']??[]) as $index=>$block) {
            if (($block['type']??'')!=='paragraph') continue;
            $text=self::block_text((array)$block);
            if (self::links($text)) continue;
            if (!preg_match_all('/(?<![\p{L}\p{N}])((?:un|una|el|la|este|esta)\s+(?:coche|autom[oó]vil|veh[ií]culo|concept\s+car|prototipo))(?![\p{L}\p{N}])/iu',$text,$found)) continue;
            foreach ((array)($found[1]??[]) as $phrase) {
                if (self::valid_internal_anchor((string)$phrase,$workflow,(string)$need['url'])
                    && substr_count($text,(string)$phrase)===1) $matches[]=[$index,(string)$phrase];
            }
        }
        // Favor a descriptive, stable noun phrase already present in the
        // article. Reject ambiguity in a single paragraph, keep article order.
        if (!$matches) return null;
        usort($matches,static fn($a,$b)=>$a[0]<=>$b[0]?:strcmp($a[1],$b[1]));
        $first=$matches[0];
        $same=array_filter($matches,static fn($m)=>$m[0]===$first[0]);
        return count($same)===1?$first:null;
    }

    /** Pick a single, factual paragraph. Never touch boxes, headings or an
     * existing link; this is only for a bounded additive fallback. */
    public static function anchor_insertion_target(array $document, array $workflow, array $need): ?array {
        $url=(string)($need['url']??''); $is_internal=!empty($need['internal']);
        if ($url==='') return null;
        if ($is_internal) {
            $verified=false;
            foreach ((array) IDG_Internal_Links::normalize($workflow) as $entry) {
                if ((string)($entry['url']??'')===$url) $verified=true;
            }
            if (!$verified) return null;
        } elseif ($url!==self::responsible_url($workflow)) return null;
        $keyword=trim((string)($workflow['keyword']??''));
        $best=null; $best_score=-999;
        foreach ((array)($document['blocks']??[]) as $index=>$block) {
            if (($block['type']??'')!=='paragraph') continue;
            $text=self::block_text((array)$block);
            if (self::links($text) || strlen($text)<70 || strlen($text)>1400) continue;
            $score= -$index;
            if ($is_internal) {
                $score+=15;
                foreach ((array) IDG_Internal_Links::normalize($workflow) as $row) {
                    if (($row['url']??'')!==$url) continue;
                    foreach (self::internal_anchor_tokens($workflow,$url) as $token) {
                        if (preg_match('/(?<![\p{L}\p{N}])'.preg_quote($token,'/').'(?![\p{L}\p{N}])/iu',self::plain($text))) $score+=30;
                    }
                }
            } else {
                if (preg_match('/\b(?:la marca|la firma|el fabricante|el estudio)\b/iu',$text)) $score+=50;
                if ($keyword!=='' && mb_stripos($text,$keyword)!==false) $score+=25;
            }
            if ($score>$best_score) { $best=['index'=>$index,'id'=>(string)($block['id']??''),'text'=>$text];$best_score=$score; }
        }
        return $best;
    }

    /** Only a real, already-linked P2 paragraph is eligible for a localized
     * editorial rewrite. This never selects a different paragraph to fix it.
     */
    public static function contextual_anchor_revision_target(array $document, array $need): ?array {
        if (empty($need['internal']) || (string)($need['url']??'')==='') return null;
        foreach ((array)($document['blocks']??[]) as $index=>$block) {
            if (($block['type']??'')!=='paragraph') continue;
            $links=self::links(self::block_text((array)$block));
            if (count($links)!==1 || (string)($links[0]['url']??'')!==(string)$need['url']) continue;
            return ['index'=>$index,'id'=>(string)($block['id']??''),'text'=>self::block_text((array)$block),'anchor'=>(string)($links[0]['anchor']??'')];
        }
        return null;
    }

    /** Apply the language editor's one-paragraph proposal only after the
     * safety reviewer verifies the authorized URL, link count, protected
     * facts, topical continuity and exact unchanged surrounding blocks.
     */
    public static function apply_contextual_anchor_revision(array $document, array $workflow, array $need, array $target, string $raw): array {
        $deny=static fn(string $reason):array=>['ok'=>false,'document'=>$document,'reason'=>$reason];
        $url=(string)($need['url']??'');
        if ($url==='' || empty($need['internal'])) return $deny('not_internal_p2');
        $approved=false;
        foreach ((array)IDG_Internal_Links::normalize($workflow) as $link) {
            if ((string)($link['url']??'')===$url) $approved=true;
        }
        if (!$approved) return $deny('unapproved_p2_url');
        $index=(int)($target['index']??-1);
        if (!isset($document['blocks'][$index]) || ($document['blocks'][$index]['type']??'')!=='paragraph'
            || (string)($document['blocks'][$index]['id']??'')!==(string)($target['id']??'')
            || self::block_text((array)$document['blocks'][$index])!==(string)($target['text']??'')) return $deny('stale_target');
        $raw=trim((string)preg_replace('/\\A```(?:json)?\\s*|\\s*```\\z/iu','',$raw));
        $patch=json_decode($raw,true);
        if (!is_array($patch) || array_keys($patch)!==['paragraph'] || !is_string($patch['paragraph'])) return $deny('invalid_editor_response');
        $after=trim($patch['paragraph']);
        if ($after==='' || strlen($after)>2500 || preg_match('/[<>\\r\\n]/u',$after)) return $deny('paragraph_out_of_scope');
        $old=(string)$target['text'];
        $before_links=self::links($old);$after_links=self::links($after);
        if (count($before_links)!==1 || count($after_links)!==1 || (string)$after_links[0]['url']!==$url) return $deny('link_destination_or_count_changed');
        if (!self::valid_internal_anchor((string)$after_links[0]['anchor'],$workflow,$url)) return $deny('new_anchor_invalid');
        $plain_old=preg_replace('/\\[([^\\]\\r\\n]+)\\]\\(https?:\\/\\/[^\\s)]+\\)/u','$1',$old);
        $plain_new=preg_replace('/\\[([^\\]\\r\\n]+)\\]\\(https?:\\/\\/[^\\s)]+\\)/u','$1',$after);
        if (!is_string($plain_old)||!is_string($plain_new)) return $deny('invalid_link_markup');
        preg_match_all('/\\d+(?:[.,]\\d+)*/u',$plain_old,$digits_old);
        preg_match_all('/\\d+(?:[.,]\\d+)*/u',$plain_new,$digits_new);
        $digits_a=$digits_old[0];$digits_b=$digits_new[0];sort($digits_a);sort($digits_b);
        if ($digits_a!==$digits_b) return $deny('protected_numbers_changed');
        // Names/keywords, explicit negations and factual hedges may not be
        // dropped or inserted during a link-only language repair.
        foreach ([(string)($workflow['keyword']??''),(string)($workflow['entity']??'')] as $protected) {
            if ($protected!=='' && substr_count(self::plain($plain_old),self::plain($protected))
                !== substr_count(self::plain($plain_new),self::plain($protected))) return $deny('protected_identity_changed');
        }
        preg_match_all('/\\b(?:no|ni|sin|nunca|jamás|siempre|solo|solamente|puede|podría|podria|hasta|más|menos|todos|ninguno)\\b/iu',$plain_old,$qual_old);
        preg_match_all('/\\b(?:no|ni|sin|nunca|jamás|siempre|solo|solamente|puede|podría|podria|hasta|más|menos|todos|ninguno)\\b/iu',$plain_new,$qual_new);
        $q1=array_map('mb_strtolower',$qual_old[0]);$q2=array_map('mb_strtolower',$qual_new[0]);sort($q1);sort($q2);
        if ($q1!==$q2) return $deny('protected_qualifier_changed');
        $old_words=self::words($plain_old);$new_words=self::words($plain_new);
        $overlap=count(array_intersect($old_words,$new_words));
        if ($old_words && $overlap / count($old_words)<0.70) return $deny('factual_paragraph_rewritten');
        if (abs(mb_strlen($plain_new)-mb_strlen($plain_old))>100) return $deny('paragraph_diff_too_large');
        $document['blocks'][$index]['text']=$after;
        return ['ok'=>true,'document'=>self::seal($document),'reason'=>'localized_editorial_anchor_reconciled'];
    }

    /**
     * Model output is NOT an authorized replacement paragraph. The model may
     * propose only a very short insertion of verified words and a canonical
     * URL; the plugin applies it itself after checking source, scope, and
     * exact unchanged pre-existing prose.
     */
    public static function apply_bounded_link_insertion(array $document, array $workflow, array $need, array $target, string $raw): array {
        $reject=static fn(string $reason):array=>['ok'=>false,'document'=>$document,'reason'=>$reason];
        $url=(string)($need['url']??''); $internal=!empty($need['internal']);
        if ($url==='' || ($internal && !array_filter((array) IDG_Internal_Links::normalize($workflow),
            static fn(array $r):bool=>(string)($r['url']??'')===$url)) || (!$internal && $url!==self::responsible_url($workflow))) {
            return $reject('url_not_canonical');
        }
        $raw=trim((string)preg_replace('/\A```(?:json)?\s*|\s*```\z/iu','',$raw));
        $data=json_decode($raw,true);
        if (!is_array($data) || array_keys($data)!==['after','addition']
            || !is_string($data['after']) || !is_string($data['addition'])) return $reject('invalid_insertion_json');
        $after=$data['after']; $addition=$data['addition'];
        if ($after==='' || $addition==='' || strlen($addition)>220 || preg_match('/[<>\r\n]/u',$addition)) return $reject('insertion_out_of_scope');
        $index=(int)($target['index']??-1);
        if (!isset($document['blocks'][$index]) || ($document['blocks'][$index]['type']??'')!=='paragraph'
            || (string)($document['blocks'][$index]['id']??'')!==(string)($target['id']??'')) return $reject('stale_paragraph');
        $text=self::block_text($document['blocks'][$index]);
        if ($text!==(string)($target['text']??'') || substr_count($text,$after)!==1 || self::links($text)) return $reject('paragraph_modified_or_ambiguous');
        // Insert only at a word/punctuation boundary, never inside a token.
        $offset=strpos($text,$after)+strlen($after);
        if ($offset<strlen($text) && preg_match('/^[\p{L}\p{N}]/u',substr($text,$offset))) return $reject('midword_insertion');
        $matches=self::links($addition);
        if (count($matches)!==1 || (string)$matches[0]['url']!==$url
            || !str_contains($addition,'['.$matches[0]['anchor'].']('.$url.')')) return $reject('unexpected_link_markup');
        $visible=str_replace('['.$matches[0]['anchor'].']('.$url.')',(string)$matches[0]['anchor'],$addition);
        if (self::links($visible) || preg_match('/https?:\/\/|\[|\]|\*|[0-9]/u',$visible)) return $reject('unsafe_added_text');
        if (count(preg_split('/\s+/u',trim($visible),-1,PREG_SPLIT_NO_EMPTY))>12) return $reject('insertion_too_long');
        $tokens=self::words(self::plain($visible));
        $whitelist=['marca','firma','el','la','los','las','un','una','en','de','del','y','para','con','al','su','este','esta','estos','estas','diseño','contexto','ámbito','sector','proyecto','tipo'];
        foreach ($tokens as $token) {
            if (in_array($token,$whitelist,true)) continue;
            $source=$internal ? '' : (string)($workflow['entity']??'');
            if ($internal) foreach ((array)IDG_Internal_Links::normalize($workflow) as $row) {
                if ((string)($row['url']??'')===$url) { $source=(string)($row['source_name']??'');break; }
            }
            if (!in_array($token,self::words($source),true)) return $reject('new_unverified_lexical_claim');
        }
        if ($internal && !self::valid_internal_anchor((string)$matches[0]['anchor'],$workflow,$url)) return $reject('invalid_internal_anchor');
        if (!$internal && self::plain((string)$matches[0]['anchor'])!==self::plain((string)($workflow['entity']??''))) return $reject('invalid_corporate_anchor');
        $document['blocks'][$index]['text']=substr($text,0,$offset).$addition.substr($text,$offset);
        $updated=self::links(self::markdown($document));
        if (count(array_filter($updated,static fn(array $l):bool=>$l['url']===$url))!==1) return $reject('link_count_invalid');
        return ['ok'=>true,'document'=>self::seal($document),'reason'=>'strictly_additive_verified_insertion'];
    }

    /** Existing prose is immutable; only the brackets enclosing the URL move. */
    private static function ends_in_broken_connector(string $anchor): bool {
        return (bool) preg_match('/\b(?:no|ni|y|o|de|del|para|por|con|sin|un|una|que|a|al|en)\s*$/iu', trim($anchor));
    }
    private static function repair_existing_official_anchor(array $document, array $workflow, array $need): ?array {
        foreach ((array) ($document['blocks'] ?? []) as $index => $block) {
            if (($block['type'] ?? '') !== 'paragraph') continue;
            $text = self::block_text((array) $block);
            $links = self::links($text);
            if (count($links) !== 1 || (string) ($links[0]['url'] ?? '') !== (string) $need['url']) continue;
            $unlinked = preg_replace('/\[([^\]\r\n]+)\]\(' . preg_quote((string) $need['url'], '/') . '\)/u', '$1', $text, 1, $replaced);
            if ($replaced !== 1 || $unlinked === null) return null;
            $candidate = $document;
            $candidate['blocks'][$index]['text'] = $unlinked;
            $match = self::official_identity_anchor($candidate, $workflow, $index);
            if ($match === null || $match[0] !== $index) return null;
            $name = (string) $match[1];
            $candidate['blocks'][$index]['text'] = preg_replace('/' . preg_quote($name, '/') . '/u', '[' . $name . '](' . $need['url'] . ')', $unlinked, 1);
            return $candidate;
        }
        return null;
    }

    /** Prefer the whole responsible name over arbitrary surrounding words. */
    private static function official_identity_anchor(array $document, array $workflow, ?int $only_index = null): ?array {
        $entity = trim((string) ($workflow['entity'] ?? ''));
        $keyword = trim((string) ($workflow['keyword'] ?? ''));
        if ($entity === '') return null;
        // The operator's explicitly configured responsible URL is the source of
        // truth. It need not have the same literal string as the business name
        // (Tripo Studio -> studio.tripo3d.ai). Domain matching only rejects a
        // suspicious brand-in-foreign-subdomain construction; it never invents
        // or replaces the authorized URL.
        $official = self::responsible_url($workflow);
        if ($official === '' || self::suspicious_responsible_subdomain($official, $entity)) return null;
        $names = [$entity];
        // Corporate/legal suffixes are not always repeated in publishable prose.
        // The abbreviated brand is accepted ONLY if it exactly matches the
        // registered brand label in the explicitly authorized corporate host.
        // This cannot derive or invent a URL from a research source.
        $official = self::responsible_url($workflow);
        if ($official !== '') {
            $short = trim((string) preg_replace('/(?:,?\s+)(?:Electronics|Inc\.?|Incorporated|Corporation|Corp\.?|Ltd\.?|Limited|GmbH)\.?$/iu', '', $entity));
            $host = strtolower((string) parse_url($official, PHP_URL_HOST));
            $host = preg_replace('/^www\./i', '', $host);
            $labels = explode('.', (string) $host);
            $brand_index = count($labels) - 2;
            if ($brand_index >= 1 && in_array($labels[$brand_index], ['co', 'com', 'org', 'net'], true)) {
                $brand_index--;
            }
            $host_label = $labels[max(0, $brand_index)] ?? '';
            $short_key = preg_replace('/[^a-z0-9]/', '', strtolower(remove_accents($short)));
            $host_key = preg_replace('/[^a-z0-9]/', '', strtolower(remove_accents($host_label)));
            if ($short !== $entity && strlen((string) $short_key) >= 3
                && $host_key !== '' && hash_equals((string) $host_key, (string) $short_key)) {
                $names[] = $short;
            }
        }
        // Prefer the complete responsible name wherever it occurs in real
        // prose, then a safely verified short brand. Never link the editorial
        // box or split the project's complete keyword phrase.
        foreach ($names as $brand) {
            foreach ((array) ($document['blocks'] ?? []) as $index => $block) {
                if ($only_index !== null && $index !== $only_index) continue;
                if (($block['type'] ?? '') !== 'paragraph' || self::links(self::block_text((array) $block))) continue;
                $text = self::block_text((array) $block);
                if (!preg_match_all('/(?<![\p{L}\p{N}])' . preg_quote($brand, '/') . '(?![\p{L}\p{N}])/iu', $text, $hits, PREG_OFFSET_CAPTURE)) continue;
                foreach ((array) ($hits[0] ?? []) as $hit) {
                    $matched = (string) $hit[0]; $offset = (int) $hit[1];
                    if (self::within_protected_keyword($text, $keyword, $offset, strlen($matched))) continue;
                    if (count((array) ($hits[0] ?? [])) !== 1) continue;
                    return [$index, $matched];
                }
            }
        }
        return null;
    }

    /** Catch brand.evil.com without assuming a legitimate company owns a
     * domain that is literally identical to its brand. No DNS or guesswork.
     */
    public static function suspicious_responsible_subdomain(string $url, string $entity): bool {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '') return true;
        $labels = array_values(array_filter(explode('.', $host), static fn($x): bool => $x !== ''));
        if (count($labels) < 2) return true;
        $root_index = count($labels) - 2;
        if ($root_index >= 1 && in_array($labels[$root_index], ['com','co','org','net'], true)) $root_index--;
        $root = preg_replace('/[^a-z0-9]/', '', (string) ($labels[$root_index] ?? ''));
        $first = preg_split('/\s+/u', trim((string) preg_replace('/[,._-]+/u', ' ', $entity)))[0] ?? '';
        $brand = preg_replace('/[^a-z0-9]/', '', strtolower(remove_accents((string)$first)));
        if (strlen((string)$brand) < 4) return false;
        if (str_starts_with((string)$root, (string)$brand)) return false;
        foreach (array_slice($labels, 0, max(0,$root_index)) as $sub) {
            $sub = preg_replace('/[^a-z0-9]/', '', $sub);
            if ($sub === $brand || (strlen((string)$sub) > strlen((string)$brand) && str_starts_with($sub, (string)$brand))) return true;
        }
        return false;
    }

    private static function within_protected_keyword(string $text, string $keyword, int $offset, int $length): bool {
        if ($keyword === '') return false;
        $pattern = '/(?<![\p{L}\p{N}])' . preg_quote($keyword, '/') . '(?![\p{L}\p{N}])/iu';
        if (!preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) return false;
        foreach ((array) ($matches[0] ?? []) as $match) {
            $start = (int) $match[1];
            $end = $start + strlen((string) $match[0]);
            if ($offset >= $start && ($offset + $length) <= $end) return true;
        }
        return false;
    }

    /** Repair an existing link where it already lives. Searching for an
     * anchor in a different paragraph cannot legitimately repair that link.
     * First try an exact-span expansion (zero prose changes). The semantic
     * paragraph editor may be invoked separately if none is possible.
     */
    private static function repair_existing_internal_anchor(array $document, array $workflow, array $need): ?array {
        foreach ((array) ($document['blocks'] ?? []) as $index => $block) {
            if (($block['type'] ?? '') !== 'paragraph') continue;
            $text = self::block_text((array)$block);
            $links = self::links($text);
            if (count($links) !== 1 || (string)($links[0]['url'] ?? '') !== (string)$need['url']) continue;
            $old_markup = '[' . $links[0]['anchor'] . '](' . $need['url'] . ')';
            if (substr_count($text, $old_markup) !== 1) continue;
            $unlinked = str_replace($old_markup, $links[0]['anchor'], $text);
            $start = strpos($unlinked, (string)$links[0]['anchor']);
            if ($start === false || substr_count($unlinked, (string)$links[0]['anchor']) !== 1) continue;
            $end = $start + strlen((string)$links[0]['anchor']);
            $ends = [$end];
            // A model may have linked a whole clause with a colon. End the
            // link at the first complete noun phrase, leaving punctuation and
            // all following words exactly where they were.
            if (preg_match('/[,:;.!?]/u', (string)$links[0]['anchor'], $delimiter, PREG_OFFSET_CAPTURE)) {
                $prefix_end = $start + (int)$delimiter[0][1];
                if ($prefix_end > $start) array_unshift($ends,$prefix_end);
            }
            $phrase = null;
            foreach ($ends as $candidate_end) {
                $phrase=self::local_internal_anchor($unlinked,$start,$candidate_end,$workflow,(string)$need['url']);
                if ($phrase !== null) break;
            }
            if ($phrase === null) continue;
            $candidate = $document;
            $candidate['blocks'][$index]['text'] = substr_replace($unlinked,
                '['.$phrase['anchor'].']('.$need['url'].')', $phrase['start'], $phrase['length']);
            return self::seal($candidate);
        }
        return null;
    }

    /** Explore contiguous words in the SAME clause, anchored on the existing
     * linked term, favoring complete noun phrases, never arbitrary windows.
     */
    private static function local_internal_anchor(string $text, int $start, int $end, array $workflow, string $url): ?array {
        $before = substr($text, max(0, $start - 96), min(96, $start));
        if (preg_match('/[.!?;:,\r\n]/u', $before)) {
            $punct = preg_split('/[.!?;:,\r\n]/u', $before);
            $before = (string) end($punct);
        }
        preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}’\'-]*/u', $before, $found, PREG_OFFSET_CAPTURE);
        $words = $found[0] ?? [];
        $candidates = [];
        $base = $start - strlen($before);
        for ($n = 1; $n <= min(6, count($words)); $n++) {
            $offset = $base + (int)$words[count($words)-$n][1];
            $span = substr($text,$offset,$end-$offset);
            if ($span === false || !self::valid_internal_anchor($span,$workflow,$url)) continue;
            if (preg_match('/[,:;.!?\r\n\[\]\(\)]/u',$span)) continue;
            $count=self::anchor_word_count($span);
            $plain=self::plain($span);
            $tokens=explode(' ',$plain);
            $first=$tokens[0]??'';
            if (in_array($first,['de','del','en','con','por','para','sobre','al','a','y','o'],true)) continue;
            $score = ($count<=6 ? 10 : 0)
                + (in_array($first,['el','la','los','las','un','una','este','esta','estos','estas'],true) ? 30 : 0)
                + (preg_match('/\b(?:del|de|para|en|con)\b/u',$plain) ? 7 : 0)
                - abs($count-5);
            $candidates[]=['anchor'=>$span,'start'=>$offset,'length'=>$end-$offset,'score'=>$score];
        }
        if (!$candidates) return null;
        usort($candidates,static fn(array $a,array $b):int=>$b['score']<=>$a['score'] ?: $a['start']<=>$b['start']);
        return $candidates[0];
    }

    private static function valid_internal_anchor(string $anchor, array $workflow, string $url): bool {
        // An anchor must be a readable phrase. Crossing a clause or sentence
        // boundary cannot be repaired by satisfying only the word-count quota.
        if (preg_match('/[,:;.!?\r\n]/u', $anchor)) return false;
        $count = self::anchor_word_count($anchor);
        if ($count < 3 || $count > 8 || self::plain($anchor) === self::plain((string) ($workflow['keyword'] ?? ''))) return false;
        foreach ((array) IDG_Internal_Links::normalize($workflow) as $item) {
            if ((string)($item['url'] ?? '') !== $url) continue;
            if (self::plain($anchor) === self::plain((string)($item['source_name'] ?? $item['label'] ?? ''))) return false;
        }
        return true;
    }
    private static function anchor_word_count(string $value): int {
        $value = trim(html_entity_decode(wp_strip_all_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $tokens = preg_split('/\s+/u', $value, -1, PREG_SPLIT_NO_EMPTY);
        return count($tokens ?: []);
    }
    private static function internal_anchor_tokens(array $workflow, string $url): array { foreach ((array) IDG_Internal_Links::normalize($workflow) as $link) if ((string) ($link['url'] ?? '') === $url) return self::words((string) ($link['source_name'] ?? $link['label'] ?? '')); return []; }
    private static function words(string $value): array { preg_match_all('/[\\p{L}\\p{N}]{3,}/u', self::plain($value), $m); return array_values(array_unique((array) ($m[0] ?? []))); }
    private static function plain(string $value): string { return trim(mb_strtolower(preg_replace('/[^\\p{L}\\p{N}]+/u', ' ', $value) ?? '')); }
    /** Prefer an existing nominal phrase ending at the canonical tag, without punctuation. */
    private static function phrases(string $text, array $tokens, bool $internal): array {
        preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}’\'-]*/u', $text, $m, PREG_OFFSET_CAPTURE);
        $words = $m[0] ?? [];
        $out = [];
        foreach ($words as $i => $word) {
            if (!in_array(self::plain((string) $word[0]), $tokens, true)) continue;
            $term = (string) $word[0];
            $end_offset = (int) $word[1] + strlen($term);
            // A short, complete Spanish noun phrase is more natural than a
            // five-word sliding window that may swallow a colon or connector.
            $prefix = substr($text, max(0, (int) $word[1] - 90), $end_offset - max(0, (int) $word[1] - 90));
            $pattern = '/(?<![\p{L}\p{N}])((?:el|la|los|las|un|una)\s+[\p{L}\p{N}]+(?:\s+[\p{L}\p{N}]+)?\s+(?:del|de(?:\s+(?:la|los|las))?|para|en|con)\s+' . preg_quote($term, '/') . ')$/iu';
            if ($internal && preg_match($pattern, $prefix, $found)
                && self::anchor_word_count((string) $found[1]) >= 3
                && self::anchor_word_count((string) $found[1]) <= 8) {
                $out[] = (string) $found[1];
                continue;
            }
            // Legacy fallback: keep only a self-contained, punctuation-free
            // window. Never invent article words or append a clause fragment.
            $start = max(0, $i - 2);
            $end = min(count($words) - 1, $start + 4);
            $start = max(0, $end - 4);
            $phrase = substr($text, (int) $words[$start][1], (int) $words[$end][1] + strlen((string) $words[$end][0]) - (int) $words[$start][1]);
            if ($phrase === false || preg_match('/[,:;.!?\r\n]/u', $phrase)) continue;
            $count = self::anchor_word_count($phrase);
            if (($internal && ($count < 3 || $count > 8)) || $count > 8) continue;
            if (preg_match('/^(?:de|del|en|con|sobre|para|por|a|al|y|o)\s/iu', $phrase)) continue;
            $out[] = $phrase;
        }
        return array_values(array_unique($out));
    }
    private static function reel_text(array $reel): string { return (string) ($reel['normalized'] ?? ''); }
    private static function wp_block(string $name, array $attrs, string $inner): array { return ['blockName'=>$name,'attrs'=>$attrs,'innerBlocks'=>[],'innerHTML'=>$inner,'innerContent'=>[$inner]]; }
    private static function inline_html(string $text): string { $safe=esc_html($text); $safe=preg_replace_callback('/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/u', static fn($m)=>'<a href="'.esc_url($m[2]).'">'.$m[1].'</a>', $safe); $safe=preg_replace('/\*\*([^*]+)\*\*/u','<strong>$1</strong>',$safe); return preg_replace('/(?<!\*)\*([^*]+)\*(?!\*)/u','<em>$1</em>',$safe); }
    private static function apply_patches(array $document, string $response, string $phase, array $workflow = []): array {
        $original = $document;
        preg_match_all('/^PATCH\s+([A-Za-z0-9_-]+)\s*\nEXPECTED_SHA256:\s*([a-f0-9]{64})\s*\nTEXT:\s*(.*?)\nEND\s+PATCH\s*$/ms', str_replace("\r\n", "\n", $response), $matches, PREG_SET_ORDER);
        if (!$matches) return ['ok'=>false,'document'=>$document,'changed_ids'=>[],'reason'=>'explicit_patches_required'];
        $seen=[]; $changed=[];
        foreach ($matches as $m) {
            $id=$m[1]; if (isset($seen[$id])) return ['ok'=>false,'document'=>$document,'changed_ids'=>[],'reason'=>'duplicate_patch']; $seen[$id]=true;
            $index=null; foreach ((array)$document['blocks'] as $i=>$block) if (($block['id']??'') === $id) { $index=$i; break; }
            if ($index===null) return ['ok'=>false,'document'=>$document,'changed_ids'=>[],'reason'=>'unknown_block'];
            $old=(array)$document['blocks'][$index]; $old_text=self::block_text($old);
            if (!hash_equals(hash('sha256',$old_text), strtolower($m[2]))) return ['ok'=>false,'document'=>$document,'changed_ids'=>[],'reason'=>'old_hash_mismatch'];
            if ($phase==='seo' && in_array($old['type']??'', ['heading','editorial_box'], true)) return ['ok'=>false,'document'=>$document,'changed_ids'=>[],'reason'=>'protected_block'];
            $new=trim($m[3]); if ($new==='' || self::has_ambiguous_link_markup($old_text) || self::has_ambiguous_link_markup($new)) return ['ok'=>false,'document'=>$document,'changed_ids'=>[],'reason'=>'href_mutation'];
            if ($phase === 'editorial' && ($old['type'] ?? '') === 'editorial_box' && $new !== $old_text) {
                return ['ok'=>false,'document'=>$original,'changed_ids'=>[],'reason'=>'protected_editorial_box_changed'];
            }
            if ($phase === 'editorial' && ($old['type'] ?? '') === 'heading' && (int) ($old['level'] ?? 0) === 1
                && !self::safe_editorial_h1($original, $old_text, $new)) {
                return ['ok'=>false,'document'=>$original,'changed_ids'=>[],'reason'=>'unsafe_h1_revision'];
            }
            if (($old['type']??'')==='list') $document['blocks'][$index]['items']=preg_split('/\s*\|\s*/u',$new); else $document['blocks'][$index]['text']=$new;
            if ($new !== $old_text) $changed[]=$id;
        }
        if ($phase === 'seo') {
            $old_links = self::links(self::markdown($original)); $new_links = self::links(self::markdown($document));
            if ($old_links !== $new_links && !self::only_authorized_link_additions($original, $document, $workflow, $old_links, $new_links)) return ['ok'=>false,'document'=>$original,'changed_ids'=>[],'reason'=>'href_mutation'];
        } elseif (self::link_url_multiset(self::links(self::markdown($original))) !== self::link_url_multiset(self::links(self::markdown($document)))) return ['ok'=>false,'document'=>$original,'changed_ids'=>[],'reason'=>'href_mutation'];
        $document['review']=['state'=>$phase.'_reviewed','validation'=>[],'changed_ids'=>$changed];
        return ['ok'=>true,'document'=>self::seal($document),'changed_ids'=>$changed,'reason'=>'patches'];
    }
    /** Reconciles a readable complete article only when its block identity is unambiguous. */
    private static function reconcile_complete_article(array $document, string $article, string $phase, array $workflow): array {
        $article = trim(str_replace(["\r\n", "\r"], "\n", $article));
        $parsed = self::from_markdown($article, [], $phase === 'seo' ? $document : null);
        // Editorial review is allowed to make a real structural edit (rename or
        // move a section, split/fuse prose, or remove repetition).  The old
        // same-shape route remains the conservative compatibility path for SEO.
        if ($phase !== 'editorial' && !self::same_shape($document, $parsed)) return ['ok'=>false, 'document'=>$document, 'changed_ids'=>[], 'reason'=>'ambiguous_article_shape'];
        if ($phase === 'editorial') {
            $structural = self::reconcile_editorial_structure($document, $parsed);
            if (empty($structural['ok'])) return ['ok'=>false, 'document'=>$document, 'changed_ids'=>[], 'reason'=>(string) ($structural['reason'] ?? 'ambiguous_article_structure')];
            $parsed = $structural['document'];
        }
        // Reconcile content blocks, never reset authoritative document metadata.
        $candidate = $document;
        $candidate['blocks'] = $parsed['blocks'];
        foreach (array_merge((array) $document['blocks'], (array) $candidate['blocks']) as $block) if (self::has_ambiguous_link_markup(self::block_text((array) $block))) return ['ok'=>false, 'document'=>$document, 'changed_ids'=>[], 'reason'=>'ambiguous_link_markup'];
        $old_links = self::links(self::markdown($document)); $new_links = self::links(self::markdown($candidate));
        if ($phase === 'editorial' && self::link_url_multiset($old_links) !== self::link_url_multiset($new_links)) return ['ok'=>false, 'document'=>$document, 'changed_ids'=>[], 'reason'=>'href_mutation'];
        if ($phase === 'seo' && !self::only_authorized_link_additions($document, $candidate, $workflow, $old_links, $new_links)) return ['ok'=>false, 'document'=>$document, 'changed_ids'=>[], 'reason'=>'unsafe_seo_article_replacement'];
        $changed = [];
        $old_blocks = (array) $document['blocks']; $new_blocks = (array) $candidate['blocks'];
        $old_by_id = []; foreach ($old_blocks as $position => $old) $old_by_id[(string) ($old['id'] ?? '')] = ['text'=>self::block_text((array) $old), 'position'=>$position];
        $new_ids = [];
        foreach ($new_blocks as $position => $new) {
            $id = (string) ($new['id'] ?? ''); $new_ids[$id] = true;
            if (!isset($old_by_id[$id]) || $old_by_id[$id]['text'] !== self::block_text((array) $new) || $old_by_id[$id]['position'] !== $position) $changed[] = $id;
        }
        foreach ($old_blocks as $old) { $id = (string) ($old['id'] ?? ''); if (!isset($new_ids[$id])) $changed[] = $id; }
        $changed = array_values(array_unique(array_filter($changed, static fn(string $id): bool => $id !== '')));
        if ($phase === 'seo' && !$changed) return ['ok'=>false, 'document'=>$document, 'changed_ids'=>[], 'reason'=>'no_seo_article_change'];
        $candidate['review'] = ['state'=>$phase.'_reviewed', 'validation'=>[], 'changed_ids'=>$changed];
        return ['ok'=>true, 'document'=>self::seal($candidate), 'changed_ids'=>$changed, 'reason'=>'complete_article_reconciled'];
    }
    /**
     * Structural editorial reconciliation deliberately has a small trust base:
     * exactly one safely revisable H1, one byte-identical editorial box, valid block
     * types, and link fingerprints are checked by the caller.  IDs travel with
     * an exact block or a uniquely identifiable section body; otherwise a fresh
     * ID is safer than guessing identity.
     */
    private static function reconcile_editorial_structure(array $previous, array $parsed): array {
        $old = (array) ($previous['blocks'] ?? []); $new = (array) ($parsed['blocks'] ?? []);
        $old_h1 = array_values(array_filter($old, static fn(array $b): bool => ($b['type'] ?? '') === 'heading' && (int) ($b['level'] ?? 0) === 1));
        $new_h1 = array_values(array_filter($new, static fn(array $b): bool => ($b['type'] ?? '') === 'heading' && (int) ($b['level'] ?? 0) === 1));
        $old_box = array_values(array_filter($old, static fn(array $b): bool => ($b['type'] ?? '') === 'editorial_box'));
        $new_box = array_values(array_filter($new, static fn(array $b): bool => ($b['type'] ?? '') === 'editorial_box'));
        if (count($old_h1) !== 1 || count($new_h1) !== 1) return ['ok'=>false, 'reason'=>'invalid_h1_count'];
        // The sole H1 remains in position; only its text may change.
        $old_h1_index = array_key_first(array_filter($old, static fn(array $b): bool => ($b['type'] ?? '') === 'heading' && (int) ($b['level'] ?? 0) === 1));
        $new_h1_index = array_key_first(array_filter($new, static fn(array $b): bool => ($b['type'] ?? '') === 'heading' && (int) ($b['level'] ?? 0) === 1));
        if ($old_h1_index !== $new_h1_index || !self::safe_editorial_h1($previous, self::block_text($old_h1[0]), self::block_text($new_h1[0]))) {
            return ['ok'=>false, 'reason'=>'unsafe_h1_revision'];
        }
        if (count($old_box) !== 1 || count($new_box) !== 1 || self::block_text($old_box[0]) !== self::block_text($new_box[0])) return ['ok'=>false, 'reason'=>'protected_editorial_box_changed'];
        foreach ($new as $block) if (!in_array((string) ($block['type'] ?? ''), ['heading', 'paragraph', 'editorial_box', 'list'], true)) return ['ok'=>false, 'reason'=>'ambiguous_article_structure'];

        $available = [];
        foreach ($old as $index => $block) $available[self::block_fingerprint($block)][] = $index;
        $used = []; $preserved = [];
        foreach ($new as $index => $block) {
            $fingerprint = self::block_fingerprint($block);
            foreach ((array) ($available[$fingerprint] ?? []) as $old_index) if (!isset($used[$old_index])) { $new[$index]['id'] = (string) $old[$old_index]['id']; $used[$old_index] = true; $preserved[$index] = true; break; }
        }
        // The title keeps its canonical block ID even when the wording improves.
        if (self::block_text($old_h1[0]) !== self::block_text($new_h1[0])) {
            $new[$new_h1_index]['id'] = (string) ($old_h1[0]['id'] ?? '');
            $used[$old_h1_index] = true;
            $preserved[$new_h1_index] = true;
        }
        // A renamed/moved H3 keeps its ID only if the first following body block
        // matches one section uniquely. This is evidence of correspondence, not
        // a positional assumption.
        foreach ($new as $index => $block) {
            if (($block['type'] ?? '') !== 'heading' || (int) ($block['level'] ?? 0) !== 3 || isset($new[$index]['id']) && str_starts_with((string) $new[$index]['id'], 'heading-') === false) continue;
            $signature = self::following_body_fingerprint($new, $index); if ($signature === '') continue;
            $matches = [];
            foreach ($old as $old_index => $old_block) if (($old_block['type'] ?? '') === 'heading' && (int) ($old_block['level'] ?? 0) === 3 && !isset($used[$old_index]) && self::following_body_fingerprint($old, $old_index) === $signature) $matches[] = $old_index;
            if (count($matches) === 1) { $new[$index]['id'] = (string) $old[$matches[0]]['id']; $used[$matches[0]] = true; $preserved[$index] = true; }
        }
        $claimed = [];
        foreach ($preserved as $index => $_) { $id = (string) ($new[$index]['id'] ?? ''); if ($id !== '') $claimed[$id] = true; }
        foreach ($new as $index => &$block) {
            if (isset($preserved[$index])) continue;
            $id = (string) ($block['id'] ?? '');
            if ($id !== '' && !isset($claimed[$id])) { $claimed[$id] = true; continue; }
            $seed = self::block_fingerprint($block) . '|' . $index; $candidate = 'block-' . substr(hash('sha256', $seed), 0, 16); $suffix = 0;
            while (isset($claimed[$candidate])) $candidate = 'block-' . substr(hash('sha256', $seed . '|' . (++$suffix)), 0, 16);
            $block['id'] = $candidate; $claimed[$candidate] = true;
        }
        unset($block);
        $parsed['blocks'] = $new;
        return ['ok'=>true, 'document'=>$parsed];
    }
    /** Allow an editorial title change only when it cannot introduce new numerical claims,
     * alter an established project keyword, or create malformed heading markup.
     * Semantics remain the editor's task; this guard is deliberately objective. */
    private static function safe_editorial_h1(array $document, string $old_title, string $new_title): bool {
        $new_title = trim($new_title);
        if ($new_title === '' || mb_strlen($new_title) > 68 || preg_match('/[\[\]<>*_`]|https?:\/\/|\R/u', $new_title)) return false;
        $keyword = trim((string) ($document['seo']['keyword'] ?? ''));
        if ($keyword !== '' && mb_stripos($old_title, $keyword) !== false && mb_stripos($new_title, $keyword) === false) return false;
        preg_match_all('/[0-9]+(?:[.,][0-9]+)?/u', $new_title, $numbers);
        // Numeric evidence must exist in visible prose, not merely in an URL slug.
        $corpus = (string) preg_replace('/\]\(https?:\/\/[^\s)]+\)/u', ']', self::markdown($document));
        foreach (array_unique((array) ($numbers[0] ?? [])) as $number) {
            if (!str_contains($corpus, (string) $number)) return false;
        }
        return true;
    }
    private static function block_fingerprint(array $block): string { return (string) ($block['type'] ?? '') . '|' . (int) ($block['level'] ?? 0) . '|' . self::block_text($block); }
    private static function following_body_fingerprint(array $blocks, int $heading_index): string { for ($i = $heading_index + 1; $i < count($blocks); $i++) { $block = (array) $blocks[$i]; if (($block['type'] ?? '') === 'heading') break; if (in_array(($block['type'] ?? ''), ['paragraph', 'list'], true)) return self::block_fingerprint($block); } return ''; }
    /** SEO may add each missing canonical URL once, by wrapping existing paragraph prose only. */
    private static function only_authorized_link_additions(array $old, array $new, array $workflow, array $old_links, array $new_links): bool {
        $allowed = self::authorized_links($workflow);
        if (!$allowed) return false;
        $required = array_column($allowed, 'url');
        $old_by_url=[]; foreach ($old_links as $link) $old_by_url[$link['url']] = ($old_by_url[$link['url']] ?? 0) + 1;
        $new_by_url=[]; foreach ($new_links as $link) $new_by_url[$link['url']] = ($new_by_url[$link['url']] ?? 0) + 1;
        foreach ($new_by_url as $url => $count) if (!in_array($url, $required, true) || $count > 1) return false;
        foreach ($old_by_url as $url => $count) if (($new_by_url[$url] ?? 0) !== $count) return false;
        $added = array_values(array_filter($new_links, static fn(array $link): bool => !in_array($link, $old_links, true)));
        if (!$added || count($new_links) !== count($old_links) + count($added)) return false;
        foreach ($added as $link) {
            if (($old_by_url[$link['url']] ?? 0) !== 0 || !in_array($link['url'], $required, true)) return false;
            $is_internal = false; foreach ($allowed as $allowed_link) if ($allowed_link['url'] === $link['url']) $is_internal = $allowed_link['kind'] === 'internal';
            if ($is_internal && (count(self::words($link['anchor'])) < 3 || count(self::words($link['anchor'])) > 8)) return false;
        }
        foreach ((array) $old['blocks'] as $i => $before) {
            $after = (array) ($new['blocks'][$i] ?? []); $before_text=self::block_text($before); $after_text=self::block_text($after);
            if ($before_text === $after_text) continue;
            if (($before['type'] ?? '') !== 'paragraph') return false;
            foreach ($added as $link) {
                $markup='['.$link['anchor'].']('.$link['url'].')';
                if (str_contains($after_text, $markup)) $after_text=str_replace($markup,$link['anchor'],$after_text);
            }
            if ($after_text !== $before_text) return false;
        }
        return true;
    }
    /** Link contracts compare exact anchor/URL occurrences, independent of block order. */
    private static function links(string $text): array { preg_match_all('/\[([^\]\r\n]+)\]\((https?:\/\/[^\s)]+)\)/u',$text,$m,PREG_SET_ORDER); return array_map(static fn(array $link): array => ['anchor'=>$link[1], 'url'=>$link[2]], $m); }
    /** Authorized link targets and counts are immutable during editorial anchor repair. */
    private static function link_url_multiset(array $links): array {
        $counts = [];
        foreach ($links as $link) {
            $url = (string) ($link['url'] ?? '');
            $counts[$url] = ($counts[$url] ?? 0) + 1;
        }
        ksort($counts);
        return $counts;
    }
    private static function link_multiset(array $links): array { $counts = []; foreach ($links as $link) { $key = (string) ($link['anchor'] ?? '') . "\n" . (string) ($link['url'] ?? ''); $counts[$key] = ($counts[$key] ?? 0) + 1; } ksort($counts); return $counts; }
    /** Patches do not accept alternate or malformed link notation that cannot be fingerprinted exactly. */
    private static function has_ambiguous_link_markup(string $text): bool { if (preg_match('/\\\\\[/u', $text)) return true; $remainder=(string) preg_replace('/\[[^\]\r\n]+\]\(https?:\/\/[^\s)]+\)/u','',$text); return (bool) preg_match('/\[[^\]\r\n]*\]\s*\(|\[[^\]\r\n]*\]\s*\[[^\]\r\n]*\]|<https?:\/\/[^>\s]+>/u', $remainder); }
    private static function without_patches(string $response): string { return (string) preg_replace('/^PATCH\s+[A-Za-z0-9_-]+\s*\nEXPECTED_SHA256:\s*[a-f0-9]{64}\s*\nTEXT:\s*.*?\nEND\s+PATCH\s*$/ms', '', str_replace("\r\n", "\n", $response)); }
    private static function ksort_recursive(array &$v): void { ksort($v); foreach($v as &$x) if(is_array($x)) self::ksort_recursive($x); }
}
