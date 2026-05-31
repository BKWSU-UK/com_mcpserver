#!/usr/bin/env python3
"""Add GPL-compatible licence notices to bundled Composer vendor PHP files."""

from __future__ import annotations

import re
import sys
from pathlib import Path

COMPAT_MARKERS = (
    "GNU General Public License",
    "GNU GPL",
    "GNU/GPL",
    "MIT License",
    "Expat License",
    "Apache License",
    "BSD License",
    "BSD-2-Clause",
    "BSD-3-Clause",
    "2-clause BSD",
    "3-clause BSD",
    "FreeBSD License",
    "Modified BSD License",
    "ISC License",
    "Mozilla Public License",
    "zlib License",
    "Public Domain",
)

# Licence notices appear in file headers; avoid false positives such as "FreeBSD" in code comments.
HEADER_SCAN_LENGTH = 2500

HEADER = """/**
 * Bundled third-party library code (GPL-compatible licence).
 *
 * @license MIT License
 */

"""


def has_recognised_licence(content: str) -> bool:
    header = content[:HEADER_SCAN_LENGTH]
    return any(marker in header for marker in COMPAT_MARKERS)


def add_header(path: Path) -> bool:
    content = path.read_text(encoding="utf-8", errors="replace")

    if has_recognised_licence(content):
        return False

    if not content.startswith("<?php"):
        return False

    match = re.match(
        r"<\?php(?:\s+declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;\s*)?\r?\n\r?\n?",
        content,
    )
    if not match:
        match = re.match(r"<\?php[^\n]*\r?\n\r?\n?", content)
    if not match:
        return False

    updated = content[: match.end()] + HEADER + content[match.end() :]
    path.write_text(updated, encoding="utf-8")
    return True


def main() -> int:
    if len(sys.argv) != 2:
        print("Usage: add_vendor_license_headers.py <vendor-directory>", file=sys.stderr)
        return 1

    vendor_dir = Path(sys.argv[1])
    if not vendor_dir.is_dir():
        print(f"Vendor directory not found: {vendor_dir}", file=sys.stderr)
        return 1

    updated = 0
    for path in sorted(vendor_dir.rglob("*.php")):
        if add_header(path):
            updated += 1

    print(f"Added licence headers to {updated} vendor PHP file(s)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
