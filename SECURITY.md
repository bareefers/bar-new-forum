# Security

## Never commit

- `forum/src/config.php` (database, Redis, debug flags)
- Any file containing **passwords**, API keys, PayPal secrets, SSH private keys
- `forum/internal_data/` or `forum/data/`
- Database dumps (`.sql`, `.sql.gz`)
- Files named like `*password*.txt`, `id_ed25519`, `id_rsa`
- Full XenForo install `.zip` archives

`.gitignore` blocks common cases; **review your diff** before every PR.

## Production secrets live on the server only

- `forum/src/config.php` on **bareefers**
- Payment profiles in **MySQL** (`xf_payment_profile`)
- PayPal / webhook credentials in ACP and config

Use `config.php.example` as a reference for **structure**, not production values.

## SSH access

SSH keys and `~/.ssh/config` for host **`bareefers`** are managed outside this repository. Do not paste private keys into Issues or PRs.

## Reporting issues

Report security-sensitive findings to the **bareefers** org owners privately, not in a public GitHub issue.
