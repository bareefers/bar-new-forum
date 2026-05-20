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
echo 'sticky supports count: ' . substr_count($t, '@supports (position: sticky) or (position: -webkit-sticky)') . "\n";
echo 'padding-top 10px count: ' . substr_count($t, 'padding-top: 10px') . "\n";
echo 'p-navSticky padding lines: ';
if (preg_match_all('/[^\n]*p-navSticky[^\n]*padding-top[^\n]*/', $t, $m)) {
    foreach ($m[0] as $line) {
        echo trim($line) . "\n";
    }
}
$i = strpos($t, '@supports (position: sticky) or (position: -webkit-sticky)');
if ($i !== false) {
    echo "\n--- sticky block excerpt ---\n";
    echo substr($t, $i, 900) . "\n";
}
