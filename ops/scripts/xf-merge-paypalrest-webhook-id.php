<?php
/**
 * Merge webhook_id into all xf_payment_profile rows with provider_id paypalrest.
 *   sudo php xf-merge-paypalrest-webhook-id.php /var/www/bareefers.org/forum WEBHOOK_ID_HERE
 */
$root = $argv[1] ?? '/var/www/bareefers.org/forum';
$wid = $argv[2] ?? '';
if ($wid === '') {
	fwrite(STDERR, "Usage: php ... forum_root WEBHOOK_ID\n");
	exit(1);
}
require $root . '/src/config.php';
$m = new mysqli(
	$config['db']['host'],
	$config['db']['username'],
	$config['db']['password'],
	$config['db']['dbname']
);
if ($m->connect_error) {
	fwrite(STDERR, $m->connect_error . "\n");
	exit(1);
}
$r = $m->query("SELECT payment_profile_id, options FROM xf_payment_profile WHERE provider_id = 'paypalrest'");
while ($row = $r->fetch_assoc()) {
	$o = json_decode($row['options'], true) ?: [];
	$o['webhook_id'] = $wid;
	$json = json_encode($o, JSON_UNESCAPED_SLASHES);
	$stmt = $m->prepare('UPDATE xf_payment_profile SET options = ? WHERE payment_profile_id = ?');
	$id = (int) $row['payment_profile_id'];
	$stmt->bind_param('si', $json, $id);
	$stmt->execute();
	echo "Updated payment_profile_id={$id}\n";
	$stmt->close();
}
