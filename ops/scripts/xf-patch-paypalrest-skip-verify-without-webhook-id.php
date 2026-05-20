<?php
/**
 * XenForo 2.3: With "Enable webhook verification" off, verifyConfig clears webhook_id but
 * validateCallback still called verifyWebhookSignature() — always fails. Skip verify when
 * webhook_id is empty (matches ACP intent).
 *
 *   sudo php /tmp/xf-patch-paypalrest-skip-verify-without-webhook-id.php /var/www/bareefers.org/forum
 */
$root = $argv[1] ?? '/var/www/bareefers.org/forum';
$path = rtrim($root, '/') . '/src/XF/Payment/PayPalRest.php';
$c = file_get_contents($path);
$needle = <<<'PHP'
		if ($this->isEventSkippable($state))
		{
			// skip any irrelevant webhooks
			$state->httpCode = 200;
			return false;
		}

		if (!$this->verifyWebhookSignature($state))
PHP;
$insert = <<<'PHP'
		if ($this->isEventSkippable($state))
		{
			// skip any irrelevant webhooks
			$state->httpCode = 200;
			return false;
		}

		if (empty($options['webhook_id']))
		{
			// Webhook verification disabled in ACP (no webhook_id); do not run signature verification.
			$state->httpCode = 200;
			return true;
		}

		if (!$this->verifyWebhookSignature($state))
PHP;
if (!str_contains($c, $needle)) {
	if (str_contains($c, 'Webhook verification disabled in ACP')) {
		echo "Already patched\n";
		exit(0);
	}
	fwrite(STDERR, "Anchor not found; XF version may differ.\n");
	exit(1);
}
file_put_contents($path, str_replace($needle, $insert, $c));
echo "Patched $path\n";
