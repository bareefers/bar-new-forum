#!/usr/bin/env python3
"""Download the forum HTML CSS bundle that includes public:extra.less and print stats."""
import re
import sys
import urllib.parse
import urllib.request

base = sys.argv[1] if len(sys.argv) > 1 else "https://www.bareefers.org/forum/"
out = sys.argv[2] if len(sys.argv) > 2 else "/tmp/xf-bundle.css"

req = urllib.request.Request(
    base + ("&" if "?" in base else "?") + "cb=" + str(int(__import__("time").time())),
    headers={"Cache-Control": "no-cache", "Pragma": "no-cache"},
)
html = urllib.request.urlopen(req, timeout=30).read().decode("utf-8", "replace")
m = re.search(r'href="(/forum/css\.php[^"]*extra\.less[^"]*)"', html)
if not m:
    sys.exit("no css bundle href with extra.less found")
href = m.group(1).replace("&amp;", "&")
url = urllib.parse.urljoin(base, href)
print(url)
css_req = urllib.request.Request(
    url,
    headers={"Cache-Control": "no-cache", "Pragma": "no-cache", "Accept-Encoding": "identity"},
)
css = urllib.request.urlopen(css_req, timeout=60).read()
open(out, "wb").write(css)
print("bytes", len(css))
print("head", css[:120].decode("utf-8", "replace").replace("\n", "\\n"))
print("p-proxy", css.count(b"p-proxy"))
print("p-navSticky.p-navSticky--primary", css.count(b"p-navSticky.p-navSticky--primary"))
