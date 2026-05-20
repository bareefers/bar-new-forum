<?php
/**
 * Rebuild compiled template PHP under internal_data/code_cache (fixes missing *.php after manual delete).
 * Usage: php xf-recompile-templates-by-title.php /var/www/bareefers.org/forum public extra.less
 */
use XF\Cli\App;
use XF\Service\Template\CompileService;

$dir = $argv[1] ?? '/var/www/bareefers.org/forum';
$type = $argv[2] ?? 'public';
$title = $argv[3] ?? 'extra.less';

require $dir . '/src/XF.php';
\XF::start($dir);
$app = \XF::setupApp(App::class);
$svc = $app->service(CompileService::class);
$svc->recompileByTitle($type, $title);
echo "OK: recompiled {$type}:{$title}\n";
