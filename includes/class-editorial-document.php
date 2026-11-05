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
        return self::apply_patches($document, $response, 'editorial');
    }

    public static function apply_seo_response(array $document, string $response, array $workflow): array {
        $sections = self::seo_sections(self::without_patches($response));
        $article = trim((string) ($sections['article'] ?? ''));
        $changed = [];
        if ($article !== '' && !hash_equals(self::markdown($document), $article)) return ['ok' => false, 'document' => $document, 'reason' => 'article_replacement_requires_patches'];
        if (preg_match('/^PATCH\s+/m', $response)) {
            $patches = self::apply_patches($document, $response, 'seo');
            if (!$patches['ok']) return $patches;
            $document = $patches['document']; $changed = $patches['changed_ids'];
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
        return ['ok' => true, 'document' => self::seal($document), 'changed_ids' => $changed, 'reason' => ''];
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
    private static function authorized_links(array $w): array { $links = []; foreach ((array) ($w['internal_links_structured'] ?? []) as $l) if (!empty($l['url'])) $links[] = ['url' => esc_url_raw((string) $l['url']), 'anchor' => sanitize_text_field((string) ($l['anchor'] ?? '')), 'kind' => 'internal']; $url = esc_url_raw((string) ($w['official_source'] ?? $w['responsible_official_url'] ?? '')); if ($url !== '') $links[] = ['url' => $url, 'anchor' => '', 'kind' => 'official']; return $links; }
    private static function seo_sections(string $raw): array { $s = ['article' => '', 'meta_description' => '', 'seo_report' => '', 'social_copy' => '', 'reel_package' => '', 'feedback_notes' => '']; $map = ['ART[ÍI]CULO FINAL' => 'article', 'META DESCRIPTION' => 'meta_description', 'INFORME SEO INTERNO' => 'seo_report', 'COPY PARA REDES' => 'social_copy', 'PAQUETE REEL' => 'reel_package', 'RETROALIMENTACI[ÓO]N GERIZIM' => 'feedback_notes']; $matches=[]; foreach ($map as $p=>$k) if (preg_match('/^\s*(?:#{1,6}\s*)?(?:\*\*)?\s*'.$p.'\s*(?:\*\*)?\s*:?\s*$/imu',$raw,$m,PREG_OFFSET_CAPTURE)) $matches[]=['key'=>$k,'start'=>$m[0][1],'body'=>$m[0][1]+strlen($m[0][0])]; usort($matches, fn($a,$b)=>$a['start']<=>$b['start']); foreach($matches as $i=>$m) $s[$m['key']]=trim(substr($raw,$m['body'],($matches[$i+1]['start']??strlen($raw))-$m['body'])); return $s; }
    private static function reel_text(array $reel): string { return (string) ($reel['normalized'] ?? ''); }
    private static function wp_block(string $name, array $attrs, string $inner): array { return ['blockName'=>$name,'attrs'=>$attrs,'innerBlocks'=>[],'innerHTML'=>$inner,'innerContent'=>[$inner]]; }
    private static function inline_html(string $text): string { $safe=esc_html($text); $safe=preg_replace_callback('/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/u', static fn($m)=>'<a href="'.esc_url($m[2]).'">'.$m[1].'</a>', $safe); $safe=preg_replace('/\*\*([^*]+)\*\*/u','<strong>$1</strong>',$safe); return preg_replace('/(?<!\*)\*([^*]+)\*(?!\*)/u','<em>$1</em>',$safe); }
    private static function apply_patches(array $document, string $response, string $phase): array {
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
            $new=trim($m[3]); if ($new==='' || self::has_ambiguous_link_markup($old_text) || self::has_ambiguous_link_markup($new) || self::links($old_text)!==self::links($new)) return ['ok'=>false,'document'=>$document,'changed_ids'=>[],'reason'=>'href_mutation'];
            if (($old['type']??'')==='list') $document['blocks'][$index]['items']=preg_split('/\s*\|\s*/u',$new); else $document['blocks'][$index]['text']=$new;
            if ($new !== $old_text) $changed[]=$id;
        }
        $document['review']=['state'=>$phase.'_reviewed','validation'=>[],'changed_ids'=>$changed];
        return ['ok'=>true,'document'=>self::seal($document),'changed_ids'=>$changed,'reason'=>'patches'];
    }
    /** Exact ordered fingerprints for the only inline Markdown link form we serialize. */
    private static function links(string $text): array { preg_match_all('/\[([^\]\r\n]+)\]\((https?:\/\/[^\s)]+)\)/u',$text,$m,PREG_SET_ORDER); return array_map(static fn(array $link): array => ['anchor'=>$link[1], 'url'=>$link[2]], $m); }
    /** Patches do not accept alternate or malformed link notation that cannot be fingerprinted exactly. */
    private static function has_ambiguous_link_markup(string $text): bool { if (preg_match('/\\\\\[/u', $text)) return true; $remainder=(string) preg_replace('/\[[^\]\r\n]+\]\(https?:\/\/[^\s)]+\)/u','',$text); return (bool) preg_match('/\[[^\]\r\n]*\]\s*\(|\[[^\]\r\n]*\]\s*\[[^\]\r\n]*\]|<https?:\/\/[^>\s]+>/u', $remainder); }
    private static function without_patches(string $response): string { return (string) preg_replace('/^PATCH\s+[A-Za-z0-9_-]+\s*\nEXPECTED_SHA256:\s*[a-f0-9]{64}\s*\nTEXT:\s*.*?\nEND\s+PATCH\s*$/ms', '', str_replace("\r\n", "\n", $response)); }
    private static function ksort_recursive(array &$v): void { ksort($v); foreach($v as &$x) if(is_array($x)) self::ksort_recursive($x); }
}
