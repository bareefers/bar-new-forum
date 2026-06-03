<?php
/**
 * Fix PayPal REST setupCallback when purchase request uses legacy PayPal profile (no webhook_id).
 * PayPal REST webhooks hit ?_xfProvider=paypalrest but getPaymentProfile() follows the
 * purchase request → payment_profile_id 1 (paypal), causing undefined webhook_id warnings
 * and failed signature verification.
 *
 *   sudo php /var/www/bareefers.org/bar-new-forum/ops/scripts/xf-patch-paypalrest-webhook-profile.php /var/www/bareefers.org/forum
 *
 * See docs/PAYPAL-PAYMENT-MAY2026.md
 */
$root = $argv[1] ?? '/var/www/bareefers.org/forum';
$path = rtrim($root, '/') . '/src/XF/Payment/PayPalRest.php';
if (!is_readable($path) || !is_writable($path)) {
	fwrite(STDERR, "Need readable+writable: {$path}\n");
	exit(1);
}

$c = file_get_contents($path);
if (str_contains($c, 'BAR: use paypalrest profile when purchase request profile lacks webhook_id')) {
	echo "Already patched: {$path}\n";
	exit(0);
}

$old = <<<'PHP'
		$paymentProfile = $state->getPaymentProfile();

		if (!$paymentProfile)
		{
			$finder = \XF::finder(\XF\Finder\PaymentProfileFinder::class)->where('provider_id', 'paypalrest');
			if ($finder->total() === 1)
			{
				$paymentProfile = $finder->fetchOne();
				$state->paymentProfile = $paymentProfile;
			}
			else
			{
				return $state;
			}
		}

		$state->webhookHeaders = [
			'auth_algo' => $request->getServer('HTTP_PAYPAL_AUTH_ALGO'),
			'cert_url' => $request->getServer('HTTP_PAYPAL_CERT_URL'),
			'transmission_id' => $request->getServer('HTTP_PAYPAL_TRANSMISSION_ID'),
			'transmission_sig' => $request->getServer('HTTP_PAYPAL_TRANSMISSION_SIG'),
			'transmission_time' => $request->getServer('HTTP_PAYPAL_TRANSMISSION_TIME'),
		];

		$state->webhookId = $paymentProfile->options['webhook_id'];
PHP;

$new = <<<'PHP'
		$paymentProfile = $state->getPaymentProfile();

		if (!$paymentProfile)
		{
			$finder = \XF::finder(\XF\Finder\PaymentProfileFinder::class)->where('provider_id', 'paypalrest');
			if ($finder->total() === 1)
			{
				$paymentProfile = $finder->fetchOne();
				$state->paymentProfile = $paymentProfile;
			}
			else
			{
				return $state;
			}
		}
		// BAR: use paypalrest profile when purchase request profile lacks webhook_id (legacy PayPal checkout).
		else if (empty($paymentProfile->options['webhook_id']))
		{
			$finder = \XF::finder(\XF\Finder\PaymentProfileFinder::class)->where('provider_id', 'paypalrest');
			if ($finder->total() >= 1)
			{
				$paymentProfile = $finder->fetchOne();
				$state->paymentProfile = $paymentProfile;
			}
		}

		$state->webhookHeaders = [
			'auth_algo' => $request->getServer('HTTP_PAYPAL_AUTH_ALGO'),
			'cert_url' => $request->getServer('HTTP_PAYPAL_CERT_URL'),
			'transmission_id' => $request->getServer('HTTP_PAYPAL_TRANSMISSION_ID'),
			'transmission_sig' => $request->getServer('HTTP_PAYPAL_TRANSMISSION_SIG'),
			'transmission_time' => $request->getServer('HTTP_PAYPAL_TRANSMISSION_TIME'),
		];

		$state->webhookId = $paymentProfile->options['webhook_id'] ?? null;
PHP;

if (!str_contains($c, $old)) {
	fwrite(STDERR, "Anchor not found; inspect PayPalRest.php setupCallback manually.\n");
	exit(2);
}

file_put_contents($path, str_replace($old, $new, $c));
echo "Patched {$path}\n";
