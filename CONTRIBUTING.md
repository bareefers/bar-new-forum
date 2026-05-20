# Contributing to bar-new-forum

## Before you start

1. Read [docs/TEAM-SETUP.md](docs/TEAM-SETUP.md) (access, clone, SSH).
2. Read [SECURITY.md](SECURITY.md) (what must never be committed).
3. Skim [docs/DEPLOY.md](docs/DEPLOY.md) so you know how changes reach production.

## Branching

- **`main`** is **protected**: changes must go through a **pull request** (at least **1 approval**, conversations resolved). No force-push.
- Work on **`feature/short-description`** or **`fix/issue-description`** branches, then open a PR into `main`.
- Org/repo **admins** can bypass in emergencies; everyone else must use PRs.

## Pull request checklist

- [ ] No `forum/src/config.php`, `.env`, passwords, or private keys
- [ ] No `forum/internal_data/` or `forum/data/` paths added
- [ ] If you change `ops/scripts/extra-less-aurora16-source.less`, also update `ops/scripts/bar-thread-list-row-patch.css.txt` when the probe CSS block changes (see `ops/docs/BAR-THREAD-LIST-LAYOUT.md`)
- [ ] Scripts remain runnable from `ops/scripts/` (use `HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"` pattern)
- [ ] Note in PR **how to deploy** (which script, which path, smoke test URL)

## Code areas

| Change type | Typical path | Deploy |
|-------------|--------------|--------|
| BAR addon | `forum/src/addons/BAR/` | ACP rebuild / upload files to live `src/addons/BAR/` |
| Public CSS (extra.less) | `ops/scripts/extra-less-aurora16-source.less` | `xf-deploy-bareefers-extra-less.sh` |
| Ops / backup / repair | `ops/scripts/xf-*.php`, `xf-*.sh` | Copy to server or run from server clone after `git pull` |
| nginx | `ops/nginx/` | Operator applies on server, reload nginx |
| Docs only | `ops/docs/`, `docs/` | Merge only |

## Testing

- **Local:** clone repo; edit LESS/addons; optional Playwright probe (`ops/scripts/bareefers-thread-layout-probe.js` + `.pw-probe/` — see `ops/docs/BAR-THREAD-LIST-LAYOUT.md`).
- **Staging:** there is no separate staging XenForo in this repo; production changes require operator deploy and smoke test on https://bareefers.org/forum/.

## Questions

Use GitHub **Issues** on this repo for bugs/tasks. Tag areas: `addon`, `theme`, `ops`, `payments`, `docs`.
