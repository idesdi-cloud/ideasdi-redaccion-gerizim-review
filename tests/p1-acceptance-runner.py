#!/usr/bin/env python3
"""P1 acceptance runner: identical subprocess scenarios, mode-only classification."""
from __future__ import annotations

import json
import hashlib
import os
import re
import shutil
import subprocess
import sys
import tarfile
import tempfile
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
BASELINE = "c5e58527c45d638423d4e48e505f4e8f4eda4dc4"
CHANGE = "chg-20261022T225545Z-3aa6c484"
MANIFEST_SHA256 = "16762a605a8912de9852e33ec109877e62f3b36b7f6c2b351efdacaca966a8da"
CANONICAL_HELPER_SHA256 = "e73d685bb42f08e02f392a6d49b27d42912b5bb1c6d078e169e5cb3532ba0e6d"
RC173_REVISION = "17d48ea38102e97038d6ff078f36824b7cb1b298"
RC173_BASELINE = "742b014d9b8b53163250eddda65edfaccabf4304"
ALLOWED = {
    "tests/p1-acceptance-runner.py",
    "tests/p1-functional-post-writes.php",
    "tests/p1-editorial-reliability.php",
    "tests/p1-case-fixtures.php",
    "includes/class-reel-contract.php",
    "includes/class-post-creator.php",
    "includes/class-admin-page.php",
    "includes/class-final-guard.php",
    "ESTADO-ACTUAL.md",
    "PROTECCION-REGRESIONES.md",
}

RC170 = [
    "tests/rc170-canonical-consumption.php",
    "tests/rc170-canonical-core.php",
    "tests/rc170-canonical-external-guard.php",
    "tests/rc170-canonical-internal-links.php",
    "tests/rc170-canonical-prompts-admin.php",
    "tests/rc170-release-regression.php",
]
RECENT_SERIES = ("rc173-", "rc174-", "rc175-", "rc176-")
BASELINE_EXPORT_IGNORED = [
    ".gitattributes",
    ".gitignore",
    "AGENTS.md",
    "scripts/build-zip.sh",
    "scripts/test.sh",
]
IMMUTABLE = [
    "REGRESION-EDITORIAL-RC1.7.6.sha256",
    "scripts/test.sh",
    "tests/support/canonical-regression.php",
    *RC170,
    "tests/rc173-yoast-contract.py",
    "tests/rc174-links-contract.py",
    "tests/rc175-gutenberg-contract.py",
    "tests/rc175-gutenberg-mock.php",
    "tests/rc176-reel-contract.php",
    "tests/rc176-reel-static.py",
]

SCENARIOS = [
    ("post_valid_pending", "tests/p1-functional-post-writes.php", False),
    ("post_invalid_rejected", "tests/p1-functional-post-writes.php", False),
    ("post_invalid_override_pending", "tests/p1-functional-post-writes.php", False),
    ("recurring_valid_update", "tests/p1-functional-post-writes.php", False),
    ("recurring_no_permission", "tests/p1-functional-post-writes.php", False),
    ("recurring_wrong_selected_id", "tests/p1-functional-post-writes.php", False),
    ("recurring_stale_fingerprint", "tests/p1-functional-post-writes.php", False),
    ("recurring_stale_signature", "tests/p1-functional-post-writes.php", False),
    ("recurring_wrong_type", "tests/p1-functional-post-writes.php", False),
    ("recurring_wrong_identity", "tests/p1-functional-post-writes.php", False),
    ("recurring_wrong_year", "tests/p1-functional-post-writes.php", False),
    ("box_valid_preserved", "tests/p1-editorial-reliability.php", False),
    ("box_absent", "tests/p1-editorial-reliability.php", False),
    ("box_short", "tests/p1-editorial-reliability.php", False),
    ("box_long", "tests/p1-editorial-reliability.php", False),
    ("box_bold", "tests/p1-editorial-reliability.php", False),
    ("box_html_link", "tests/p1-editorial-reliability.php", False),
    ("box_markdown_link", "tests/p1-editorial-reliability.php", False),
    ("box_misplaced", "tests/p1-editorial-reliability.php", False),
    ("reel_valid_contract", "tests/p1-editorial-reliability.php", False),
    ("reel_invalid_13_words", "tests/p1-editorial-reliability.php", False),
    ("reel_invalid_15_words", "tests/p1-editorial-reliability.php", False),
    ("reel_invalid_missing_cta", "tests/p1-editorial-reliability.php", False),
    ("reel_invalid_missing_overlay", "tests/p1-editorial-reliability.php", False),
    ("reel_invalid_missing_scene", "tests/p1-editorial-reliability.php", False),
    ("reel_invalid_extra_overlay", "tests/p1-editorial-reliability.php", False),
    ("reel_invalid_long_overlay", "tests/p1-editorial-reliability.php", False),
    ("reel_multiline_continuation", "tests/p1-editorial-reliability.php", True),
    ("case_192_spline_cleaner", "tests/p1-case-fixtures.php", False),
    ("case_191_cordwainers", "tests/p1-case-fixtures.php", True),
    ("case_189_delorean_airspeeder", "tests/p1-case-fixtures.php", False),
]


def command(
    args: list[str],
    timeout: int = 20,
    cwd: Path = ROOT,
    env: dict[str, str] | None = None,
) -> dict:
    try:
        completed = subprocess.run(
            args,
            cwd=cwd,
            text=True,
            capture_output=True,
            timeout=timeout,
            check=False,
            env={**os.environ, **(env or {})},
        )
        return {
            "command": " ".join(args),
            "stdout": completed.stdout,
            "stderr": completed.stderr,
            "exit": completed.returncode,
            "timeout": False,
        }
    except subprocess.TimeoutExpired as exc:
        return {
            "command": " ".join(args),
            "stdout": exc.stdout or "",
            "stderr": exc.stderr or "",
            "exit": None,
            "timeout": True,
        }


def sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as stream:
        for chunk in iter(lambda: stream.read(65536), b""):
            digest.update(chunk)
    return digest.hexdigest()


def immutable_check() -> tuple[dict, bool]:
    files = []
    ok = True
    for relative in IMMUTABLE:
        candidate = ROOT / relative
        baseline = subprocess.run(
            ["git", "show", f"{BASELINE}:{relative}"],
            cwd=ROOT,
            capture_output=True,
            check=False,
        )
        current = candidate.read_bytes() if candidate.is_file() else None
        same = baseline.returncode == 0 and current == baseline.stdout
        files.append({
            "path": relative,
            "candidate_sha256": hashlib.sha256(current).hexdigest() if current is not None else None,
            "baseline_sha256": hashlib.sha256(baseline.stdout).hexdigest() if baseline.returncode == 0 else None,
            "git_show_exit": baseline.returncode,
            "unchanged": same,
        })
        ok = ok and same
    return {"check": "immutable_bytes_vs_baseline", "files": files, "ok": ok}, ok


def changed_paths() -> tuple[list[str], dict]:
    result = command(["git", "status", "--porcelain"], timeout=10)
    paths: list[str] = []
    if result["exit"] == 0:
        for line in result["stdout"].splitlines():
            raw = line[3:]
            if " -> " in raw:
                raw = raw.split(" -> ", 1)[1]
            paths.append(raw.strip('"'))
    return sorted(paths), result


def self_check() -> dict:
    checks: list[dict] = []
    ok = True
    required_tests = [
        "tests/p1-acceptance-runner.py",
        "tests/p1-functional-post-writes.php",
        "tests/p1-editorial-reliability.php",
        "tests/p1-case-fixtures.php",
    ]
    for relative in required_tests:
        exists = (ROOT / relative).is_file()
        checks.append({"check": "file_exists", "path": relative, "ok": exists})
        ok = ok and exists

    for relative in [*required_tests[1:], "includes/class-reel-contract.php"]:
        lint = command(["php", "-l", relative])
        lint_ok = lint["exit"] == 0 and "No syntax errors detected" in lint["stdout"]
        lint.update({"check": "php_lint", "path": relative, "ok": lint_ok})
        checks.append(lint)
        ok = ok and lint_ok

    paths, status = changed_paths()
    paths_ok = set(paths) == ALLOWED
    status.update({
        "check": "authorized_changed_paths",
        "paths": paths,
        "expected": sorted(ALLOWED),
        "ok": paths_ok,
    })
    checks.append(status)
    ok = ok and paths_ok

    immutable, immutable_ok = immutable_check()
    checks.append(immutable)
    ok = ok and immutable_ok

    manifest_hash = sha256(ROOT / "REGRESION-EDITORIAL-RC1.7.6.sha256")
    manifest_ok = manifest_hash == MANIFEST_SHA256
    checks.append({
        "check": "pinned_manifest_sha256",
        "observed": manifest_hash,
        "expected": MANIFEST_SHA256,
        "ok": manifest_ok,
    })
    ok = ok and manifest_ok

    scenario_names = [name for name, _, _ in SCENARIOS]
    scenarios_ok = len(scenario_names) == 31 and len(set(scenario_names)) == 31
    checks.append({
        "check": "exact_31_unique_scenarios",
        "count": len(scenario_names),
        "ok": scenarios_ok,
    })
    ok = ok and scenarios_ok

    by_file: dict[str, list[str]] = {}
    for name, relative, _ in SCENARIOS:
        by_file.setdefault(relative, []).append(name)
    for relative, identifiers in by_file.items():
        text = (ROOT / relative).read_text(encoding="utf-8") if (ROOT / relative).is_file() else ""
        missing = [name for name in identifiers if name not in text]
        evidence = []
        if relative.endswith("p1-functional-post-writes.php"):
            evidence = ["IDG_Post_Creator::create_pending_post", "IDG_Post_Creator::update_existing_event", "wp_insert_post", "wp_update_post", "update_post_meta", "wp_set_post_terms"]
        elif relative.endswith("p1-editorial-reliability.php"):
            evidence = ["IDG_Final_Guard::validate_before_draft", "IDG_Reel_Contract::normalize", "IDG_Reel_Contract::inspect"]
        elif relative.endswith("p1-case-fixtures.php"):
            evidence = ["synthetic", "postprocessed_html", "seo_result"]
        missing_evidence = [token for token in evidence if token not in text]
        strict_ok = not missing and not missing_evidence
        checks.append({
            "check": "strict_scenario_identifiers",
            "path": relative,
            "identifiers": identifiers,
            "missing": missing,
            "missing_evidence": missing_evidence,
            "ok": strict_ok,
        })
        ok = ok and strict_ok

    return {
        "mode": "selftest",
        "change": CHANGE,
        "baseline": BASELINE,
        "status": "PASS" if ok else "FAIL",
        "checks": checks,
    }


def run_case(
    name: str,
    relative: str,
    expected_red: bool,
    mode: str,
    cwd: Path = ROOT,
) -> dict:
    result = command(["php", relative, name], cwd=cwd)
    stdout = result["stdout"]
    stderr = result["stderr"]
    pass_match = re.fullmatch(
        rf"P1_RESULT:{re.escape(name)}:PASS:ASSERTIONS=(\d+)\n?",
        stdout,
    )
    assert_match = re.fullmatch(
        rf"P1_ASSERT:{re.escape(name)}:([^\r\n]+)\n?",
        stdout,
    )
    assertions = int(pass_match.group(1)) if pass_match else None

    if result["timeout"] or result["exit"] not in (0, 42):
        status = "HARNESS_ERROR"
    elif "HARNESS_ERROR" in stdout or "HARNESS_ERROR" in stderr or "Fatal error" in stdout or "Fatal error" in stderr:
        status = "HARNESS_ERROR"
    elif result["exit"] == 0 and pass_match and assertions is not None and assertions >= 2 and stderr == "":
        status = "PASS"
    elif result["exit"] == 42 and assert_match and stderr == "":
        status = "EXPECTED_RED" if expected_red else "FALSE_RED"
    else:
        status = "WRONG_SENTINEL"

    result.update({
        "name": name,
        "assertions": assertions,
        "assert_fault": assert_match.group(1) if assert_match else None,
        "expected_red": expected_red,
        "status": status,
        "classification_mode": mode,
    })
    return result


def archive_baseline(parent: Path, revision: str = BASELINE) -> tuple[Path | None, dict]:
    archive = parent / "baseline.tar"
    tree = parent / "baseline"
    archived = command(
        ["git", "archive", "--format=tar", f"--output={archive}", revision],
        timeout=30,
    )
    if archived["exit"] != 0 or archived["timeout"] or not archive.is_file():
        return None, archived
    tree.mkdir()
    try:
        with tarfile.open(archive, "r") as package:
            package.extractall(tree)
    except (OSError, tarfile.TarError) as exc:
        archived["extract_error"] = str(exc)
        return None, archived
    supplemented = []
    for relative in BASELINE_EXPORT_IGNORED:
        baseline = subprocess.run(
            ["git", "show", f"{revision}:{relative}"],
            cwd=ROOT,
            capture_output=True,
            check=False,
        )
        source = ROOT / relative
        if baseline.returncode != 0:
            archived["supplement_error"] = f"export-ignored baseline bytes unavailable: {relative}"
            return None, archived
        tree_entry = subprocess.run(
            ["git", "ls-tree", revision, "--", relative],
            cwd=ROOT,
            capture_output=True,
            text=True,
            check=False,
        )
        if tree_entry.returncode != 0 or not tree_entry.stdout.strip():
            archived["supplement_error"] = f"export-ignored baseline mode unavailable: {relative}"
            return None, archived
        mode = tree_entry.stdout.split(maxsplit=1)[0]
        target = tree / relative
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_bytes(baseline.stdout)
        target.chmod(0o755 if mode == "100755" else 0o644)
        supplemented.append({
            "path": relative,
            "sha256": hashlib.sha256(baseline.stdout).hexdigest(),
            "git_mode": mode,
            "candidate_source_matches_git_show": source.is_file() and source.read_bytes() == baseline.stdout,
        })
    archived["extracted_to_temporary_tree"] = True
    archived["revision"] = revision
    archived["export_ignored_restored_from_verified_baseline_bytes"] = supplemented
    return tree, archived


def acceptance(mode: str) -> tuple[dict, int]:
    selftest = self_check()
    if selftest["status"] != "PASS":
        report = {
            "mode": mode,
            "change": CHANGE,
            "baseline": BASELINE,
            "status": "HARNESS_ERROR",
            "reason": "selftest_failed",
            "selftest": selftest,
            "cases": [],
        }
        return report, 2

    archive_result = None
    temporary_cleaned = None
    if mode == "red":
        temporary = tempfile.TemporaryDirectory(prefix="gerizim-p1-red-")
        temporary_path = Path(temporary.name)
        baseline_root, archive_result = archive_baseline(temporary_path)
        if baseline_root is None:
            temporary.cleanup()
            report = {
                "mode": mode,
                "change": CHANGE,
                "baseline": BASELINE,
                "status": "HARNESS_ERROR",
                "reason": "baseline_archive_failed",
                "selftest": selftest,
                "archive": archive_result,
                "cases": [],
            }
            return report, 2
        for _, relative, _ in SCENARIOS:
            source = ROOT / relative
            target = baseline_root / relative
            if not target.exists():
                target.parent.mkdir(parents=True, exist_ok=True)
                shutil.copy2(source, target)
        cases = [
            run_case(name, relative, expected_red, mode, baseline_root)
            for name, relative, expected_red in SCENARIOS
        ]
        temporary.cleanup()
        temporary_cleaned = not temporary_path.exists()
    else:
        cases = [
            run_case(name, relative, expected_red, mode)
            for name, relative, expected_red in SCENARIOS
        ]
    bad_harness = [case for case in cases if case["status"] in {"HARNESS_ERROR", "WRONG_SENTINEL"}]
    false_red = [case for case in cases if case["status"] == "FALSE_RED"]
    expected_reds = [case for case in cases if case["status"] == "EXPECTED_RED"]
    all_pass = all(case["status"] == "PASS" for case in cases)

    if bad_harness:
        status, reason, exit_code = "HARNESS_ERROR", "fatal_timeout_or_wrong_sentinel", 2
    elif false_red:
        status, reason, exit_code = "FAIL", "unexpected_behavior_failure", 1
    elif mode == "red":
        red_faults = {
            case["name"]: case["assert_fault"]
            for case in expected_reds
        }
        expected_faults = {
            "reel_multiline_continuation": "continuation_not_bound_to_own_vo_or_overlay",
            "case_191_cordwainers": "synthetic_191_multiline_not_kept_in_same_scene",
        }
        if len(cases) == 31 and sum(case["status"] == "PASS" for case in cases) == 29 and red_faults == expected_faults:
            status, reason, exit_code = "PASS", "REAL_RED_DEMONSTRATED", 0
        else:
            status, reason, exit_code = "NOT_DEMONSTRATED", "exact_known_red_not_reproduced", 1
    else:
        if all_pass:
            status, reason, exit_code = "PASS", "all_scenarios_green", 0
        else:
            status, reason, exit_code = "FAIL", "genuine_red_remains", 1

    report = {
        "mode": mode,
        "change": CHANGE,
        "baseline": BASELINE,
        "status": status,
        "reason": reason,
        "summary": {
            "total": len(cases),
            "pass": sum(case["status"] == "PASS" for case in cases),
            "expected_red": len(expected_reds),
            "false_red": len(false_red),
            "harness_error": len(bad_harness),
        },
        "cases": cases,
    }
    if archive_result is not None:
        report["archive"] = archive_result
        report["temporary_baseline_cleaned"] = temporary_cleaned
    return report, exit_code


def candidate_manifest_state() -> tuple[dict, bool]:
    entries = []
    for line in (ROOT / "REGRESION-EDITORIAL-RC1.7.6.sha256").read_text(encoding="utf-8").splitlines():
        if not line.strip():
            continue
        expected, relative = line.split(maxsplit=1)
        relative = relative.lstrip(" *")
        path = ROOT / relative
        observed = sha256(path) if path.is_file() else None
        entries.append({
            "path": relative,
            "expected": expected,
            "observed": observed,
            "matches": observed == expected,
        })
    mismatches = [entry["path"] for entry in entries if not entry["matches"]]
    checked = command(["sha256sum", "-c", "REGRESION-EDITORIAL-RC1.7.6.sha256"], timeout=60)
    scoped_exception = (
        mismatches == [
            "includes/class-admin-page.php",
            "includes/class-final-guard.php",
            "includes/class-post-creator.php",
            "includes/class-reel-contract.php",
        ]
        and checked["exit"] == 1
        and not checked["timeout"]
    )
    report = {
        "check": "candidate_manifest_deliberate_scoped_exception",
        "manifest_sha256": sha256(ROOT / "REGRESION-EDITORIAL-RC1.7.6.sha256"),
        "manifest_bytes_unchanged": sha256(ROOT / "REGRESION-EDITORIAL-RC1.7.6.sha256") == MANIFEST_SHA256,
        "mismatches": mismatches,
        "reel_observed_sha256": next(
            (entry["observed"] for entry in entries if entry["path"] == "includes/class-reel-contract.php"),
            None,
        ),
        "external_pin_unsealed": scoped_exception,
        "sha256sum": checked,
        "ok": scoped_exception,
    }
    return report, scoped_exception


def recent_acceptance() -> tuple[dict, int]:
    selftest = self_check()
    manifest, manifest_ok = candidate_manifest_state()
    series = {}
    selected = []
    series_ok = True
    for prefix in RECENT_SERIES:
        files = sorted(
            path.relative_to(ROOT).as_posix()
            for path in (ROOT / "tests").glob(f"{prefix}*")
            if path.suffix in {".php", ".py"}
        )
        series[prefix.rstrip("-")] = files
        series_ok = series_ok and bool(files)
        selected.extend(files)

    commands = []
    isolated_archive = None
    isolated_cleaned = None
    isolated_temporary = tempfile.TemporaryDirectory(prefix="gerizim-p1-recent-rc173-")
    isolated_path = Path(isolated_temporary.name)
    isolated_root, isolated_archive = archive_baseline(isolated_path, RC173_REVISION)
    isolated_index = isolated_path / "rc173.index"
    isolated_gitdir = isolated_path / "gitdir"
    if isolated_root is not None:
        isolated_gitdir.mkdir()
        (isolated_gitdir / "objects").mkdir()
        (isolated_gitdir / "refs").mkdir()
        (isolated_gitdir / "HEAD").write_text(RC173_REVISION + "\n", encoding="ascii")
        isolated_env = {
            "GIT_DIR": str(isolated_gitdir),
            "GIT_WORK_TREE": str(isolated_root),
            "GIT_INDEX_FILE": str(isolated_index),
            "GIT_OBJECT_DIRECTORY": str(ROOT / ".git" / "objects"),
            "GIT_OPTIONAL_LOCKS": "0",
        }
        index_result = command(
            ["git", "read-tree", RC173_REVISION],
            cwd=ROOT,
            env=isolated_env,
        )
        isolated_archive["temporary_index"] = index_result
        if index_result["exit"] != 0:
            isolated_root = None
    for relative in selected:
        interpreter = "php" if relative.endswith(".php") else "python3"
        if relative.startswith("tests/rc173-") and isolated_root is not None:
            result = command(
                [interpreter, relative],
                timeout=60,
                cwd=isolated_root,
                env={
                    "GIT_DIR": str(isolated_gitdir),
                    "GIT_WORK_TREE": str(isolated_root),
                    "GIT_INDEX_FILE": str(isolated_index),
                    "GIT_OBJECT_DIRECTORY": str(ROOT / ".git" / "objects"),
                    "GIT_OPTIONAL_LOCKS": "0",
                },
            )
            execution_context = "temporary_exact_rc173_revision_for_immutable_scope"
        elif relative.startswith("tests/rc173-"):
            result = {
                "command": f"{interpreter} {relative}",
                "stdout": "",
                "stderr": "baseline archive unavailable for immutable RC173 scope",
                "exit": None,
                "timeout": False,
            }
            execution_context = "blocked_missing_temporary_baseline"
        else:
            result = command([interpreter, relative], timeout=60)
            execution_context = "candidate"
        skipped = bool(re.search(
            r"\b(?:skip|skipped|omitted|omitido|omitida)\b",
            result["stdout"] + "\n" + result["stderr"],
            re.IGNORECASE,
        ))
        result.update({
            "path": relative,
            "execution_context": execution_context,
            "skipped": skipped,
            "ok": result["exit"] == 0 and not result["timeout"] and not skipped,
        })
        commands.append(result)
    isolated_temporary.cleanup()
    isolated_cleaned = not isolated_path.exists()

    ok = (
        selftest["status"] == "PASS"
        and manifest_ok
        and series_ok
        and len(selected) == len(set(selected))
        and all(result["ok"] for result in commands)
    )
    report = {
        "mode": "recent",
        "change": CHANGE,
        "baseline": BASELINE,
        "status": "PASS" if ok else "FAIL",
        "selftest": selftest,
        "series": series,
        "commands": commands,
        "rc173_isolated_archive": isolated_archive,
        "rc173_contract_baseline": RC173_BASELINE,
        "rc173_temporary_baseline_cleaned": isolated_cleaned,
        "manifest": manifest,
    }
    return report, 0 if ok else 1


def historical_acceptance() -> tuple[dict, int]:
    selftest = self_check()
    temporary = tempfile.TemporaryDirectory(prefix="gerizim-p1-historical-")
    temporary_path = Path(temporary.name)
    baseline_root, archived = archive_baseline(temporary_path)
    if baseline_root is None:
        temporary.cleanup()
        return {
            "mode": "historical",
            "change": CHANGE,
            "baseline": BASELINE,
            "status": "BLOCKED",
            "reason": "baseline_archive_failed",
            "selftest": selftest,
            "archive": archived,
            "temporary_baseline_cleaned": not temporary_path.exists(),
        }, 2

    manifest_hash = sha256(baseline_root / "REGRESION-EDITORIAL-RC1.7.6.sha256")
    helper_hash = sha256(baseline_root / "tests/support/canonical-regression.php")
    hash_ok = manifest_hash == MANIFEST_SHA256 and helper_hash == CANONICAL_HELPER_SHA256
    manifest_command = command(
        ["sha256sum", "-c", "REGRESION-EDITORIAL-RC1.7.6.sha256"],
        timeout=60,
        cwd=baseline_root,
    )

    found_rc170 = sorted(
        path.relative_to(baseline_root).as_posix()
        for path in (baseline_root / "tests").glob("rc170-*.php")
    )
    exact_six = found_rc170 == RC170
    rc170_commands = [
        command(["php", relative], timeout=60, cwd=baseline_root)
        for relative in found_rc170
    ]
    historical_gitdir = temporary_path / "gitdir"
    historical_gitdir.mkdir()
    (historical_gitdir / "objects").mkdir()
    (historical_gitdir / "refs").mkdir()
    (historical_gitdir / "HEAD").write_text(BASELINE + "\n", encoding="ascii")
    historical_index = temporary_path / "historical.index"
    historical_env = {
        "GIT_DIR": str(historical_gitdir),
        "GIT_WORK_TREE": str(baseline_root),
        "GIT_INDEX_FILE": str(historical_index),
        "GIT_OBJECT_DIRECTORY": str(ROOT / ".git" / "objects"),
        "GIT_OPTIONAL_LOCKS": "0",
    }
    historical_index_result = command(
        ["git", "read-tree", BASELINE],
        cwd=ROOT,
        env=historical_env,
    )
    full_suite = command(
        ["bash", "scripts/test.sh"],
        timeout=180,
        cwd=baseline_root,
        env=historical_env,
    )
    ok = (
        selftest["status"] == "PASS"
        and hash_ok
        and manifest_command["exit"] == 0
        and not manifest_command["timeout"]
        and exact_six
        and all(result["exit"] == 0 and not result["timeout"] for result in rc170_commands)
        and historical_index_result["exit"] == 0
        and full_suite["exit"] == 0
        and not full_suite["timeout"]
    )
    report = {
        "mode": "historical",
        "change": CHANGE,
        "baseline": BASELINE,
        "status": "PASS" if ok else "BLOCKED",
        "selftest": selftest,
        "archive": archived,
        "original_hashes": {
            "manifest": {"observed": manifest_hash, "expected": MANIFEST_SHA256},
            "canonical_helper": {"observed": helper_hash, "expected": CANONICAL_HELPER_SHA256},
        },
        "original_manifest_check": manifest_command,
        "original_rc170": {
            "found": found_rc170,
            "expected": RC170,
            "exactly_six": exact_six,
            "commands": rc170_commands,
        },
        "temporary_git_index": historical_index_result,
        "original_full_suite": full_suite,
    }
    temporary.cleanup()
    report["temporary_baseline_cleaned"] = not temporary_path.exists()
    return report, 0 if ok else 1


def final_acceptance() -> tuple[dict, int]:
    """Run the real WP-boundary scenarios as the final R2 proof, not a lint."""
    report, exit_code = acceptance("green")
    report["mode"] = "final"
    report["final_scope"] = [
        "box preservation and Final Guard negatives",
        "zero-write rejection and pending creation",
        "shared create/recurrence Gutenberg payload",
        "recurrence identity non-bypass",
        "Reel warning remains non-blocking",
    ]
    return report, exit_code


def main() -> int:
    modes = {"--selftest", "--red", "--green", "--recent", "--historical", "--final"}
    if len(sys.argv) != 2 or sys.argv[1] not in modes:
        print("usage: tests/p1-acceptance-runner.py --selftest|--red|--green|--recent|--historical|--final", file=sys.stderr)
        return 64
    option = sys.argv[1]
    if option == "--selftest":
        report = self_check()
        print(json.dumps(report, ensure_ascii=False, indent=2))
        return 0 if report["status"] == "PASS" else 2
    if option == "--recent":
        report, exit_code = recent_acceptance()
    elif option == "--historical":
        report, exit_code = historical_acceptance()
    elif option == "--final":
        report, exit_code = final_acceptance()
    else:
        report, exit_code = acceptance(option[2:])
    print(json.dumps(report, ensure_ascii=False, indent=2))
    return exit_code


if __name__ == "__main__":
    raise SystemExit(main())
