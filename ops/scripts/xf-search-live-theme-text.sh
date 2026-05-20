#!/usr/bin/env bash

set -Eeuo pipefail

python3 - <<'PY'
from pathlib import Path

roots = [
    Path("/var/www/bareefers.org/forum"),
    Path("/var/www/bareefers.org"),
]
needles = [
    "Aurora theme sets the standard",
    "Important Links",
    "reef_nutrition_logo_BAR",
    "sponsor_banners",
]

for root in roots:
    for path in root.rglob("*"):
        if not path.is_file():
            continue
        try:
            text = path.read_text(errors="ignore")
        except Exception:
            continue
        hits = [needle for needle in needles if needle in text]
        if hits:
            print(path)
            print("  " + ", ".join(hits))
PY
