<?php
/**
 * One-off: Aurora style 16 extra.less used top: 20px on all .p-navSticky,
 * leaving a gap for non-moderators. Moderators use .p-navSticky.p-staffSticky { top: 35px } from core_dt.
 * Set default sticky offset to top: 0 so non-staff nav sits flush; staff rule still wins by specificity.
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

$t = $pdo->query(
    "SELECT template FROM xf_template WHERE style_id=16 AND title='extra.less' AND type='public'"
)->fetchColumn();
if ($t === false) {
    fwrite(STDERR, "extra.less not found for style 16\n");
    exit(1);
}
$t2 = str_replace('top: 20px', 'top: 0', $t, $count);
if ($count === 0) {
    fwrite(STDERR, "top: 20px not found; already fixed?\n");
    exit(0);
}
$st = $pdo->prepare(
    'UPDATE xf_template SET template = ?, last_edit_date = ? WHERE style_id=16 AND title=\'extra.less\' AND type=\'public\''
);
$st->execute([$t2, time()]);
echo "OK: replaced {$count} occurrence(s)\n";
