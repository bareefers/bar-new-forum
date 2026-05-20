#!/usr/bin/env python3
"""Patch bareefers.org nginx: Linode DNS-friendly proxy + resolver. Run on server as root."""
from pathlib import Path

p = Path("/etc/nginx/sites-available/bareefers.org")
s = p.read_text()
needle = "    ssl_certificate_key /etc/nginx/ssl/beta.bareefers.org.key;\n\n    root /var/www/bareefers.org;\n"
insert = (
    "    ssl_certificate_key /etc/nginx/ssl/beta.bareefers.org.key;\n\n"
    "    resolver 1.1.1.1 8.8.8.8 valid=300s ipv6=off;\n"
    "    resolver_timeout 5s;\n\n"
    "    root /var/www/bareefers.org;\n"
)
if needle not in s:
    raise SystemExit("needle1 missing")
s = s.replace(needle, insert, 1)
old = "        proxy_pass https://barcode.bareefers.org;\n        proxy_set_header Host barcode.bareefers.org;\n"
d = "$"
new = (
    f"        set {d}bc_upstream_host barcode.bareefers.org;\n"
    f"        proxy_pass https://{d}bc_upstream_host;\n"
    "        proxy_ssl_server_name on;\n"
    "        proxy_ssl_name barcode.bareefers.org;\n"
    "        proxy_set_header Host barcode.bareefers.org;\n"
)
if old not in s:
    raise SystemExit("needle2 missing")
s = s.replace(old, new, 1)
p.write_text(s)
print("patched")
