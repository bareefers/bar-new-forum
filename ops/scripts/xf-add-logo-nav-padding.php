<?php
/**
 * Append a small block to extra.less in the default style to:
 * - increase header logo max-width
 * - add a bit more vertical padding inside the nav
 *
 * Usage on server:
 *   php xf-add-logo-nav-padding.php /var/www/bareefers.org/forum
 */

$root = $argv[1] ?? '/var/www/bareefers.org/forum';

$config = [];
require $root . '/src/config.php';
$host = $config['db']['host'] ?? '127.0.0.1';
if ($host === 'localhost') {
    $host = '127.0.0.1';
}
$dsn = 'mysql:host=' . $host . ';port=' . ($config['db']['port'] ?? 3306) . ';dbname=' . $config['db']['dbname'] . ';charset=utf8mb4';
$pdo = new PDO($dsn, $config['db']['username'], $config['db']['password']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Determine default style
$defaultStyleId = (int) $pdo->query("SELECT option_value FROM xf_option WHERE option_id = 'defaultStyleId'")->fetchColumn();
if ($defaultStyleId <= 0) {
    fwrite(STDERR, "Could not determine defaultStyleId\n");
    exit(1);
}

$st = $pdo->prepare(
    "SELECT template FROM xf_template WHERE style_id = ? AND title = 'extra.less' AND type = 'public'"
);
$st->execute([$defaultStyleId]);
$t = $st->fetchColumn();
if ($t === false) {
    fwrite(STDERR, "extra.less not found for style {$defaultStyleId}\n");
    exit(1);
}

$marker = '/* BAR: logo + nav padding */';
if (strpos($t, $marker) !== false) {
    fwrite(STDOUT, "extra.less already contains BAR logo/nav block for style {$defaultStyleId}, no change\n");
    exit(0);
}

$append = <<<LESS

{$marker}
.p-header-logo.p-header-logo--image img {
    max-width: 260px;
}

.p-nav-inner {
    padding-bottom: 18px;
}

.p-header-logo.p-header-logo--image {
    margin-top: 6px;
}
/* BAR: logo + nav padding end */

LESS;

$t2 = rtrim($t) . $append;

$up = $pdo->prepare(
    "UPDATE xf_template SET template = ?, last_edit_date = ? WHERE style_id = ? AND title = 'extra.less' AND type = 'public'"
);
$up->execute([$t2, time(), $defaultStyleId]);

echo "OK: updated extra.less for style {$defaultStyleId}\n";

