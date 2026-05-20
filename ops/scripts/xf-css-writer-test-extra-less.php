<?php
/**
 * Render public:extra.less through CssWriter to surface LESS parse errors.
 *
 * Usage:
 *   php xf-css-writer-test-extra-less.php /var/www/bareefers.org/forum 16
 */
use XF\Cli\App;

$root = $argv[1] ?? '/var/www/bareefers.org/forum';
$styleId = (int) ($argv[2] ?? 0);
if ($styleId <= 0) {
    fwrite(STDERR, "Usage: php xf-css-writer-test-extra-less.php <forum_root> <style_id>\n");
    exit(1);
}

require $root . '/src/XF.php';
\XF::start($root);
$app = \XF::setupApp(App::class);
\XF::$debugMode = true;

try {
    $response = $app->cssWriter()->run(['public:extra.less'], $styleId, 1, null);
    $body = $response->body();
    echo 'OK bytes=' . strlen($body) . "\n";
    echo substr($body, 0, 300) . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . "\n");
    if (method_exists($e, 'getMessageParameters')) {
        fwrite(STDERR, print_r($e->getMessageParameters(), true));
    }
    fwrite(STDERR, $e->getTraceAsString() . "\n");
    exit(1);
}
