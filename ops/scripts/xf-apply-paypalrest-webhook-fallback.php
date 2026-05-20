<?php
/**
 * One-off on server: fix PayPal REST webhooks returning 403 when JSON has no XenForo purchase key.
 *   sudo php /tmp/xf-apply-paypalrest-webhook-fallback.php /var/www/bareefers.org/forum
 */
$root = $argv[1] ?? '/var/www/bareefers.org/forum';
$path = rtrim($root, '/') . '/src/XF/Payment/PayPalRest.php';
if (!is_readable($path) || !is_writable($path)) {
    fwrite(STDERR, "Need readable+writable PayPalRest.php: {$path}\n");
    exit(1);
}
$c = file_get_contents($path);
// Tabs must match stock XenForo PayPalRest.php indentation.
$old = "\t\t\$paymentProfile = \$state->getPaymentProfile();\n\n\t\tif (!\$paymentProfile)\n\t\t{\n\t\t\treturn \$state;\n\t\t}\n";
$new = "\t\t\$paymentProfile = \$state->getPaymentProfile();\n\n\t\tif (!\$paymentProfile)\n\t\t{\n"
	. "\t\t\t\$finder = \\XF::finder(\\XF\\Finder\\PaymentProfileFinder::class)->where('provider_id', 'paypalrest');\n"
	. "\t\t\tif (\$finder->total() === 1)\n"
	. "\t\t\t{\n"
	. "\t\t\t\t\$paymentProfile = \$finder->fetchOne();\n"
	. "\t\t\t\t\$state->paymentProfile = \$paymentProfile;\n"
	. "\t\t\t}\n"
	. "\t\t\telse\n"
	. "\t\t\t{\n"
	. "\t\t\t\treturn \$state;\n"
	. "\t\t\t}\n"
	. "\t\t}\n";
if (!str_contains($c, $old)) {
    fwrite(STDERR, "Anchor not found (already patched or XF version differs?)\n");
    exit(2);
}
file_put_contents($path, str_replace($old, $new, $c));
echo "Patched {$path}\n";
