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
| `scripts/xf-payment-health.sh` | PayPal REST / IPN sanity (patches + 24h log counts); exit non-zero if failing |
| `scripts/xf-patch-paypalrest-webhook-profile.php` | Re-apply PayPalRest profile fallback after `xf:upgrade` |
| `scripts/xf-patch-paypalrest-webhook-crc32.sh` | Re-apply unsigned crc32 webhook body hash after `xf:upgrade` |
| `scripts/bareefers-thread-layout-probe.js` | Headless thread-row layout check (optional Playwright) |
| `scripts/bareefers-mobile-audit.js` | Mobile audit — 12 URLs at 390px + 768px (optional Playwright) |
| `scripts/extra-less-aurora16-source.less` | Source for live `public:extra.less` (theme + mobile) |
| `cron/xf-payment-health.cron` | Install to `/etc/cron.d/` — runs health check twice daily |

**PayPal incident (May 2026):** [../docs/PAYPAL-PAYMENT-MAY2026.md](../docs/PAYPAL-PAYMENT-MAY2026.md).

**Mobile / layout (June 2026):** [../docs/MOBILE-COMPAT.md](../docs/MOBILE-COMPAT.md), [docs/BAR-THREAD-LIST-LAYOUT.md](docs/BAR-THREAD-LIST-LAYOUT.md).

## From Windows

Use **WSL** for scripts that `ssh bareefers` or `scp`:

```bash
wsl bash ops/scripts/xf-deploy-bareefers-extra-less.sh
```

## Docs

See `docs/` for runbooks. Team onboarding: [../docs/TEAM-SETUP.md](../docs/TEAM-SETUP.md).
