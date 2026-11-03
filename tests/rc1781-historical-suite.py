#!/usr/bin/env python3
"""Execute the protected regressions in an exported RC1.7.8 baseline snapshot."""

from __future__ import annotations

import io
import os
from pathlib import Path
import subprocess
import tarfile
import tempfile


ROOT = Path(__file__).resolve().parents[1]
BASELINE = "9b629b13006f9a9dd0cbbcad6213f97a5a73c551"
PROTECTED = (
    "tests/rc170-canonical-consumption.php",
    "tests/rc170-canonical-core.php",
    "tests/rc170-canonical-external-guard.php",
    "tests/rc170-canonical-internal-links.php",
    "tests/rc170-canonical-prompts-admin.php",
    "tests/rc178-targeted-regression.php",
)


def git_bytes(*args: str) -> bytes:
    return subprocess.run(
        ["git", *args],
        cwd=ROOT,
        check=True,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
    ).stdout


def run(
    command: list[str],
    cwd: Path,
    extra_environment: dict[str, str] | None = None,
) -> subprocess.CompletedProcess[str]:
    environment = os.environ.copy()
    if extra_environment:
        environment.update(extra_environment)
    return subprocess.run(
        command,
        cwd=cwd,
        env=environment,
        text=True,
        stdout=subprocess.PIPE,
        stderr=subprocess.STDOUT,
        check=False,
    )


def main() -> int:
    resolved = git_bytes("rev-parse", BASELINE + "^{commit}").decode().strip()
    if resolved != BASELINE:
        print("FAIL: no se pudo verificar el commit histórico exacto")
        return 1

    archive = git_bytes("archive", "--format=tar", BASELINE)
    historical_script = git_bytes("show", f"{BASELINE}:scripts/test.sh")

    with tempfile.TemporaryDirectory(prefix="gerizim-rc178-history-") as temp:
        snapshot = Path(temp) / "snapshot"
        snapshot.mkdir()
        with tarfile.open(fileobj=io.BytesIO(archive), mode="r:") as tar:
            root = snapshot.resolve()
            for member in tar.getmembers():
                target = (snapshot / member.name).resolve()
                if target != root and root not in target.parents:
                    print(f"FAIL unsafe archive member: {member.name}")
                    return 1
            tar.extractall(snapshot)

        scripts = snapshot / "scripts"
        scripts.mkdir(exist_ok=True)
        runner = scripts / "test.sh"
        runner.write_bytes(historical_script)
        runner.chmod(0o755)

        # HEAD histórico aislado; el repositorio actual permanece intacto.
        historical_git = Path(temp) / "history.git"
        initialized = run(["git", "init", "--bare", str(historical_git)], ROOT)
        if initialized.returncode != 0:
            print("FAIL: no se pudo preparar el contexto Git histórico")
            return 1
        objects = Path(git_bytes("rev-parse", "--git-path", "objects").decode().strip())
        if not objects.is_absolute():
            objects = ROOT / objects
        (historical_git / "objects/info/alternates").write_text(
            str(objects.resolve()) + "\n", encoding="utf-8"
        )
        (historical_git / "HEAD").write_text(BASELINE + "\n", encoding="ascii")
        read_only_git = {
            "GIT_DIR": str(historical_git),
            "GIT_WORK_TREE": str(snapshot),
        }
        baseline_suite = run(["bash", "scripts/test.sh"], snapshot, read_only_git)
        if baseline_suite.returncode != 0:
            print("FAIL historical scripts/test.sh")
            print(baseline_suite.stdout.rstrip())
            return 1
        print("PASS historical scripts/test.sh from exported RC1.7.8 baseline")

        passed = 0
        for relative in PROTECTED:
            result = run(["php", relative], snapshot)
            if result.returncode != 0:
                print(f"FAIL {relative}")
                print(result.stdout.rstrip())
                continue
            passed += 1
            print(f"PASS {relative}")

        print(f"HISTORICAL {passed}/{len(PROTECTED)}")
        return 0 if passed == len(PROTECTED) else 1


if __name__ == "__main__":
    raise SystemExit(main())
