<?php
/**
 * Print XenForo LESS compile errors for a style's public extra.less (CLI diagnostic).
 *
 * Usage:
 *   php xf-compile-extra-less-test.php /var/www/bareefers.org/forum 16
 */
use XF\Cli\App;

$root = $argv[1] ?? '/var/www/bareefers.org/forum';
$styleId = (int) ($argv[2] ?? 0);
if ($styleId <= 0) {
    fwrite(STDERR, "Usage: php xf-compile-extra-less-test.php <forum_root> <style_id>\n");
    exit(1);
}

require $root . '/src/XF.php';
\XF::start($root);
$app = \XF::setupApp(App::class);

/** @var \XF\Entity\Template|null $template */
$template = $app->finder('XF:Template')
    ->where('style_id', $styleId)
    ->where('title', 'extra.less')
    ->where('type', 'public')
    ->fetchOne();

if (!$template) {
    fwrite(STDERR, "Template not found\n");
    exit(1);
}

$compiler = $app->templateCompiler();
$ast = $template->template_parsed;
if (!$ast) {
    fwrite(STDERR, "No template_parsed\n");
    exit(1);
}

$lang = $app->language(0);
try {
    $compiler->reset();
    $compiled = $compiler->compileAst($ast, $lang);
    echo 'OK compileAst length=' . strlen($compiled) . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
    exit(1);
}
