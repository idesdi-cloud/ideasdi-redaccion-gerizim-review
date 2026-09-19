#!/usr/bin/env python3
"""RC1.7.2 release wiring; this test never invokes scripts/test.sh."""
from pathlib import Path
import hashlib
import subprocess


ROOT = Path(__file__).resolve().parents[1]
BASELINE = '72d659acc9e9079d9112a1f04405a5525591fe3d'
RELEASE = '742b014d9b8b53163250eddda65edfaccabf4304'
MANIFEST = ROOT / 'REGRESION-EDITORIAL-RC1.7.2.sha256'


def require(condition, label):
    if not condition:
        raise AssertionError(label)


def git_object(path):
    return subprocess.run(
        ['git', 'show', f'{RELEASE}:{path}'], cwd=ROOT, check=True, capture_output=True
    ).stdout


def release_matches_baseline(path):
    return subprocess.run(
        ['git', 'diff', '--quiet', BASELINE, RELEASE, '--', path], cwd=ROOT
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

MAIN = git_object('ideasdi-redaccion-gerizim.php').decode('utf-8')
RUNNER = git_object('scripts/test.sh').decode('utf-8')


require('Version: 0.4.0-RC1.7.2' in MAIN, 'plugin header version')
require("define('IDG_VERSION', '0.4.0-RC1.7.2');" in MAIN, 'runtime version')
require("define('IDG_TRACEABILITY_DB_VERSION', '1.2.0');" in MAIN, 'DB version unchanged')
for test_name in ('rc172-seo-alignment.py', 'rc172-release-integration.py'):
    require(test_name in RUNNER, f'test runner includes {test_name}')
require('./scripts/test.sh' not in RUNNER, 'runner does not self-invoke')
require(MANIFEST.is_file(), 'RC1.7.2 manifest exists')
historical_manifest = git_object(MANIFEST.name)
require(MANIFEST.read_bytes() == historical_manifest, 'RC1.7.2 manifest matches its release artifact')
manifest_paths = []
for line in historical_manifest.decode('utf-8').splitlines():
    digest, separator, path = line.partition('  ')
    require(separator == '  ' and len(digest) == 64 and path and not path.startswith('/'), f'manifest entry format: {line}')
    require(hashlib.sha256(git_object(path)).hexdigest() == digest, f'manifest digest matches release artifact: {path}')
    manifest_paths.append(path)
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
    require(release_matches_baseline(path), f'protected production file differs in RC1.7.2 release boundary: {path}')

print('RC172_RELEASE_INTEGRATION_OK')
