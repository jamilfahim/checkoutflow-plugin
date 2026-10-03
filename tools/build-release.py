#!/usr/bin/env python3
"""Build a clean, deterministic WordPress plugin ZIP from tracked runtime files."""

from __future__ import annotations

import argparse
import re
import subprocess
from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile, ZipInfo


ROOT = Path(__file__).resolve().parents[1]
SLUG = "eilmo-checkout-flow"
ROOT_FILES = {
    "LICENSE",
    "eilmo-checkout-flow.php",
    "readme.txt",
    "uninstall.php",
}
RUNTIME_DIRS = ("assets/", "src/", "templates/", "languages/")
REQUIRED_ASSETS = {
    "assets/build/css/admin.css",
    "assets/build/css/frontend.css",
    "assets/build/js/admin.js",
    "assets/build/js/frontend.js",
    "assets/images/bkash.png",
    "assets/images/nagad.png",
}


def version() -> str:
    header = (ROOT / "eilmo-checkout-flow.php").read_text(encoding="utf-8")
    readme = (ROOT / "readme.txt").read_text(encoding="utf-8")
    plugin = re.search(r"^ \* Version:\s*(\S+)", header, re.MULTILINE)
    stable = re.search(r"^Stable tag:\s*(\S+)", readme, re.MULTILINE)
    if not plugin or not stable or plugin.group(1) != stable.group(1):
        raise SystemExit("Plugin header and readme stable tag must match")
    return plugin.group(1)


def release_files() -> list[Path]:
    dirty = subprocess.check_output(
        ["git", "diff", "--name-only", "HEAD", "--", *sorted(ROOT_FILES), "assets", "src", "templates", "languages"],
        cwd=ROOT,
    ).decode("utf-8").strip()
    if dirty:
        raise SystemExit("Commit runtime changes before building a release: " + dirty.replace("\n", ", "))
    tracked = subprocess.check_output(
        ["git", "ls-files", "-z", "--cached"], cwd=ROOT
    ).split(b"\0")
    paths = []
    for raw in tracked:
        if not raw:
            continue
        name = raw.decode("utf-8")
        if name not in ROOT_FILES and not name.startswith(RUNTIME_DIRS):
            continue
        path = ROOT / name
        if not path.is_file() or path.is_symlink():
            raise SystemExit(f"Missing or unsafe tracked release file: {name}")
        paths.append(path)
    names = {path.relative_to(ROOT).as_posix() for path in paths}
    missing = (ROOT_FILES | REQUIRED_ASSETS) - names
    if missing:
        raise SystemExit("Missing required release files: " + ", ".join(sorted(missing)))
    return sorted(paths)


def build(output: Path) -> None:
    current_version = version()
    files = release_files()
    output = output.expanduser().resolve()
    if output == ROOT or ROOT in output.parents:
        raise SystemExit("Write release archives outside the plugin directory")
    output.parent.mkdir(parents=True, exist_ok=True)
    with ZipFile(output, "w", compression=ZIP_DEFLATED, compresslevel=9) as archive:
        for path in files:
            name = f"{SLUG}/{path.relative_to(ROOT).as_posix()}"
            info = ZipInfo(name, date_time=(1980, 1, 1, 0, 0, 0))
            info.compress_type = ZIP_DEFLATED
            info.external_attr = 0o644 << 16
            archive.writestr(info, path.read_bytes(), compress_type=ZIP_DEFLATED, compresslevel=9)
    with ZipFile(output) as archive:
        if archive.testzip() is not None:
            raise SystemExit("Release archive failed CRC validation")
        members = archive.namelist()
        if len(members) != len(files) or any(
            part in {".git", "tests", "tools"} for member in members for part in Path(member).parts
        ):
            raise SystemExit("Release archive contains unexpected files")
    print(f"Built {output} for v{current_version}: {len(files)} runtime files, {output.stat().st_size:,} bytes")


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--output", required=True, type=Path, help="ZIP path outside the plugin directory")
    build(parser.parse_args().output)
