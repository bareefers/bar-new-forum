# BAR forum thread list layout (`extra.less`)

## What was wrong

On forum home and **What’s new → Posts** (`--withSidebar` / `--withSideNav`), XenForo’s default row layout could shrink **`.structItem-cell--main`** to ~150px around ~1200px viewport while **`.structItem-cell--meta`** stayed wide. **`.structItem-cell--latest`** with `flex-grow` also competed with the title column.

## What we changed (source)

- **`xenforo/scripts/extra-less-aurora16-source.less`**: mixin **`.barThreadListRowFlex()`** (scoped inside `@media (max-width: 1600px)` on those main layouts only).
- **Title**: stay on **one horizontal line** when resizing down — **`.structItem-title`** (and inner `a`) use `white-space: nowrap` + `text-overflow: ellipsis` so long titles ellipsize instead of stacking into a tall multi-line block. The probe flags failure when the title link’s `scrollHeight` exceeds `clientHeight` (wrapped text), not by raw box height (prefixes can make a one-line box ~45px tall).
- **Meta**: shrinkable caps (`flex: 0 1 …`, `max-width` on cells and ellipsis on `.pairs`).
- **Latest**: **`flex: 0 1 11rem`** (no flex-grow), **`margin-left: auto`** so last-activity stays **trailing / far right**, **`text-align: right`** (not centered when the row wraps).
- **Very narrow (`max-width: 640px`)**: hide **views** and **reactions** meta cells (BAR classes `barThreadStatView`, `barThreadStatRx`); keep replies. Longer labels still use existing **R / V / ★** rules tied to `@xf-responsiveMedium` in the same LESS file.
- **Sidebar**: unchanged — still hidden below **1180px** for `--withSidebar`.

## What's new (`/whats-new/`, `data-template="whats_new"`)

This page uses **three columns**: left **side nav**, center **Latest posts** (`structItem--thread`), right **sidebar** (login, featured, members online, etc.). Around 1200px that squeezed the post list to ~250px titles.

**Fix (in `extra-less-aurora16-source.less`):**

- **Always** on What's new: **right sidebar** is a **full-width row below** Latest posts (not beside them). Wide viewports were the worst case (three columns side-by-side → ~240px titles).
- Hide **views** and **reactions** meta columns on What's new only (keep replies).
- **Sidebar `contentRow`** widgets (Featured content): flex + `min-width: 0` so titles do not collapse vertically.
- Below **1180px**: existing rule still **hides** the right sidebar; side nav + posts use the full width.

## Deploy

Edit **`ops/scripts/extra-less-aurora16-source.less`**, then:

```bash
wsl bash ops/scripts/xf-deploy-bareefers-extra-less.sh
```

Hard-refresh after deploy. Mobile rules are in [../../docs/MOBILE-COMPAT.md](../../docs/MOBILE-COMPAT.md).

## Automated check (no deploy required)

**`xenforo/scripts/bareefers-thread-layout-probe.js`** loads live bareefers HTML in headless Chromium.

- **Default** injects **`xenforo/scripts/bar-thread-list-row-patch.css.txt`**, which must stay in sync with **`.barThreadListRowFlex()`** in `extra-less-aurora16-source.less`. Exit **0** = patch passes width checks and **thread titles are not multi-line wrapped** (`scrollHeight` vs `clientHeight` on the title link) across the viewport list below.
- **`--live-only`**: production CSS only (expect failure at ~1200px until deploy).

From a folder with Playwright installed (e.g. local `.pw-probe` with `npm i playwright` + `npx playwright install chromium`):

```bash
cd .pw-probe
NODE_PATH="$PWD/node_modules" node ../xenforo/scripts/bareefers-thread-layout-probe.js
NODE_PATH="$PWD/node_modules" node ../xenforo/scripts/bareefers-thread-layout-probe.js --live-only
```

## Maintenance

Whenever you edit **`.barThreadListRowFlex()`** or the **640px** hide block, update **`bar-thread-list-row-patch.css.txt`** the same way so the probe still reflects what will ship in `extra.less`.
