#!/usr/bin/env bash
# XenForo 2.3+: SV ExpiringUserUpgrades calls removed XF\Mail\Mail::getMessageObject().
# Symfony Mail uses getEmailObject() (Symfony\Component\Mime\Email) which still has addBcc().
#
# Usage (on forum server, as root or with write access to forum tree):
#   sudo bash xf-patch-sv-exup-xf23-mail.sh [/path/to/forum]
set -euo pipefail
ROOT="${1:-/var/www/bareefers.org/forum}"
FILE="$ROOT/src/addons/SV/ExpiringUserUpgrades/XF/Repository/UserUpgrade.php"
if [[ ! -f "$FILE" ]]; then
  echo "Not found: $FILE" >&2
  exit 1
fi
if ! grep -q 'getMessageObject()' "$FILE"; then
  if grep -q 'getEmailObject()->addBcc' "$FILE"; then
    echo "Already patched."
    exit 0
  fi
  echo "No getMessageObject() in $FILE (unexpected)" >&2
  exit 1
fi
cp -a "$FILE" "/var/tmp/UserUpgrade.php.bak-$(date +%Y%m%d-%H%M%S)"
sed -i 's/getMessageObject()/getEmailObject()/' "$FILE"
echo "OK: patched $FILE"
