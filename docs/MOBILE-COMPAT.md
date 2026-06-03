# Mobile compatibility — bareefers.org forum

CSS lives in **`ops/scripts/extra-less-aurora16-source.less`** (sections **BAR mobile + tablet compatibility**, **What's new** layout, **thread list** flex). Deploy with:

```bash
cd bar-new-forum
wsl bash ops/scripts/xf-deploy-bareefers-extra-less.sh
```

Hard-refresh after deploy (Ctrl+Shift+R on desktop; clear cache or private tab on phone).

## Breakpoints

| XenForo token | Typical | Used for |
|---------------|---------|----------|
| `@xf-responsiveWide` | ~900px | Thread row stack, node list, posts, overflow clip (covers **768px tablets**) |
| `@xf-responsiveMedium` | ~650px | Phones, sponsor banners, header tweaks |
| `@xf-responsiveNarrow` | ~480px | Compact header / logo |

## What we fix (June 2026)

- **Thread lists** (home, What's new, categories): title column gets most of the row; hide views/reactions/latest avatar columns on tablet+phone; 2-line title clamp; reply count on second line.
- **What's new** (`/whats-new/`): right sidebar **always** below Latest posts (wide screens were the worst case — three columns side-by-side); side nav is off-canvas trigger only below 1180px.
- **Forum node list** (`/forums/`): `node-extra-row` ellipsized, stacks under forum name.
- **Thread view**: images/quotes/code blocks constrained to viewport width.
- **Search**: tab scroller scrolls inside its box.
- **Conversations**: larger inline-mod checkbox hit targets (iOS).
- **Global**: `overflow-x: clip` on body/main to prevent horizontal page scroll.

Related: [../ops/docs/BAR-THREAD-LIST-LAYOUT.md](../ops/docs/BAR-THREAD-LIST-LAYOUT.md) (desktop / wide layout).

## Automated audit

From repo root, with Playwright (`cd .pw-probe && npm i playwright && npx playwright install chromium`):

```bash
cd bar-new-forum
NODE_PATH="$PWD/../.pw-probe/node_modules" node ops/scripts/bareefers-mobile-audit.js
```

Audits 12 URLs at **390** and **768** width. Exit **0** = no issues flagged.

## Deploy checklist (operators)

1. Merge to `main`, then on server: `sudo bash /var/www/bareefers.org/bar-new-forum/ops/scripts/bar-forum-git-pull.sh`
2. `wsl bash ops/scripts/xf-deploy-bareefers-extra-less.sh` (from local clone or run restore on server using pulled `extra-less-aurora16-source.less`)
3. Smoke: forum home, [What's new](https://bareefers.org/forum/whats-new/), one thread — phone + 768px-wide browser
4. Optional: run `bareefers-mobile-audit.js`

## If users still report a page

Note **URL**, **device**, and **screenshot**. Add a scoped rule under the mobile section in `extra-less-aurora16-source.less` (prefer `body[data-template="…"]` when possible), deploy, re-test.
