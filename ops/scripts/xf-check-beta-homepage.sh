#!/usr/bin/env bash

set -Eeuo pipefail

python3 <<'PY'
import urllib.request

request = urllib.request.Request(
    'http://127.0.0.1/forum/',
    headers={
        'Host': 'beta.bareefers.org',
        'User-Agent': 'Mozilla/5.0',
        'Cache-Control': 'no-cache'
    }
)
with urllib.request.urlopen(request) as response:
    html = response.read().decode('utf-8', 'ignore')

for needle in [
    'About Us',
    'Bay Area Reefers is a community for saltwater aquarium hobbyists across the Bay Area.',
    'Club Links',
    'BAR sponsors',
    'Events &amp; announcements',
    'Volunteer thread for 4/26 frag swap!',
    'Aurora theme sets the standard',
    'dohtheme.com'
]:
    print(f'{needle}: {needle in html}')

if 'About Us' in html:
    idx = html.index('About Us')
    print('--- ABOUT US SNIPPET ---')
    print(html[max(0, idx - 300):idx + 1400])
else:
    print('--- RESPONSE START ---')
    print(html[:1500])

if 'Volunteer thread for 4/26 frag swap!' in html:
    idx = html.index('Volunteer thread for 4/26 frag swap!')
    print('--- FEATURED SNIPPET ---')
    print(html[max(0, idx - 300):idx + 900])
PY
