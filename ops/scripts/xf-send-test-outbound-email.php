<?php
/**
 * Send mail through **`contact_email`** so XenForo runs **MAIL_CONTAINER** + email CSS (same pipeline as real notices).
 *
 * Usage (on the forum host, after XenForo bootstrap paths exist):
 *   php xf-send-test-outbound-email.php /var/www/bareefers.org/forum recipient@example.com
 */
use XF\Cli\App;

$root = $argv[1] ?? '';
$to = $argv[2] ?? '';
if ($root === '' || $to === '' || !is_dir($root)) {
	fwrite(STDERR, "usage: php xf-send-test-outbound-email.php /path/to/forum recipient@example.com\n");
	exit(1);
}

if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
	fwrite(STDERR, "invalid email: {$to}\n");
	exit(1);
}

require $root . '/src/XF.php';
\XF::start($root);
$app = \XF::setupApp(App::class);

$mailer = $app->mailer();
$mail = $mailer->newMail();
$mail->setTo($to);
// Use a real email template so XenForo wraps HTML in MAIL_CONTAINER (header logo + styled body).
$mail->setTemplate('contact_email', [
	'subject' => \XF::phrase('outbound_email_test_subject', ['board' => $app->options()->boardTitle])->render('raw'),
	'name' => 'CLI outbound test',
	'email' => $to,
	'ip' => '127.0.0.1',
	'message' => \XF::phrase('outbound_email_test_body', ['username' => 'CLI outbound test', 'board' => $app->options()->boardTitle])->render('raw'),
]);

$sent = $mail->send($mailer->getDefaultTransport(), false);
if ($sent) {
	echo "OK: sent test outbound email to {$to}\n";
	exit(0);
}

fwrite(STDERR, "send failed (check xf_error_log / mail transport)\n");
exit(1);
