#!/usr/bin/env python3
"""
Restore Aurora (style_id=16) public extra.less on bareefers.

Tabs in RAW_LESS must be real TAB bytes — do NOT use a raw r\"\"\" string or \\t becomes
backslash-t and breaks LESS.

Writes a temp .less with real newlines and runs xf-set-template-from-file.php so XenForo
saves the template correctly. Do not raw SQL UPDATE.
"""
import os
import subprocess
import sys
import tempfile


# Your known-good BAR baseline (before experimental overrides).
RAW_LESS = """/* Navigation - logo */
.p-header-logo.p-header-logo--image {
\tmargin: 0;
\tpicture,
\timg {
\t\tmax-width: 220px;   /* was 50px — increase until it looks right */
\t\twidth: auto;
\t\theight: auto;
\t}
}

/* Aurora caps header logo in dark (~100px) — match BAR 220 / 160 */
@media (prefers-color-scheme: dark) {
\t:root:not([data-variation]) .p-header-logo.p-header-logo--image picture,
\t:root:not([data-variation]) .p-header-logo.p-header-logo--image img {
\t\tmax-width: 220px !important;
\t\twidth: auto !important;
\t\theight: auto !important;
\t}
}

html[data-color-scheme="dark"] .p-header-logo.p-header-logo--image picture,
html[data-color-scheme="dark"] .p-header-logo.p-header-logo--image img {
\tmax-width: 220px !important;
\twidth: auto !important;
\theight: auto !important;
}

/* Staff bar height tweak (safe: no CSS var/hsla transform) */
.p-staffBar {
\theight: 40px;
}

@media (prefers-color-scheme: dark) and (max-width: @xf-responsiveNarrow) {
\t:root:not([data-variation]) .p-header-logo.p-header-logo--image picture,
\t:root:not([data-variation]) .p-header-logo.p-header-logo--image img {
\t\tmax-width: 88px !important;
\t\tmax-height: 40px !important;
\t}
}

@media (max-width: @xf-responsiveNarrow) {
\thtml[data-color-scheme="dark"] .p-header-logo.p-header-logo--image picture,
\thtml[data-color-scheme="dark"] .p-header-logo.p-header-logo--image img {
\t\tmax-width: 88px !important;
\t\tmax-height: 40px !important;
\t}
}

@supports ~"(position: sticky) or (position: -webkit-sticky)" {
\t/*
\t * Sticky gap: theme uses .p-navSticky { top: 20px }. html body raises specificity above repeats.
\t * Tilde @supports: XenForo less.php breaks on bare (sticky) or (-webkit-sticky).
\t */
\thtml body .p-navSticky,
\thtml body .p-navSticky.p-navSticky--primary {
\t\tposition: -webkit-sticky;
\t\tposition: sticky;
\t\ttop: 0 !important;
\t}
\thtml body .p-navSticky.is-sticky,
\thtml body .p-navSticky.p-navSticky--primary.is-sticky {
\t\ttop: 0 !important;
\t}
\thtml body .p-staffBar ~ .p-header .p-navSticky,
\thtml body .p-staffBar + .p-header .p-navSticky,
\thtml body .p-navSticky.p-staffSticky,
\thtml body .p-staffBar ~ .p-header .p-navSticky.p-navSticky--primary,
\thtml body .p-staffBar + .p-header .p-navSticky.p-navSticky--primary {
\t\ttop: 20px !important;
\t}
}

/*
 * Mobile only: logo left (small), visitor icons center-right, hamburger far right.
 */
@media (max-width: @xf-responsiveNarrow) {
\t.p-header-logo.p-header-logo--image picture,
\t.p-header-logo.p-header-logo--image img {
\t\tmax-width: 88px;
\t\tmax-height: 40px;
\t\twidth: auto;
\t\theight: auto;
\t\tobject-fit: contain;
\t}
\t.p-nav-intra {
\t\tdisplay: flex;
\t\tflex-wrap: nowrap;
\t\talign-items: center;
\t\tgap: 6px;
\t\tmin-width: 0;
\t}
\t.p-nav-intra .p-header-logo.p-header-logo--image {
\t\torder: 1;
\t\tflex: 0 1 auto;
\t\tmin-width: 0;
\t\tmax-width: 30vw;
\t\tmax-height: 44px;
\t\toverflow: hidden;
\t}
\t.p-nav-intra .p-header-logo.p-header-logo--image picture,
\t.p-nav-intra .p-header-logo.p-header-logo--image img {
\t\tmax-width: 100%;
\t\tmax-height: 40px;
\t}
\t.p-nav-intra .p-discovery {
\t\torder: 2;
\t\tflex-shrink: 0;
\t}
\t.p-nav-intra .p-nav-opposite {
\t\torder: 3;
\t\tflex: 1 1 auto;
\t\tmin-width: 0;
\t\tdisplay: flex;
\t\tflex-wrap: nowrap;
\t\talign-items: center;
\t\tjustify-content: flex-end;
\t}
\t.p-nav-intra .p-nav-opposite .p-navgroup {
\t\tflex-wrap: nowrap;
\t}
\t.p-nav-intra .p-nav-smallLogo {
\t\torder: 4;
\t\tflex-shrink: 0;
\t\tmargin-left: 2px;
\t}
}

.p-nav-inner {
\tmax-width: 1400px;
\tpadding-top: 0px;
}

/* aligns and centers iconic nav icons vertically */
.p-navgroup-link.p-navgroup-link--iconic i::after {
\tline-height: 25px;
\tvertical-align: middle;
}

.p-navgroup-link--dbtech-credits .p-navgroup-linkText {
\tdisplay: none;
}

/* Supporting member callout */
div.supportingMember {
\ttext-align: center;
\tcolor: #692bd4;
\tfont-size: 32px;
\tfont-weight: 600;
\tpadding-top: 12px;
\tpadding-bottom: 12px;
\ttransition: color .2s ease, transform .2s ease;
}

div.supportingMember:hover {
\tcolor: #000000;
\ttransform: translateY(-1px);
}

/* Center sponsor / ad blocks */
.alignCenter {
\ttext-align: center;
\ta {
\t\tdisplay: inline-block;
\t}
\timg {
\t\tdisplay: block;
\t\tmargin-left: auto;
\t\tmargin-right: auto;
\t\tmax-width: 100%;
\t\theight: auto;
\t}
}
"""


def main() -> int:
    here = os.path.dirname(os.path.abspath(__file__))
    source_less = os.environ.get(
        "XF_EXTRA_LESS_SOURCE",
        os.path.join(here, "extra-less-aurora16-source.less"),
    )

    if os.path.isfile(source_less):
        with open(source_less, "r", encoding="utf-8", newline=None) as f:
            raw = f.read()
    else:
        # Backward-compatible fallback if source file is missing.
        raw = RAW_LESS

    raw = raw.rstrip("\n") + "\n"
    if "\t" not in raw:
        print("SANITY_FAIL: expected real TAB characters in RAW_LESS", file=sys.stderr)
        return 1

    forum_root = os.environ.get("XF_FORUM_ROOT", "/var/www/bareefers.org/forum")
    candidates = [
        os.environ.get("XF_SET_TEMPLATE_PHP"),
        os.path.join(here, "xf-set-template-from-file.php"),
        "/tmp/xf-set-template-from-file.php",
    ]
    php_helper = next((p for p in candidates if p and os.path.isfile(p)), None)
    if not php_helper:
        print(
            "SANITY_FAIL: xf-set-template-from-file.php not found "
            "(set XF_SET_TEMPLATE_PHP or place next to this file or in /tmp/)",
            file=sys.stderr,
        )
        return 1

    fd, tmp_path = tempfile.mkstemp(prefix="extra-less-aurora16-", suffix=".less", dir="/tmp")
    try:
        with os.fdopen(fd, "w", encoding="utf-8", newline="\n") as f:
            f.write(raw)
        os.chmod(tmp_path, 0o644)
        subprocess.run(
            [
                "sudo",
                "-u",
                "www-data",
                "php",
                php_helper,
                forum_root,
                "16",
                "public",
                "extra.less",
                tmp_path,
            ],
            check=True,
        )
        subprocess.run(["redis-cli", "-n", "7", "FLUSHDB"], check=True)
        chk = subprocess.run(
            [
                "sudo",
                "mysql",
                "baraforo",
                "-Nse",
                "SELECT LENGTH(template) FROM xf_template WHERE style_id=16 AND title='extra.less' AND type='public'",
            ],
            capture_output=True,
            text=True,
            check=True,
        )
        n = int((chk.stdout or "0").strip() or "0")
        if n < 100:
            print(
                f"SANITY_FAIL: extra.less in DB is empty/short (len={n}); PHP save failed",
                file=sys.stderr,
            )
            return 1
    finally:
        try:
            os.unlink(tmp_path)
        except OSError:
            pass

    print("OK", len(raw.encode("utf-8")))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
