<?php

/**
 * One-shot: rebuild XenForo widget cache (registry).
 * Usage: php xf-rebuild-widget-cache-once.php /path/to/forum
 */

$root = $argv[1] ?? '';
if ($root === '' || !is_file($root . '/src/XF.php')) {
	fwrite(STDERR, "Usage: php xf-rebuild-widget-cache-once.php /path/to/forum\n");
	exit(1);
}

require $root . '/src/XF.php';
XF::start($root);

$app = XF::app();
$app->em()->getRepository('XF:Widget')->rebuildWidgetCache();
echo "widgetCache rebuilt.\n";
