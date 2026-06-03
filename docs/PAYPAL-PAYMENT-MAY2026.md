# PayPal payments — May 2026 incident and ongoing ops

Operator runbook for payment issues that appeared after the **bareefers.org** forum cutover (~**2026-05-18**). Supporting memberships use **legacy PayPal** (profile #1); PayPal also sends **REST webhooks** to the same site.

## Symptoms (May 18 → early June)

| What users saw | What logged |
|----------------|-------------|
| PayPal checkout errors; “Venmo has a problem” style messages | Often **legacy IPN**, not Venmo |
| ACP / `xf_error_log` noise | `Undefined array key "webhook_id"` in `PayPalRest.php` |
| `xf_payment_provider_log` | Hundreds of **“Webhook could not be verified”** |
| Supporting upgrade not applied after payment | Failed IPN or webhook path |

## Root causes (two separate bugs)

### 1. Legacy PayPal IPN — wrong receiver email

- **Profile #1** (`provider_id`: `paypal`, title “PayPal”) had typo **`1paypal@bareefers.org`** instead of **`paypal@bareefers.org`**.
- PayPal IPN returned **`Invalid business or receiver_email.`**
- User upgrades that depend on IPN never completed.

**Fix:** Update `xf_payment_profile` id **1** `options.primary_account` to `paypal@bareefers.org` (ACP or SQL). Replay missed IPNs with `ops/scripts/xf-replay-paypal-invalid-business-ipn.php`.

### 2. PayPal REST webhooks — wrong payment profile

- REST callbacks hit `payment_callback.php?_xfProvider=paypalrest`.
- XenForo `setupCallback()` followed the **purchase request** → profile **#1**, which has **no `webhook_id`**.
- Result: PHP warnings, failed signature verification (~780 log rows May 18 – Jun 2).

**Fixes on `src/XF/Payment/PayPalRest.php`:**

1. **Profile fallback** — if active profile lacks `webhook_id`, use the **paypalrest** profile (#2). Script: `ops/scripts/xf-patch-paypalrest-webhook-profile.php`
2. **Safe read** — `$paymentProfile->options['webhook_id'] ?? null`
3. **Unsigned crc32** — PayPal sends unsigned 32-bit decimal; PHP `crc32()` can be negative on 64-bit. Script: `ops/scripts/xf-patch-paypalrest-webhook-crc32.sh`

Profile **#2** (“PayPal Webhook”, `paypalrest`) holds `client_id`, `secret_key`, and `webhook_id` (set in ACP; not documented here).

## Production layout

| Item | Value |
|------|--------|
| Live forum root | `/var/www/bareefers.org/forum` |
| Git clone | `/var/www/bareefers.org/bar-new-forum` |
| MySQL DB | `baraforo` |
| Legacy checkout profile | `payment_profile_id` **1** |
| REST / webhook profile | `payment_profile_id` **2** |
| Webhook URL (PayPal Developer, live app) | `https://bareefers.org/forum/payment_callback.php?_xfProvider=paypalrest` |
| Live payments | `$config['enableLivePayments'] = true` in `src/config.php` |

## After every `php cmd.php xf:upgrade`

Core may overwrite `PayPalRest.php`. Re-apply patches, then health-check:

```bash
cd /var/www/bareefers.org/forum
sudo php /var/www/bareefers.org/bar-new-forum/ops/scripts/xf-patch-paypalrest-webhook-profile.php /var/www/bareefers.org/forum
sudo bash /var/www/bareefers.org/bar-new-forum/ops/scripts/xf-patch-paypalrest-webhook-crc32.sh /var/www/bareefers.org/forum
sudo bash /var/www/bareefers.org/bar-new-forum/ops/scripts/xf-payment-health.sh
```

All three patch scripts are **idempotent** (safe to re-run).

## Monitoring (installed on server)

| Piece | Path |
|-------|------|
| Health script | `ops/scripts/xf-payment-health.sh` |
| Cron (06:00 & 18:00 UTC) | `/etc/cron.d/xf-payment-health` |
| Log | `/var/log/xf-payment-health.log` |

**Success criteria:** script exits **0**; `webhook_verify_fail_last_24h=0`; `invalid_business_ipn_last_24h=0`; all three patches reported **ok**.

From Windows:

```bash
wsl bash -lc "ssh bareefers 'sudo bash /var/www/bareefers.org/bar-new-forum/ops/scripts/xf-payment-health.sh'"
```

Tail scheduled runs:

```bash
ssh bareefers 'sudo tail -80 /var/log/xf-payment-health.log'
```

Install or refresh cron after `git pull`:

```bash
sudo cp /var/www/bareefers.org/bar-new-forum/ops/cron/xf-payment-health.cron /etc/cron.d/xf-payment-health
sudo chmod 644 /etc/cron.d/xf-payment-health
```

## Replay failed legacy IPNs

After fixing `primary_account`, dry-run then execute:

```bash
sudo -u www-data php /var/www/bareefers.org/bar-new-forum/ops/scripts/xf-replay-paypal-invalid-business-ipn.php \
  /var/www/bareefers.org/forum

sudo -u www-data php /var/www/bareefers.org/bar-new-forum/ops/scripts/xf-replay-paypal-invalid-business-ipn.php \
  /var/www/bareefers.org/forum --execute --days=30
```

Only replays rows with `log_message = 'Invalid business or receiver_email.'` and a stored transaction id.

## Manual checks (MySQL)

```sql
-- Last 24h webhook verification failures (expect 0 after fix)
SELECT COUNT(*) FROM xf_payment_provider_log
WHERE log_message LIKE '%could not be verified%'
  AND log_date > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 1 DAY));

-- Invalid business IPN (expect 0 after email fix)
SELECT COUNT(*) FROM xf_payment_provider_log
WHERE log_message = 'Invalid business or receiver_email.'
  AND log_date > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 7 DAY));

-- Recent PayPal-related PHP errors
SELECT error_id, FROM_UNIXTIME(exception_date), LEFT(message, 100)
FROM xf_error_log
WHERE message LIKE '%webhook_id%' OR filename LIKE '%PayPalRest%'
ORDER BY exception_date DESC LIMIT 10;
```

`options` is stored as binary; use `CAST(options AS CHAR CHARACTER SET utf8mb4)` for `JSON_EXTRACT` in ad-hoc queries.

## If problems return

1. Run `xf-payment-health.sh` and read `/var/log/xf-payment-health.log`.
2. Confirm patches on `PayPalRest.php` (see health section 1).
3. ACP → **Setup → Payment profiles** → **PayPal Webhook**: webhook id present; try toggling **Enable webhook verification** off/on only if verification failures are **new** in the last 24h.
4. PayPal Developer → live app → Webhooks: URL matches production HTTPS callback above.
5. Confirm profile #1 `primary_account` is exactly `paypal@bareefers.org` (no leading `1`).
6. For users charged but not upgraded: check `xf_payment_provider_log`, `xf_purchase_request`, `xf_user_upgrade_active`; replay IPN or grant upgrade in ACP.

## Related scripts in this repo

| Script | Purpose |
|--------|---------|
| `ops/scripts/xf-payment-health.sh` | Automated sanity; non-zero exit if failing |
| `ops/scripts/xf-patch-paypalrest-webhook-profile.php` | Profile fallback + `?? null` |
| `ops/scripts/xf-patch-paypalrest-webhook-crc32.sh` | Unsigned crc32 body hash |
| `ops/scripts/xf-replay-paypal-invalid-business-ipn.php` | Replay IPNs after email fix |
| `ops/cron/xf-payment-health.cron` | Twice-daily cron snippet |

Deploy overview: [DEPLOY.md](DEPLOY.md). Ops index: [../ops/README.md](../ops/README.md).

## Timeline (reference)

| Date | Event |
|------|--------|
| ~2026-05-18 | Cutover; payment errors begin |
| 2026-05-18 – 06-02 | ~780 REST webhook verification failures; `webhook_id` PHP warnings |
| Fix window | `primary_account` corrected; `PayPalRest.php` patches applied on server |
| 2026-06-03 | Health check + cron installed; **0** failures in rolling 24h |

The **7-day** counter in the health script can still show historical failures until they age out; only **last 24h** gates a failed exit.
