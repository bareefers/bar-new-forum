<?php
/**
 * Remove a marked block from extra.less between markers like (slash-star KEY star-slash) ... (slash-star KEY end star-slash).
 *
 * Usage:
 *   php xf-extra-less-strip-marked-block.php /var/www/bareefers.org/forum 16 "BAR: seamless header chrome"
 */
use XF\Cli\App;
use XF\Service\Template\CompileService;

$root = $argv[1] ?? '/var/www/bareefers.org/forum';
$styleId = (int) ($argv[2] ?? 0);
$key = $argv[3] ?? '';
if ($styleId <= 0 || $key === '') {
    fwrite(STDERR, "Usage: php xf-extra-less-strip-marked-block.php <forum_root> <style_id> <marker_key>\n");
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
    fwrite(STDERR, "extra.less not found\n");
    exit(1);
}

$start = '/* ' . $key . ' */';
$end = '/* ' . $key . ' end */';
$tpl = $template->template;
$re = '/\n\n' . preg_quote($start, '/') . '[\s\S]*?' . preg_quote($end, '/') . '\n?/';
$new = preg_replace($re, '', $tpl, 1, $count);
if ($count === 0) {
    fwrite(STDOUT, "Marker block not found\n");
    exit(0);
}

$template->setTemplateUnchecked($new);
$template->save(false);

$compile = $app->service(CompileService::class);
$compile->deleteCompiled($template);
$compile->recompile($template);

echo "OK: stripped {$key} block ({$count})\n";
