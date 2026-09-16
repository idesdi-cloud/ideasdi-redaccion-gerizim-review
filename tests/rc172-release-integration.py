#!/usr/bin/env python3
"""RC1.7.2 release wiring; this test never invokes scripts/test.sh."""
from pathlib import Path
import subprocess


ROOT = Path(__file__).resolve().parents[1]
BASELINE = '72d659acc9e9079d9112a1f04405a5525591fe3d'
MAIN = (ROOT / 'ideasdi-redaccion-gerizim.php').read_text(encoding='utf-8')
RUNNER = (ROOT / 'scripts' / 'test.sh').read_text(encoding='utf-8')
MANIFEST = ROOT / 'REGRESION-EDITORIAL-RC1.7.2.sha256'


def require(condition, label):
    if not condition:
        raise AssertionError(label)


def differs_from_baseline(path):
    return subprocess.run(
        ['git', 'diff', '--quiet', BASELINE, '--', path], cwd=ROOT
    ).returncode != 0


require('Version: 0.4.0-RC1.7.2' in MAIN, 'plugin header version')
require("define('IDG_VERSION', '0.4.0-RC1.7.2');" in MAIN, 'runtime version')
require("define('IDG_TRACEABILITY_DB_VERSION', '1.2.0');" in MAIN, 'DB version unchanged')
for test_name in ('rc172-seo-alignment.py', 'rc172-release-integration.py'):
    require(test_name in RUNNER, f'test runner includes {test_name}')
require('./scripts/test.sh' not in RUNNER, 'runner does not self-invoke')
require(MANIFEST.is_file(), 'RC1.7.2 manifest exists')
manifest_paths = [line.split('  ', 1)[1] for line in MANIFEST.read_text(encoding='utf-8').splitlines()]
require('REGRESION-EDITORIAL-RC1.7.2.sha256' not in manifest_paths, 'manifest has no self reference')
for path in ('REGRESION-EDITORIAL-RC1.7.1.sha256', 'tests/rc172-seo-alignment.py', 'tests/rc172-release-integration.py'):
    require(path in manifest_paths, f'manifest covers {path}')
for path in (
    'includes/class-prompt-library.php',
    'includes/class-validator.php',
    'includes/class-final-guard.php',
    'includes/class-post-creator.php',
    'includes/class-internal-links.php',
    'includes/class-canonical-context.php',
    'includes/data/editorial-canonical.php',
):
    require(not differs_from_baseline(path), f'protected production file differs from baseline: {path}')

print('RC172_RELEASE_INTEGRATION_OK')
