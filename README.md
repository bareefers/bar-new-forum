# bareefers.org forum — [bar-new-forum](https://github.com/bareefers/bar-new-forum)

Version-controlled **XenForo 2.3.10** application tree and **ops** for [bareefers.org](https://bareefers.org/forum/).

| | |
|--|--|
| **Production forum (live)** | `/var/www/bareefers.org/forum` on host `bareefers` |
| **This git clone on server** | `/var/www/bareefers.org/bar-new-forum` |
| **GitHub** | https://github.com/bareefers/bar-new-forum |

New developers: start with **[docs/KNOWLEDGE-TRANSFER.md](docs/KNOWLEDGE-TRANSFER.md)** (full handoff), then **[docs/TEAM-SETUP.md](docs/TEAM-SETUP.md)** and **[CONTRIBUTING.md](CONTRIBUTING.md)**.

## Repository layout

| Path | Purpose |
|------|---------|
| `forum/` | XenForo PHP app: `src/` (core + addons), `js/`, `styles/`, `library/`, root entrypoints |
| `ops/scripts/` | Deploy, backup, repair, LESS/CSS, cutover helpers (`xf-*`, `bar-*`) |
| `ops/docs/` | Operator runbooks (layout, migration, style backup, etc.) |
| `ops/nginx/` | nginx snippets for bareefers.org |
| `config.php.example` | Template only — **never** commit real `forum/src/config.php` |

## Not in git (by design)

- `forum/internal_data/` — cache, compiled templates (rebuilt on server)
- `forum/data/` — user uploads / attachments
- `forum/src/config.php` — DB, Redis, payment secrets
- `*.zip`, dumps, `error_log`, credential files

**Theme/CSS and templates** also live in **MySQL** (`xf_template`, `extra.less`, etc.). File changes here are not enough for full theme work without deploy scripts or ACP.

## Team workflow (summary)

1. Get **GitHub access** to the `bareefers` org / this repo (see [docs/TEAM-SETUP.md](docs/TEAM-SETUP.md)).
2. Clone locally, create a branch, open a **pull request** to `main`.
3. After merge, an operator updates the server git clone and deploys to live (see [docs/DEPLOY.md](docs/DEPLOY.md)).

```bash
git clone https://github.com/bareefers/bar-new-forum.git
cd bar-new-forum
```

## Common tasks

| Task | Where |
|------|--------|
| Edit forum CSS (extra.less source) | `ops/scripts/extra-less-aurora16-source.less` → deploy via `ops/scripts/xf-deploy-bareefers-extra-less.sh` |
| BAR custom addon | `forum/src/addons/BAR/` |
| Pull latest on server | `sudo bash /var/www/bareefers.org/bar-new-forum/ops/scripts/bar-forum-git-pull.sh` |
| Thread list layout notes | `ops/docs/BAR-THREAD-LIST-LAYOUT.md` |
| Mobile / tablet compatibility | [docs/MOBILE-COMPAT.md](docs/MOBILE-COMPAT.md) |
| PayPal / webhooks (May 2026 incident + monitoring) | [docs/PAYPAL-PAYMENT-MAY2026.md](docs/PAYPAL-PAYMENT-MAY2026.md) |

## Live vs git (important)

Production still runs from **`/var/www/bareefers.org/forum`**. This repo is the **source of truth going forward**; deploying to live is **explicit** (scripts in `ops/scripts/`, not automatic on `git pull`). See [docs/DEPLOY.md](docs/DEPLOY.md).

## SSH / server

- SSH config host: **`bareefers`** (operators maintain `~/.ssh/config`; keys are not in this repo).
- Forum root on server: **`/var/www/bareefers.org/forum`**
- Git working copy: **`/var/www/bareefers.org/bar-new-forum`**

## Security

Read **[SECURITY.md](SECURITY.md)** before your first commit. No production secrets in git.
