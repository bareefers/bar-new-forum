<?php

$root = $argv[1] ?? '/var/www/bareefers.org/forum';
require $root . '/src/XF.php';
XF::start($root);

$app = XF::app();
$db = $app->db();

echo "Active banners (SQL):\n";
foreach ($db->fetchAll(
	'SELECT banner_id, title, is_active, image_path, remote_url FROM xf_bar_sponsor_banner WHERE is_active = 1 AND (image_path <> \'\' OR remote_url <> \'\')'
) as $r) {
	print_r($r);
}

$n = (int) $db->fetchOne(
	'SELECT COUNT(*) FROM xf_bar_sponsor_banner WHERE is_active = 1 AND (image_path <> \'\' OR remote_url <> \'\')'
);
echo "count=$n\n";

$widget = $app->widget()->widget('BAR\\SponsorBanners\\Widget\\SponsorBanners', []);
$out = $widget->render();
echo 'widget render length=' . strlen((string) $out) . "\n";
echo substr((string) $out, 0, 800) . "\n";
