# Option 1: Full migration — One Linode (Forums + Barcode API)

End-to-end guide: one **Ubuntu 24.04 LTS** Linode running **XenForo (forums)** and the **BARcode API**, with the barcode **frontend** on Vercel. You will retire the old CentOS forum server and the separate barcode server.

---

## Current state → Target state

| Before | After |
|--------|--------|
| **bareefers.org** = CentOS, XenForo, nginx, MySQL/MariaDB | **bareefers.org** = **Ubuntu 24.04** Linode: nginx, PHP-FPM, **new XenForo**, MySQL/MariaDB, **Node (barcode API)**, SQLite, uploads |
| **Barcode** = separate Ubuntu 20.04 server (API + SQLite + uploads) | **Barcode API** = on same Linode; barcode server **retired** |
| **Barcode frontend** = Vercel (barcode2-0.bareefers.org) | **Unchanged** — still Vercel |

**You need:** SSH to the **CentOS** server (forums), SSH to the **barcode** server (Ubuntu 20.04), and a new **Ubuntu 24.04** Linode. Replace placeholders like `CENTOS_HOST`, `BARCODE_HOST`, `NEW_LINODE_IP`, and `your_ssh_user` with your real values.

---

## Beta server strategy (beta.bareefers.org)

Use the new Linode as a **beta** environment before production cutover:

- **Hostname:** Point **beta.bareefers.org** at the new server’s IP. In nginx, add `beta.bareefers.org` to `server_name` so the same server block serves the beta URL.
- **Purpose:** Test the **XenForo upgrade** to the latest version and **barcode 2.0** (Vercel frontend + API via `/bc`) without touching production.
- **When ready:** Run through the go-live checklist (SSL, cookie secure, DNS), then switch **bareefers.org** to point at this server and treat it as production. Optionally remove or repurpose the beta hostname.

Keep production (bareefers.org) on the old CentOS + barcode server until beta is signed off.

---

## Phase 1: Provision and base setup (new Linode)

All steps in this phase are on the **new Ubuntu 24.04 Linode** unless noted.

- [ ] Create a Linode (Ubuntu 24.04 LTS). Suggested: 4GB RAM, 2 vCPU; same region as current servers if possible. Note the IP (`NEW_LINODE_IP`).

- [ ] SSH in and set hostname:

```bash
sudo hostnamectl set-hostname bareefers
```

- [ ] Update and install basics:

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y ufw curl git
```

- [ ] Firewall (allow SSH first, then nginx):

```bash


```

---

## Phase 2: Install stack (nginx, PHP, database)

On the **new Linode**:

- [ ] nginx:

```bash
sudo apt install -y nginx
```

- [ ] PHP 8.3 + FPM and extensions (XenForo 2.x):

```bash
sudo apt install -y php-fpm php-mysql php-gd php-json php-xml php-mbstring php-curl php-zip php-intl
php -v
```

- [ ] Database — **MySQL** or MariaDB (both work with XenForo and barcode’s forum-DB connection):

```bash
# MySQL (Ubuntu 24.04)
sudo apt install -y mysql-server
sudo mysql_secure_installation
```

Or MariaDB: `sudo apt install -y mariadb-server` then `sudo mysql_secure_installation`.

- [ ] Optional: CORS headers module for nginx (if you want `more_set_headers`; otherwise we use `add_header` below):

```bash
sudo apt install -y libnginx-mod-http-headers-more-filter
```

---

## Phase 3: Migrate XenForo from CentOS

### 3.1 Find web root and DB on CentOS

On the **old CentOS** server:

- [ ] Find web root:

```bash
nginx -T 2>/dev/null | grep -E '^\s*root ' || grep -E '^\s*root ' /etc/nginx/nginx.conf /etc/nginx/conf.d/*.conf 2>/dev/null
```

Note the path (e.g. `/var/www/html`). Set it as `WEB_ROOT` below.

- [ ] Find XenForo DB config (use the root you found; XenForo 2 uses `src/config.php`, older uses `library/config.php`):

```bash
WEB_ROOT=/var/www/html
grep -E 'db|host|password|user' "$WEB_ROOT/src/config.php" 2>/dev/null || grep -E 'db|host|password|user' "$WEB_ROOT/library/config.php" 2>/dev/null
```

Or list the file and cat it (redact when sharing):

```bash
ls -la $WEB_ROOT/src/config.php $WEB_ROOT/library/config.php 2>/dev/null
cat $WEB_ROOT/src/config.php 2>/dev/null || cat $WEB_ROOT/library/config.php 2>/dev/null
```

Note: DB name, user, password for the dump and for creating the DB on the new server.

### 3.2 Copy forum files to new Linode

From **CentOS** (replace `your_ssh_user` and `NEW_LINODE_IP`):

```bash
cd /path/to/WEB_ROOT
sudo tar czf - . | ssh your_ssh_user@NEW_LINODE_IP 'sudo mkdir -p /var/www/bareefers.org && cd /var/www/bareefers.org && sudo tar xzf -'
```

Or from **new Linode** (pull):

```bash
sudo mkdir -p /var/www/bareefers.org
sudo rsync -avz --progress your_ssh_user@CENTOS_HOST:/path/to/WEB_ROOT/ /var/www/bareefers.org/
```

- [ ] On new Linode, set ownership:

```bash
sudo chown -R www-data:www-data /var/www/bareefers.org
```

### 3.3 Dump forum DB on CentOS and import on new Linode

On **CentOS** (use DB name/user from XenForo config):

```bash
mysqldump -u XF_DB_USER -p XF_DB_NAME > ~/xf-forum.sql
scp ~/xf-forum.sql your_ssh_user@NEW_LINODE_IP:~/
```

On **new Linode**:

- [ ] Create DB and user:

```bash
sudo mysql
```

```sql
CREATE DATABASE xf_forum CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'xf_user'@'localhost' IDENTIFIED BY 'YOUR_STRONG_PASSWORD';
GRANT ALL PRIVILEGES ON xf_forum.* TO 'xf_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

- [ ] Import:

```bash
mysql -u xf_user -p xf_forum < ~/xf-forum.sql
```

- [ ] Update XenForo `config.php` on the new server if DB name/user/password changed (path is usually `src/config.php` or `library/config.php` under `/var/www/bareefers.org`).

---

## Phase 4: Barcode API on the new Linode

### 4.1 Prepare directories

On the **new Linode**:

- [ ] Run the script (if you have the repo), or run by hand:

```bash
sudo useradd -r -s /bin/false barcode
sudo mkdir -p /home/barcode/barcode-data/databases /home/barcode/barcode-data/uploads
sudo chown -R barcode:barcode /home/barcode
```

### 4.2 Copy barcode data from barcode server

From **barcode server** (Ubuntu 20.04), push to new Linode (replace `your_ssh_user`, `NEW_LINODE_IP`):

```bash
cd /home/barcode/barcode-data
tar czf - databases/ | ssh your_ssh_user@NEW_LINODE_IP 'sudo mkdir -p /home/barcode/barcode-data && sudo tar -C /home/barcode/barcode-data -xzf -'
tar czf - uploads/   | ssh your_ssh_user@NEW_LINODE_IP 'sudo mkdir -p /home/barcode/barcode-data && sudo tar -C /home/barcode/barcode-data -xzf -'
```

On **new Linode**:

```bash
sudo chown -R barcode:barcode /home/barcode/barcode-data
```

- [ ] Copy barcode env file from barcode server (then edit paths and secrets as needed):

```bash
scp your_ssh_user@BARCODE_HOST:/home/barcode/barcode-env ~/barcode-env
# Edit and move into place in Phase 4.4
```

### 4.3 Install Node.js 20

On the **new Linode**:

```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
node -v
```

### 4.4 Deploy barcode API app

On the **new Linode**:

```bash
sudo mkdir -p /home/barcode/barcode-app
sudo chown "$USER":"$USER" /home/barcode/barcode-app
git clone --depth 1 YOUR_BARCODE_REPO_URL /home/barcode/barcode-app
cd /home/barcode/barcode-app/server
npm install --production
```

- [ ] Create env file (replace placeholders; use values from barcode server’s `barcode-env`):

```bash
sudo tee /home/barcode/barcode-env << 'EOF'
BC_FORUM_MODE=production
BC_SMS_MODE=production
BC_SESSION_COOKIE_NAME=bc-a
BC_SESSION_COOKIE_SECURE=production
BC_SITE_BASE_URL=https://bareefers.org/bc
BC_XF_API_URL=https://bareefers.org/forum/api
BC_DATABASE_DIR=/home/barcode/barcode-data/databases
BC_UPLOADS_DIR=/home/barcode/barcode-data/uploads
BC_XF_API_KEY=YOUR_XF_API_KEY
BC_SESSION_COOKIE_SECRETS=YOUR_SESSION_SECRETS
EOF
```

If barcode jobs need the XenForo DB (same machine now), add:

```bash
# On same server, no SSH tunnel; use local MySQL/MariaDB
# BC_XF_DB_SSH_CREDENTIALS=  (optional; leave unset if DB is local)
# BC_XF_DB_CREDENTIALS=xf_forum,xf_user,password
```

```bash
sudo chmod 600 /home/barcode/barcode-env
sudo chown barcode:barcode /home/barcode/barcode-env
```

### 4.5 Systemd service for barcode API

On the **new Linode**:

```bash
sudo tee /etc/systemd/system/barcode-api.service << 'EOF'
[Unit]
Description=BARcode API
After=network.target

[Service]
Type=simple
User=barcode
WorkingDirectory=/home/barcode/barcode-app/server
EnvironmentFile=/home/barcode/barcode-env
ExecStart=/usr/bin/node server.js
Restart=on-failure
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable barcode-api
sudo systemctl start barcode-api
sudo systemctl status barcode-api
curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:3003/dbtc/your-collection
```

(401 is OK.)

---

## Phase 5: Nginx — forum + /bc (uploads + API)

On the **new Linode**, nginx will serve the forum and proxy `/bc` (uploads from disk, API to localhost).

### 5.1 CORS map (http block)

- [ ] Edit nginx main config. On Ubuntu, often `/etc/nginx/nginx.conf`. Inside the existing `http {` block add:

```nginx
map $http_origin $cors_allowed_origin {
    default "";
    "https://barcode2-0.bareefers.org" "$http_origin";
    "~^https://[a-z0-9-]+\\.vercel\\.app$" "$http_origin";
}
```

### 5.2 Site server block (forum + /bc)

- [ ] Create (or edit) `/etc/nginx/sites-available/bareefers.org`:

```nginx
server {
    listen 80;
    server_name bareefers.org www.bareefers.org;

    root /var/www/bareefers.org;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # BARcode: serve uploads from disk
    location ^~ /bc/uploads/ {
        alias /home/barcode/barcode-data/uploads/;
        add_header Access-Control-Allow-Origin $cors_allowed_origin;
    }

    # BARcode: proxy API to Node (path rewrite: /bc/api -> localhost:3003)
    location ^~ /bc/api/ {
        proxy_hide_header Access-Control-Allow-Origin;
        proxy_hide_header Access-Control-Allow-Credentials;
        proxy_hide_header Access-Control-Allow-Methods;
        proxy_hide_header Access-Control-Allow-Headers;
        add_header Access-Control-Allow-Origin $cors_allowed_origin;
        add_header Access-Control-Allow-Credentials true;
        add_header Access-Control-Allow-Methods "GET, POST, OPTIONS, PUT, DELETE";
        add_header Access-Control-Allow-Headers "Authorization,Content-Type,Accept,Origin,User-Agent,DNT,Cache-Control,X-Mx-ReqToken,Keep-Alive,X-Requested-With,If-Modified-Since";

        if ($request_method = 'OPTIONS') {
            add_header Access-Control-Allow-Origin $cors_allowed_origin;
            add_header Access-Control-Allow-Credentials true;
            add_header Access-Control-Allow-Methods "GET, POST, OPTIONS, PUT, DELETE";
            add_header Access-Control-Allow-Headers "Authorization,Content-Type,Accept,Origin,User-Agent,DNT,Cache-Control,X-Mx-ReqToken,Keep-Alive,X-Requested-With,If-Modified-Since";
            add_header Content-Length 0;
            add_header Content-Type "text/plain; charset=utf-8";
            return 204;
        }

        rewrite ^/bc/api(.*) $1 break;
        proxy_pass http://127.0.0.1:3003;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

- [ ] Enable and test:

```bash
sudo ln -sf /etc/nginx/sites-available/bareefers.org /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

---

## XenForo cookie setting (HTTP vs HTTPS)

XenForo is configured with **secure cookies** (`$config['cookie']['secure'] = true` in `forum/src/config.php`). Browsers only send those over **HTTPS**. Over **HTTP** you get “Cookies are required.”

- **While testing over HTTP** (e.g. http://45.79.82.104/forum/ or http://beta.bareefers.org/forum/): set `$config['cookie']['secure'] = false` in `forum/src/config.php` so login works.
- **Before going live with HTTPS:** set it back to `true` so cookies are only sent over HTTPS.

```bash
# Edit on server
sudo nano /var/www/bareefers.org/forum/src/config.php
# Set $config['cookie']['secure'] = true;  for production (HTTPS)
```

---

## Phase 6: SSL (Let’s Encrypt)

On the **new Linode**, after DNS for bareefers.org (or beta.bareefers.org) points to this server:

- [ ] Install certbot and get cert:

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d bareefers.org -d www.bareefers.org
```

- [ ] Certbot will adjust the server block for HTTPS. Reload nginx if needed:

```bash
sudo nginx -t && sudo systemctl reload nginx
```

---

## Phase 7: Test before DNS cutover

- [ ] On your laptop, add to `/etc/hosts` (or `C:\Windows\System32\drivers\etc\hosts`):

```
NEW_LINODE_IP  bareefers.org www.bareefers.org
```

- [ ] In browser:
  - https://bareefers.org — forum loads; log in and browse.
  - https://barcode2-0.bareefers.org — log in with forum; open collection; confirm API and images work (they go to bareefers.org/bc/api and bareefers.org/bc/uploads).

- [ ] On server:

```bash
curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:3003/dbtc/your-collection
```

---

## Phase 8: Go-live checklist and DNS cutover

**Before pointing production DNS at this server:**

- [ ] **XenForo cookie secure:** In `/var/www/bareefers.org/forum/src/config.php` set `$config['cookie']['secure'] = true;` so cookies are only sent over HTTPS (see [XenForo cookie setting](#xenforo-cookie-setting-http-vs-https) above).
- [ ] SSL is in place (Phase 6) for the hostname you use for production (e.g. bareefers.org).

**DNS cutover:**

- [ ] Update DNS A (and AAAA if used) for **bareefers.org** and **www.bareefers.org** to `NEW_LINODE_IP`.

- [ ] After TTL / propagation, remove the hosts file entry and test again from a normal connection.

- [ ] When satisfied:
  - **CentOS (old forums):** Stop services or power off; keep a final backup of files and DB if needed.
  - **Barcode server (Ubuntu 20.04):** Stop the barcode stack; keep a final backup of `/home/barcode/barcode-data`; then cancel or repurpose the instance.

---

## Phase 9: Ongoing

| Task | Command / note |
|------|-----------------|
| Restart barcode API | `sudo systemctl restart barcode-api` |
| Barcode API logs | `journalctl -u barcode-api -f` |
| Nginx test/reload | `sudo nginx -t && sudo systemctl reload nginx` |
| PHP-FPM | `sudo systemctl status php*-fpm` |
| DB backups | Back up MariaDB/MySQL and `/home/barcode/barcode-data/databases` (and uploads) regularly |
| XenForo upgrades | Follow XenForo docs; keep PHP and nginx compatible |

---

## Quick reference

| Item | Value |
|------|--------|
| OS | Ubuntu 24.04 LTS |
| Web root | `/var/www/bareefers.org` |
| Forum DB | MariaDB/MySQL `xf_forum` (or your name) |
| Barcode API | Node 20, port 3003, systemd `barcode-api.service` |
| Barcode data | `/home/barcode/barcode-data/databases`, `.../uploads` |
| Nginx | `/etc/nginx/sites-available/bareefers.org` |
| CORS | `map $http_origin $cors_allowed_origin` in `http {}`; `/bc/uploads` and `/bc/api` use it |

---

## Troubleshooting

### "Attempted to recursively load configuration file" when running CLI

When you run:

```bash
cd /var/www/bareefers.org/forum
sudo -u www-data php cmd.php xf:rebuild-master-data
```

and get **"Attempted to recursively load configuration file"** in `src/XF/App.php`, the cause is almost always **custom code in `src/config.php`** that runs during config load and triggers another config load (or uses `$_SERVER` in a way that fails in CLI).

**Common causes:**

1. **IP-based debug** — e.g. `if ($_SERVER['REMOTE_ADDR'] == '1.2.3.4') { $config['debug'] = true; }`. In CLI, `$_SERVER['REMOTE_ADDR']` may be unset; any code that then touches the app (e.g. error handler) can cause recursion.
2. **Any use of `\XF::app()` or DB/error handling inside `config.php`** — that can force config to be loaded again while it is already loading.

**Fix on the server:**

1. Open the forum config:
   ```bash
   sudo nano /var/www/bareefers.org/forum/src/config.php
   ```
2. Look for:
   - Conditional blocks that use `$_SERVER['REMOTE_ADDR']`, `$_SERVER['HTTP_...']`, or similar without `isset()`.
   - Any code that calls `\XF::app()` or sets debug/error options based on request data.
3. Either:
   - **Skip that logic in CLI** by wrapping it so it only runs in web context:
     ```php
     if (php_sapi_name() !== 'cli' && isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] === 'YOUR_IP') {
         $config['debug'] = true;
     }
     ```
   - Or remove/simplify the conditional so `config.php` only sets the `$config` array and does not touch the app or unreliable `$_SERVER` keys.
4. Save and run again:
   ```bash
   sudo -u www-data php cmd.php xf:rebuild-master-data
   ```

After this, outdated templates still need to be handled in the XenForo Admin CP (no supported CLI for merging template changes).

---

## Related docs

- [ARCHITECTURE-OPTIONS.md](ARCHITECTURE-OPTIONS.md) — Why Option 1 (single Linode).
- [OPTION-B-MIGRATION-STEPS.md](OPTION-B-MIGRATION-STEPS.md) — Barcode-on-same-server steps in isolation.
- [NGINX-CORS-HOWTO.md](NGINX-CORS-HOWTO.md) — CORS for Vercel frontend.
