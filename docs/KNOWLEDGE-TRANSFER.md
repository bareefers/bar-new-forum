# Knowledge transfer — Bay Area Reefers forum (bareefers.org)

**Purpose:** Single onboarding document for operators and developers taking over **production XenForo**, **theme/CSS**, **payments**, and the **bar-new-forum** git workflow.  
**Last updated:** June 2026 (post cutover, PayPal fixes, mobile/layout pass).  
**Canonical repo:** https://github.com/bareefers/bar-new-forum

---

## 1. Executive summary

| What | Where |
|------|--------|
| **Public site** | https://bareefers.org/forum/ |
| **Platform** | XenForo **2.3.10**, Aurora-derived theme (**style id 16**) |
| **Live PHP tree** | `/var/www/bareefers.org/forum` (not auto-updated from git) |
| **Team git repo** | `/var/www/bareefers.org/bar-new-forum` → GitHub `bareefers/bar-new-forum` |
| **Database** | MySQL **`baraforo`** |
| **SSH** | Host alias **`bareefers`** (keys/config not in git) |

**Critical idea:** Live forum state lives in **MySQL** (templates, styles, posts), **Redis** (CSS cache, sessions), and **`internal_data/`** (compiled templates, page cache). The git repo holds **code**, **ops scripts**, and **LESS source** for `extra.less` — deploying theme changes is an **explicit** step.

A second workspace in the org, **`barcode`** (Barcode 2.0 Next.js client + historical `xenforo/scripts/`), mirrors many ops scripts. **bar-new-forum** is the intended **long-term** home for forum ops and handoff.

---

## 2. Architecture diagram

```text
                    ┌─────────────────────────────────────┐
                    │  Developers (GitHub PRs → main)     │
                    └─────────────────┬───────────────────┘
                                      │ git pull (only)
                                      ▼
┌──────────────┐    deploy scripts     ┌──────────────────────────────┐
│ bar-new-forum│ ────────────────────► │ /var/www/bareefers.org/forum │
│  (git clone) │   extra.less, addons, │  XenForo + nginx + PHP       │
│              │   patches, php cmds   │  src/config.php (secrets)    │
└──────────────┘                       └───────────┬──────────────────┘
                                                   │
                     ┌─────────────────────────────┼─────────────────────────────┐
                     ▼                             ▼                             ▼
              MySQL baraforo                  Redis (e.g. DB 7 = CSS)     internal_data/
              xf_* tables                     guest/session caches         page_cache, code_cache
```

**What is NOT in git (by design):**

- `forum/src/config.php` — DB credentials, Redis, `enableLivePayments`, salts  
- `forum/data/` — uploads, attachments  
- `forum/internal_data/` — rebuildable cache  
- Production TLS keys, PayPal secrets, SSH private keys  

See [SECURITY.md](../SECURITY.md).

---

## 3. Access and environment

### 3.1 SSH and paths

| Item | Value |
|------|--------|
| SSH config host | `bareefers` |
| Forum root | `/var/www/bareefers.org/forum` |
| Git clone | `/var/www/bareefers.org/bar-new-forum` |
| Web user | typically `www-data` for `php cmd.php` |

**Windows operators:** use **WSL** for `ssh bareefers` and deploy scripts (`~/.ssh/config` in WSL).

```bash
ssh bareefers 'hostname && php -v | head -1'
```

### 3.2 GitHub

- Org/repo: **bareefers/bar-new-forum** (public read; write via invite)  
- **`main`** may be branch-protected (PR required)  
- **`git pull` on server does not deploy to live** — see [DEPLOY.md](DEPLOY.md)

### 3.3 XenForo ACP

- URL: `https://bareefers.org/forum/admin.php`  
- Used for: payment profiles, addons, style properties, user upgrades, **Tools → Checks and tests** (email), error log viewer  

---

## 4. Repository layout (bar-new-forum)

| Path | Purpose |
|------|---------|
| `forum/` | XenForo 2.3.10 tree (core + `src/addons/BAR/`, etc.) — reference copy, not live path |
| `ops/scripts/` | Deploy, backup, repair, LESS deploy, PayPal patches, probes |
| `ops/cron/` | Example cron snippets (e.g. payment health) |
| `ops/docs/` | Focused runbooks (thread layout) |
| `docs/` | Team docs, this file, PayPal, mobile, deploy |
| `config.php.example` | Template only |

**Start here for new team members:** [TEAM-SETUP.md](TEAM-SETUP.md) → [CONTRIBUTING.md](../CONTRIBUTING.md) → [DEPLOY.md](DEPLOY.md).

---

## 5. Day-to-day operations

### 5.1 Routine workflow

1. Clone `bar-new-forum`, branch, PR to `main`.  
2. After merge: on server, `sudo bash .../bar-forum-git-pull.sh`.  
3. Run the **deploy step** for your change type (CSS, addon, PHP script, nginx).  
4. Smoke-test production URLs.  
5. Hard-refresh browser (or CDN/cache) after CSS changes.

### 5.2 Sync server git only

```bash
sudo bash /var/www/bareefers.org/bar-new-forum/ops/scripts/bar-forum-git-pull.sh
```

### 5.3 Health checks (recommended)

| Check | Command / location |
|-------|------------------|
| XenForo jobs | `sudo bash .../ops/scripts/xf-job-health.sh` (also in barcode `xenforo/scripts/`) |
| PayPal / webhooks | `sudo bash .../ops/scripts/xf-payment-health.sh` — log: `/var/log/xf-payment-health.log` |
| Error log (DB) | `SELECT * FROM xf_error_log ORDER BY exception_date DESC LIMIT 10` |

### 5.4 OS cron (XenForo jobs)

Low-traffic forums need **system cron** so `xf:run-jobs` runs reliably:

- `/etc/cron.d/xf-jobs` — every 5 min, `www-data`, `php cmd.php xf:run-jobs --max-execution-time=55`  
- `/etc/cron.d/xf-jobs-fast` — every 1 min, `--max-execution-time=15`  

Payment health (if installed):

- `/etc/cron.d/xf-payment-health` — 06:00 & 18:00 UTC → `xf-payment-health.sh`

---

## 6. Theme and CSS (`extra.less`)

### 6.1 Where theme lives

| Layer | Location |
|-------|----------|
| **Source of truth (git)** | `ops/scripts/extra-less-aurora16-source.less` |
| **Live (DB)** | MySQL `xf_template` — `title='extra.less'`, `type='public'`, **`style_id=16`** |
| **Compiled CSS** | Served via `css.php`; cached in **Redis DB 7** (production convention) |

**Rules:**

- Edit **LESS** with XenForo variables (`@xf-*`). Do **not** paste compiled browser CSS (`hsla(var(--xf-…))`) into LESS — the compiler will reject it.  
- After deploy, **hard-refresh** (Ctrl+Shift+R). Clear guest page cache if needed: `internal_data/page_cache`.

### 6.2 Deploy CSS

```bash
cd bar-new-forum
wsl bash ops/scripts/xf-deploy-bareefers-extra-less.sh
```

This copies helper scripts + LESS to the server, updates the template via `xf-restore-extra-less-aurora16.py`, flushes CSS cache.

### 6.3 Major CSS workstreams (2026)

| Topic | Doc |
|-------|-----|
| Thread list + desktop flex | [../ops/docs/BAR-THREAD-LIST-LAYOUT.md](../ops/docs/BAR-THREAD-LIST-LAYOUT.md) |
| What's new 3-column crush | Same file — sidebar always below list on `data-template="whats_new"` |
| Mobile / tablet (768px gap) | [MOBILE-COMPAT.md](MOBILE-COMPAT.md) |
| Style 16 backup before DB import | [../ops/docs/BAREEFERS-STYLE16-BACKUP.md](../ops/docs/BAREEFERS-STYLE16-BACKUP.md) |

### 6.4 Automated layout tests (optional, local)

- **Thread rows:** `bareefers-thread-layout-probe.js` (+ `bar-thread-list-row-patch.css.txt` in sync with LESS)  
- **Mobile:** `bareefers-mobile-audit.js` — 12 URLs at 390px and 768px  

Requires Playwright in a local `.pw-probe` directory.

---

## 7. Database restore and migration

**DNS cutover alone does not fix schema drift.** After importing an old mysqldump into a server whose **files** are newer, run upgrades and repair in order:

| Step | Action |
|------|--------|
| 0 (before import) | Style 16 snapshot: `xf-deploy-bareefers-style16-backup.sh` — copy off-server |
| 1 | `cd /var/www/bareefers.org/forum && php cmd.php xf:upgrade` |
| 2 | `php .../xf-post-db-restore-repair.php /var/www/bareefers.org/forum` (+ optional LESS path argv[2]) — fixes XFMG/XFRM user columns, email style, `extra.less` baseline |
| 3 | Redis — flush **guest/CSS** DBs only (e.g. `redis-cli -n 7`) unless you intend to wipe sessions |
| 4 | Clear `internal_data/page_cache` if guest HTML is stale |
| 5 | Smoke: outbound email (ACP), test registration, PayPal if applicable |
| 6 (if needed) | Restore visual: `xf-restore-bar-style16-visual.sh` with saved `visual.sql.gz` |

**Style snapshot locations on server:**

- `/var/tmp/bar-style16-snapshots/`  
- `/home/xf-emergency-rollback/bar-style16-primary/latest`  

**PayPal after restore:** re-apply `PayPalRest.php` patches (§8), run `xf-payment-health.sh`.

---

## 8. Payments (PayPal)

Supporting memberships use **legacy PayPal** (profile **#1**). PayPal **REST webhooks** use profile **#2** (`paypalrest`).

**May 2026 incidents (resolved on production):**

1. **Typo** `1paypal@bareefers.org` on profile #1 → IPN `Invalid business or receiver_email` — fixed to `paypal@bareefers.org`; replay script available.  
2. **REST webhooks** used profile #1 (no `webhook_id`) → verification failures — fixed via patches to `src/XF/Payment/PayPalRest.php`.

**Full runbook:** [PAYPAL-PAYMENT-MAY2026.md](PAYPAL-PAYMENT-MAY2026.md)

**After every `xf:upgrade`**, re-apply (idempotent):

```bash
sudo php .../xf-patch-paypalrest-webhook-profile.php /var/www/bareefers.org/forum
sudo bash .../xf-patch-paypalrest-webhook-crc32.sh /var/www/bareefers.org/forum
sudo bash .../xf-payment-health.sh
```

**PayPal Developer (live):** webhook URL must be  
`https://bareefers.org/forum/payment_callback.php?_xfProvider=paypalrest`  
and match `$config['enableLivePayments']` in `src/config.php`.

---

## 9. BAR XenForo addon and customizations

| Item | Notes |
|------|--------|
| **BAR addon** | `forum/src/addons/BAR/` — e.g. SponsorBanners widget, thread stat CSS classes (`barThreadStatView`, etc.) |
| **Deploy addon** | rsync to live `src/addons/BAR/`, then ACP rebuild or `php cmd.php xf:addon-rebuild BAR` |
| **Sponsor banners** | Widget + `barSponsorBanners` in templates; mobile rules in `extra.less` |
| **Other addons** | XFMG, XFRM, XFES, SV ExpiringUserUpgrades, Tapatalk, etc. — verify after DB import |

---

## 10. nginx, TLS, and infrastructure

- Production nginx/TLS is **on the server**, not fully driven from git.  
- Snippets may live under `ops/nginx/` in repo — merge manually, `nginx -t`, reload.  
- Historical cutover/migration notes exist in the parent **`barcode`** repo under `xenforo/docs/` (e.g. LINODE-DNS, OPTION-B migration) if you still have that checkout.

**Do not commit:** private keys, `config.php`, full database dumps, user exports with PII unless policy allows.

---

## 11. Related project: Barcode 2.0 (`barcode` repo)

Separate from XenForo:

- **Next.js client** under `client/` — equipment, tanks, market, etc. (may deploy to Vercel).  
- **Historical XenForo ops** under `xenforo/scripts/` — many `xf-*.sh` / `xf-*.php` scripts; often duplicated or ahead of `bar-new-forum/ops/scripts/`.  

When in doubt, prefer **bar-new-forum** for forum ops going forward; port scripts from `barcode` as needed.

---

## 12. Document index (deep dives)

| Document | Audience | Topic |
|----------|----------|--------|
| [TEAM-SETUP.md](TEAM-SETUP.md) | New devs | GitHub, SSH, clone |
| [DEPLOY.md](DEPLOY.md) | Operators | Deploy paths by change type |
| [CONTRIBUTING.md](../CONTRIBUTING.md) | Devs | PR workflow |
| [SECURITY.md](../SECURITY.md) | Everyone | Secrets, what not to commit |
| [MOBILE-COMPAT.md](MOBILE-COMPAT.md) | Operators / front-end | Mobile CSS, audit |
| [PAYPAL-PAYMENT-MAY2026.md](PAYPAL-PAYMENT-MAY2026.md) | Operators | PayPal IPN + REST |
| [../ops/docs/BAR-THREAD-LIST-LAYOUT.md](../ops/docs/BAR-THREAD-LIST-LAYOUT.md) | Front-end | Thread list / What's new layout |
| [../ops/docs/BAREEFERS-STYLE16-BACKUP.md](../ops/docs/BAREEFERS-STYLE16-BACKUP.md) | Operators | Style 16 snapshot / restore |
| [../ops/README.md](../ops/README.md) | Operators | Script quick reference |

**Additional history in the parent `barcode` repo (if available):**

- `xenforo/docs/SPONSOR-BANNERS-ADDON.md`  
- Migration / cutover runbooks under `xenforo/docs/`

---

## 13. Key ops scripts (cheat sheet)

| Script | When to use |
|--------|-------------|
| `bar-forum-git-pull.sh` | After merge to `main` — update server clone |
| `xf-deploy-bareefers-extra-less.sh` | Ship `extra.less` changes |
| `xf-deploy-bareefers-style16-backup.sh` | Before risky DB import |
| `xf-restore-bar-style16-visual.sh` | Restore style 16 from `visual.sql.gz` |
| `xf-post-db-restore-repair.php` | After full mysqldump restore |
| `xf-payment-health.sh` | PayPal sanity (cron or manual) |
| `xf-patch-paypalrest-webhook-profile.php` | After `xf:upgrade` |
| `xf-patch-paypalrest-webhook-crc32.sh` | After `xf:upgrade` |
| `xf-replay-paypal-invalid-business-ipn.php` | After fixing PayPal primary email |
| `xf-job-health.sh` | Pending jobs / cron diagnosis |
| `xf-send-test-outbound-email.php` | CLI email smoke test |
| `bareefers-mobile-audit.js` | Regression pass on mobile layouts |
| `bareefers-thread-layout-probe.js` | Thread row layout regression |

Full table: [../ops/README.md](../ops/README.md).

---

## 14. Handoff checklist (for incoming owner)

Use this when transferring responsibility to a new lead or vendor.

### Access

- [ ] GitHub org **bareefers** — maintain on `bar-new-forum`  
- [ ] SSH `bareefers` — key rotation documented  
- [ ] XenForo ACP admin account  
- [ ] PayPal Business + Developer dashboard (live app, webhooks)  
- [ ] DNS/registrar (bareefers.org) — if applicable  
- [ ] Hosting provider / Linode (or current host) billing and console  

### Verify production

- [ ] https://bareefers.org/forum/ loads, login works  
- [ ] https://bareefers.org/forum/whats-new/ — thread titles readable (desktop + phone)  
- [ ] Supporting member checkout (test or documented process)  
- [ ] `xf-payment-health.sh` exits 0  
- [ ] `xf-job-health.sh` — pending jobs not growing unbounded  
- [ ] Outbound email from ACP test  

### Verify repo and server clone

- [ ] Server: `git -C /var/www/bareefers.org/bar-new-forum pull`  
- [ ] Local clone matches `main`  
- [ ] This document and linked runbooks reviewed  

### Secrets inventory (store in password manager, not git)

- [ ] `src/config.php` contents / backup procedure  
- [ ] MySQL root/app credentials  
- [ ] Redis  
- [ ] PayPal API (profile #2) + legacy IPN email  
- [ ] SMTP for XenForo email  
- [ ] SSH keys  

### Known gotchas (save the next person time)

1. **`git pull` ≠ deploy** for the live forum.  
2. **Theme is in MySQL**, not only files under `forum/`.  
3. **768px tablets** use `@xf-responsiveWide` (~900px) rules — not only “phone” breakpoints.  
4. **What's new** wide layout: right sidebar must stay **below** the post list, not beside it.  
5. **`xf:upgrade` overwrites** `PayPalRest.php` patches — re-apply after core upgrades.  
6. **Profile #1** = checkout IPN; **profile #2** = REST webhooks — do not confuse.  
7. **Style 16 backup** before any full DB swap — copy snapshot off-server.  

---

## 15. Support and escalation

| Issue type | First look |
|------------|------------|
| Layout / CSS | `extra-less-aurora16-source.less`, MOBILE-COMPAT, BAR-THREAD-LIST-LAYOUT |
| Payment failed | PAYPAL-PAYMENT-MAY2026, `xf_payment_provider_log`, `xf-payment-health.sh` |
| 500 after import | `xf-post-db-restore-repair.php`, `xf:upgrade`, `xf_error_log` |
| Email broken | ACP email settings, `xf-send-test-outbound-email.php` |
| Slow/stuck jobs | `xf-job-health.sh`, `/etc/cron.d/xf-jobs*` |

For user-specific upgrade problems: check `xf_purchase_request`, `xf_user_upgrade_active`, `xf_payment_provider_log`; consider IPN replay script after confirming PayPal received payment.

---

*This document is the umbrella for BAR forum operations. Keep it updated when you add runbooks or change production conventions.*
