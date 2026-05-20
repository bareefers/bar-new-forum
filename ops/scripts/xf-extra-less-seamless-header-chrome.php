<?php
/**
 * Append a small extra.less block so primary nav + section links share the same
 * 354deg gradient as .p-proxy (avoids visible seams / mismatched chrome).
 *
 * Usage:
 *   php xf-extra-less-seamless-header-chrome.php /var/www/bareefers.org/forum 16
 */
use XF\Cli\App;
use XF\Service\Template\CompileService;

$root = $argv[1] ?? '/var/www/bareefers.org/forum';
$styleId = (int) ($argv[2] ?? 0);
if ($styleId <= 0) {
    fwrite(STDERR, "Usage: php xf-extra-less-seamless-header-chrome.php <forum_root> <style_id>\n");
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
    fwrite(STDERR, "extra.less not found for style {$styleId}\n");
    exit(1);
}

$marker = '/* BAR: seamless header chrome */';
if (strpos($template->template, $marker) !== false) {
    fwrite(STDOUT, "Already present\n");
    exit(0);
}

$append = <<<LESS


{$marker}
.p-navSticky.p-navSticky--primary {
	background-color: transparent;
	background-image: linear-gradient(
		354deg,
		@xf-paletteColor1 0%,
		@xf-paletteAccent1 100%
	);
}

.p-navSticky.p-navSticky--primary + .p-sectionLinks {
	background-color: transparent;
	background-image: linear-gradient(
		354deg,
		@xf-paletteColor1 0%,
		@xf-paletteAccent1 100%
	);
	border-top: none;
}

.p-navSticky.p-navSticky--primary .p-nav {
	background-color: transparent;
	background-image: none;
}
/* BAR: seamless header chrome end */

LESS;

$template->setTemplateUnchecked(rtrim($template->template) . $append);
$template->save(false);

$compile = $app->service(CompileService::class);
$compile->deleteCompiled($template);
$compile->recompile($template);

echo "OK: appended seamless header chrome to style {$styleId} extra.less\n";
