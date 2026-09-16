#!/usr/bin/env python3
"""RC1.7.2: SEO prompt and validator use the final-guard keyword semantics only."""
from pathlib import Path
import subprocess


ROOT = Path(__file__).resolve().parents[1]
PROMPTS = (ROOT / 'includes' / 'class-prompt-library.php').read_text(encoding='utf-8')
VALIDATOR = (ROOT / 'includes' / 'class-validator.php').read_text(encoding='utf-8')
FINAL_GUARD = (ROOT / 'includes' / 'class-final-guard.php').read_text(encoding='utf-8')


def require(condition, label):
    if not condition:
        raise AssertionError(label)


def git_paths():
    result = subprocess.run(
        ['git', 'status', '--porcelain=v1', '--untracked-files=all'],
        cwd=ROOT,
        check=True,
        capture_output=True,
        text=True,
    )
    return {line[3:] for line in result.stdout.splitlines() if len(line) >= 4}


require(
    'Preserva sin alterar la tesis editorial ya aprobada, la caja editorial, H1, H2 y la estructura del artículo' in PROMPTS,
    'SEO review preserves the approved editorial thesis, box, headings and structure',
)
require('no introduzcas nueva semántica editorial' in PROMPTS, 'SEO review forbids new editorial semantics')
require(
    'Una sola línea de 106 a 150 caracteres; objetivo recomendado 120 a 145. Incluye la keyword principal o una variante suficientemente reconocible' in PROMPTS,
    'meta description range and flexible keyword instruction',
)
require(
    'La meta description no contiene la keyword principal ni una variante suficientemente reconocible.' in FINAL_GUARD,
    'final guard flexible meta-description semantic',
)
require(
    "stripos($content, $keyword) === false && !self::keyword_flexible_match($content, $keyword)" in VALIDATOR,
    'validator retains flexible matching behavior',
)
require('La keyword principal no aparece ni de forma exacta ni mediante una variante suficientemente reconocible.' in VALIDATOR, 'validator warning matches flexible semantic')
require('La keyword principal no aparece de forma exacta en el texto.' not in VALIDATOR, 'legacy exact-keyword warning removed')

allowed_paths = {
    'includes/class-prompt-library.php',
    'includes/class-validator.php',
    'tests/rc172-seo-alignment.py',
}
changed_paths = git_paths()
require(changed_paths <= allowed_paths, f'unrelated paths changed: {sorted(changed_paths - allowed_paths)}')
for protected_path in (
    'ideasdi-redaccion-gerizim.php',  # version and registered metadata
    'includes/class-post-creator.php',  # Yoast metadata and Reel logic
    'includes/class-internal-links.php',  # link logic
    'includes/class-final-guard.php',  # final guard and Reel logic
    'includes/class-canonical-context.php',  # canonical projection
    'includes/data/editorial-canonical.php',  # canonical projection
):
    require(protected_path not in changed_paths, f'protected logic changed: {protected_path}')

print('RC172_SEO_ALIGNMENT_OK')
