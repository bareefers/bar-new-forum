<?php
/**
 * Snapshot BAR / Aurora child style (default style_id=16) for restore after a DB import:
 *   xf_style, xf_style_property, xf_style_property_map, xf_template (rows for that style_id)
 *
 * Uses mysqldump --replace --no-create-info so restore reapplies rows by primary key without
 * dropping whole tables.
 *
 * Usage:
 *   php xf-backup-style16-visual.php /path/to/forum [/parent/snapshot/dir] [style_id]
 *
 * Creates: {parent}/BAR-style16-visual-YYYYMMDD-HHMMSS/visual.sql.gz
 */
$root = $argv[1] ?? '';
$parentDir = $argv[2] ?? '/var/tmp/bar-style16-snapshots';
$styleId = (int) ($argv[3] ?? 16);

if ($root === '' || !is_dir($root) || $styleId <= 0) {
    fwrite(STDERR, "Usage: php xf-backup-style16-visual.php /path/to/forum [/parent_snapshot_dir] [style_id]\n");
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

$stamp = date('Ymd-His');
$dest = rtrim($parentDir, '/') . '/BAR-style16-visual-' . $stamp;
if (!@mkdir($dest, 0755, true) && !is_dir($dest)) {
    fwrite(STDERR, "mkdir failed: {$dest}\n");
    exit(1);
}
// So sudo -u www-data php xf-export-template-to-less-file.php can write extra.less here
if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
    @chown($dest, 'www-data');
    @chgrp($dest, 'www-data');
}

$prefix = $config['db']['tablePrefix'] ?? '';
$whereStyle = 'style_id=' . $styleId;
$specs = [
    [$prefix . 'xf_style', $whereStyle],
    [$prefix . 'xf_style_property', $whereStyle],
    [$prefix . 'xf_style_property_map', $whereStyle],
    [$prefix . 'xf_template', $whereStyle],
];

$parts = [];
$parts[] = "-- BAR style {$styleId} visual snapshot {$stamp}\n";
$parts[] = "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n";

$env = $_ENV;
$env['MYSQL_PWD'] = $pass;

foreach ($specs as [$table, $where]) {
    $cmd = [
        'mysqldump',
        '-h', $host,
        '-P', (string) $port,
        '-u', $user,
        '--no-tablespaces',
        '--single-transaction',
        '--skip-comments',
        '--replace',
        '--no-create-info',
        '--complete-insert',
        $db,
        $table,
        '--where=' . $where,
    ];
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $proc = proc_open($cmd, $descriptors, $pipes, null, $env);
    if (!is_resource($proc)) {
        fwrite(STDERR, "mysqldump failed to start for {$table}\n");
        exit(1);
    }
    fclose($pipes[0]);
    $sql = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($proc);
    if ($code !== 0) {
        fwrite(STDERR, "mysqldump {$table} exit {$code}: {$err}\n");
        exit(1);
    }
    $parts[] = "\n-- --- {$table} ({$where}) ---\n";
    $parts[] = $sql;
}

$parts[] = "\nSET FOREIGN_KEY_CHECKS=1;\n";
$raw = implode('', $parts);
$gz = gzencode($raw, 9);
if ($gz === false) {
    fwrite(STDERR, "gzencode failed\n");
    exit(1);
}

$outGz = $dest . '/visual.sql.gz';
if (file_put_contents($outGz, $gz) === false) {
    fwrite(STDERR, "write failed: {$outGz}\n");
    exit(1);
}
@chmod($outGz, 0644);

$gzSize = filesize($outGz);
if ($gzSize === false || $gzSize < 1024) {
    fwrite(STDERR, "SANITY_FAIL: visual.sql.gz missing or too small ({$gzSize} bytes)\n");
    exit(1);
}

$dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $db . ';charset=utf8mb4';
$pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$counts = [];
foreach ([
    $prefix . 'xf_style' => 'style_id=' . $styleId,
    $prefix . 'xf_style_property' => 'style_id=' . $styleId,
    $prefix . 'xf_style_property_map' => 'style_id=' . $styleId,
    $prefix . 'xf_template' => 'style_id=' . $styleId,
] as $tbl => $w) {
    $bt = '`' . str_replace('`', '``', $tbl) . '`';
    $q = $pdo->query('SELECT COUNT(*) FROM ' . $bt . ' WHERE ' . $w);
    $counts[$tbl] = (int) $q->fetchColumn();
}
$tplTable = '`' . str_replace('`', '``', $prefix . 'xf_template') . '`';
$extraLen = (int) $pdo->query(
    "SELECT COALESCE(MAX(LENGTH(template)), 0) FROM {$tplTable} WHERE style_id={$styleId} AND title='extra.less' AND type='public'"
)->fetchColumn();

$verify = $dest . '/VERIFY.txt';
$verifyBody = "BAR style {$styleId} snapshot audit {$stamp}\n";
$verifyBody .= 'database=' . $db . "\n";
foreach ($counts as $t => $c) {
    $verifyBody .= "rows\t{$t}\t{$c}\n";
}
$verifyBody .= "extra.less_template_bytes_in_db\t{$extraLen}\n";
$verifyBody .= "visual.sql.gz_bytes_on_disk\t{$gzSize}\n";
if ($counts[$prefix . 'xf_template'] < 1) {
    fwrite(STDERR, "SANITY_FAIL: xf_template has no rows for style_id={$styleId}\n");
    exit(1);
}
if (file_put_contents($verify, $verifyBody) === false) {
    fwrite(STDERR, "write failed: {$verify}\n");
    exit(1);
}
@chmod($verify, 0644);

echo "OK: {$outGz}\n";
