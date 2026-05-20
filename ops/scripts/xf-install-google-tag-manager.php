<?php
/**
 * bareefers.org (XenForo): install Google Tag Manager container into public templates.
 *
 * - google_analytics: GTM loader (normally included near end of <head> via PAGE_CONTAINER)
 * - PAGE_CONTAINER: noscript iframe immediately after opening <body ...>
 *
 * Idempotent: strips prior GTM snippets that match Google’s comment wrappers, then reapplies.
 *
 * Usage (copy script to server or run from deployed repo path):
 *   sudo -u www-data php xf-install-google-tag-manager.php /var/www/bareefers.org/forum 16 GTM-WS6J8RBD
 *
 * Style ID 16 = Aurora child on bareefers.org — change if your customized templates live elsewhere.
 */
use XF\Cli\App;
use XF\Service\Template\CompileService;

$root = $argv[1] ?? '';
$styleId = (int) ($argv[2] ?? 0);
$gtmId = $argv[3] ?? 'GTM-WS6J8RBD';

if ($styleId <= 0 || !is_dir($root)) {
    fwrite(STDERR, "Usage: php xf-install-google-tag-manager.php <forum_root> <style_id> [GTM-XXXXXXXX]\n");
    exit(1);
}

if (!preg_match('/^GTM-[A-Z0-9]+$/', $gtmId)) {
    fwrite(STDERR, "Invalid GTM id (expected GTM-...)\n");
    exit(1);
}

$headSnippet = <<<HTML
<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','{$gtmId}');</script>
<!-- End Google Tag Manager -->
HTML;

$noscriptBlock = <<<HTML
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id={$gtmId}"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
HTML;

require $root . '/src/XF.php';
\XF::start($root);
$app = \XF::setupApp(App::class);

/** @var \XF\Entity\Template|null $fetch */
$fetch = static function (\XF\App $app, int $styleId, string $title): ?\XF\Entity\Template {
    return $app->finder('XF:Template')
        ->where('style_id', $styleId)
        ->where('type', 'public')
        ->where('title', $title)
        ->fetchOne();
};

/**
 * Resolve a template row: prefer the requested style, then master (0).
 * Many sites only customize PAGE_CONTAINER on a child style while google_analytics lives on master.
 *
 * @return array{\XF\Entity\Template,int}|array{null,null}
 */
$resolve = static function (\XF\App $app, int $preferredStyleId, string $title) use ($fetch): array {
    foreach ([$preferredStyleId, 0] as $sid) {
        $t = $fetch($app, $sid, $title);
        if ($t) {
            return [$t, $sid];
        }
    }
    return [null, 0];
};

[$ga, $gaStyleId] = $resolve($app, $styleId, 'google_analytics');
if (!$ga) {
    fwrite(STDERR, "Template not found: public google_analytics (tried style_id {$styleId} and 0)\n");
    exit(1);
}

$gaBody = $ga->template;
$gaBody = preg_replace('/<!-- Google Tag Manager -->[\s\S]*?<!-- End Google Tag Manager -->\s*/', '', $gaBody);
$gaBody = trim($gaBody) . "\n\n" . trim($headSnippet) . "\n";

$ga->setTemplateUnchecked($gaBody);
if ($ga->hasErrors()) {
    foreach ($ga->getErrors() as $k => $msg) {
        fwrite(STDERR, "google_analytics [{$k}]: {$msg}\n");
    }
    exit(1);
}
$ga->save(false);

[$page, $pageStyleId] = $resolve($app, $styleId, 'PAGE_CONTAINER');
if (!$page) {
    fwrite(STDERR, "Template not found: public PAGE_CONTAINER (tried style_id {$styleId} and 0)\n");
    exit(1);
}

$pbody = $page->template;
$pbody = preg_replace('/<!-- Google Tag Manager \(noscript\) -->[\s\S]*?<!-- End Google Tag Manager \(noscript\) -->\s*/', '', $pbody);

if (strpos($pbody, 'googletagmanager.com/ns.html') !== false) {
    fwrite(STDERR, "PAGE_CONTAINER still contains a GTM noscript URL after strip; edit template manually or adjust this script.\n");
    exit(1);
}

$pbody = preg_replace('/(<body[^>]*>)/', '$1' . "\n" . $noscriptBlock . "\n", $pbody, 1);
if ($pbody === null) {
    fwrite(STDERR, "preg_replace failed on PAGE_CONTAINER\n");
    exit(1);
}

$page->setTemplateUnchecked($pbody);
if ($page->hasErrors()) {
    foreach ($page->getErrors() as $k => $msg) {
        fwrite(STDERR, "PAGE_CONTAINER [{$k}]: {$msg}\n");
    }
    exit(1);
}
$page->save(false);

$compile = $app->service(CompileService::class);
foreach ([$ga, $page] as $tpl) {
    $compile->deleteCompiled($tpl);
    $compile->recompile($tpl);
}

echo "OK: GTM {$gtmId} installed (google_analytics style_id={$gaStyleId}, PAGE_CONTAINER style_id={$pageStyleId})\n";
