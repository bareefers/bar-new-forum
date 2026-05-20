# bareefers.org XenForo — bar-new-forum

This file is a template copied into the GitHub repo root during initial publish.

## What this repo is

Snapshot of **production XenForo** at `/var/www/bareefers.org/forum` (bareefers server), plus **ops** (scripts, nginx, docs) from the barcode workspace.

**Live XenForo version:** 2.3.10 (`src/XF.php` `$versionId = 2031070`).

## Layout

| Path | Purpose |
|------|---------|
| `forum/` | Application tree synced from server (PHP, `src/`, `js/`, `styles/`, `library/`, addons). |
| `ops/scripts/` | Operator scripts (`xf-*`, LESS deploy, backups, PayPal replay, etc.). |
| `ops/docs/` | Runbooks and reference docs. |
| `ops/nginx/` | nginx snippets for bareefers.org. |
| `config.php.example` | Starter config — copy to `forum/src/config.php` on the server (never commit real secrets). |

## Intentionally NOT in git

| Excluded | Why |
|----------|-----|
| `forum/internal_data/` | Cache, compiled templates, code cache — regenerated on server. |
| `forum/data/` | User uploads and attachments (~GB). |
| `forum/src/config.php` | DB passwords, Redis, API keys. |
| `*.zip` | Install archives and exports. |
| `error_log`, `*.bak` | Runtime / backup noise. |

Database state (templates, phrases, `extra.less`, options) lives in **MySQL** — back up with mysqldump; use `ops/scripts/xf-backup-bar-style16-visual.sh` for style snapshots.

## Deploy workflow (going forward)

1. Change code or ops in this repo → PR → merge to `main`.
2. On server: `git pull` in `/var/www/bareefers.org/bar-new-forum` (or rsync `forum/` + run scripts from `ops/scripts/`).
3. For theme/CSS: edit `ops/scripts/extra-less-aurora16-source.less`, run `ops/scripts/xf-deploy-bareefers-extra-less.sh` (see `ops/docs/BAR-THREAD-LIST-LAYOUT.md`).
4. For addons: deploy `forum/src/addons/BAR/` (and other addons as needed), rebuild in ACP.

## Server clone path (suggested)

```bash
sudo mkdir -p /var/www/bareefers.org/bar-new-forum
sudo git clone https://github.com/bareefers/bar-new-forum.git /var/www/bareefers.org/bar-new-forum
# Symlink or rsync forum/ → /var/www/bareefers.org/forum as you adopt git-based deploy
```

## Initial publish

Populated from bareefers via `rsync` + barcode `xenforo/` ops (May 2026).
