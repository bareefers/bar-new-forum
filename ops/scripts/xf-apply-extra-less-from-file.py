#!/usr/bin/env python3
"""
Apply a normal .less file (real newlines + real tabs) to style public extra.less
via XenForo PHP (setTemplateUnchecked) — never UPDATE xf_template via SQL.

Usage:
  xf-apply-extra-less-from-file.py /path/to/extra.less [forum_root] [style_id]

Defaults: forum_root=/var/www/bareefers.org/forum style_id=16
"""
import os
import pathlib
import subprocess
import sys


def main() -> int:
    if len(sys.argv) < 2:
        print(
            "usage: xf-apply-extra-less-from-file.py /path/to/extra.less "
            "[forum_root] [style_id]",
            file=sys.stderr,
        )
        return 2
    path = pathlib.Path(sys.argv[1])
    forum_root = sys.argv[2] if len(sys.argv) > 2 else "/var/www/bareefers.org/forum"
    style_id = sys.argv[3] if len(sys.argv) > 3 else "16"
    if not path.is_file():
        print(f"Not found: {path}", file=sys.stderr)
        return 1

    here = os.path.dirname(os.path.abspath(__file__))
    candidates = [
        os.environ.get("XF_SET_TEMPLATE_PHP"),
        os.path.join(here, "xf-set-template-from-file.php"),
        "/tmp/xf-set-template-from-file.php",
    ]
    php_helper = next((p for p in candidates if p and os.path.isfile(p)), None)
    if not php_helper:
        print("xf-set-template-from-file.php not found (set XF_SET_TEMPLATE_PHP)", file=sys.stderr)
        return 1

    subprocess.run(
        [
            "sudo",
            "-u",
            "www-data",
            "php",
            php_helper,
            forum_root,
            style_id,
            "public",
            "extra.less",
            str(path.resolve()),
        ],
        check=True,
    )
    subprocess.run(["redis-cli", "-n", "7", "FLUSHDB"], check=True)
    print("OK", path)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
