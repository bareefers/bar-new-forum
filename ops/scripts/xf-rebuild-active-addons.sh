#!/usr/bin/env bash

set -Eeuo pipefail

FORUM_ROOT="${1:-/var/www/bareefers.org/forum}"

addons=(
    "Andy/ConversationSearch"
    "DL6/WNTPL"
    "PB/SimpleStats"
    "SV/ExpiringUserUpgrades"
    "SV/StandardLib"
    "XFES"
    "XFMG"
    "XFRM"
)

for addon in "${addons[@]}"; do
    echo "REBUILD ${addon}"
    sudo -u www-data php "${FORUM_ROOT}/cmd.php" xf:addon-rebuild "${addon}"
done
