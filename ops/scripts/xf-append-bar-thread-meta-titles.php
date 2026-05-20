<?php
/**
 * Sync footer script: tooltips + barThreadStat* classes on first three .structItem-cell--meta for extra.less labels.
 * Desktop: full words Replies / Views / Reactions. Mobile: compact R / V / ★ (see nested @media in extra.less).
 */
use XF\Cli\App;
use XF\Service\Template\CompileService;

$root = $argv[1] ?? '';
$styleId = (int) ($argv[2] ?? 0);

if ($styleId <= 0 || !is_dir($root)) {
    fwrite(STDERR, "Usage: php xf-append-bar-thread-meta-titles.php <forum_root> <style_id>\n");
    exit(1);
}

$markerV1 = 'BAR_THREAD_META_TITLES_V1';
$markerV2 = 'BAR_THREAD_META_FULL_V2';
$markerV3 = 'BAR_THREAD_META_FULL_V3';
$markerV4 = 'BAR_THREAD_META_TOOLTIP_V4';
$markerV5 = 'BAR_THREAD_META_TOOLTIP_V5';
$markerV6 = 'BAR_THREAD_META_TOOLTIP_V6';
$markerV7 = 'BAR_THREAD_META_TOOLTIP_V7';
$markerV8 = 'BAR_THREAD_META_TOOLTIP_V8';
$markerV9 = 'BAR_THREAD_META_TOOLTIP_V9';
$markerV10 = 'BAR_THREAD_META_TOOLTIP_V10';
$markerV11 = 'BAR_THREAD_META_TOOLTIP_V11';
$markerV12 = 'BAR_THREAD_META_TOOLTIP_V12';
$markerV13 = 'BAR_THREAD_META_TOOLTIP_V13';
$markerV14 = 'BAR_THREAD_META_TOOLTIP_V14';
$markerV15 = 'BAR_THREAD_META_TOOLTIP_V15';
$markerV16 = 'BAR_THREAD_META_TOOLTIP_V16';
$markerV17 = 'BAR_THREAD_META_TOOLTIP_V17';
$activeMarker = $markerV17;

$snippet = <<<HTML

<!-- {$activeMarker} -->
<script>
(function () {
	'use strict';
	var statClassReply = 'barThreadStatReply';
	var statClassView = 'barThreadStatView';
	var statClassRx = 'barThreadStatRx';
	var statClasses = [statClassReply, statClassView, statClassRx];
	function rowCells(row) {
		return row.querySelectorAll(':scope > .structItem-cell--meta');
	}
	function cellNumber(pairs) {
		var raw = (pairs.textContent || '').replace(/\s+/g, ' ').trim();
		var cleaned = raw
			.replace(/^\(R\)\s*/i, '')
			.replace(/^\(V\)\s*/i, '')
			.replace(/^R\s+/i, '')
			.replace(/^V\s+/i, '')
			.replace(/^\u2605\s*/, '')
			.trim();
		var m = cleaned.match(/([0-9]+(?:\.[0-9]+)?[KMB]?)/i);
		return m ? m[1] : cleaned;
	}
	function skipThreadRow(row) {
		/* Only skip widgets where the first three meta columns are NOT thread reply/view/reaction totals. */
		var block = row.closest('.block[data-widget-key]');
		if (!block) {
			return false;
		}
		var key = block.getAttribute('data-widget-key') || '';
		return key === 'frontpage_featured' || key === 'forum_overview_featured_content';
	}
	function fixRow(row) {
		row.querySelectorAll(':scope > .structItem-cell--meta').forEach(function (c) {
			c.classList.remove('barThreadStatReply', 'barThreadStatView', 'barThreadStatRx');
		});
		if (skipThreadRow(row)) {
			return;
		}
		var cells = rowCells(row);
		if (cells.length < 3) {
			return;
		}
		var defs = [
			{ titlePrefix: 'Replies' },
			{ titlePrefix: 'Views' },
			{ titlePrefix: 'Reactions' }
		];
		for (var i = 0; i < 3; i++) {
			var cell = cells[i];
			cell.classList.add(statClasses[i]);
			var pairs = cell.querySelector(':scope > .pairs');
			if (!pairs) {
				continue;
			}
			var lab = cell.querySelector(':scope > .barThreadStatLabel');
			if (lab) {
				lab.remove();
			}
			var num = cellNumber(pairs);
			var fullTitle = defs[i].titlePrefix + ': ' + num;
			cell.removeAttribute('data-original-title');
			cell.setAttribute('title', fullTitle);
			cell.setAttribute('aria-label', fullTitle);
			(function (c, t) {
				requestAnimationFrame(function () {
					c.setAttribute('title', t);
					c.setAttribute('aria-label', t);
				});
			})(cell, fullTitle);
		}
	}
	function run() {
		document.querySelectorAll('.structItem.structItem--thread').forEach(fixRow);
	}
	function topNavList() {
		return document.querySelector('.p-nav .p-nav-list.js-offCanvasNavSource')
			|| document.querySelector('.p-nav .p-nav-list');
	}
	function navElById(navId) {
		var list = topNavList();
		if (!list) {
			return null;
		}
		return list.querySelector('.p-navEl a[data-nav-id="' + navId + '"]');
	}
	function markTopNav(navId) {
		if (!navId) {
			return;
		}
		var targetLink = navElById(navId);
		if (!targetLink) {
			return;
		}
		var list = topNavList();
		if (!list) {
			return;
		}
		list.querySelectorAll('.p-navEl.is-selected').forEach(function (el) {
			el.classList.remove('is-selected');
		});
		var el = targetLink.closest('.p-navEl');
		if (el) {
			el.classList.add('is-selected');
		}
	}
	function markTopNavByHrefFragment(fragment) {
		if (!fragment) {
			return false;
		}
		var list = topNavList();
		if (!list) {
			return false;
		}
		var targetLink = list.querySelector('.p-navEl-link[href*="' + fragment + '"]');
		if (!targetLink) {
			return false;
		}
		list.querySelectorAll('.p-navEl.is-selected').forEach(function (el) {
			el.classList.remove('is-selected');
		});
		var el = targetLink.closest('.p-navEl');
		if (!el) {
			return false;
		}
		el.classList.add('is-selected');
		return true;
	}
	function normalizePath(path) {
		if (!path) {
			return '/';
		}
		return path.toLowerCase().replace(/\/+$/, '') || '/';
	}
	function normalizedRouteForNav() {
		var pathname = normalizePath(window.location.pathname || '');
		var search = (window.location.search || '').toLowerCase();
		if (pathname === '/forum/index.php' && search.indexOf('?threads/') === 0) {
			return normalizePath('/forum/' + search.substring(1));
		}
		if (pathname === '/forum/index.php' && search.indexOf('?pages/') === 0) {
			return normalizePath('/forum/' + search.substring(1));
		}
		return pathname;
	}
	function contentKeyForNav() {
		var body = document.body;
		if (!body) {
			return '';
		}
		return (body.getAttribute('data-content-key') || '').toLowerCase();
	}
	function threadIdFromPath(path) {
		var m = (path || '').match(/\/threads\/[^\/]*\.(\d+)(?:\/|$)/i);
		return m ? m[1] : '';
	}
	function fixTopNavSelection() {
		var path = normalizedRouteForNav();
		var contentKey = contentKeyForNav();
		if (contentKey === 'page-97') {
			if (!markTopNavByHrefFragment('/pages/new-sales')) {
				markTopNav('home');
			}
			return;
		}
		if (contentKey === 'thread-14130') {
			markTopNav('join');
			return;
		}
		if (
			contentKey === 'thread-14200' ||
			contentKey === 'thread-39737' ||
			contentKey === 'thread-14195' ||
			contentKey === 'thread-14198' ||
			contentKey === 'thread-14196' ||
			contentKey === 'thread-14197' ||
			contentKey === 'thread-27219'
		) {
			markTopNav('about');
			return;
		}
		if (path === '/forum' || path === '/forum/') {
			markTopNav('home');
			return;
		}
		if (path.indexOf('/forum/forums') === 0) {
			markTopNav('forums');
			return;
		}
		if (path.indexOf('/forum/whats-new') === 0 || path.indexOf('/forum/featured') === 0) {
			markTopNav('whatsNew');
			return;
		}
		if (path.indexOf('/forum/members') === 0 || path.indexOf('/forum/online') === 0) {
			markTopNav('members');
			return;
		}
		var threadId = threadIdFromPath(path);
		if (threadId === '14130' || path.indexOf('/forum/threads/how-do-i-become-a-supporting-member.') === 0) {
			markTopNav('join');
			return;
		}
		if (
			threadId === '14200' ||
			threadId === '39737' ||
			threadId === '14195' ||
			threadId === '14198' ||
			threadId === '14196' ||
			threadId === '14197' ||
			threadId === '27219'
		) {
			markTopNav('about');
			return;
		}
		var aboutPatterns = [
			'/forum/threads/welcome-to-bar.',
			'/forum/threads/about-bar.',
			'/forum/threads/meet-the-board-of-directors',
			'/forum/threads/bar-contact-information.',
			'/forum/threads/bar-sponsors.',
			'/forum/threads/bar-by-laws.',
			'/forum/threads/bar-privacy-policy.',
			'/forum/threads/bar-forum-posting-guidelines-and-code-of-conduct.'
		];
		for (var i = 0; i < aboutPatterns.length; i++) {
			if (path.indexOf(aboutPatterns[i]) === 0) {
				markTopNav('about');
				return;
			}
		}
	}
	function runAll() {
		run();
		fixTopNavSelection();
	}
	var debounce;
	function schedule() {
		clearTimeout(debounce);
		debounce = setTimeout(runAll, 40);
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', runAll);
	} else {
		runAll();
	}
	new MutationObserver(schedule).observe(document.body, { childList: true, subtree: true });
})();
</script>
HTML;

require $root . '/src/XF.php';
\XF::start($root);
$app = \XF::setupApp(App::class);

/** @var \XF\Entity\Template|null $template */
$template = $app->finder('XF:Template')
    ->where('style_id', $styleId)
    ->where('type', 'public')
    ->where('title', 'extra_footer')
    ->fetchOne();

if (!$template) {
    $template = $app->finder('XF:Template')
        ->where('style_id', $styleId)
        ->where('type', 'public')
        ->where('title', 'PAGE_CONTAINER')
        ->fetchOne();
}

if (!$template) {
    fwrite(STDERR, "No public extra_footer or PAGE_CONTAINER at style_id={$styleId}\n");
    exit(1);
}

$body = $template->template;
$markers = [$markerV1, $markerV2, $markerV3, $markerV4, $markerV5, $markerV6, $markerV7, $markerV8, $markerV9, $markerV10, $markerV11, $markerV12, $markerV13, $markerV14, $markerV15, $markerV16, $markerV17];
foreach ($markers as $marker) {
    $body = preg_replace('/\r?\n<!--\s*' . preg_quote($marker, '/') . '\s*-->[\s\S]*?<\/script>/i', "\n", $body);
}

$template->setTemplateUnchecked(rtrim($body) . $snippet);
if ($template->hasErrors()) {
    foreach ($template->getErrors() as $k => $msg) {
        fwrite(STDERR, "After setTemplateUnchecked [{$k}]: {$msg}\n");
    }
    exit(1);
}
$template->save(false);
if ($template->hasErrors()) {
    foreach ($template->getErrors() as $k => $msg) {
        fwrite(STDERR, "After save [{$k}]: {$msg}\n");
    }
    exit(1);
}

$compile = $app->service(CompileService::class);
$compile->deleteCompiled($template);
$compile->recompile($template);

echo "OK: synced {$activeMarker} in public:{$template->title} (style_id={$styleId}, template_id={$template->template_id})\n";
