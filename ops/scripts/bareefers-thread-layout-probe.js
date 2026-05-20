/**
 * Headless layout probe for bareefers thread rows (Playwright Chromium).
 *
 * Default: injects `bar-thread-list-row-patch.css.txt` (must match
 * `extra-less-aurora16-source.less` .barThreadListRowFlex) and asserts layout — validates the patch
 * against live DOM without a server deploy.
 *
 *   cd .pw-probe && NODE_PATH="$PWD/node_modules" node ../xenforo/scripts/bareefers-thread-layout-probe.js
 *
 * Production CSS only (no inject; often fails at ~1200px until deploy):
 *
 *   node ../xenforo/scripts/bareefers-thread-layout-probe.js --live-only
 */

const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const URLS = [
  { name: 'home', url: 'https://bareefers.org/forum/' },
  { name: 'whats_new_posts', url: 'https://bareefers.org/forum/whats-new/posts/' },
];

const WIDTHS = [360, 400, 480, 520, 700, 900, 1100, 1180, 1200, 1300, 1400];

const liveOnly = process.argv.includes('--live-only');
const patchPath = path.join(__dirname, 'bar-thread-list-row-patch.css.txt');
let patchCss = '';
if (!liveOnly) {
  patchCss = fs.readFileSync(patchPath, 'utf8');
}

async function probePage(page, label, url, width, injectCss) {
  await page.setViewportSize({ width, height: 900 });
  await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 60000 });
  if (injectCss && patchCss) {
    await page.addStyleTag({ content: patchCss });
  }

  await page.waitForSelector('.structItem--thread', { timeout: 45000 }).catch(() => null);

  const data = await page.evaluate(() => {
    const rows = Array.from(document.querySelectorAll('.structItem--thread'));
    const row = rows[0];
    const row2 = rows[1];
    if (!row) return { error: 'no_thread_row' };

    const pick = (rowEl, sel) => {
      const el = rowEl.querySelector(sel);
      if (!el) return null;
      const box = el.getBoundingClientRect();
      return {
        offsetW: el.offsetWidth,
        offsetH: el.offsetHeight,
        clientW: el.clientWidth,
        rectW: Math.round(box.width),
        rectH: Math.round(box.height),
        rectLeft: Math.round(box.left),
        rectTop: Math.round(box.top),
      };
    };

    /** True if title text has wrapped to multiple lines (not just tall line-height / prefix). */
    const titleWrapped = (rowEl) => {
      const el = rowEl.querySelector('.structItem-title');
      if (!el) return false;
      const inner = el.querySelector('a') || el;
      return inner.scrollHeight > inner.clientHeight + 6;
    };

    const pickTitle = (rowEl) => {
      const base = pick(rowEl, '.structItem-title');
      if (!base) return null;
      const el = rowEl.querySelector('.structItem-title');
      const inner = el && (el.querySelector('a') || el);
      const scrollH = inner ? inner.scrollHeight : 0;
      const clientH = inner ? inner.clientHeight : 0;
      return {
        ...base,
        scrollH,
        clientH,
        wrapped: titleWrapped(rowEl),
      };
    };

    const titleEl = row.querySelector('.structItem-title');
    let titleSample = '';
    if (titleEl) {
      titleSample = (titleEl.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 80);
    }

    const rowClasses = row.className;
    const row2Main = row2 ? pick(row2, '.structItem-cell--main') : null;
    const row2Title = row2 ? pickTitle(row2) : null;

    const body = document.body;
    const bodyRect = body ? body.getBoundingClientRect() : null;

    return {
      rowW: row.offsetWidth,
      main: pick(row, '.structItem-cell--main'),
      title: pickTitle(row),
      icon: pick(row, '.structItem-cell--icon'),
      metaFirst: pick(row, '.structItem-cell--meta'),
      titleSample,
      rowClasses,
      row2Main,
      row2Title,
      bodyClientW: body ? body.clientWidth : null,
      bodyRectW: bodyRect ? Math.round(bodyRect.width) : null,
      hasWithSidebar: !!document.querySelector('.p-body-main--withSidebar'),
      hasWithSideNav: !!document.querySelector('.p-body-main--withSideNav'),
    };
  });

  return { label, url, width, injectCss, ...data };
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();

  const injectCss = !liveOnly;
  const mode = injectCss ? 'injected_patch' : 'live_only';
  console.error(`MODE=${mode}`);

  const rows = [];
  for (const { name, url } of URLS) {
    for (const width of WIDTHS) {
      const row = await probePage(page, name, url, width, injectCss);
      rows.push(row);
      const m = row.main;
      const t = row.title;
      const m2 = row.row2Main;
      const t2 = row.row2Title;
      const minMainWide = width >= 1181 ? 220 : 100;
      const titleOk = (t) => !t || !t.wrapped;
      const readable =
        m &&
        t &&
        m.clientW >= minMainWide &&
        t.rectW >= Math.min(100, minMainWide * 0.45) &&
        titleOk(t);
      const readable2 =
        !m2 ||
        (m2.clientW >= minMainWide &&
          t2 &&
          t2.rectW >= Math.min(100, minMainWide * 0.45) &&
          titleOk(t2));
      console.log(
        JSON.stringify({
          mode,
          page: name,
          width,
          readableGuess: readable && readable2,
          mainClientW: m && m.clientW,
          titleRectW: t && t.rectW,
          titleRectH: t && t.rectH,
          titleWrapped: t && t.wrapped,
          row2TitleH: t2 && t2.rectH,
          row2Wrapped: t2 && t2.wrapped,
          row2MainW: m2 && m2.clientW,
          row2TitleW: t2 && t2.rectW,
          hasSidebar: row.hasWithSidebar,
          hasSideNav: row.hasWithSideNav,
          sample: row.titleSample,
        }),
      );
    }
  }

  await browser.close();

  const bad = rows.filter((r) => {
    const m = r.main;
    const t = r.title;
    const m2 = r.row2Main;
    const t2 = r.row2Title;
    const minMain = r.width >= 1181 ? 220 : 100;
    if (!m || !t || r.error) return true;
    if (m.clientW < minMain || t.rectW < 80) return true;
    if (t.wrapped) return true;
    if (m2 && (m2.clientW < minMain || (t2 && t2.rectW < 80))) return true;
    if (t2 && t2.wrapped) return true;
    return false;
  });
  if (bad.length) {
    console.error('LAYOUT_WARN', bad.length, 'samples look tight:', JSON.stringify(bad, null, 0));
    process.exitCode = 1;
  } else {
    console.error(
      injectCss
        ? 'LAYOUT_OK injected barThreadListRow patch passed thresholds (deploy extra.less to match live).'
        : 'LAYOUT_OK live site passed thresholds.',
    );
  }
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
