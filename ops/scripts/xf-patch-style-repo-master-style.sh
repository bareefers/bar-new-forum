#!/usr/bin/env bash

set -Eeuo pipefail

TARGET="/var/www/bareefers.org/forum/src/XF/Repository/StyleRepository.php"
BACKUP="${TARGET}.bak.$(date +%Y%m%d-%H%M%S)"

cp "$TARGET" "$BACKUP"

python3 - <<'PY'
from pathlib import Path

path = Path("/var/www/bareefers.org/forum/src/XF/Repository/StyleRepository.php")
text = path.read_text()
old = """\t\t$style = $this->em->create(Style::class);
\t\t$style->setTrusted('style_id', 0);
\t\t$style->setTrusted('parent_list', [0]);
\t\t$style->setTrusted('parent_id', -1);
\t\t$style->title = \\XF::phrase('master_style');
\t\t$style->setReadOnly(true);
"""
new = """\t\t$style = $this->em->create(Style::class);
\t\t$style->setTrusted('style_id', 0);
\t\t$style->setTrusted('parent_list', [0]);
\t\t$style->setTrusted('parent_id', -1);
\t\t$style->setTrusted('description', '');
\t\t$style->setTrusted('properties', []);
\t\t$style->setTrusted('assets', []);
\t\t$style->setTrusted('effective_assets', []);
\t\t$style->setTrusted('last_modified_date', \\XF::$time);
\t\t$style->setTrusted('enable_variations', false);
\t\t$style->setTrusted('user_selectable', false);
\t\t$style->setTrusted('designer_mode', null);
\t\t$style->title = \\XF::phrase('master_style');
\t\t$style->setReadOnly(true);
"""
if old not in text:
    raise SystemExit("target snippet not found")
path.write_text(text.replace(old, new, 1))
PY

php -l "$TARGET"
echo "$BACKUP"
