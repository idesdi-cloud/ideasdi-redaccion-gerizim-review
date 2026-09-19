#!/usr/bin/env python3
"""RC1.7.1 release wiring; this test never invokes scripts/test.sh."""
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
MAIN = (ROOT / 'ideasdi-redaccion-gerizim.php').read_text(encoding='utf-8')
RUNNER = (ROOT / 'scripts' / 'test.sh').read_text(encoding='utf-8')
MANIFEST = ROOT / 'REGRESION-EDITORIAL-RC1.7.1.sha256'


def require(condition, label):
    if not condition:
        raise AssertionError(label)


require("define('IDG_TRACEABILITY_DB_VERSION', '1.2.0');" in MAIN, 'DB version unchanged')
for test_name in ('rc171-canonical-fidelity.py', 'rc171-editorial-consumption.py', 'rc171-release-integration.py'):
    require(test_name in RUNNER, f'test runner includes {test_name}')
require('./scripts/test.sh' not in RUNNER, 'runner does not self-invoke')
require(MANIFEST.is_file(), 'RC1.7.1 manifest exists')
manifest_paths = [line.split('  ', 1)[1] for line in MANIFEST.read_text(encoding='utf-8').splitlines()]
require('REGRESION-EDITORIAL-RC1.7.1.sha256' not in manifest_paths, 'manifest has no self reference')
for path in ('REGRESION-EDITORIAL-RC1.7.0.sha256', 'tests/rc171-canonical-fidelity.py', 'tests/rc171-editorial-consumption.py'):
    require(path in manifest_paths, f'manifest covers {path}')

print('RC171_RELEASE_INTEGRATION_OK')
