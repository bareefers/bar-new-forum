# XenForo Video Transcode Worker

This adds an external FFmpeg-based worker for XenForo video uploads.

It is designed for a local-storage XenForo install on the BAR server:

- XenForo root: `/var/www/bareefers.org/forum`
- internal data: `/var/www/bareefers.org/forum/internal_data`
- direct DB access over SSH using the existing `BC_XF_DB_*` environment values

## What it does

- scans XenForo attachment rows for video files
- queues new work in a local SQLite database under `internal_data/video_queue`
- runs `ffprobe` and `ffmpeg`
- converts source video to H.264/AAC MP4
- generates a poster JPG
- backs up the original source file
- updates `xf_attachment_data` so XenForo serves the processed file

## What it does not do yet

- it does not patch XenForo/XFMG internals
- it assumes local filesystem attachment storage, not remote/object storage
- it updates `xf_attachment_data`, but does not attempt XFMG-specific queue-state cleanup
- it is safest for installs where attachments are served from the attachment data row and local disk

## Files

- Worker CLI: `server/xenforo-video-transcode/cli.js`
- Queue/state DB: `internal_data/video_queue/queue.sqlite3`
- Original backups: `internal_data/video_queue/originals/`
- Work temp files: `internal_data/video_queue/work/`
- Posters: `internal_data/video_transcoded/`

## Environment variables

Add these to the forum worker environment:

```bash
BC_XF_ROOT=/var/www/bareefers.org/forum
BC_XF_INTERNAL_DATA_ROOT=/var/www/bareefers.org/forum/internal_data
BC_XF_DATA_ROOT=/var/www/bareefers.org/forum/data
BC_XF_DB_PREFIX=xf_

BC_XF_VIDEO_QUEUE_ROOT=/var/www/bareefers.org/forum/internal_data/video_queue
BC_XF_VIDEO_TRANSCODE_ROOT=/var/www/bareefers.org/forum/internal_data/video_transcoded
BC_XF_VIDEO_FFMPEG=/usr/bin/ffmpeg
BC_XF_VIDEO_FFPROBE=/usr/bin/ffprobe
BC_XF_VIDEO_TRANSCODE_ALL=false
BC_XF_VIDEO_OUTPUT_HEIGHT=1080
BC_XF_VIDEO_CRF=23
BC_XF_VIDEO_PRESET=veryfast
BC_XF_VIDEO_MAX_ATTEMPTS=3
BC_XF_VIDEO_WORKER_INTERVAL_MS=5000
BC_XF_VIDEO_FFMPEG_TIMEOUT_SECONDS=1800
BC_XF_VIDEO_FFPROBE_TIMEOUT_SECONDS=120
BC_XF_VIDEO_CLEANUP_HOURS=24
```

The worker also depends on the existing XenForo DB credentials:

```bash
BC_XF_DB_SSH_CREDENTIALS=host,sshPort,user,password,mysqlPort
BC_XF_DB_CREDENTIALS=database,user,password
```

## Commands

From the repo root:

```bash
cd server
node xenforo-video-transcode/cli.js discover
node xenforo-video-transcode/cli.js process-next
node xenforo-video-transcode/cli.js worker
node xenforo-video-transcode/cli.js list
node xenforo-video-transcode/cli.js list failed
node xenforo-video-transcode/cli.js retry 12
node xenforo-video-transcode/cli.js cleanup
```

## Suggested systemd service

Use the provided template:

- `xenforo/scripts/xenforo-video-transcode.service`

Install it on the XenForo server, set the repo path and environment file, then:

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now xenforo-video-transcode
sudo systemctl status xenforo-video-transcode
```

## Operational notes

- Run the worker with concurrency `1` on the current VPS.
- Keep the XenForo upload request limits high enough to accept the original file.
- If a job fails repeatedly, inspect `last_error` in the queue DB and the worker logs.
- Original uploads are backed up before replacement so failed output can be retried.

## Validation queries

Check queued jobs:

```sql
SELECT job_id, attachment_id, data_id, status, attempts, last_error
FROM jobs
ORDER BY job_id DESC;
```

Check updated XenForo attachment rows:

```sql
SELECT data_id, filename, file_hash, file_path, file_size, width, height
FROM xf_attachment_data
WHERE data_id = ?;
```
