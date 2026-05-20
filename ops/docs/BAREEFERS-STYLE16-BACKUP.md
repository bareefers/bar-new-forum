# BAR / bareefers.org — style 16 “primary” backup (before old-forum import)

This is the **canonical** snapshot of the live look-and-feel (Aurora child style **16**): templates, style row, style properties, and `extra.less`. Use it **after** importing another forum database so you can put the BAR visual state back.

## What gets saved

| Artifact | Purpose |
|----------|---------|
| `visual.sql.gz` | `REPLACE` dumps (no `DROP TABLE`) for `xf_style`, `xf_style_property`, `xf_style_property_map`, `xf_template` where `style_id=16` |
| `VERIFY.txt` | Row counts per table + compressed size + `extra.less` size in DB |
| `extra.less` | Human-readable export of `public:extra.less` |
| `SHA256SUMS.txt` | `sha256sum` of the important files |
| `RESTORE.txt` | Copy-paste restore command |

## Create a backup (recommended)

From your Windows repo, with WSL and SSH host `bareefers` configured:

```bash
wsl bash xenforo/scripts/xf-deploy-bareefers-style16-backup.sh
```

That copies the helper scripts to the server and runs `xf-backup-bar-style16-visual.sh`.

Or **on the server**:

```bash
sudo bash /path/to/barcode/xenforo/scripts/xf-backup-bar-style16-visual.sh /var/www/bareefers.org/forum
```

### Optional: same snapshot inside `xf-final-cutover.sh`

The **import** script does **not** run the style-16 primary backup unless you turn it on. After copying the three helpers to `/tmp` on the server, add to `xf-final-cutover.env` (or export before cutover):

```bash
RUN_BAR_STYLE16_PRIMARY_SNAPSHOT=1
BAR_STYLE16_PRIMARY_BACKUP_SCRIPT=/tmp/xf-backup-bar-style16-visual.sh
```

Then `sudo xf-final-cutover.sh /root/xf-final-cutover.env` will run the BAR snapshot **before** it exports style ZIPs and imports the old DB. **Post-import** style restore is still `xf-restore-bar-style16-visual.sh` if you need to re-apply this snapshot on top of imported data.

## Where files land

- **Working snapshot:** `/var/tmp/bar-style16-snapshots/BAR-style16-visual-YYYYMMDD-HHMMSS/`
- **Durable copy on the server:** `/home/xf-emergency-rollback/bar-style16-primary/<same-folder-name>/` plus symlink **`latest`** → that folder.

**Important:** Copy the snapshot folder **off the server** (SCP/rsync to your PC or object storage) so a future disk wipe or bad import cannot erase the only copy.

Example:

```bash
scp -r bareefers:/home/xf-emergency-rollback/bar-style16-primary/latest ./bar-style16-primary-backup
```

## Restore after a DB import

On the server (path from `RESTORE.txt` or `latest`):

```bash
sudo bash xenforo/scripts/xf-restore-bar-style16-visual.sh /path/to/visual.sql.gz
```

The restore script imports the gzip, rebuilds XenForo style cache, and flushes Redis CSS DB **7**. Hard-refresh the site.

## Verify integrity

```bash
gzip -t visual.sql.gz
sha256sum -c SHA256SUMS.txt
cat VERIFY.txt
```

## Environment variables

| Variable | Default | Meaning |
|----------|---------|---------|
| `BAR_STYLE16_SNAPSHOT_PARENT` | `/var/tmp/bar-style16-snapshots` | Where timestamped folders are created |
| `BAR_STYLE16_PRIMARY_ARCHIVE` | `/home/xf-emergency-rollback/bar-style16-primary` | Second full copy + `latest` symlink |

## If something fails

- **`extra.less` too small:** Template missing or failed export; fix in ACP first, then re-run backup.
- **`visual.sql.gz` too small:** PHP sanity check fails; check DB connectivity and style id **16**.
- **Permissions:** Snapshot directory must allow `www-data` to write `extra.less` (script `chown`s the stamp folder when run as root).

## Lighter option

`xf-backup-bar-extra-less-snapshot.sh` — only the `extra.less` row + `.less` file. Not a full visual restore.
