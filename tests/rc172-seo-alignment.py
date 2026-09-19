#!/usr/bin/env python3
"""RC1.7.2: SEO prompt and validator use the final-guard keyword semantics only."""
from pathlib import Path
import subprocess


ROOT = Path(__file__).resolve().parents[1]
BASELINE = '72d659acc9e9079d9112a1f04405a5525591fe3d'
RELEASE = '742b014d9b8b53163250eddda65edfaccabf4304'


def require(condition, label):
    if not condition:
        raise AssertionError(label)


def git_object(path):
    return subprocess.run(
        ['git', 'show', f'{RELEASE}:{path}'], cwd=ROOT, check=True, capture_output=True
    ).stdout.decode('utf-8')


def release_matches_baseline(path):
    return subprocess.run(
        ['git', 'diff', '--quiet', BASELINE, RELEASE, '--', path],
        cwd=ROOT,
    ).returncode == 0


release_commit = subprocess.run(
    ['git', 'rev-parse', f'{RELEASE}^{{commit}}'], cwd=ROOT, check=True,
    capture_output=True, text=True,
).stdout.strip()
release_parent = subprocess.run(
    ['git', 'rev-parse', f'{RELEASE}^'], cwd=ROOT, check=True,
    capture_output=True, text=True,
).stdout.strip()
require(release_commit == RELEASE, 'RC1.7.2 release object is available and exact')
require(release_parent == BASELINE, 'RC1.7.2 release parent is the SEO baseline')

PROMPTS = git_object('includes/class-prompt-library.php')
VALIDATOR = git_object('includes/class-validator.php')
FINAL_GUARD = git_object('includes/class-final-guard.php')


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

for protected_path in (
    'includes/class-prompt-library.php',
    'includes/class-validator.php',
    'includes/class-final-guard.php',  # final guard and Reel logic
    'includes/class-post-creator.php',  # Yoast metadata and Reel logic
    'includes/class-internal-links.php',  # link logic
    'includes/class-canonical-context.php',  # canonical projection
    'includes/data/editorial-canonical.php',  # canonical projection
):
    require(release_matches_baseline(protected_path), f'protected production file differs in RC1.7.2 release boundary: {protected_path}')

print('RC172_SEO_ALIGNMENT_OK')
