#!/usr/bin/env python3
"""Idempotent: add limit_req for aggressive crawlers inside location /forum/ { ... }."""
from pathlib import Path

path = Path("/etc/nginx/sites-available/bareefers.org")
text = path.read_text(encoding="utf-8")
needle = "limit_req zone=bareefers_aggressive_crawlers"
if needle in text:
    print("already_patched")
    raise SystemExit(0)

old = "    location /forum/ {\n        try_files"
new = (
    "    location /forum/ {\n"
    "        limit_req zone=bareefers_aggressive_crawlers burst=2 nodelay;\n"
    "        limit_req_status 429;\n"
    "        try_files"
)
if old not in text:
    print("pattern_not_found")
    raise SystemExit(1)
path.write_text(text.replace(old, new, 1), encoding="utf-8")
print("patched")
