# fail2ban for bareefers.org

Blocks repeat scrapers that overwhelm PHP-FPM (502s). Complements nginx `limit_req` — rate limits slow them down; fail2ban bans the repeat offenders.

Live paths on the server:

- Jail: `/etc/fail2ban/jail.d/bareefers.conf`
- Filters: `/etc/fail2ban/filter.d/bareefers-*.conf`
- Log: `/var/log/fail2ban.log`

Repo paths (this tree): `ops/fail2ban/`.

## Install / refresh on server

From the server git clone (`/var/www/bareefers.org/bar-new-forum`):

```bash
sudo apt-get install -y fail2ban
cd /var/www/bareefers.org/bar-new-forum
sudo cp ops/fail2ban/filter.d/*.conf /etc/fail2ban/filter.d/
sudo cp ops/fail2ban/jail.d/bareefers.conf /etc/fail2ban/jail.d/
# log to file (needed for recidive jail)
sudo sed -i 's|^logtarget =.*|logtarget = /var/log/fail2ban.log|' /etc/fail2ban/fail2ban.conf
sudo systemctl enable --now fail2ban
sudo fail2ban-client reload
sudo fail2ban-client status
```

From a local Windows clone via WSL:

```bash
wsl bash -lc 'scp -r ops/fail2ban bareefers:/tmp/ && ssh bareefers "sudo cp /tmp/fail2ban/filter.d/*.conf /etc/fail2ban/filter.d/ && sudo cp /tmp/fail2ban/jail.d/bareefers.conf /etc/fail2ban/jail.d/ && sudo fail2ban-client reload && sudo fail2ban-client status"'
```

## Jails

| Jail | Trigger | Ban |
|------|---------|-----|
| `bareefers-nginx-limit` | nginx `limiting requests/connections` in error.log | 2h after 10 hits / 5m |
| `bareefers-nginx-whatsnew` | `/forum/whats-new/posts\|profile-posts` | 6h after 8 hits / 3m |
| `bareefers-nginx-botua` | known bot UAs (GPTBot, meta-externalagent, Ahrefs, …) | 24h after 6 hits / 10m |
| `bareefers-recidive` | 3 bans in 24h | 1 week |

Uses **nftables** (`nftables-multiport`) so bans work with UFW. Backend is **pyinotify** on the nginx log files (not the systemd journal).

Related nginx rate-limit snippets: `ops/nginx/bareefers-crawler-rate-limit.conf`.

---

## Find and remove an incorrectly banned IP

SSH to the server first (`ssh bareefers` via WSL).

### 1. See if the IP is banned (and which jail)

```bash
# Overview of all jails
sudo fail2ban-client status

# Search every bareefers jail for one IP
IP=1.2.3.4
for j in bareefers-nginx-limit bareefers-nginx-whatsnew bareefers-nginx-botua bareefers-recidive; do
  echo "=== $j ==="
  sudo fail2ban-client status "$j" | grep -F "$IP" || true
done
```

Or inspect one jail’s full ban list:

```bash
sudo fail2ban-client status bareefers-nginx-whatsnew
sudo fail2ban-client status bareefers-nginx-limit
sudo fail2ban-client status bareefers-nginx-botua
sudo fail2ban-client status bareefers-recidive
```

### 2. Confirm from the ban log

```bash
sudo grep '1.2.3.4' /var/log/fail2ban.log | tail -20
```

Look for lines like `[bareefers-nginx-limit] Ban 1.2.3.4` — that jail name is what you unban from.

### 3. Unban the IP

Unban from **every jail that listed it** (an IP can sit in more than one):

```bash
sudo fail2ban-client set bareefers-nginx-limit unbanip 1.2.3.4
sudo fail2ban-client set bareefers-nginx-whatsnew unbanip 1.2.3.4
sudo fail2ban-client set bareefers-nginx-botua unbanip 1.2.3.4
sudo fail2ban-client set bareefers-recidive unbanip 1.2.3.4
```

`fail2ban-client` prints `0` or `1` for success on many versions; “not banned” is fine if that jail never had the IP.

Quick one-liner (tries all bareefers jails):

```bash
IP=1.2.3.4
for j in bareefers-nginx-limit bareefers-nginx-whatsnew bareefers-nginx-botua bareefers-recidive; do
  sudo fail2ban-client set "$j" unbanip "$IP" || true
done
```

### 4. Optional: stop it from getting banned again

If it’s a known-good address (home, office, board member), add it to `ignoreip` in `/etc/fail2ban/jail.d/bareefers.conf`:

```ini
ignoreip = 127.0.0.1/8 ::1 203.0.113.50
```

Then:

```bash
sudo fail2ban-client reload
```

Also update `ops/fail2ban/jail.d/bareefers.conf` in this repo so the next deploy does not wipe the whitelist.

### 5. How a member can tell you their IP

Ask them to open [https://www.whatismyip.com/](https://www.whatismyip.com/) (or similar) on the same network/device that can’t reach the forum, and send the IPv4 address.

---

## Other ops

```bash
sudo fail2ban-client status bareefers-nginx-botua
sudo fail2ban-client set bareefers-nginx-whatsnew banip 1.2.3.4
```

## Limits

Distributed scrapers that rotate IPs after a few hits still need nginx rate limits (and optionally Cloudflare). fail2ban stops the sticky / named bots and rate-limit abusers.
