#!/usr/bin/env bash

set -Eeuo pipefail

DB_HOST="${DB_HOST:-localhost}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${DB_NAME:-baraforo}"
DB_USER="${DB_USER:-barausr}"
DB_PASSWORD="${DB_PASSWORD:-}"
STYLE_ID="${STYLE_ID:-15}"

if [[ -z "${DB_PASSWORD}" ]]; then
  echo "DB_PASSWORD is required" >&2
  exit 1
fi

backup_file="/var/tmp/aurora-extra-footer-template-${STYLE_ID}-$(date +%Y%m%d-%H%M%S).sql"

MYSQL_PWD="${DB_PASSWORD}" mysqldump \
  -h "${DB_HOST}" \
  -P "${DB_PORT}" \
  -u "${DB_USER}" \
  --no-tablespaces \
  "${DB_NAME}" \
  xf_template \
  --where="style_id = ${STYLE_ID} AND title = 'extra_footer'" \
  > "${backup_file}"

encoded_template="$(
  MYSQL_PWD="${DB_PASSWORD}" mysql \
    --batch \
    --raw \
    --skip-column-names \
    -Nse "SELECT TO_BASE64(template) FROM xf_template WHERE style_id = ${STYLE_ID} AND title = 'extra_footer'" \
    -h "${DB_HOST}" \
    -P "${DB_PORT}" \
    -u "${DB_USER}" \
    "${DB_NAME}"
)"

if [[ -z "${encoded_template}" ]]; then
  echo "extra_footer template not found" >&2
  exit 1
fi

updated_template_b64="$(
  TEMPLATE_B64="${encoded_template}" python3 <<'PY'
import base64
import os

template = base64.b64decode(os.environ["TEMPLATE_B64"]).decode("utf-8")

old_about = """\t\t\t\t<xf:elseif is=\"property('dt_extra_footer_first_column') == 'option9'\" />\n\t\t\t\t\t<div class=\"pre-footer--content\">\n\t\t\t\t\t\t<h3>{{ property('dt_extra_footer_custom_html_title') }}</h3>\n\t\t\t\t\t\t<div class=\"pre-footer-html--content\">\n\t\t\t\t\t\t\t{{ property('dt_extra_footer_custom_html') }}\n\t\t\t\t\t\t</div>\n\t\t\t\t\t</div>\n"""
new_about = """\t\t\t\t<xf:elseif is=\"property('dt_extra_footer_first_column') == 'option9'\" />\n\t\t\t\t\t<div class=\"pre-footer--content\">\n\t\t\t\t\t\t<h3>About Us</h3>\n\t\t\t\t\t\t<div class=\"pre-footer-html--content\">\n\t\t\t\t\t\t\tBay Area Reefers is a community for saltwater aquarium hobbyists across the Bay Area. Join local discussions, events, build threads, DBTC, and club resources from San Francisco to San Jose.\n\t\t\t\t\t\t</div>\n\t\t\t\t\t</div>\n"""

old_links = """\t\t\t\t<xf:elseif is=\"property('dt_extra_footer_second_column') == 'option10'\" />\n\t\t\t\t\t<div class=\"pre-footer--content -links\">\n\t\t\t\t\t\t<h3>{{ property('dt_extra_footer_link_1_title') }}</h3>\n\t\t\t\t\t\t<ul class=\"pre-footer--links\">\n\t\t\t\t\t\t\t{{ property('dt_extra_footer_link_1') }}\n\t\t\t\t\t\t</ul>\n\t\t\t\t\t</div>\n"""
new_links = """\t\t\t\t<xf:elseif is=\"property('dt_extra_footer_second_column') == 'option10'\" />\n\t\t\t\t\t<div class=\"pre-footer--content -links\">\n\t\t\t\t\t\t<h3>Club Links</h3>\n\t\t\t\t\t\t<ul class=\"pre-footer--links\">\n\t\t\t\t\t\t\t<ul class='footer-list'>\n<li><i class=\"fas fa-angle-right\"></i><a href=\"/forum/threads/how-do-i-become-a-supporting-member.14130/\">Become a supporting member</a></li>\n<li><i class=\"fas fa-angle-right\"></i><a href=\"/forum/index.php?threads/bar-sponsors.14198/\">BAR sponsors</a></li>\n<li><i class=\"fas fa-angle-right\"></i><a href=\"/forum/forums/events-announcements/\">Events &amp; announcements</a></li>\n</ul>\n\t\t\t\t\t\t</ul>\n\t\t\t\t\t</div>\n"""

if old_about not in template:
    raise SystemExit("about-us block not found")
if old_links not in template:
    raise SystemExit("club-links block not found")

template = template.replace(old_about, new_about, 1)
template = template.replace(old_links, new_links, 1)

print(base64.b64encode(template.encode("utf-8")).decode("ascii"))
PY
)"

MYSQL_PWD="${DB_PASSWORD}" mysql \
  -h "${DB_HOST}" \
  -P "${DB_PORT}" \
  -u "${DB_USER}" \
  "${DB_NAME}" \
  -e "UPDATE xf_template SET template = FROM_BASE64('${updated_template_b64}') WHERE style_id = ${STYLE_ID} AND title = 'extra_footer';"

echo "${backup_file}"
