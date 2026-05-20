# Ops — bareefers.org forum

Scripts and docs for operating production XenForo. Run from a clone of **bar-new-forum** (paths below are relative to repo root).

## Quick reference

| Script | Purpose |
|--------|---------|
| `scripts/bar-forum-git-pull.sh` | On **server**: `git pull` this repo |
| `scripts/xf-deploy-bareefers-extra-less.sh` | Push `extra-less-aurora16-source.less` → live `extra.less` |
| `scripts/xf-deploy-bareefers-style16-backup.sh` | Snapshot style/templates before risky DB work |
| `scripts/xf-post-db-restore-repair.php` | Post–mysqldump repair (run on server) |
| `scripts/xf-replay-paypal-invalid-business-ipn.php` | Replay failed PayPal IPNs (operators) |
| `scripts/bareefers-thread-layout-probe.js` | Headless layout check (optional Playwright) |

## From Windows

Use **WSL** for scripts that `ssh bareefers` or `scp`:

```bash
wsl bash ops/scripts/xf-deploy-bareefers-extra-less.sh
```

## Docs

See `docs/` for runbooks. Team onboarding: [../docs/TEAM-SETUP.md](../docs/TEAM-SETUP.md).
