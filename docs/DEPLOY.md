# Deploying changes to bareefers.org

Production XenForo path:

```text
/var/www/bareefers.org/forum
```

Git working copy:

```text
/var/www/bareefers.org/bar-new-forum
```

**`git pull` does not change the live site by itself.** You must run the appropriate deploy step after merging to `main`.

## 1. Sync the server git clone

```bash
ssh bareefers
sudo bash /var/www/bareefers.org/bar-new-forum/ops/scripts/bar-forum-git-pull.sh
```

## 2. Deploy by change type

### A. `extra.less` / public theme CSS

Edit source in repo:

```text
ops/scripts/extra-less-aurora16-source.less
```

From your workstation (WSL recommended on Windows):

```bash
cd bar-new-forum
wsl bash ops/scripts/xf-deploy-bareefers-extra-less.sh
```

This copies scripts + LESS to the server and updates the `public:extra.less` template in MySQL, then flushes CSS cache (Redis DB 7 on production).

Hard-refresh the forum after deploy.

### B. BAR XenForo addon (`forum/src/addons/BAR/`)

Copy addon files to live tree (example — adjust if your team uses a different rsync policy):

```bash
ssh bareefers 'sudo rsync -a /var/www/bareefers.org/bar-new-forum/forum/src/addons/BAR/ /var/www/bareefers.org/forum/src/addons/BAR/'
```

Then in XenForo **ACP → Add-ons → BAR** → rebuild/recompile as needed, or:

```bash
ssh bareefers 'cd /var/www/bareefers.org/forum && sudo -u www-data php cmd.php xf:addon-rebuild BAR'
```

### C. One-off PHP repair / maintenance scripts

Copy the script to the server and run from forum root with `www-data`, e.g.:

```bash
scp ops/scripts/xf-some-script.php bareefers:/tmp/
ssh bareefers 'sudo -u www-data php /tmp/xf-some-script.php /var/www/bareefers.org/forum'
```

Read each script’s header comment for exact usage.

### D. nginx

Snippets live in `ops/nginx/`. Operators merge into server nginx config under `/etc/nginx/`, `nginx -t`, `systemctl reload nginx`.

## 3. Smoke test

- https://bareefers.org/forum/
- https://bareefers.org/forum/account/upgrades (payments — coordinate with lead)
- ACP **Tools → Checks and tests** if you changed email or addons

## 4. Before risky database work

Run style snapshot (see `ops/docs/BAREEFERS-STYLE16-BACKUP.md`):

```bash
wsl bash ops/scripts/xf-deploy-bareefers-style16-backup.sh
```

## 5. Post–DB restore (operators)

See `ops/scripts/xf-post-db-restore-repair.php` header and workspace rule **bareefers.org** post-restore checklist (`xf:upgrade`, repair script, Redis, etc.).
