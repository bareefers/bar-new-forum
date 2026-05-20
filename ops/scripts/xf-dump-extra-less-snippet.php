<?php
$root = $argv[1] ?? '/var/www/bareefers.org/forum';
$config = [];
require $root . '/src/config.php';
$h = ($config['db']['host'] ?? '') === 'localhost' ? '127.0.0.1' : $config['db']['host'];
$dsn = 'mysql:host=' . $h . ';port=' . ($config['db']['port'] ?? 3306) . ';dbname=' . $config['db']['dbname'] . ';charset=utf8mb4';
$pdo = new PDO($dsn, $config['db']['username'], $config['db']['password']);
$t = $pdo->query(
    "SELECT template FROM xf_template WHERE style_id=16 AND title='extra.less' AND type='public'"
)->fetchColumn();
echo strpos($t, 'padding-top: 10px') !== false ? "HAS padding-top 10px\n" : "MISSING padding-top 10px\n";
echo substr_count($t, '@supports (position: sticky) or (position: -webkit-sticky)') . " sticky-supports occurrences\n";
$i = strpos($t, '@supports (position: sticky) or (position: -webkit-sticky)');
if ($i !== false) {
    echo "--- first 1400 chars from first @supports sticky ---\n";
    echo substr($t, $i, 1400) . "\n";
}
