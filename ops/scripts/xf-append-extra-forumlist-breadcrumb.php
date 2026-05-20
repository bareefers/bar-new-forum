<?php
/**
 * Collapse empty breadcrumb band on forum index (template-forum_list).
 * DohTheme hides .p-breadcrumbs there but leaves .p-breadcrumbs--container with
 * margin/padding and ::before (core_dt_extra.less) — reads as a gap under the nav.
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

$append = <<<'LESS'


/* Forum index: remove empty breadcrumb wrapper gap (DohTheme hides crumbs but not container when collapsible sidebar is on) */
.template-forum_list .p-breadcrumbs--container {
	display: none;
}
LESS;

$t = $pdo->query(
    "SELECT template FROM xf_template WHERE style_id=16 AND title='extra.less' AND type='public'"
)->fetchColumn();
if ($t === false) {
    fwrite(STDERR, "extra.less not found\n");
    exit(1);
}
if (strpos($t, 'Forum index: remove empty breadcrumb wrapper gap') !== false) {
    echo "Already applied.\n";
    exit(0);
}

$t2 = $t . $append;
$st = $pdo->prepare(
    'UPDATE xf_template SET template = ?, last_edit_date = ? WHERE style_id=16 AND title=\'extra.less\' AND type=\'public\''
);
$st->execute([$t2, time()]);
echo "OK: appended forum_list breadcrumb fix\n";
