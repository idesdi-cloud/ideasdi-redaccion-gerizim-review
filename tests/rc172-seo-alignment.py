#!/usr/bin/env python3
"""RC1.7.2: SEO prompt and validator use the final-guard keyword semantics only."""
from pathlib import Path
import subprocess


ROOT = Path(__file__).resolve().parents[1]
BASELINE = '72d659acc9e9079d9112a1f04405a5525591fe3d'
PROMPTS = (ROOT / 'includes' / 'class-prompt-library.php').read_text(encoding='utf-8')
VALIDATOR = (ROOT / 'includes' / 'class-validator.php').read_text(encoding='utf-8')
FINAL_GUARD = (ROOT / 'includes' / 'class-final-guard.php').read_text(encoding='utf-8')


def require(condition, label):
    if not condition:
        raise AssertionError(label)


def changed_paths():
    result = subprocess.run(
        ['git', 'status', '--porcelain=v1', '--untracked-files=all'],
        cwd=ROOT,
        check=True,
        capture_output=True,
        text=True,
    )
    return {line[3:] for line in result.stdout.splitlines() if len(line) >= 4}


def differs_from_baseline(path):
    return subprocess.run(
        ['git', 'diff', '--quiet', BASELINE, '--', path],
        cwd=ROOT,
    ).returncode != 0


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
    'CAMBIOS-v0.4.0-RC1.7.2.md',
    'PRUEBAS-v0.4.0-RC1.7.2.md',
    'REGRESION-EDITORIAL-RC1.7.2.sha256',
    'ideasdi-redaccion-gerizim.php',
    'scripts/test.sh',
    'tests/plugin-load-smoke.php',
    'tests/rc157-acceptance.php',
    'tests/rc160-acceptance.php',
    'tests/rc161-acceptance.php',
    'tests/rc162-acceptance.php',
    'tests/rc163-acceptance.php',
    'tests/rc165-legacy-cleanup-equivalence.php',
    'tests/rc170-canonical-consumption.php',
    'tests/rc170-canonical-external-guard.php',
    'tests/rc170-canonical-internal-links.php',
    'tests/rc170-canonical-prompts-admin.php',
    'tests/rc170-release-regression.php',
    'tests/rc171-release-integration.py',
    'tests/rc172-seo-alignment.py',
    'tests/rc172-release-integration.py',
    'tests/support/canonical-regression.php',
    'tests/traceability-static.php',
}
changed = changed_paths()
require(changed <= allowed_paths, f'unrelated paths changed: {sorted(changed - allowed_paths)}')
for protected_path in (
    'includes/class-prompt-library.php',
    'includes/class-validator.php',
    'includes/class-final-guard.php',  # final guard and Reel logic
    'includes/class-post-creator.php',  # Yoast metadata and Reel logic
    'includes/class-internal-links.php',  # link logic
    'includes/class-canonical-context.php',  # canonical projection
    'includes/data/editorial-canonical.php',  # canonical projection
):
    require(not differs_from_baseline(protected_path), f'protected production file differs from baseline: {protected_path}')

print('RC172_SEO_ALIGNMENT_OK')
