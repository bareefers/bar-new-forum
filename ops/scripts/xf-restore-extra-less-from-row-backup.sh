#!/usr/bin/env bash
# Restore style 16 public extra.less from xf-backup-template-row.php output (.sql.gz).
# Does NOT run the full-table dump (which contains DROP TABLE).
set -euo pipefail
DUMP_GZ="${1:?usage: $0 /var/tmp/xf-template-s16-extra.less-public-YYYYMMDD-HHMMSS.sql.gz}"
TMP_INS="$(mktemp)"
trap 'rm -f "$TMP_INS"' EXIT
gunzip -c "$DUMP_GZ" | grep '^INSERT INTO `xf_template` VALUES' >"$TMP_INS" || {
  echo "No INSERT line found in $DUMP_GZ" >&2
  exit 1
}
TID="$(mysql baraforo -N -e "SELECT template_id FROM xf_template WHERE style_id=16 AND title='extra.less' AND type='public' LIMIT 1")"
if [[ -z "${TID}" ]]; then
  echo "No current extra.less row for style 16" >&2
  exit 1
fi
mysql baraforo -e "DELETE FROM xf_template WHERE template_id=${TID};"
mysql baraforo <"$TMP_INS"
echo "OK: restored extra.less from $(basename "$DUMP_GZ") (was template_id ${TID})"
