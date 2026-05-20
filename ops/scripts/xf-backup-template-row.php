<?php
/**
 * mysqldump a single xf_template row (for rollback before editing templates in DB).
 *
 * Usage: php xf-backup-template-row.php [/path/to/forum] <style_id> <title> <type>
 * Example: php xf-backup-template-row.php /var/www/bareefers.org/forum 16 extra.less public
 *
 * Writes: /var/tmp/xf-template-<style>-<title>-<type>-<timestamp>.sql.gz
 */
$root = $argv[1] ?? '';
$styleId = (int)($argv[2] ?? 0);
$title = $argv[3] ?? '';
$type = $argv[4] ?? '';
if ($styleId <= 0 || $title === '' || $type === '' || !is_dir($root)) {
    fwrite(STDERR, "usage: php xf-backup-template-row.php /path/to/forum <style_id> <title> <type>\n");
    exit(1);
}

$config = [];
require $root . '/src/config.php';
$host = $config['db']['host'] ?? '127.0.0.1';
if ($host === 'localhost') {
    $host = '127.0.0.1';
}
$port = (int)($config['db']['port'] ?? 3306);
$db = $config['db']['dbname'];
$user = $config['db']['username'];
$pass = $config['db']['password'];

$safe = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $title);
$out = sprintf(
    '/var/tmp/xf-template-s%d-%s-%s-%s.sql.gz',
    $styleId,
    $safe,
    $type,
    date('Ymd-His')
);

$where = sprintf(
    "style_id=%d AND title=%s AND type=%s",
    $styleId,
    "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], $title) . "'",
    "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], $type) . "'"
);

$env = $_ENV;
$env['MYSQL_PWD'] = $pass;
$cmd = [
    'mysqldump',
    '-h', $host,
    '-P', (string) $port,
    '-u', $user,
    '--no-tablespaces',
    $db,
    'xf_template',
    '--where=' . $where,
];
$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];
$proc = proc_open($cmd, $descriptors, $pipes, null, $env);
if (!is_resource($proc)) {
    fwrite(STDERR, "mysqldump failed to start\n");
    exit(1);
}
fclose($pipes[0]);
$sql = stream_get_contents($pipes[1]);
$err = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$code = proc_close($proc);
if ($code !== 0) {
    fwrite(STDERR, "mysqldump exit $code: $err\n");
    exit(1);
}

$gz = gzencode($sql, 9);
if ($gz === false || file_put_contents($out, $gz) === false) {
    fwrite(STDERR, "write failed: $out\n");
    exit(1);
}

echo "OK: $out\n";
