<?php
$root = $argv[1] ?? '/var/www/bareefers.org/forum';
$config = [];
require $root . '/src/config.php';
$h = ($config['db']['host'] ?? '') === 'localhost' ? '127.0.0.1' : $config['db']['host'];
$dsn = 'mysql:host=' . $h . ';port=' . ($config['db']['port'] ?? 3306) . ';dbname=' . $config['db']['dbname'] . ';charset=utf8mb4';
$pdo = new PDO($dsn, $config['db']['username'], $config['db']['password']);
foreach ($pdo->query('DESCRIBE xf_style') as $r) {
    echo $r['Field'] . "\t" . $r['Type'] . "\t" . $r['Null'] . "\n";
}
echo "---\n";
foreach ($pdo->query('SELECT style_id, parent_id, title, user_selectable FROM xf_style ORDER BY style_id') as $r) {
    echo json_encode($r, JSON_UNESCAPED_SLASHES) . "\n";
}
echo "--- parent_list samples (id, hex) ---\n";
foreach ([4, 12, 16] as $id) {
    $st = $pdo->prepare('SELECT style_id, parent_id, parent_list FROM xf_style WHERE style_id = ?');
    $st->execute([$id]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    if ($r) {
        echo $id . "\tparent=" . $r['parent_id'] . "\tpl_hex=" . bin2hex($r['parent_list']) . "\n";
    }
}
