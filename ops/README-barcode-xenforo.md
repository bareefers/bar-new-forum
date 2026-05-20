# XenForo (bareefers.org) — repo workspace

This folder holds **XenForo-related ops** for Bay Area Reefers: shell/PHP/Python helpers, nginx snippets, migration notes, and large **`BAR_xenforo_Update/`** exports.

| Path | Contents |
|------|----------|
| `scripts/` | `xf-*` deploy/repair/backup scripts, cutover helpers, `extra-less-aurora16-source.less`, video transcode unit file, etc. Run from repo root, e.g. `wsl bash xenforo/scripts/xf-deploy-bareefers-extra-less.sh`. |
| `docs/` | Operator docs (style backup, migration option B, video transcode, forum cookie notes, HTML/LESS snapshots). |
| `BAR_xenforo_Update/` | XenForo/add-on zip exports and user CSVs (large; not required for day-to-day dev). |

**Barcode app code** that talks to XenForo’s API stays under `server/` (`xenforo-api.js`, `xenforo-passport-strategy.js`, `server/xenforo-video-transcode/`).

See `.cursor/rules/bareefers-org-server.mdc` for live-server workflow.
