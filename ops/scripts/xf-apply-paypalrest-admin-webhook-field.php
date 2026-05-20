<?php
/**
 * On the XenForo server (run as root): show Webhook ID in ACP for PayPal REST profiles.
 *   sudo php /tmp/xf-apply-paypalrest-admin-webhook-field.php /var/www/bareefers.org/forum [/path/to/template-body.html]
 *
 * Optional 3rd arg: JSON options merge for payment_profile_id=2 (paypalrest), e.g. webhook_id only.
 */
$root = $argv[1] ?? '/var/www/bareefers.org/forum';
$tplPath = $argv[2] ?? __DIR__ . '/xf-payment-profile-paypalrest-admin-template.html';
if (!is_readable($tplPath)) {
	fwrite(STDERR, "Template file not readable: {$tplPath}\n");
	exit(1);
}
$template = file_get_contents($tplPath);
if ($template === false || $template === '') {
	fwrite(STDERR, "Empty template\n");
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
$m->set_charset('utf8mb4');

$stmt = $m->prepare('UPDATE xf_template SET template = ?, last_edit_date = UNIX_TIMESTAMP() WHERE title = ? AND style_id = 0 AND type = ?');
if (!$stmt) {
	fwrite(STDERR, $m->error . "\n");
	exit(1);
}
$title = 'payment_profile_paypalrest';
$type = 'admin';
$stmt->bind_param('sss', $template, $title, $type);
$stmt->execute();
if ($stmt->error) {
	fwrite(STDERR, $stmt->error . "\n");
	exit(1);
}
if ($stmt->affected_rows < 1) {
	fwrite(STDERR, "No row updated (missing master template {$title}?)\n");
	exit(2);
}
$stmt->close();

if (!empty($argv[3])) {
	$merge = json_decode($argv[3], true);
	if (!is_array($merge)) {
		fwrite(STDERR, "Third arg must be JSON object\n");
		exit(3);
	}
	$r = $m->query("SELECT payment_profile_id, options FROM xf_payment_profile WHERE provider_id = 'paypalrest' ORDER BY payment_profile_id ASC");
	if (!$r || $r->num_rows !== 1) {
		fwrite(STDERR, "Expected exactly one paypalrest profile; found " . ($r ? $r->num_rows : 0) . "\n");
		exit(4);
	}
	$row = $r->fetch_assoc();
	$opts = json_decode($row['options'], true) ?: [];
	$opts = array_merge($opts, $merge);
	$json = json_encode($opts, JSON_UNESCAPED_SLASHES);
	$pid = (int) $row['payment_profile_id'];
	$stmt2 = $m->prepare('UPDATE xf_payment_profile SET options = ? WHERE payment_profile_id = ?');
	$stmt2->bind_param('si', $json, $pid);
	$stmt2->execute();
	if ($stmt2->error) {
		fwrite(STDERR, $stmt2->error . "\n");
		exit(5);
	}
	$stmt2->close();
	echo "Updated payment_profile_id={$pid} options merge.\n";
}

echo "Updated xf_template payment_profile_paypalrest (style 0). Rebuild admin templates in ACP if needed.\n";
