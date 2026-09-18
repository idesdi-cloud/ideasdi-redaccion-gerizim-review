import re
import sys
from pathlib import Path


if len(sys.argv) != 2:
    print(f"uso: {Path(sys.argv[0]).name} EXPECTED_VERSION", file=sys.stderr)
    raise SystemExit(2)

expected_version = sys.argv[1]
root = Path(__file__).resolve().parents[1]
bootstrap = (root / "ideasdi-redaccion-gerizim.php").read_text(encoding="utf-8")

header_match = re.search(r"^ \* Version: (.+)$", bootstrap, re.MULTILINE)
define_match = re.search(
    r"^define\('IDG_VERSION', '([^']+)'\);$",
    bootstrap,
    re.MULTILINE,
)

actual_header = header_match.group(1) if header_match else None
actual_define = define_match.group(1) if define_match else None

if actual_header != expected_version or actual_define != expected_version:
    print(
        "plugin version mismatch: "
        f"header={actual_header!r}, IDG_VERSION={actual_define!r}, "
        f"expected={expected_version!r}",
        file=sys.stderr,
    )
    raise SystemExit(1)

print(f"PLUGIN_VERSION_CONTRACT_OK {expected_version}")
