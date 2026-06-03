/**
 * Mobile layout audit for bareefers.org (Playwright Chromium).
 *
 *   cd .pw-probe && NODE_PATH="$PWD/node_modules" node ../xenforo/scripts/bareefers-mobile-audit.js
 *
 * Writes a JSON summary to stdout; exit 1 if any page+viewport has failures.
 */

const { chromium } = require('playwright');

const PAGES = [
  { id: 'home', url: 'https://bareefers.org/forum/' },
  { id: 'whats_new', url: 'https://bareefers.org/forum/whats-new/' },
  { id: 'whats_new_posts', url: 'https://bareefers.org/forum/whats-new/posts/' },
  { id: 'forums', url: 'https://bareefers.org/forum/forums/' },
  { id: 'forum_category', url: 'https://bareefers.org/forum/forums/tank-journals.45/' },
  { id: 'thread', url: 'https://bareefers.org/forum/threads/welcome-to-bar.14200/' },
  { id: 'login', url: 'https://bareefers.org/forum/login/' },
  { id: 'register', url: 'https://bareefers.org/forum/register/' },
  { id: 'search', url: 'https://bareefers.org/forum/search/' },
  { id: 'members', url: 'https://bareefers.org/forum/members/' },
  { id: 'conversations', url: 'https://bareefers.org/forum/conversations/' },
  { id: 'account_upgrades', url: 'https://bareefers.org/forum/account/upgrades' },
];

const VIEWPORTS = [390, 768];

function auditEvaluate(viewportWidth) {
  const vw = viewportWidth;
  const OVERFLOW_SLACK = 5;
  const CELL_MIN_FRAC = 0.35;
  const issues = [];

  const add = (check, detail) => issues.push({ check, ...detail });

  const doc = document.documentElement;
  const docScrollW = doc.scrollWidth;
  if (docScrollW > vw + OVERFLOW_SLACK) {
    add('doc_horizontal_overflow', {
      scrollWidth: docScrollW,
      viewportWidth: vw,
      excess: docScrollW - vw,
    });
  }

  const pickBox = (el) => {
    if (!el) return null;
    const r = el.getBoundingClientRect();
    return {
      offsetW: el.offsetWidth,
      clientW: el.clientWidth,
      scrollW: el.scrollWidth,
      rectW: Math.round(r.width),
      rectLeft: Math.round(r.left),
      rectRight: Math.round(r.right),
    };
  };

  const titleWrapped = (titleEl) => {
    if (!titleEl) return false;
    const inner = titleEl.querySelector('a') || titleEl;
    return inner.scrollHeight > inner.clientHeight + 6;
  };

  const mainCol =
    document.querySelector('.p-body-main') || document.querySelector('.p-body-content');
  const threadRow = mainCol
    ? mainCol.querySelector('.structItem--thread')
    : document.querySelector('.structItem--thread');
  const contentRow = mainCol
    ? mainCol.querySelector('.contentRow')
    : document.querySelector('.contentRow');
  const listRow = threadRow || contentRow;

  if (listRow) {
    const isThread = listRow.classList.contains('structItem--thread');
    const mainSel = isThread ? '.structItem-cell--main' : '.contentRow-main';
    const titleSel = isThread ? '.structItem-title' : '.contentRow-title';
    const main = listRow.querySelector(mainSel);
    const title = listRow.querySelector(titleSel);
    const mainBox = pickBox(main);
    const titleBox = pickBox(title);

    if (mainBox && mainBox.clientW > 0 && mainBox.clientW < vw * CELL_MIN_FRAC) {
      add('list_main_too_narrow', {
        rowType: isThread ? 'structItem--thread' : 'contentRow',
        mainClientW: mainBox.clientW,
        minExpected: Math.round(vw * CELL_MIN_FRAC),
        viewportWidth: vw,
      });
    }
    if (titleBox && titleBox.rectRight > vw + OVERFLOW_SLACK) {
      add('list_title_past_viewport', {
        rowType: isThread ? 'structItem--thread' : 'contentRow',
        titleRectRight: titleBox.rectRight,
        viewportWidth: vw,
      });
    }
    if (title && titleWrapped(title)) {
      add('list_title_wrapped', {
        rowType: isThread ? 'structItem--thread' : 'contentRow',
        sample: (title.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 72),
      });
    }
  }

  const pBody = document.querySelector('.p-body');
  if (pBody) {
    const overflowEls = [];
    const walk = (root, depth) => {
      if (depth > 14 || overflowEls.length >= 12) return;
      const nodes = root.children.length ? Array.from(root.children) : [root];
      for (const el of nodes) {
        if (el.nodeType !== 1) continue;
        const sw = el.scrollWidth;
        const cw = el.clientWidth;
        if (cw > 0 && sw > cw + 2) {
          if (el.closest('.p-breadcrumbs')) continue;
          const r = el.getBoundingClientRect();
          const excess = sw - cw;
          if (
            r.width > 8 &&
            r.height > 8 &&
            (r.right > vw + OVERFLOW_SLACK || excess > OVERFLOW_SLACK)
          ) {
            const tag = el.tagName.toLowerCase();
            const cls =
              typeof el.className === 'string' && el.className
                ? '.' + el.className.trim().split(/\s+/).slice(0, 3).join('.')
                : '';
            overflowEls.push({
              tag,
              class: cls.slice(0, 120),
              scrollW: sw,
              clientW: cw,
              excess,
              rectRight: Math.round(r.right),
            });
          }
        }
        if (el.children.length) walk(el, depth + 1);
      }
    };
    walk(pBody, 0);
    if (overflowEls.length) {
      add('p_body_inner_overflow', {
        count: overflowEls.length,
        elements: overflowEls.slice(0, 8),
      });
    }
  }

  const header = document.querySelector('.p-body-header');
  if (header) {
    const hb = pickBox(header);
    if (hb && (hb.scrollW > hb.clientW + 2 || hb.rectRight > vw + OVERFLOW_SLACK)) {
      add('p_body_header_overflow', {
        scrollW: hb.scrollW,
        clientW: hb.clientW,
        rectRight: hb.rectRight,
        viewportWidth: vw,
      });
    }
  }

  const sponsorRoot =
    document.querySelector('.barSponsorBanners') ||
    document.querySelector('.p-body-header .barSponsorBanners');
  if (sponsorRoot) {
    const sb = pickBox(sponsorRoot);
    if (sb && (sb.scrollW > sb.clientW + 2 || sb.rectRight > vw + OVERFLOW_SLACK)) {
      add('sponsor_banner_overflow', {
        scrollW: sb.scrollW,
        clientW: sb.clientW,
        rectRight: sb.rectRight,
        viewportWidth: vw,
      });
    }
    const items = sponsorRoot.querySelectorAll('.barSponsorBanners-item, .barSponsorBanners-item a, img');
    for (const item of items) {
      const ib = pickBox(item);
      if (ib && ib.rectRight > vw + OVERFLOW_SLACK) {
        add('sponsor_item_past_viewport', {
          rectRight: ib.rectRight,
          viewportWidth: vw,
          class: (item.className || '').toString().slice(0, 80),
        });
        break;
      }
    }
  }

  return {
    finalUrl: location.href,
    template: document.body.getAttribute('data-template') || null,
    docScrollWidth: docScrollW,
    viewportWidth: vw,
    hasThreadRow: !!threadRow,
    hasContentRow: !!contentRow,
    issueCount: issues.length,
    issues,
  };
}

async function auditPage(page, pageDef, width) {
  await page.setViewportSize({ width, height: 900 });
  let gotoError = null;
  try {
    await page.goto(pageDef.url, { waitUntil: 'domcontentloaded', timeout: 60000 });
    await page.waitForTimeout(800);
  } catch (e) {
    gotoError = e.message;
  }

  if (gotoError) {
    return {
      page: pageDef.id,
      url: pageDef.url,
      viewport: width,
      ok: false,
      gotoError,
      issues: [{ check: 'navigation_failed', message: gotoError }],
    };
  }

  const data = await page.evaluate(auditEvaluate, width);
  const ok = data.issueCount === 0;
  return {
    page: pageDef.id,
    url: pageDef.url,
    viewport: width,
    ok,
    finalUrl: data.finalUrl,
    template: data.template,
    docScrollWidth: data.docScrollWidth,
    hasThreadRow: data.hasThreadRow,
    hasContentRow: data.hasContentRow,
    issues: data.issues,
  };
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();

  const results = [];
  for (const pageDef of PAGES) {
    for (const width of VIEWPORTS) {
      results.push(await auditPage(page, pageDef, width));
    }
  }

  await browser.close();

  const failures = results.filter((r) => !r.ok);
  const failedPages = [...new Set(failures.map((f) => f.page))];

  const summary = {
    auditedAt: new Date().toISOString(),
    viewports: VIEWPORTS,
    pages: PAGES.map((p) => p.id),
    totalChecks: results.length,
    failureCount: failures.length,
    failedPages,
    failures: failures.map((f) => ({
      page: f.page,
      url: f.url,
      viewport: f.viewport,
      finalUrl: f.finalUrl,
      template: f.template,
      issues: f.issues,
    })),
    results,
  };

  console.log(JSON.stringify(summary, null, 2));

  if (failures.length) {
    process.exitCode = 1;
  }
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
