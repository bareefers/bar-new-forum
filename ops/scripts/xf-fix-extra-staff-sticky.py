#!/usr/bin/env python3
"""One-off: fix Aurora extra.less staff bar vs sticky nav (DB stores \\n as two chars)."""
import subprocess
import sys


def mysql_string_literal(s: str) -> str:
    return "'" + s.replace("\\", "\\\\").replace("'", "''") + "'"


def main() -> int:
    sel = subprocess.check_output(
        [
            "sudo",
            "mysql",
            "baraforo",
            "-Nse",
            "SELECT template FROM xf_template WHERE template_id=4871",
        ],
        text=True,
    )
    old = r"@supports (position: sticky) or (position: -webkit-sticky) {\n    .p-navSticky {\n        position: -webkit-sticky;\n        position: sticky;\n        top: 40px;\n    }\n}"
    new = r"@supports (position: sticky) or (position: -webkit-sticky) {\n    .p-navSticky {\n        position: -webkit-sticky;\n        position: sticky;\n        top: 0;\n    }\n    .p-staffBar ~ .p-header .p-navSticky {\n        top: 40px;\n    }\n    .p-staffBar + .p-header {\n        margin-top: -1px;\n    }\n}"
    if old not in sel:
        print("OLD_BLOCK_NOT_FOUND", file=sys.stderr)
        return 1
    out = sel.replace(old, new, 1)
    if out == sel:
        print("NO_CHANGE", file=sys.stderr)
        return 1
    sql = (
        "UPDATE xf_template SET template="
        + mysql_string_literal(out)
        + ", last_edit_date=UNIX_TIMESTAMP() WHERE template_id=4871;\n"
    )
    subprocess.run(
        ["sudo", "mysql", "baraforo"],
        input=sql,
        text=True,
        check=True,
    )
    print("OK", len(out))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
