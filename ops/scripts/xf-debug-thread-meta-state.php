<?php
$root = $argv[1] ?? '/var/www/bareefers.org/forum';
$config = [];
require $root . '/src/config.php';

$h = ($config['db']['host'] ?? '') === 'localhost' ? '127.0.0.1' : $config['db']['host'];
$dsn = 'mysql:host=' . $h . ';port=' . ($config['db']['port'] ?? 3306) . ';dbname=' . $config['db']['dbname'] . ';charset=utf8mb4';
$pdo = new PDO($dsn, $config['db']['username'], $config['db']['password']);

function tpl(PDO $pdo, int $styleId, string $title): ?string {
    $stmt = $pdo->prepare("SELECT template FROM xf_template WHERE style_id=? AND type='public' AND title=?");
    $stmt->execute([$styleId, $title]);
    $row = $stmt->fetchColumn();
    return $row === false ? null : (string) $row;
}

$extra = tpl($pdo, 16, 'extra.less') ?? '';
$footer = tpl($pdo, 16, 'extra_footer') ?? '';
$page = tpl($pdo, 16, 'PAGE_CONTAINER') ?? '';

echo "extra.less exists: " . ($extra !== '' ? 'yes' : 'no') . PHP_EOL;
echo "extra.less has barThreadStatLabel CSS: " . (strpos($extra, '.barThreadStatLabel') !== false ? 'yes' : 'no') . PHP_EOL;
echo "extra.less has @xf-responsiveNarrow block: " . (strpos($extra, '@xf-responsiveNarrow') !== false ? 'yes' : 'no') . PHP_EOL;
echo "extra_footer exists: " . ($footer !== '' ? 'yes' : 'no') . PHP_EOL;
echo "extra_footer has BAR_THREAD_META_FULL_V2 marker: " . (strpos($footer, 'BAR_THREAD_META_FULL_V2') !== false ? 'yes' : 'no') . PHP_EOL;
echo "extra_footer has mqStr 650: " . (strpos($footer, '(max-width: 650px)') !== false ? 'yes' : 'no') . PHP_EOL;
echo "PAGE_CONTAINER includes extra_footer include: " . (strpos($page, 'include template="extra_footer"') !== false ? 'yes' : 'no') . PHP_EOL;
