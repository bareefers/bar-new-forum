<?php
/**
 * Remove @supports wrapper around sticky nav rules (some LESS pipelines choke on
 * @supports (…) or (…) even with escaping). Inner rules are preserved.
 *
 * Usage:
 *   php xf-extra-less-unwrap-sticky-supports.php /var/www/bareefers.org/forum 16
 */
use XF\Cli\App;
use XF\Service\Template\CompileService;

$root = $argv[1] ?? '/var/www/bareefers.org/forum';
$styleId = (int) ($argv[2] ?? 0);
if ($styleId <= 0) {
    fwrite(STDERR, "Usage: php xf-extra-less-unwrap-sticky-supports.php <forum_root> <style_id>\n");
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

$old = <<<'LESS'
@supports ~"(position: sticky) or (position: -webkit-sticky)" {
	/* Guests & members: no gap. Staff + sticky tools: .p-staffSticky keeps nav under sticky staff bar */
	.p-navSticky:not(.p-staffSticky) {
		position: -webkit-sticky;
		position: sticky;
		top: 0 !important;
	}
	.p-navSticky.p-staffSticky {
		position: -webkit-sticky;
		position: sticky;
		top: 35px !important;
	}
}

LESS;

$new = <<<'LESS'
/* Guests & members: no gap. Staff + sticky tools: .p-staffSticky keeps nav under sticky staff bar */
.p-navSticky:not(.p-staffSticky) {
	position: -webkit-sticky;
	position: sticky;
	top: 0 !important;
}
.p-navSticky.p-staffSticky {
	position: -webkit-sticky;
	position: sticky;
	top: 35px !important;
}

LESS;

$tpl = $template->template;
if (strpos($tpl, $old) === false) {
    fwrite(STDERR, "Expected @supports block not found; no change\n");
    exit(1);
}

$template->setTemplateUnchecked(str_replace($old, $new, $tpl));
$template->save(false);

$compile = $app->service(CompileService::class);
$compile->deleteCompiled($template);
$compile->recompile($template);

echo "OK: unwrapped sticky @supports in style {$styleId} extra.less\n";
