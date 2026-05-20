<?php
/**
 * One-time: seed xf_bar_sponsor_banner from the legacy "BAR Sponsors" advertising HTML
 * (images already live under forum/sponsor_banners/).
 *
 * Idempotent: skips any row whose image_path already exists.
 *
 * Usage (forum root = argv[1]):
 *   php xenforo/scripts/xf-migrate-legacy-bar-sponsor-banners.php /var/www/bareefers.org/forum
 */
use XF\Cli\App;

$root = $argv[1] ?? '';
if ($root === '' || !is_dir($root)) {
	fwrite(STDERR, "usage: php xf-migrate-legacy-bar-sponsor-banners.php /path/to/forum\n");
	exit(1);
}

require $root . '/src/XF.php';
\XF::start($root);
$app = \XF::setupApp(App::class);

$rows = [
	['title' => 'Kessil', 'image_path' => 'sponsor_banners/kessilbanner.jpg', 'target_url' => 'https://kessil.com', 'alt_text' => 'Kessil', 'display_order' => 10],
	['title' => 'Reef Nutrition', 'image_path' => 'sponsor_banners/reef_nutrition_logo_BAR.png', 'target_url' => 'https://reefnutrition.com/', 'alt_text' => 'Reef nutrition', 'display_order' => 20],
	['title' => 'Got ethical husbandry?', 'image_path' => 'sponsor_banners/got-ethical-husbandry.png', 'target_url' => 'https://bareefers.org/forum/threads/welcome-to-bar.14200/', 'alt_text' => 'got ethical husbandry?', 'display_order' => 30],
	['title' => 'High Tide Aquatics', 'image_path' => 'sponsor_banners/hta-logo.png', 'target_url' => 'https://www.hightideaquatics.net/', 'alt_text' => 'High Tide Aquatics', 'display_order' => 40],
	['title' => 'Neptune Aquatics', 'image_path' => 'sponsor_banners/neptune-aquatics-logo.jpg', 'target_url' => 'https://www.neptuneaquatics.com/', 'alt_text' => 'Neptune Aquatics', 'display_order' => 50],
	['title' => 'Cali Kid Corals', 'image_path' => 'sponsor_banners/cali-kid-corals.jpg', 'target_url' => 'https://www.calikidcorals.com/', 'alt_text' => 'Cali Kid Corals', 'display_order' => 60],
	['title' => 'Fishy Business', 'image_path' => 'sponsor_banners/fishy-business.png', 'target_url' => 'http://www.fishybusiness707.com/', 'alt_text' => 'Fishy Business', 'display_order' => 70],
];

$em = $app->em();
$added = 0;
$skipped = 0;

foreach ($rows as $row) {
	$existing = $em->getFinder('BAR\SponsorBanners:Banner')
		->where('image_path', $row['image_path'])
		->fetchOne();
	if ($existing) {
		$skipped++;

		continue;
	}

	/** @var \BAR\SponsorBanners\Entity\Banner $banner */
	$banner = $em->create('BAR\SponsorBanners:Banner');
	$banner->title = $row['title'];
	$banner->image_path = $row['image_path'];
	$banner->target_url = $row['target_url'];
	$banner->alt_text = $row['alt_text'];
	$banner->display_order = $row['display_order'];
	$banner->is_active = true;
	$banner->save();
	$added++;
	echo "OK: added {$row['image_path']}\n";
}

echo "Done. added={$added} skipped_existing={$skipped}\n";
echo "Next: ACP → Setup → Advertising → edit BAR Sponsors 2026 → replace template body with one line:\n";
echo "  <xf:widget key=\"YOUR_WIDGET_KEY\" />\n";
echo "(Use the same Widget key you set under Appearance → Widgets for Sponsor banners.)\n";
