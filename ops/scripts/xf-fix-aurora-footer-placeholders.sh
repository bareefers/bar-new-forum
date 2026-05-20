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

backup_file="/var/tmp/aurora-footer-style-${STYLE_ID}-$(date +%Y%m%d-%H%M%S).sql"

MYSQL_PWD="${DB_PASSWORD}" mysqldump \
  -h "${DB_HOST}" \
  -P "${DB_PORT}" \
  -u "${DB_USER}" \
  --no-tablespaces \
  "${DB_NAME}" \
  xf_style_property \
  --where="style_id = ${STYLE_ID} AND property_name IN ('dt_extra_footer_custom_html','dt_extra_footer_custom_html_title','dt_extra_footer_link_1','dt_extra_footer_link_1_title','dt_extra_footer_link_2','dt_extra_footer_link_2_title','dt_extra_footer_facebook','dt_extra_footer_twitter','dt_extra_footer_instagram','dt_extra_footer_youtube','dt_guest_message_content')" \
  > "${backup_file}"

read -r -d '' SQL <<'EOF' || true
UPDATE xf_style_property
SET property_value = JSON_QUOTE('About Us')
WHERE style_id = __STYLE_ID__ AND property_name = 'dt_extra_footer_custom_html_title';

UPDATE xf_style_property
SET property_value = JSON_QUOTE('Bay Area Reefers is a community for saltwater aquarium hobbyists across the Bay Area. Join local discussions, events, build threads, DBTC, and club resources from San Francisco to San Jose.')
WHERE style_id = __STYLE_ID__ AND property_name = 'dt_extra_footer_custom_html';

UPDATE xf_style_property
SET property_value = JSON_QUOTE('Bay Area Reefers is a community for saltwater aquarium hobbyists across the Bay Area. Join local discussions, events, build threads, DBTC, and club resources from San Francisco to San Jose.')
WHERE style_id = __STYLE_ID__ AND property_name = 'dt_guest_message_content';

UPDATE xf_style_property
SET property_value = JSON_QUOTE('Club Links')
WHERE style_id = __STYLE_ID__ AND property_name = 'dt_extra_footer_link_1_title';

UPDATE xf_style_property
SET property_value = JSON_QUOTE('<ul class=''footer-list''>\n<li><i class=\"fas fa-angle-right\"></i><a href=\"/forum/threads/how-do-i-become-a-supporting-member.14130/\">Become a supporting member</a></li>\n<li><i class=\"fas fa-angle-right\"></i><a href=\"/forum/index.php?threads/bar-sponsors.14198/\">BAR sponsors</a></li>\n<li><i class=\"fas fa-angle-right\"></i><a href=\"/forum/forums/events-announcements/\">Events & announcements</a></li>\n</ul>')
WHERE style_id = __STYLE_ID__ AND property_name = 'dt_extra_footer_link_1';

UPDATE xf_style_property
SET property_value = JSON_QUOTE('Helpful Links')
WHERE style_id = __STYLE_ID__ AND property_name = 'dt_extra_footer_link_2_title';

UPDATE xf_style_property
SET property_value = JSON_QUOTE('<ul class=''footer-list''>\n<li><i class=\"fas fa-angle-right\"></i><a href=\"/forum/whats-new/\">What''s new</a></li>\n<li><i class=\"fas fa-angle-right\"></i><a href=\"/forum/members/\">Members</a></li>\n<li><i class=\"fas fa-angle-right\"></i><a href=\"/forum/register/\">Register</a></li>\n</ul>')
WHERE style_id = __STYLE_ID__ AND property_name = 'dt_extra_footer_link_2';

UPDATE xf_style_property
SET property_value = JSON_QUOTE('')
WHERE style_id = __STYLE_ID__ AND property_name IN ('dt_extra_footer_facebook','dt_extra_footer_twitter','dt_extra_footer_instagram','dt_extra_footer_youtube');
EOF

SQL="${SQL//__STYLE_ID__/${STYLE_ID}}"

MYSQL_PWD="${DB_PASSWORD}" mysql \
  -h "${DB_HOST}" \
  -P "${DB_PORT}" \
  -u "${DB_USER}" \
  "${DB_NAME}" \
  -e "${SQL}"

echo "${backup_file}"
