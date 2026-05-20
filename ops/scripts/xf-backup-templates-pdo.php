<?php
// Backup specific XenForo templates via PDO (no XF bootstrap).
$root = $argv[1] ?? '/var/www/bareefers.org/forum';
$styleId = (int)($argv[2] ?? 16);
$outDir = $argv[3] ?? '/var/tmp/bar-style-backups';

$config = [];
require $root . '/src/config.php';

$host = ($config['db']['host'] ?? '') === 'localhost' ? '127.0.0.1' : ($config['db']['host'] ?? '127.0.0.1');
$port = $config['db']['port'] ?? 3306;
$db = $config['db']['dbname'] ?? '';
$user = $config['db']['username'] ?? '';
$pass = $config['db']['password'] ?? '';

$dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
$pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

if (!is_dir($outDir) && !mkdir($outDir, 0775, true)) {
    fwrite(STDERR, "Failed to create output dir: {$outDir}\n");
    exit(1);
}

$stamp = gmdate('Ymd-His');
$targets = ['extra.less', 'extra_footer'];
$stmt = $pdo->prepare("SELECT template FROM xf_template WHERE style_id = ? AND type = 'public' AND title = ?");

foreach ($targets as $title) {
    $stmt->execute([$styleId, $title]);
    $template = $stmt->fetchColumn();
    if ($template === false) {
        fwrite(STDERR, "Template not found: {$title}\n");
        continue;
    }
    $path = rtrim($outDir, '/') . "/{$title}.{$stamp}.bak";
    file_put_contents($path, str_replace("\r\n", "\n", (string)$template));
    $hash = hash_file('sha256', $path);
    echo "{$path} sha256={$hash}\n";
}
