<?php
/**
 * Print XenForo auto-job + relevant cron rows (run on forum server).
 * Usage: php xf-query-cron-info.php [/path/to/forum]
 */
$root = $argv[1] ?? '/var/www/bareefers.org/forum';
$config = [];
require $root . '/src/config.php';
$h = ($config['db']['host'] ?? '127.0.0.1') === 'localhost' ? '127.0.0.1' : $config['db']['host'];
$dsn = 'mysql:host=' . $h . ';port=' . ($config['db']['port'] ?? 3306) . ';dbname=' . $config['db']['dbname'] . ';charset=utf8mb4';
$pdo = new PDO($dsn, $config['db']['username'], $config['db']['password']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== xf_option (job / cron related) ===\n";
$st = $pdo->query(
    "SELECT option_id, option_value FROM xf_option WHERE option_id IN ('autoJobRun','enableMailQueue','boardUrl','contactUrl') OR option_id LIKE '%cron%' ORDER BY option_id"
);
foreach ($st as $row) {
    echo $row['option_id'] . "\t" . $row['option_value'] . "\n";
}

// XF 2.2+ uses cron_class / cron_method (older docs said callback_*).
$cols = $pdo->query("SHOW COLUMNS FROM xf_cron_entry")->fetchAll(PDO::FETCH_COLUMN);
$classCol = in_array('cron_class', $cols, true) ? 'cron_class' : (in_array('callback_class', $cols, true) ? 'callback_class' : null);
$methodCol = in_array('cron_method', $cols, true) ? 'cron_method' : (in_array('callback_method', $cols, true) ? 'callback_method' : null);
if (!$classCol || !$methodCol) {
    echo "\n(could not detect cron class/method columns; columns: " . implode(', ', $cols) . ")\n";
} else {
    echo "\n=== xf_cron_entry (CleanUp / downgrade / user upgrade) ===\n";
    $sql = "SELECT entry_id, active, {$classCol} AS cron_class, {$methodCol} AS cron_method, run_rules
     FROM xf_cron_entry
     WHERE {$classCol} LIKE '%CleanUp%'
        OR {$methodCol} LIKE '%downgrade%'
        OR {$methodCol} LIKE '%UserUpgrade%'
        OR entry_id LIKE '%upgrade%'
     ORDER BY entry_id";
    foreach ($pdo->query($sql) as $row) {
        echo json_encode($row, JSON_UNESCAPED_SLASHES) . "\n";
    }
}

echo "\n=== downgradeExpired next_run (unix time) ===\n";
try {
    $row = $pdo->query(
        "SELECT entry_id, next_run, LENGTH(run_rules) AS rules_len FROM xf_cron_entry WHERE entry_id = 'downgradeExpired'"
    )->fetch(PDO::FETCH_ASSOC);
    echo $row ? json_encode($row, JSON_UNESCAPED_SLASHES) . "\n" : "(not found)\n";
} catch (Throwable $e) {
    echo $e->getMessage() . "\n";
}
