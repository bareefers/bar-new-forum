#!/usr/bin/env bash
set -euo pipefail
CFG=/var/www/bareefers.org/forum/src/config.php
cp -a "$CFG" "/var/tmp/config.php.bak.pagecache-off-$(date +%Y%m%d-%H%M%S)"
python3 - <<'PY'
from pathlib import Path
p = Path('/var/www/bareefers.org/forum/src/config.php')
t = p.read_text(encoding='utf-8', errors='replace')
old = "$config['pageCache']['enabled'] = true;"
new = "$config['pageCache']['enabled'] = false;"
if old not in t:
    raise SystemExit('pattern not found')
p.write_text(t.replace(old, new, 1), encoding='utf-8')
PY
grep -n "pageCache" "$CFG" | head -5
systemctl restart php8.3-fpm
sleep 2
curl -sS -m 25 -L -H "Host: www.bareefers.org" "http://127.0.0.1/forum/?pcoff=$(date +%s)" -o /tmp/nopc.html
grep -oE 'd=[0-9]+' /tmp/nopc.html | sort | uniq -c || true
