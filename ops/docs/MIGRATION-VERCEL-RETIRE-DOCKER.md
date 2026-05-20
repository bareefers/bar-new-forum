# Full transition to Vercel and retiring the barcode Docker server

## Current setup (two servers)

- **bareefers.org** – Linux server hosting the forum (XenForo), nginx, and the main site. Nginx proxies `/bc` to the barcode API.
- **Barcode Docker server** – Separate Linux server running the old barcode stack in Docker (API + SQLite DBs + uploads). This is what nginx on bareefers.org currently proxies `/bc` to.

The **new frontend** (Next.js) is already on **Vercel** (barcode2-0.bareefers.org) and talks to the API via `https://bareefers.org/bc/api`.

## Target setup (no Docker, one server for API)

- **Vercel** – Serves the BARcode **frontend** only (barcode2-0.bareefers.org).
- **bareefers.org** – Same Linux server runs the **Node API** (and SQLite + image storage or Wasabi). Nginx continues to serve `/bc` but proxies to the API process on the **same** machine instead of the Docker server.
- **Barcode Docker server** – **Retired.** No more Docker for barcode.

So “fully transition barcode to Vercel” means: frontend entirely on Vercel, API and data on bareefers.org, old Docker system shut down.

---

## Why the API can’t run on Vercel

Vercel runs the frontend (and serverless functions if you add them). The current barcode **API** is a long‑running Node/Express app that:

- Uses **SQLite** (local files).
- Handles **file uploads** (multer) and optional object storage.
- Runs **scheduled jobs** (backups, nags, etc.).
- Talks to XenForo and validates forum cookies.

That doesn’t fit Vercel’s serverless model. So the plan is: **frontend on Vercel, API on bareefers.org**, then retire the Docker server.

---

## Free / cheap options (non‑profit, minimal cash flow)

You can keep the **database on your own server(s)** and avoid paid DB services (Neon, Supabase, etc.). SQLite is just files on disk—no separate DB license or subscription. Below are options from **$0 extra** to low cost.

### Option A: Run DB (and API) on the barcode server — $0 extra

**Keep the barcode Docker server.** Run the Node API + SQLite + uploads there. No migration of DB to the cloud; no new monthly cost beyond what you already pay for that box.

- **DB:** SQLite files in `BC_DATABASE_DIR` on that server (same as today).
- **API:** Node app on the same server (in Docker or not).
- **Images:** `BC_UPLOADS_DIR` on the same server, or later move to Wasabi if you want.
- **Frontend:** Stays on Vercel; calls `https://bareefers.org/bc/api`; nginx on bareefers.org keeps proxying `/bc` to the barcode server.

**With Docker:** Use your existing `build/target/barcode-prod` (or a single container) so API + DB + uploads stay in one place. No need for a separate “DB container”—SQLite lives in a volume next to the Node process.

**Without Docker:** Install Node on the barcode server, run the API with systemd or PM2, point `BC_DATABASE_DIR` and `BC_UPLOADS_DIR` at local directories. Same result, simpler stack.

**Cost:** Only the existing barcode server (or $0 if that server is donated / already in the budget).

---

### Option B: Run DB (and API) on bareefers.org — $0 extra, one server

**Retire the barcode server.** Run the Node API + SQLite + uploads on **bareefers.org** (same machine as the forum and nginx).

- **DB:** SQLite in e.g. `/home/barcode/barcode-data/databases` on bareefers.org.
- **API:** Node on bareefers.org (systemd or PM2); nginx proxies `/bc/api` to `localhost:3003`.
- **Images:** Local directory on bareefers.org (or Wasabi later).
- **Cost:** $0 extra—you already pay for bareefers.org. One less server to maintain and pay for.

**Step-by-step:** See **[OPTION-B-MIGRATION-STEPS.md](OPTION-B-MIGRATION-STEPS.md)** for the full checklist (directories, copying data, Node + systemd, nginx config, testing, decommissioning the barcode server).

---

### Option C: Barcode server, minimal Docker (or single container)

If you prefer to keep the barcode server but simplify:

- **One Docker container** that runs the Node API; mount a volume for `BC_DATABASE_DIR` and `BC_UPLOADS_DIR`. SQLite and uploads live on the host (or in a named volume). No separate Postgres/MySQL container—SQLite is file-based.
- Or **no Docker**: Node + SQLite on the host, as in Option A.

Again, **no paid database**; the “DB” is just files on that server.

---

### Option D: Free-tier app hosting (optional, more moving parts)

If you want to get rid of the barcode server and don’t want to put the API on bareefers.org:

- **Railway**, **Render**, **Fly.io** (and similar) have **free or low-cost tiers**. You’d run the Node API there with SQLite on the same instance (ephemeral disk on some free tiers—so you’d need to back up SQLite or use a small paid volume). Good for trying things out; free tiers often sleep after inactivity or have limits.
- **Oracle Cloud** sometimes offers free ARM VMs; you could run Node + SQLite there. No ongoing cost if you stay in the free tier.

These add a new provider and (on free tiers) possible limits or sleep; the **cheapest and simplest** is still Option A or B: DB on the barcode server or on bareefers.org.

---

### Summary: keep it free

| Option | Where DB runs | Extra monthly cost | Notes |
|--------|----------------|--------------------|------|
| **A**  | Barcode server | $0 (existing server) | Keep current box; API + SQLite + uploads there (Docker or not). |
| **B**  | bareefers.org  | $0                 | One server for forum + API + SQLite; retire barcode server. |
| **C**  | Barcode server | $0 (existing server) | Same as A; single container or no Docker. |
| **D**  | Railway/Render/Fly/Oracle | $0–small | External host; free tiers may sleep or limit resources. |

**Recommendation for a non-profit with little cash flow:** Prefer **Option A** (keep barcode server, DB on that server) or **Option B** (DB on bareefers.org, retire barcode server). Both are **$0 extra** and use SQLite on your own hardware—no Neon, Supabase, or other paid DB. Use Docker on the barcode server only if you already like that setup; otherwise run Node + SQLite directly.

---

## Migration steps (high level)

1. **Run the Node API on bareefers.org**
   - Install Node on the bareefers.org Linux box (if not already).
   - Copy the `server/` app and run it (e.g. with systemd, PM2, or a single process). No Docker required.
   - Use a dedicated user and directory for barcode (e.g. `/home/barcode/` or `/opt/barcode/`).

2. **Migrate data from the Docker server**
   - **Databases:** Copy `BC_DATABASE_DIR` (all `*.sqlite3` files) from the Docker server to bareefers.org (e.g. `/home/barcode/barcode-data/databases` or similar).
   - **Images:** Either copy `BC_UPLOADS_DIR` to bareefers.org, or migrate to **Wasabi** (S3‑compatible) and point the app at Wasabi (see [S3/Wasabi](#images--wasabi) below).
   - Set **BC_DATABASE_DIR** and **BC_UPLOADS_DIR** (or object‑storage config) in env on bareefers.org.

3. **Configure nginx on bareefers.org**
   - Change the `/bc` upstream from the **Docker server** to **localhost** (or the Node port on bareefers.org), e.g. `proxy_pass http://127.0.0.1:3003;`.
   - Keep CORS and cookie domain as they are for barcode2-0.bareefers.org.

4. **Point env at the new host**
   - On bareefers.org, set **BC_SITE_BASE_URL** (and any other URLs) to the production values (e.g. `https://bareefers.org/bc`).
   - XenForo API URL, DB tunnel, etc., stay as they are (forum is already on bareefers.org).

5. **Smoke test**
   - Use barcode2-0.bareefers.org; confirm login, API calls, and images work.
   - Run a few critical flows (add item, view collection, etc.).

6. **Retire the Docker server**
   - Once traffic is on the new API and you’ve verified backups, turn off the old barcode Docker server and remove or repurpose it.

---

## Images & Wasabi

If you want images on **Wasabi** (S3‑compatible) instead of local disk:

- The app currently writes uploads to **BC_UPLOADS_DIR** (multer to disk). To use Wasabi you’d either:
  - **Option A:** Add S3‑compatible upload in the server (e.g. `@aws-sdk/client-s3` with Wasabi endpoint), store new uploads in Wasabi, and serve image URLs from a Wasabi bucket (or CDN in front). Migrate existing files from the Docker server to the same bucket.
  - **Option B:** Keep writing to local disk on bareefers.org and run a sync job to Wasabi for backup/CDN; or keep serving from disk and only use Wasabi for new uploads after a code change.

Wasabi uses the S3 API; endpoint and region are set in config (e.g. `https://s3.wasabisys.com` or your region). No code changes in the docs here—this is the migration plan; implementation of Wasabi is a separate task.

---

## Summary

| Component        | Current                    | After migration                |
|-----------------|----------------------------|--------------------------------|
| Frontend        | Vercel (barcode2-0…)      | Vercel (unchanged)             |
| API + DB        | Barcode Docker server      | **bareefers.org** (Node + SQLite) |
| Images          | Docker server (or S3)      | bareefers.org disk or Wasabi  |
| Nginx `/bc`     | Proxies to Docker server   | Proxies to **localhost** (same machine) |
| Docker for barcode | Used                    | **Retired**                    |

Result: barcode is fully on the new stack (Vercel + bareefers.org), and the old Dockerized system is gone.

---

## Free database on Vercel (optional path)

If you want the **database** to live in Vercel’s ecosystem (and ideally free), you can use a [Vercel native integration](https://vercel.com/docs/integrations/install-an-integration/product-integration) from the [Database category](https://vercel.com/marketplace/category/database):

| Product | Type | Free tier |
|--------|------|-----------|
| **[Neon](https://vercel.com/integrations/neon)** | Serverless Postgres | Yes (generous free tier) |
| **[Supabase](https://vercel.com/integrations/supabase)** | Postgres + auth/storage | Yes |
| **Vercel Postgres** (Neon-backed) | Postgres | Free tier available |
| **[Upstash](https://vercel.com/integrations/upstash)** | Redis / Vector / Queue | Free tier |

**Catch:** The current app uses **SQLite** (local `*.sqlite3` files). To use a Vercel DB you’d either:

1. **Migrate to Postgres** – Use Neon or Supabase (free tier), migrate schema and data from SQLite, and change the API to use Postgres. The API would then need to run as **Vercel serverless functions** (or stay on bareefers.org and only *connect* to the Vercel-provisioned DB over the network).
2. **Use a SQLite-compatible hosted DB** – e.g. [Turso](https://turso.tech/) (LibSQL), which is SQLite-compatible and has a free tier. Less schema change, but you’d still need to adapt the server to use Turso’s client and run the API somewhere (Vercel serverless or a small host).

**Recommendation for “free and on Vercel”:** Use **Neon** (best fit for avoiding charges). Add it via the [Vercel–Neon integration](https://vercel.com/integrations/neon) or the [Database marketplace](https://vercel.com/marketplace/category/database), then migrate from SQLite to Postgres and move API logic into the Next.js app as serverless API routes. That gives you frontend + DB + API on Vercel with no Docker.

### Neon free tier – staying at $0

Neon’s free tier is **no credit card required** and allows commercial use. To avoid any charges, stay within these limits (see [Neon plans](https://neon.com/docs/introduction/plans) for current numbers):

| Limit | Free tier |
|-------|-----------|
| **Storage** | 0.5 GB per project |
| **Compute** | 100 CU-hours/month (e.g. ~100 hours at 1 vCPU, or ~400 hours at 0.25 CU) |
| **Outbound transfer** | 5 GB/month |
| **Branches** | 10 per project |
| **Scale to zero** | DB suspends after ~5 min inactivity (saves compute) |

**Tips to stay free:**

- **Enable scale-to-zero** so the database sleeps when idle; you only burn CU when handling requests.
- **Keep data small** – 0.5 GB is enough for moderate SQLite-style data; prune or archive old data if needed.
- **Use a single Neon project** for barcode; put all tables in one Postgres DB to avoid spreading storage.
- **Monitor usage** in the [Neon console](https://console.neon.tech) (Storage and Compute usage). Neon will warn before you hit paid tiers.
- If you add Neon via **Vercel integration**, the connection string is injected as env vars; no extra setup for billing.

Once you migrate the barcode schema and API to Postgres + Vercel serverless, Neon’s free tier is typically enough for a community app like BARcode as long as you stay under the limits above.
