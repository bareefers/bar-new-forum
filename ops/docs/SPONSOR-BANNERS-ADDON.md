# BAR Sponsor Banners add-on

**See also:** [KNOWLEDGE-TRANSFER.md](../../docs/KNOWLEDGE-TRANSFER.md) §9.1 (handoff summary).

This add-on gives staff an **ACP-only library** for sponsor banner images and metadata (title, link, alt text, order, active). The **Appearance → Sponsor banners** screen is intentionally just that library — it does not configure where anything appears on the public forum.

Separately, whoever handles **Appearance → Widgets** can place the **Sponsor banners** widget on the front end if you want them visible to members.

What the ACP library does:

- upload image files in ACP
- store them under `forum/sponsor_banners/`
- set target URL / alt text / active state / display order

Optional front-end display uses **Appearance → Widgets** (widget) or an advanced template macro (see below).

## One place: migrate off “Advertising → BAR Sponsors 2026”

If sponsors still live only in **Setup → Advertising** (template with `$adx.1` … `$adx.7`), they **do not** appear under **Appearance → Sponsor banners** until you copy them into the add-on’s database.

1. Deploy add-on **`1.0.5`** (or newer; production target **1.1.x**) to the server.
2. From the forum root, run once (idempotent):

   ```bash
   sudo -u www-data php /var/www/bareefers.org/bar-new-forum/ops/scripts/xf-migrate-legacy-bar-sponsor-banners.php \
     /var/www/bareefers.org/forum
   ```

3. Confirm **Appearance → Sponsor banners** lists all sponsors.
4. Under **Appearance → Widgets**, edit your **Sponsor banners** widget:
   - **Rotation → One at a time (rotate)** — same behavior as the old `($xf.time % 7) + 1` ad.
   - **Max banner width** — e.g. `468` to match the old markup.
5. In **Setup → Advertising → BAR Sponsors 2026**, replace the entire template body with **one line** (use your real widget key from step 4):

   `<xf:widget key="bar_sponsor_banners_forum_list" />`

   (Replace `bar_sponsor_banners_forum_list` with whatever **Widget key** you set when you created the widget.)

After that, **only** **Appearance → Sponsor banners** is used to add/edit/disable sponsors; the advertisement slot only **embeds** the widget.

## Add-on source and install

| Location | Path |
|----------|------|
| **Git (bar-new-forum, when vendored)** | `forum/src/addons/BAR/SponsorBanners/` |
| **Git (parent barcode repo)** | `xenforo/addons/BAR/SponsorBanners/` |
| **Live server** | `/var/www/bareefers.org/forum/src/addons/BAR/SponsorBanners` |

## Install on live server (`bareefers`)

1. Copy files to server XenForo add-on path:
   - source: `bar-new-forum/forum/src/addons/BAR/SponsorBanners` (or `barcode/xenforo/addons/BAR/SponsorBanners`)
   - destination: `/var/www/bareefers.org/forum/src/addons/BAR/SponsorBanners`
2. In ACP, go to **Add-ons** and install **BAR Sponsor Banners** (or from SSH: `php cmd.php xf-addon:install BAR/SponsorBanners`).
3. Go to **Appearance → Sponsor banners** (ACP nav item).
4. Add banners and upload images.

The add-on writes images to:

`/var/www/bareefers.org/forum/sponsor_banners`

## Part A — Upload and manage banners (ACP)

Who can do this: an administrator account that has **Style properties and templates** permission (XenForo’s `style` admin permission). Full super-admins already have this.

1. Log in to **ACP** (`admin.php`).
2. In the left sidebar, open **Appearance → Sponsor banners** (added by this add-on).
3. Click **Add banner**.
4. Fill in:
   - **Title** — internal name (also used in the generated filename prefix).
   - **Target URL** — where the banner click goes (include `https://`). Leave blank if you do not want a link (widget will use `#`).
   - **Alt text** — accessibility text for the image.
   - **Display order** — lower numbers appear first when multiple banners are active.
   - **Active** — checked so the widget will show it; use the list’s inline toggle to hide without deleting.
   - **Remote image URL** (optional) — if set, the public site uses this URL instead of an uploaded file.
5. Under **Banner image**, choose a file (`jpg`, `jpeg`, `png`, `gif`, or `webp`) and **Save** (skip upload if you only use a remote URL).
6. Repeat for additional sponsors.

Files land on disk under:

`/var/www/bareefers.org/forum/sponsor_banners/`

The database stores a path like `sponsor_banners/1740000000-your-title.png`.

## Part B — Show banners on the public site (widget)

XenForo does **not** allow loading banner rows from the database inside arbitrary **public** templates, so the supported “no shell” path is a **widget**. The add-on registers a widget definition named **Sponsor banners**.

### 1) Create the widget

1. ACP → **Appearance → Widgets**.
2. Click **Add widget**.
3. **Widget definition** — choose **Sponsor banners** (scroll the list; it is registered by `BAR/SponsorBanners`).

### 2) Required fields explained

- **Title** — what staff see in the widget list, e.g. `Sponsor banners (forum list)`.
- **Widget key** (**required**) — a **unique machine name** for this widget instance. Use lowercase letters, numbers, and underscores only. Examples:
  - `bar_sponsor_banners_forum_list`
  - `bar_sponsor_banners_home`
  If XenForo says the key is taken, pick a new one or delete the old test widget that reused it.
- **Position** — where on the site it renders. Common choices:
  - **`forum_list_above_nodes`** — above the forum list on the main forum index (good default).
  - **`forum_list_below_nodes`** — below the forum list.
  - **`container_content_above`** / **`container_content_below`** — very wide placements; use only if you want site-wide visibility.
  Use **Position** dropdown search if your style exposes many positions; pick one that matches where you want sponsors seen.
- **Max banner width (pixels)** — in the widget’s options block. Typical values: `300`–`600`. Use **`0`** for no limit (image can grow with the page layout). This only changes how large the image **looks**; it does not re-upload the file.
- **Rotation** — **One at a time (rotate)** shows a single active sponsor per page load (time-based index, same idea as the old `($xf.time % N) + 1` ad). **Show all** lists every active banner in display order.

4. **Display styling** — usually leave default unless you use a portal/style that requires a wrapper.
5. **User group criteria** / **Node criteria** (if shown) — optional; leave blank to show to everyone, or restrict (e.g. guests only, registered only).
6. **Save**.

### 3) Verify

1. Open the **public** forum index (or the page that matches the position you chose).
2. Hard refresh (**Ctrl+Shift+R**) or an incognito window.
3. If nothing appears:
   - Confirm at least one banner is **Active** and has an **image** saved.
   - Confirm the widget is **enabled** and assigned to the **position** you think you are viewing.
   - If you use guest/page caching, clear **guest page cache** / relevant caches after changes.

### 4) Multiple placements

Create **another** widget (another **Widget key**) with the same definition **Sponsor banners** but a different **Position** — e.g. one on the forum list and one in a sidebar position if your style provides one.

## Advanced: macro only (when you already have `$banners`)

```html
<xf:macro template="bar_sb_public_macros" name="carousel" arg-banners="{$banners}" arg-max_width="400" />
```

## Notes

- Permissions currently require admin `style` permission (simple safe default).
- Allowed file extensions: `jpg`, `jpeg`, `png`, `gif`, `webp`.
- If you later want this editable by non-admin moderators, we can add a dedicated admin permission and narrow ACP access.

## Server error log during first install

If you attempted install before `1.0.1`, you may see transient template/setup errors in **Tools → Server error log**. After a successful install/upgrade, you can **Clear** the log; new errors should not repeat.

### `widget_def_options_bar_sponsor_banners` unknown (`1.0.3` / `1.0.4`)

XenForo’s widget system expects an admin template `widget_def_options_<definition_id>`. **`1.0.3`** avoided that by returning no options template. **`1.0.4`** adds a real options template (max width) so sizing can be configured in ACP without shell access.

### `admin_navigation.barSponsorBanners` showing in the menu (fixed in `1.0.4`)

XenForo expects a phrase titled `admin_navigation.<navigation_id>`. `1.0.4` ships that phrase so the sidebar shows **Sponsor banners** instead of the raw phrase name.

### Banner size on the public site (`1.0.4`)

Each **Sponsor banners** widget has **Max banner width (pixels)** in its widget options. Lower = smaller on screen; **0** = no CSS max-width (image can grow with the container). Different widgets can use different widths if you place multiple widgets.

### Theme CSS (production)

Mobile and layout rules for `.barSponsorBanners` live in `ops/scripts/extra-less-aurora16-source.less`. Deploy with `ops/scripts/xf-deploy-bareefers-extra-less.sh` after editing.
