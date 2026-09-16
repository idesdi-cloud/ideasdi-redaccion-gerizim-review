#!/usr/bin/env python3
"""RC1.7.3: internal SEO title is parsed, contained, and sent to Yoast."""
from pathlib import Path
import re
import subprocess


ROOT = Path(__file__).resolve().parents[1]
BASELINE = '742b014d9b8b53163250eddda65edfaccabf4304'
PROMPTS = (ROOT / 'includes' / 'class-prompt-library.php').read_text(encoding='utf-8')
POST_CREATOR = (ROOT / 'includes' / 'class-post-creator.php').read_text(encoding='utf-8')
OUTPUT_PARSER = (ROOT / 'includes' / 'class-workflow-output-parser.php').read_text(encoding='utf-8')
ADMIN = (ROOT / 'includes' / 'class-admin-page.php').read_text(encoding='utf-8')


def require(condition, label):
    if not condition:
        raise AssertionError(label)


def function_body(source, name):
    match = re.search(r'private static function ' + re.escape(name) + r'\([^\)]*\).*?\n    }', source, re.S)
    require(match is not None, f'{name} exists')
    return match.group(0)


def changed_paths():
    result = subprocess.run(
        ['git', 'status', '--porcelain=v1', '--untracked-files=all'], cwd=ROOT, check=True,
        capture_output=True, text=True,
    )
    return {line[3:] for line in result.stdout.splitlines() if len(line) >= 4}


require("'seo' => 'seo-v2.1.0-RC1.7.3'" in PROMPTS, 'SEO prompt version is RC1.7.3')
require('TÍTULO SEO\n[Título SEO interno para Yoast.' in PROMPTS, 'SEO prompt requires internal SEO title')
require('Puede diferir del H1' in PROMPTS and 'no modifica el H1 editorial' in PROMPTS, 'SEO title remains independent from editorial H1')
require('Una sola línea de 106 a 150 caracteres; objetivo recomendado 120 a 145.' in PROMPTS, 'meta-description contract remains unchanged')

extract = function_body(POST_CREATOR, 'extract_sections')
require("'seo_title' => []" in extract, 'extract_sections collects seo_title')
require(re.search(r"'seo_title'\s*=>\s*trim\(implode\(\"\\n\", \$sections\['seo_title'\]\)\)", extract), 'extract_sections explicitly returns seo_title')
section_key = function_body(POST_CREATOR, 'section_key_from_line')
require("'titulo seo' => 'seo_title'" in section_key, 'accent-normalized SEO title heading is recognized')
rebuild = function_body(POST_CREATOR, 'rebuild_seo_result')
require("$parts[] = 'TÍTULO SEO';" in rebuild and '$parts[] = trim($seo_title);' in rebuild, 'rebuild preserves internal SEO title')

require(POST_CREATOR.count("$seo_title = trim((string) ($sections['seo_title'] ?? ''));" ) == 2, 'creation and recurrent paths resolve SEO title')
require(POST_CREATOR.count('if ($seo_title === \'\') {\n            $seo_title = $title;\n        }') == 2, 'legacy blank SEO title falls back to H1')
require(POST_CREATOR.count("self::update_yoast_meta($post_id, $meta_description, $workflow['keyword'] ?? '', $seo_title);") == 2, 'creation and recurrent paths pass resolved SEO title to Yoast')
yoast = function_body(POST_CREATOR, 'update_yoast_meta')
require("'_yoast_wpseo_metadesc', sanitize_text_field($meta_description)" in yoast, 'Yoast meta description wiring unchanged')
require("'_yoast_wpseo_focuskw', sanitize_text_field($keyword)" in yoast, 'Yoast exact workflow keyword wiring unchanged')
require("'_yoast_wpseo_title', sanitize_text_field($title)" in yoast, 'Yoast title receives resolved SEO title argument')
require("'seo_title' => '(?:TÍTULO SEO|TITULO SEO)'" in POST_CREATOR, 'both title spellings are internal parser boundaries')
require("'article' => self::remove_internal_sections_from_article($article)" in extract, 'public article is stripped of internal sections')
require(POST_CREATOR.count('$html_content = self::markdownish_to_html($content, $title);') == 2, 'Gutenberg source receives only public article content')
require('$html_content = self::markdownish_to_html($seo_title' not in POST_CREATOR, 'SEO title cannot enter Gutenberg conversion')

require('TÍTULO SEO|TITULO SEO' in OUTPUT_PARSER, 'workflow parser treats SEO title as internal boundary')
public_article = function_body(ADMIN, 'report_public_article_from_seo')
require(public_article.count('T[ÍI]TULO SEO') == 2, 'admin public article parser stops at SEO title in both branches')
report_sections = function_body(ADMIN, 'report_extract_sections')
require("'TÍTULO SEO'" in report_sections and "'T[ÍI]TULO SEO'" in report_sections, 'admin report exposes canonical SEO title key and unaccented heading')
require("'TÍTULO SEO' => 'Título SEO'" in ADMIN, 'internal report displays SEO title')

allowed_paths = {
    'includes/class-prompt-library.php',
    'includes/class-post-creator.php',
    'includes/class-workflow-output-parser.php',
    'includes/class-admin-page.php',
    'tests/rc173-yoast-contract.py',
}
changed = changed_paths()
require(changed <= allowed_paths, f'unrelated paths changed: {sorted(changed - allowed_paths)}')
for protected in (
    'includes/class-canonical-context.php',
    'includes/data/editorial-canonical.php',
    'includes/class-internal-links.php',
    'includes/class-final-guard.php',
    'includes/class-post-creator.php',
):
    if protected == 'includes/class-post-creator.php':
        continue  # Its Yoast/parser-only diff is checked above; Gutenberg/Reel methods remain protected below.
    result = subprocess.run(['git', 'diff', '--quiet', BASELINE, '--', protected], cwd=ROOT)
    require(result.returncode == 0, f'protected behavior changed: {protected}')
for method in (
    'ensure_official_source_link',
    'ensure_internal_links',
    'deduplicate_configured_internal_links',
    'html_to_gutenberg_blocks',
    'ensure_valid_reel_package',
):
    current = function_body(POST_CREATOR, method)
    baseline = subprocess.run(['git', 'show', f'{BASELINE}:includes/class-post-creator.php'], cwd=ROOT, check=True, capture_output=True, text=True).stdout
    require(current == function_body(baseline, method), f'protected {method} changed')

print('RC173_YOAST_CONTRACT_OK')
