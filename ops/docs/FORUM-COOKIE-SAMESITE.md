# Fix: Logged in on forum but app still shows "Log in required" (401)

## Cause

The BARcode app runs on **barcode2-0-omega.vercel.app**. When it calls **bareefers.org/bc/api**, that request is **cross-origin**. The API authenticates using XenForo session cookies (`xfc_session`, `xfc_user`). Browsers only send cookies on cross-origin requests if the cookie has **SameSite=None** and **Secure**. XenForo’s default is effectively **SameSite=Lax**, so the forum cookie is **not** sent when the request comes from the Vercel app. The API gets no cookie → 401.

## Fix (on bareefers.org forum server)

Set XenForo’s cookies to **SameSite=None** so they are sent with requests from the Vercel app.

1. On the **bareefers.org** server, open XenForo’s config file.  
   Common path: **`src/config.php`** in the XenForo install (or the path your host uses for the forum config).

2. Add (or edit) the cookie SameSite setting:

   ```php
   $config['cookie']['samesite'] = 'None';
   ```

   The site must be served over **HTTPS** so the cookie can be `Secure` (XenForo does this when the request is HTTPS).

3. Save the file. No restart usually needed; the next request will use the new setting.

4. **User must log in again**: have the user clear forum cookies for bareefers.org (or log out and log in again) so the new cookie is set with SameSite=None. Then in a new tab go to **https://bareefers.org/forum/login/** (type the URL), log in, then return to the app and refresh.

## Verify

- In browser DevTools → Application (Chrome) or Storage (Firefox) → Cookies → **bareefers.org**, the forum session cookie should show **SameSite=None** and **Secure**.
- Reload https://barcode2-0-omega.vercel.app; it should no longer show "Log in required" if you’re logged in on the forum.

## Reference

- XenForo config: [Config.php options](https://xenforo.com/docs/xf2/config/)
- SameSite=None requires Secure (HTTPS): [Chromium SameSite FAQ](https://chromium.org/updates/same-site/faq)
