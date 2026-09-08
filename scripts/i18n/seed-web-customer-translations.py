#!/usr/bin/env python3
"""Deprecated shim — web-customer packs are exported from messages.ts.

Prefer:
  php scripts/i18n/export-web-customer.php

This script forwards to that exporter so older generate pipelines stay safe.
"""

from __future__ import annotations

import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]


def main() -> int:
    script = ROOT / "scripts" / "i18n" / "export-web-customer.php"
    print(
        "seed-web-customer-translations.py is deprecated; "
        "forwarding to export-web-customer.php …",
        file=sys.stderr,
    )
    return subprocess.call(["php", str(script)], cwd=str(ROOT))


if __name__ == "__main__":
    raise SystemExit(main())
