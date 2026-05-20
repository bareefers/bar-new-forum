<?php

/**
 * Fix BAR Sponsors advertising: use xf:widget class= (definition) so it works
 * when the widget has no Display positions (key= lookup fails).
 *
 * Usage: php xf-fix-bar-sponsor-ad-widget.php /path/to/forum
 */

$root = $argv[1] ?? '';
if ($root === '' || !is_file($root . '/src/XF.php')) {
	fwrite(STDERR, "Usage: php xf-fix-bar-sponsor-ad-widget.php /path/to/forum\n");
	exit(1);
}

require $root . '/src/XF.php';
XF::start($root);

$app = XF::app();
$em = $app->em();

$ad = $em->findOne('XF:Advertising', ['title' => 'BAR Sponsors 2026']);
if (!$ad) {
	fwrite(STDERR, "No advertising entry titled \"BAR Sponsors 2026\".\n");
	exit(1);
}

$ad->ad_html = '<xf:widget class="BAR\SponsorBanners\Widget\SponsorBanners" />';
$ad->save(false);

$app->repository('XF:Widget')->rebuildWidgetCache();

echo "Updated ad_id={$ad->ad_id} ad_html and rebuilt widgetCache.\n";
