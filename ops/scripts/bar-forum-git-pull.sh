#!/usr/bin/env bash
# Update bar-new-forum clone on bareefers.org (run on server).
#   sudo bash /var/www/bareefers.org/bar-new-forum/ops/scripts/bar-forum-git-pull.sh
set -euo pipefail

REPO="${BAR_NEW_FORUM_ROOT:-/var/www/bareefers.org/bar-new-forum}"

if [[ ! -d "$REPO/.git" ]]; then
	echo "Missing git repo: $REPO" >&2
	exit 1
fi

cd "$REPO"
git fetch origin
git pull --ff-only origin main
echo "Updated $(git rev-parse --short HEAD) on branch $(git branch --show-current)"
