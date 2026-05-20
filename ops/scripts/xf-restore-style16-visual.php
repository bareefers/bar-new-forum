<?php

use XF\Cli\App;
use XF\Repository\StyleRepository;

/**
 * Restore a snapshot from xf-backup-style16-visual.php (visual.sql.gz), then bump XF style caches.
 *
 * Usage:
 *   sudo php xf-restore-style16-visual.php /path/to/forum /path/to/visual.sql.gz
 */
$root = $argv[1] ?? '';
$gz = $argv[2] ?? '';

if ($root === '' || !is_dir($root) || $gz === '' || !is_readable($gz)) {
    fwrite(STDERR, "Usage: php xf-restore-style16-visual.php /path/to/forum /path/to/visual.sql.gz\n");
    exit(1);
}

$config = [];
require $root . '/src/config.php';
$host = $config['db']['host'] ?? '127.0.0.1';
if ($host === 'localhost') {
    $host = '127.0.0.1';
}
$port = (int) ($config['db']['port'] ?? 3306);
$db = $config['db']['dbname'];
$user = $config['db']['username'];
$pass = $config['db']['password'];

$env = $_ENV;
$env['MYSQL_PWD'] = $pass;

$cmd = sprintf(
    'gzip -dc %s | mysql -h%s -P%d -u%s %s',
    escapeshellarg($gz),
    escapeshellarg($host),
    $port,
    escapeshellarg($user),
    escapeshellarg($db)
);
passthru($cmd, $code);
if ($code !== 0) {
    fwrite(STDERR, "mysql import failed with exit {$code}\n");
    exit(1);
}

require $root . '/src/XF.php';
\XF::start($root);
$app = \XF::setupApp(App::class);
/** @var StyleRepository $styles */
$styles = $app->repository(StyleRepository::class);
$styles->updateAllStylesLastModifiedDate();
$styles->rebuildStyleCache();

echo "OK: visual snapshot restored + style cache rebuilt\n";
