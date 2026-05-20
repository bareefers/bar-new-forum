# Team setup — bar-new-forum

## 1. GitHub access

Ask a **bareefers** org owner to:

1. Open https://github.com/orgs/bareefers/people (or repo **Settings → Collaborators**).
2. **Invite** your GitHub user.
3. Grant **Write** (push branches, open PRs) or **Maintain** as appropriate.

The repo is **public** — anyone can **clone** without an invite. **Write** access is required to push branches and merge (unless you only use forks + PRs from forks).

### Suggested org hygiene

- Protect **`main`**: require PR before merge (optional reviews).
- Do **not** store production passwords in GitHub Secrets unless you adopt CI deploy later.

## 2. Clone the repository

```bash
git clone https://github.com/bareefers/bar-new-forum.git
cd bar-new-forum
```

On Windows, use **WSL** for deploy scripts that call `ssh bareefers` (see below).

## 3. Production server access (operators / trusted devs)

Production XenForo runs on the **bareefers** server. Access is **not** stored in this repo.

Typical setup (maintained by your lead):

- SSH host alias: **`bareefers`** in `~/.ssh/config`
- Live forum: **`/var/www/bareefers.org/forum`**
- Git clone of this repo: **`/var/www/bareefers.org/bar-new-forum`**

Test SSH:

```bash
ssh bareefers 'hostname && php -v | head -1'
```

From Windows, prefer:

```bash
wsl ssh bareefers 'hostname'
```

## 4. Update the server clone after a merge

On the server (sudo if the clone is owned by root):

```bash
sudo bash /var/www/bareefers.org/bar-new-forum/ops/scripts/bar-forum-git-pull.sh
```

That runs `git pull --ff-only origin main` in `/var/www/bareefers.org/bar-new-forum`.

Deploying to **live** `forum/` is a separate step — see [DEPLOY.md](DEPLOY.md).

## 5. What you can do without server access

- Edit addons under `forum/src/addons/`
- Edit LESS source and docs under `ops/`
- Open PRs, review code, run layout probe locally against public URLs
- Improve scripts and documentation

## 6. Key documentation

| Doc | Topic |
|-----|--------|
| [../CONTRIBUTING.md](../CONTRIBUTING.md) | PR workflow |
| [DEPLOY.md](DEPLOY.md) | Getting changes to production |
| [../SECURITY.md](../SECURITY.md) | Secrets policy |
| [../ops/docs/BAR-THREAD-LIST-LAYOUT.md](../ops/docs/BAR-THREAD-LIST-LAYOUT.md) | Thread list CSS |
| [../ops/docs/BAREEFERS-STYLE16-BACKUP.md](../ops/docs/BAREEFERS-STYLE16-BACKUP.md) | Style snapshots before risky DB work |

## 7. XenForo version

**2.3.10** (`forum/src/XF.php` → `$versionId = 2031070`).

Installed addons under `forum/src/addons/` include **BAR**, **SV** (StandardLib, ExpiringUserUpgrades), **XFMG**, **XFRM**, **XFES**, **Tapatalk**, etc.
