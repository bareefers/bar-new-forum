<?php
/**
 * Replay XenForo legacy PayPal IPNs that failed with "Invalid business or receiver_email."
 * (after fixing xf_payment_profile primary_account to paypal@bareefers.org).
 *
 * Usage on forum host:
 *   php xf-replay-paypal-invalid-business-ipn.php /var/www/bareefers.org/forum
 *   php xf-replay-paypal-invalid-business-ipn.php /var/www/bareefers.org/forum --execute
 *   php xf-replay-paypal-invalid-business-ipn.php /var/www/bareefers.org/forum --execute --days=365
 *
 * See docs/PAYPAL-PAYMENT-MAY2026.md
 */
use XF\Cli\App;
use XF\Entity\PaymentProvider;
use XF\Http\Request;
use XF\Payment\AbstractProvider;
use XF\Repository\PaymentRepository;

$root = '';
$execute = false;
$days = 365;

foreach (array_slice($argv, 1) as $arg) {
	if ($arg === '--execute') {
		$execute = true;
	} elseif (str_starts_with($arg, '--days=')) {
		$days = max(1, (int) substr($arg, 7));
	} elseif ($root === '' && $arg[0] !== '-') {
		$root = $arg;
	}
}

if ($root === '' || !is_dir($root)) {
	fwrite(STDERR, "usage: php xf-replay-paypal-invalid-business-ipn.php /path/to/forum [--execute] [--days=365]\n");
	exit(1);
}

require $root . '/src/XF.php';
\XF::start($root);
$app = \XF::setupApp(App::class);

$db = \XF::db();
$since = time() - ($days * 86400);
$rows = $db->fetchAll(
	'SELECT provider_log_id, purchase_request_key, transaction_id, log_details, log_date
	 FROM xf_payment_provider_log
	 WHERE log_message = ?
	   AND log_type = ?
	   AND transaction_id IS NOT NULL AND transaction_id != ?
	   AND log_date > ?
	 ORDER BY provider_log_id ASC',
	['Invalid business or receiver_email.', 'error', '', $since]
);

/** @var PaymentProvider|null $provider */
$provider = $app->em()->find(PaymentProvider::class, 'paypal');
if (!$provider) {
	fwrite(STDERR, "PayPal provider not found.\n");
	exit(1);
}

/** @var AbstractProvider $handler */
$handler = $provider->handler;
$paymentRepo = $app->repository(PaymentRepository::class);

$seenTxn = [];
$stats = ['would' => 0, 'ok' => 0, 'skip' => 0, 'fail' => 0];

echo ($execute ? 'EXECUTE' : 'DRY-RUN') . " replay since " . gmdate('Y-m-d', $since) . " UTC ({$days} days)\n";
echo 'Candidate log rows: ' . count($rows) . "\n\n";

foreach ($rows as $row) {
	$txnId = $row['transaction_id'];
	if (isset($seenTxn[$txnId])) {
		continue;
	}
	$seenTxn[$txnId] = true;

	$post = json_decode($row['log_details'], true);
	if (!is_array($post) || $post === []) {
		echo "SKIP txn={$txnId}: empty log_details\n";
		$stats['skip']++;
		continue;
	}

	$status = $post['payment_status'] ?? '';
	if ($status !== 'Completed') {
		echo "SKIP txn={$txnId}: payment_status={$status}\n";
		$stats['skip']++;
		continue;
	}

	$txnType = $post['txn_type'] ?? '';
	if (!in_array($txnType, ['web_accept', 'subscr_payment'], true)) {
		echo "SKIP txn={$txnId}: txn_type={$txnType}\n";
		$stats['skip']++;
		continue;
	}

	if ($paymentRepo->findLogsByTransactionIdForProvider($txnId, 'paypal')->total()) {
		echo "SKIP txn={$txnId}: already has payment/cancel log\n";
		$stats['skip']++;
		continue;
	}

	$custom = $post['custom'] ?? '';
	$item = $post['item_name'] ?? '';
	echo ($execute ? 'REPLAY' : 'WOULD') . " txn={$txnId} type={$txnType} custom={$custom} item={$item}\n";
	$stats[$execute ? 'ok' : 'would']++;

	if (!$execute) {
		continue;
	}

	$server = [
		'REQUEST_METHOD' => 'POST',
		'REMOTE_ADDR' => '127.0.0.1',
		'HTTP_USER_AGENT' => 'BAR-IPN-Replay/1.0',
	];
	$request = new Request($app->inputFilterer(), $post, [], [], $server);

	$state = $handler->setupCallback($request);
	$state->legacy = false;
	$state->_POST = $post;

	if (!$handler->validateCallback($state)) {
		echo "  FAIL validateCallback: {$state->logMessage}\n";
		$stats['fail']++;
		$stats['ok']--;
		continue;
	}
	if (!$handler->validateTransaction($state)) {
		echo "  SKIP validateTransaction: {$state->logMessage}\n";
		$stats['skip']++;
		$stats['ok']--;
		continue;
	}
	if (!$handler->validatePurchaseRequest($state)
		|| !$handler->validatePurchasableHandler($state)
		|| !$handler->validatePaymentProfile($state)
		|| !$handler->validatePurchaser($state)
	) {
		echo "  FAIL pre-complete validation: {$state->logMessage}\n";
		$stats['fail']++;
		$stats['ok']--;
		continue;
	}
	if (!$handler->validatePurchasableData($state) || !$handler->validateCost($state)) {
		echo "  FAIL cost/data: {$state->logMessage}\n";
		$stats['fail']++;
		$stats['ok']--;
		continue;
	}

	$handler->setProviderMetadata($state);
	$handler->getPaymentResult($state);

	if (!$state->paymentResult) {
		echo "  SKIP no paymentResult (status/type)\n";
		$stats['skip']++;
		$stats['ok']--;
		continue;
	}

	$handler->completeTransaction($state);

	if ($state->logType) {
		$handler->log($state);
	}

	echo "  OK paymentResult={$state->paymentResult} log={$state->logType}: {$state->logMessage}\n";
}

echo "\nSummary: " . json_encode($stats) . "\n";
exit($execute && $stats['fail'] > 0 ? 1 : 0);
