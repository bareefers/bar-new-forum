# Option B: Move API + DB to bareefers.org (Ubuntu 24.04 LTS)

Concrete checklist to run the BARcode API and SQLite DB on **bareefers.org** (Ubuntu 24.04 LTS) and retire the barcode Docker server. Frontend stays on Vercel.

**Server:** bareefers.org = **Ubuntu 24.04 LTS**  
**BARcode data:** SQLite (files on disk) — no MySQL/MariaDB install needed for barcode.  
**XenForo (forum):** If your forum uses MySQL or MariaDB on this server, barcode only needs connection details (`BC_XF_DB_*`) for jobs that sync forum data; the `mysql2` driver works with both.

---

## Prerequisites

- [ ] SSH access to **bareefers.org** (Ubuntu 24.04 LTS) and to the **barcode Docker server**
- [ ] A few minutes of downtime for the final cutover (or do copy + switch in a maintenance window)

---

## 1. Prepare directories on bareefers.org (Ubuntu 24.04)

All commands on the **new Ubuntu server** (bareefers.org) unless noted.

**Option A – Run the script (from repo on the server):**

```bash
cd /path/to/barcode/repo
chmod +x xenforo/scripts/option-b-01-prepare-directories.sh
sudo ./xenforo/scripts/option-b-01-prepare-directories.sh
```

**Option B – Run by hand:**

- [ ] Create `barcode` user and data dirs:

```bash
sudo useradd -r -s /bin/false barcode
sudo mkdir -p /home/barcode/barcode-data/databases
sudo mkdir -p /home/barcode/barcode-data/uploads
sudo chown -R barcode:barcode /home/barcode
```

---

## 2. Copy data from the barcode Docker server

Data on the barcode server is under `/home/barcode/barcode-data/` (databases + uploads).

**Option A – From barcode server, push to bareefers.org**

- [ ] On the **barcode Docker server** (replace `user` with your SSH user for bareefers.org):

```bash
cd /home/barcode/barcode-data
tar czf - databases/ | ssh user@bareefers.org 'mkdir -p /home/barcode/barcode-data && cd /home/barcode/barcode-data && tar xzf -'
tar czf - uploads/   | ssh user@bareefers.org 'cd /home/barcode/barcode-data && tar xzf -'
```

**Option B – From bareefers.org, pull from barcode server**

- [ ] On **bareefers.org** (replace `user` and `BARCODE_SERVER` with your barcode server SSH user and hostname):

```bash
sudo mkdir -p /home/barcode/barcode-data/databases /home/barcode/barcode-data/uploads
sudo chown barcode:barcode /home/barcode/barcode-data
cd /home/barcode/barcode-data
sudo -u barcode scp -r user@BARCODE_SERVER:/home/barcode/barcode-data/databases/* ./databases/
sudo -u barcode scp -r user@BARCODE_SERVER:/home/barcode/barcode-data/uploads/*   ./uploads/
```

- [ ] Fix ownership after copy (on bareefers.org):

```bash
sudo chown -R barcode:barcode /home/barcode/barcode-data
```

---

## 3. Install Node.js 20 LTS on Ubuntu 24.04

- [ ] On **bareefers.org**:

```bash
# NodeSource setup for Node 20.x on Ubuntu 24.04
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt-get update
sudo apt-get install -y nodejs
node -v   # should show v20.x
```

---

## 4. Deploy the API app on bareefers.org

- [ ] Clone repo to `/home/barcode/barcode-app` (monorepo: client + server). Example:

```bash
sudo mkdir -p /home/barcode/barcode-app
sudo chown "$USER":"$USER" /home/barcode/barcode-app
git clone --depth 1 <YOUR_REPO_URL> /home/barcode/barcode-app
cd /home/barcode/barcode-app/server
npm install --production
```

- [ ] If you deploy only the `server/` folder (e.g. copy to `/home/barcode/barcode-app`), then:

```bash
cd /home/barcode/barcode-app
npm install --production
```

Use the same path as `WorkingDirectory` in the systemd unit in step 6 (e.g. `.../server` for full repo, `.../barcode-app` for server-only).

---

## 5. Environment file on bareefers.org

- [ ] Create `/home/barcode/barcode-env` (replace placeholders with real values from your barcode Docker server):

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

- [ ] If you use jobs that need the XenForo DB (MySQL or MariaDB on this server), add (same format as before):

```bash
# BC_XF_DB_SSH_CREDENTIALS=host,port,user,password,dbport
# BC_XF_DB_CREDENTIALS=dbname,user,password
```

- [ ] Restrict permissions:

```bash
sudo chmod 600 /home/barcode/barcode-env
sudo chown barcode:barcode /home/barcode/barcode-env
```

---

## 6. Systemd service (Ubuntu 24.04)

- [ ] Create unit file. Use `WorkingDirectory` = directory that contains `server.js` (e.g. `.../server` when you cloned the full repo):

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

If you deployed only the `server/` folder to `/home/barcode/barcode-app`, use `WorkingDirectory=/home/barcode/barcode-app` instead.

- [ ] Enable and start:

```bash
sudo systemctl daemon-reload
sudo systemctl enable barcode-api
sudo systemctl start barcode-api
sudo systemctl status barcode-api
```

- [ ] Confirm API listens on 3003:

```bash
curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:3003/dbtc/your-collection
```

(401 is OK; it means the API responded.)

---

## 7. Nginx: serve uploads and proxy `/bc/api` to localhost

- [ ] Add location for uploads (before any existing `location ^~ /bc`). Edit the vhost that serves bareefers.org (e.g. `/etc/nginx/sites-available/bareefers.org` or `conf.d/bareefers.org.ssl.conf`):

```nginx
location ^~ /bc/uploads/ {
    alias /home/barcode/barcode-data/uploads/;
    add_header Access-Control-Allow-Origin $cors_allowed_origin;
}
```

- [ ] Replace the existing `location ^~ /bc` that proxies to barcode.bareefers.org with a block that proxies only `/bc/api/` to localhost and rewrites the path:

```nginx
location ^~ /bc/api/ {
    proxy_hide_header Access-Control-Allow-Origin;
    proxy_hide_header Access-Control-Allow-Credentials;
    proxy_hide_header Access-Control-Allow-Methods;
    proxy_hide_header Access-Control-Allow-Headers;

    more_set_headers "Access-Control-Allow-Origin: $cors_allowed_origin";
    more_set_headers "Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE";
    more_set_headers "Access-Control-Allow-Headers: Authorization,Content-Type,Accept,Origin,User-Agent,DNT,Cache-Control,X-Mx-ReqToken,Keep-Alive,X-Requested-With,If-Modified-Since";
    more_set_headers "Access-Control-Allow-Credentials: true";

    if ($request_method = 'OPTIONS') {
        more_set_headers "Access-Control-Allow-Origin: $cors_allowed_origin";
        more_set_headers "Access-Control-Allow-Credentials: true";
        more_set_headers "Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE";
        more_set_headers "Access-Control-Allow-Headers: Authorization,Content-Type,Accept,Origin,User-Agent,DNT,Cache-Control,X-Mx-ReqToken,Keep-Alive,X-Requested-With,If-Modified-Since";
        more_set_headers "Content-Length: 0";
        more_set_headers "Content-Type: text/plain; charset=utf-8";
        return 204;
    }

    rewrite ^/bc/api(.*) $1 break;
    proxy_pass http://127.0.0.1:3003;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
}
```

Ensure the `map $http_origin $cors_allowed_origin` from your CORS setup is in the same nginx config (see [NGINX-CORS-HOWTO.md](NGINX-CORS-HOWTO.md)).

- [ ] Test and reload nginx:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

---

## 8. Smoke test before decommissioning barcode server

- [ ] From bareefers.org: `curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:3003/dbtc/your-collection` (expect 401 or 200)
- [ ] In browser: open **https://barcode2-0.bareefers.org**, log in to forum, then:
  - [ ] Collection loads
  - [ ] One add/view/edit flow works
  - [ ] An image from `/bc/uploads/...` loads

---

## 9. Decommission the barcode Docker server

- [ ] On the barcode Docker server: `cd /path/to/barcode-deploy && docker compose down`
- [ ] (Optional) Final backup: copy `/home/barcode/barcode-data` from barcode server to bareefers.org or offline
- [ ] Cancel or repurpose the barcode server

---

## 10. Ongoing

- **Restart after code deploy:** `sudo systemctl restart barcode-api`
- **Logs:** `journalctl -u barcode-api -f`
- **Backups:** App writes SQLite backups under `BC_DATABASE_DIR/backups/`; optionally copy off-server

---

## Quick reference (Ubuntu 24.04 LTS)

| Item | Value |
|------|--------|
| OS | Ubuntu 24.04 LTS |
| Node | 20.x LTS (NodeSource) |
| API port | 3003 |
| DB (barcode) | SQLite in `/home/barcode/barcode-data/databases` |
| Forum DB | MySQL or MariaDB (existing); barcode uses `BC_XF_DB_*` only to connect |
| Uploads | `/home/barcode/barcode-data/uploads` |
| systemd unit | `barcode-api.service` |
| Nginx API | `location ^~ /bc/api/` → rewrite, `proxy_pass http://127.0.0.1:3003` |
| Nginx uploads | `location ^~ /bc/uploads/` → `alias .../uploads/` |
