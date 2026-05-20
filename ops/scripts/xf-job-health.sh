#!/usr/bin/env bash
#
# XenForo job queue + cron runner sanity (read-only by default).
# Use before/after DB restore, DNS cutover, or when ACP warns about outstanding jobs.
#
# Run ON the forum server (needs sudo mysql + syslog/journal read):
#   sudo bash xenforo/scripts/xf-job-health.sh
#   sudo bash xenforo/scripts/xf-job-health.sh --db baraforo --forum /var/www/bareefers.org/forum
#
# From Windows (script on stdin; args after bash -s -- go to this script):
#   wsl bash -lc "ssh bareefers 'sudo bash -s --'" < xenforo/scripts/xf-job-health.sh
#   wsl bash -lc "ssh bareefers 'sudo bash -s -- --run-jobs'" < xenforo/scripts/xf-job-health.sh
#
# Optional: one-shot job drain after checks (not required for read-only health):
#   sudo bash xenforo/scripts/xf-job-health.sh --run-jobs
#
set -euo pipefail

if [[ "${EUID:-0}" -ne 0 ]]; then
	exec sudo bash "$0" "$@"
fi

_sn="${BASH_SOURCE[0]:-$0}"
case "${_sn}" in '' | '-' | bash | */bash) SCRIPT_NAME='xf-job-health.sh' ;; *) SCRIPT_NAME="$(basename "${_sn}")" ;; esac
DB="${XF_DB:-baraforo}"
FORUM="${XF_FORUM_ROOT:-/var/www/bareefers.org/forum}"
RUN_JOBS=0

while [[ $# -gt 0 ]]; do
	case "$1" in
		--db)
			DB="${2:?}"
			shift 2
			;;
		--forum)
			FORUM="${2:?}"
			shift 2
			;;
		--run-jobs)
			RUN_JOBS=1
			shift
			;;
		-h | --help)
			sed -n '1,25p' "$0"
			exit 0
			;;
		--)
			shift
			break
			;;
		*)
			printf '%s: unknown option %q\n' "$SCRIPT_NAME" "$1" >&2
			exit 1
			;;
	esac
done

echo ""
echo "=== ${SCRIPT_NAME} $(date -u '+%Y-%m-%d %H:%M:%S UTC') ==="
echo "MySQL DB: ${DB}"
echo "Forum root: ${FORUM}"
echo ""

if [[ ! -d "${FORUM}" ]]; then
	printf '%s: forum root not found: %s\n' "$SCRIPT_NAME" "${FORUM}" >&2
	exit 1
fi

echo "--- 1) Pending job count (xf_job) ---"
PENDING="$(sudo mysql "${DB}" -Nse "SELECT COUNT(*) FROM xf_job" 2>/dev/null || true)"
if [[ -z "${PENDING}" ]]; then
	printf '%s: could not query xf_job (wrong DB name or MySQL auth?)\n' "$SCRIPT_NAME" >&2
	exit 1
fi
echo "pending_jobs=${PENDING}"
echo ""

echo "--- 2) Next / oldest queued jobs (up to 12 rows) ---"
sudo mysql "${DB}" -e "
SELECT unique_key,
       execute_class,
       trigger_date,
       FROM_UNIXTIME(trigger_date) AS trigger_utc
FROM xf_job
ORDER BY trigger_date ASC
LIMIT 12;
" 2>/dev/null || true
echo ""

echo "--- 3) Recent OS cron executions for xf:run-jobs (last ~40 matching lines) ---"
if command -v journalctl >/dev/null 2>&1; then
	lines="$(sudo journalctl --since "45 min ago" --no-pager 2>/dev/null | grep -F 'xf:run-jobs' | tail -n 40 || true)"
	if [[ -n "${lines}" ]]; then
		printf '%s\n' "${lines}"
	else
		sudo grep 'xf:run-jobs' /var/log/syslog 2>/dev/null | tail -n 40 || true
	fi
else
	sudo grep 'xf:run-jobs' /var/log/syslog 2>/dev/null | tail -n 40 || true
fi
echo ""

echo "--- Installed cron snippets (expected) ---"
for f in /etc/cron.d/xf-jobs /etc/cron.d/xf-jobs-fast; do
	if [[ -f "${f}" ]]; then
		echo "# ${f}"
		sudo sed -n '1,5p' "${f}"
	else
		echo "# ${f} (missing)"
	fi
done
echo ""

if [[ "${RUN_JOBS}" -eq 1 ]]; then
	echo "--- One-shot: xf:run-jobs (max 30s) ---"
	sudo -u www-data bash -lc "cd '${FORUM}' && /usr/bin/php cmd.php xf:run-jobs --max-execution-time=30"
	echo ""
	echo "--- Pending count after run ---"
	sudo mysql "${DB}" -Nse "SELECT COUNT(*) FROM xf_job"
	echo ""
fi

echo "=== ${SCRIPT_NAME} done ==="
echo ""
