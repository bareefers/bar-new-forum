<?php
/**
 * =============================================================================
 * POST-DB IMPORT / MIGRATION — OPERATOR NOTES (supervisor) & WHAT THIS SCRIPT DOES
 * =============================================================================
 * The user supervises; the agent runs steps on `bareefers` (or the target host).
 *
 * ORDER (do not skip):
 *
 * 1) XenForo upgrade (canonical schema). From forum root, e.g.:
 *        cd /var/www/bareefers.org/forum
 *        php cmd.php xf:upgrade
 *    Required when MySQL was restored from an older dump while `src/addons` files are newer.
 *    Skipping this leaves missing columns and random HTTP 500s.
 *
 * 2) THIS SCRIPT (idempotent backstop + style repair), e.g.:
 *        php /path/to/barcode/xenforo/scripts/xf-post-db-restore-repair.php /var/www/bareefers.org/forum [/path/to/extra-less-aurora16-source.less]
 *    - Ensures XFMG/XFRM columns on the user table (`xfmg_album_count`, `xfmg_media_count`,
 *      `xfmg_media_quota`, `xfrm_resource_count`) and their indexes. If those columns are
 *      missing while addons are active, registration fails with:
 *      "Unknown column 'xfmg_album_count' in 'field list'" — check `xf_error_log` in MySQL.
 *    - Ensures **`xf_forum.xfmg_media_mirror_category_id`** (XFMG 2.2+ attachment mirror). If
 *      missing, saving forums/nodes in the ACP can throw:
 *      "Unknown column 'xfmg_media_mirror_category_id' in 'field list'".
 *    - Bumps all style last-modified dates and rebuilds the styles registry cache (helps
 *      broken/stale CSS/HTML after a restore when `xf_css_cache` / registry were from another DB).
 *    - Fixes **HTML email readability**: XenForo’s mail templater uses **`defaultEmailStyleId`**, not
 *      `defaultStyleId`. If `defaultEmailStyleId` is **0** (master), mail ignores your public style’s
 *      overrides and colors can collapse to dark-on-dark. This script sets `defaultEmailStyleId` to match
 *      `defaultStyleId` when it was 0, then writes explicit light **`xf_style_property` email\*** colors
 *      on that style (body/background/text/links).
 *    - Re-applies **BAR `public:extra.less` baseline** from `extra-less-aurora16-source.less` (same directory
 *      as this script, or optional 2nd argv path): mobile nav order, logo caps, sticky nav tweaks, etc.
 *      Copy that `.less` file next to this script on the server when you deploy only the PHP file.
 *
 * 3) Redis — flush only the DB(s) used for guest/CSS cache (site-specific; often a dedicated DB).
 *    Do not flush session Redis unless that is intentional.
 *
 * 4) Files — clear `internal_data/page_cache` if guest page cache is stale after restore.
 *
 * 5) Smoke tests before DNS / go-live:
 *    - ACP → Tools → Checks and tests → Test outbound email (not only under Setup → Options).
 *    - Optional throwaway registration; on 500 run:
 *        SELECT * FROM xf_error_log ORDER BY exception_date DESC LIMIT 5;
 *
 * 6) PayPal REST (when testing payments): `$config['enableLivePayments']` in `src/config.php`
 *    must match sandbox vs live; ACP Client ID/secret must be the same PayPal Developer *app*
 *    as the webhook; webhook URL must be the public HTTPS callback, e.g.:
 *    https://beta.bareefers.org/forum/payment_callback.php?_xfProvider=paypalrest
 *
 * DNS cutover does not fix DB/schema drift — run steps 1–2 after every full mysqldump restore.
 * =============================================================================
 */
use XF\Cli\App;
use XF\Db\AbstractAdapter;
use XF\Finder\TemplateFinder;
use XF\Repository\OptionRepository;
use XF\Repository\StyleRepository;

$root = $argv[1] ?? '/var/www/bareefers.org/forum';
if (!is_dir($root)) {
	fwrite(STDERR, "usage: php xf-post-db-restore-repair.php /path/to/forum [/path/to/extra-less-aurora16-source.less]\n");
	exit(1);
}

require $root . '/src/XF.php';
\XF::start($root);
$app = \XF::setupApp(App::class);

echoSupervisorPreamble($root);

ensureXfUserMediaResourceColumns($app->db());
ensureXfForumMediaMirrorColumn($app->db());
ensureEmailReadability($app);
ensureExtraLessBarBaseline($app, $argv[2] ?? null);

/** @var StyleRepository $styles */
$styles = $app->repository(StyleRepository::class);
$styles->updateAllStylesLastModifiedDate();
$styles->rebuildStyleCache();

echo "OK: updateAllStylesLastModifiedDate + rebuildStyleCache complete\n";

echoSupervisorPostScriptReminders($root);

/**
 * @param AbstractAdapter $db
 */
function ensureXfUserMediaResourceColumns($db): void
{
	$dbConfig = \XF::app()->config('db');
	$dbName = $dbConfig['dbname'] ?? '';
	$prefix = $dbConfig['prefix'] ?? 'xf_';
	$userTable = $prefix . 'user';
	if ($dbName === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $userTable)) {
		fwrite(STDERR, "skip: could not resolve db name / user table for schema repair\n");

		return;
	}

	$columns = [
		'xfmg_album_count' => 'INT UNSIGNED NOT NULL DEFAULT 0',
		'xfmg_media_count' => 'INT UNSIGNED NOT NULL DEFAULT 0',
		'xfmg_media_quota' => 'INT UNSIGNED NOT NULL DEFAULT 0',
		'xfrm_resource_count' => 'INT UNSIGNED NOT NULL DEFAULT 0',
	];
	foreach ($columns as $column => $definition) {
		$exists = (int) $db->fetchOne(
			'SELECT COUNT(*)
				FROM information_schema.COLUMNS
				WHERE TABLE_SCHEMA = ?
				  AND TABLE_NAME = ?
				  AND COLUMN_NAME = ?',
			[$dbName, $userTable, $column]
		);
		if ($exists === 0) {
			$db->query("ALTER TABLE `{$userTable}` ADD COLUMN `{$column}` {$definition}");
			echo "OK: added column {$userTable}.{$column}\n";
		}
	}

	$indexes = [
		'xengallery_album_count' => 'xfmg_album_count',
		'xengallery_media_count' => 'xfmg_media_count',
		'resource_count' => 'xfrm_resource_count',
	];
	foreach ($indexes as $indexName => $column) {
		$exists = (int) $db->fetchOne(
			'SELECT COUNT(*)
				FROM information_schema.STATISTICS
				WHERE TABLE_SCHEMA = ?
				  AND TABLE_NAME = ?
				  AND INDEX_NAME = ?',
			[$dbName, $userTable, $indexName]
		);
		if ($exists === 0) {
			$db->query("ALTER TABLE `{$userTable}` ADD INDEX `{$indexName}` (`{$column}`)");
			echo "OK: added index {$userTable}.{$indexName}\n";
		}
	}
}

/**
 * XFMG extends `XF\Entity\Forum` with `xfmg_media_mirror_category_id` on `xf_forum` (installStep6 /
 * upgrade902020010Step6). Restored DBs sometimes lack it while addon files are current.
 *
 * @param AbstractAdapter $db
 */
function ensureXfForumMediaMirrorColumn($db): void
{
	$dbConfig = \XF::app()->config('db');
	$dbName = $dbConfig['dbname'] ?? '';
	$prefix = $dbConfig['prefix'] ?? 'xf_';
	$forumTable = $prefix . 'forum';
	$column = 'xfmg_media_mirror_category_id';
	if ($dbName === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $forumTable)) {
		fwrite(STDERR, "skip: could not resolve db name / forum table for XFMG mirror column repair\n");

		return;
	}

	$exists = (int) $db->fetchOne(
		'SELECT COUNT(*)
			FROM information_schema.COLUMNS
			WHERE TABLE_SCHEMA = ?
			  AND TABLE_NAME = ?
			  AND COLUMN_NAME = ?',
		[$dbName, $forumTable, $column]
	);
	if ($exists === 0) {
		$db->query("ALTER TABLE `{$forumTable}` ADD COLUMN `{$column}` INT UNSIGNED NOT NULL DEFAULT 0");
		echo "OK: added column {$forumTable}.{$column}\n";
	}
}

/**
 * Mail uses **`defaultEmailStyleId`** (`XF\App::mailTemplater()`), not `defaultStyleId`. If email style is **0**
 * (master), public-style fixes never apply and mail can render dark-on-dark. Align the option, then set
 * literal light email colors on the resolved style so HTML mail stays readable.
 */
function ensureEmailReadability(\XF\App $app): void
{
	$db = $app->db();
	$publicStyleId = (int) $db->fetchOne("SELECT option_value FROM xf_option WHERE option_id = 'defaultStyleId'");
	$emailStyleId = (int) $db->fetchOne("SELECT option_value FROM xf_option WHERE option_id = 'defaultEmailStyleId'");

	if ($publicStyleId <= 0) {
		fwrite(STDERR, "skip: could not resolve defaultStyleId\n");

		return;
	}

	if ($emailStyleId === 0 && $publicStyleId > 0) {
		$db->update('xf_option', ['option_value' => (string) $publicStyleId], "option_id = 'defaultEmailStyleId'");
		$app->repository(OptionRepository::class)->rebuildOptionCache();
		$emailStyleId = $publicStyleId;
		echo "OK: defaultEmailStyleId was 0; set to {$publicStyleId} (matches defaultStyleId) and rebuilt option cache\n";
	}

	if ($emailStyleId <= 0) {
		fwrite(STDERR, "skip: could not resolve defaultEmailStyleId after alignment\n");

		return;
	}

	$fixes = [
		'emailBg' => 'rgb(247, 247, 255)',
		'emailContentBg' => 'rgb(255, 255, 255)',
		'emailContentAltBg' => 'rgb(240, 242, 248)',
		'emailBorderColor' => 'rgb(210, 214, 226)',
		'emailTextColor' => 'rgb(46, 48, 57)',
		'emailTextColorMuted' => 'rgb(95, 99, 110)',
		'emailHeaderColor' => 'rgb(46, 48, 57)',
		'emailLinkColor' => 'rgb(0, 102, 204)',
	];

	foreach ($fixes as $name => $value) {
		$child = $db->fetchRow(
			'SELECT property_id FROM xf_style_property WHERE style_id = ? AND property_name = ?',
			[$emailStyleId, $name]
		);
		if ($child) {
			$updated = $db->update(
				'xf_style_property',
				['property_value' => $value],
				'style_id = ? AND property_name = ?',
				[$emailStyleId, $name]
			);
			if ($updated) {
				echo "OK: xf_style_property style_id={$emailStyleId} {$name} -> {$value}\n";
			}

			continue;
		}

		$master = $db->fetchRow(
			'SELECT * FROM xf_style_property WHERE style_id = 0 AND property_name = ?',
			[$name]
		);
		if (!$master) {
			fwrite(STDERR, "skip: missing master xf_style_property row for {$name}\n");

			continue;
		}

		unset($master['property_id']);
		$master['style_id'] = $emailStyleId;
		$master['property_value'] = $value;
		$db->insert('xf_style_property', $master);
		echo "OK: inserted xf_style_property style_id={$emailStyleId} {$name} -> {$value}\n";
	}
}

/**
 * Re-apply BAR Aurora baseline **`public:extra.less`** (mobile nav order, compact logo, sticky nav tweaks).
 * Reads **`extra-less-aurora16-source.less`** from the same directory as this script, or from **`$argv[2]`**
 * when you pass an explicit path (e.g. copied to `/tmp` on the server).
 */
function ensureExtraLessBarBaseline(\XF\App $app, ?string $explicitPath): void
{
	$candidates = [];
	if ($explicitPath !== null && $explicitPath !== '') {
		$candidates[] = $explicitPath;
	}
	$candidates[] = __DIR__ . DIRECTORY_SEPARATOR . 'extra-less-aurora16-source.less';

	$path = null;
	foreach ($candidates as $p) {
		if ($p !== '' && is_readable($p)) {
			$path = $p;
			break;
		}
	}
	if ($path === null) {
		fwrite(STDERR, "skip: extra-less-aurora16-source.less not readable (argv[2] or file next to this script)\n");

		return;
	}

	$less = file_get_contents($path);
	if ($less === false || $less === '') {
		fwrite(STDERR, "skip: empty or unreadable {$path}\n");

		return;
	}
	$less = str_replace("\r\n", "\n", $less);

	$db = $app->db();
	$styleId = (int) $db->fetchOne("SELECT option_value FROM xf_option WHERE option_id = 'defaultStyleId'");
	if ($styleId <= 0) {
		fwrite(STDERR, "skip: defaultStyleId not found for extra.less\n");

		return;
	}

	/** @var \XF\Entity\Template|null $template */
	$template = $app->finder(TemplateFinder::class)
		->where('type', 'public')
		->where('title', 'extra.less')
		->where('style_id', $styleId)
		->fetchOne();

	if (!$template) {
		fwrite(STDERR, "skip: public extra.less not found for style_id={$styleId}\n");

		return;
	}

	$existing = str_replace("\r\n", "\n", (string) $template->template);
	if ($existing === $less) {
		echo "OK: extra.less already matches baseline ({$path})\n";

		return;
	}

	$template->set('template', $less);
	$template->save();
	echo "OK: public extra.less (style_id={$styleId}) updated from {$path}\n";
}

function echoSupervisorPreamble(string $root): void
{
	echo "\n";
	echo "=== xf-post-db-restore-repair.php (forum: {$root}) ===\n";
	echo "This run: xf_user XFMG/XFRM columns (if missing) + email readability + extra.less baseline (if present) + style cache rebuild.\n";
	echo "If you have NOT run `php cmd.php xf:upgrade` from forum root since the last DB restore, STOP and run upgrade first.\n\n";
}

function echoSupervisorPostScriptReminders(string $root): void
{
	$forum = rtrim($root, '/');
	$lines = [
		'',
		'--- After this script (supervisor checklist; agent should verify) ---',
		"1) Ran xf:upgrade from {$forum} since last mysqldump restore? (required for full schema.)",
		'2) Redis: flush guest/CSS DB only (confirm DB index with ops; do not wipe sessions by mistake). If extra.less did not apply, copy extra-less-aurora16-source.less next to this script or pass it as argv[2].',
		"3) Clear {$forum}/internal_data/page_cache if guest HTML cache is wrong.",
		'4) ACP → Tools → Checks and tests → Test outbound email.',
		'5) Optional: test registration; on failure: SELECT * FROM xf_error_log ORDER BY exception_date DESC LIMIT 5;',
		'6) Payments: enableLivePayments + live/sandbox credentials + PayPal webhook same app as Client ID.',
		'',
	];

	echo implode("\n", $lines);
}
