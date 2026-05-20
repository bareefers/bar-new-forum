<?php
/**
 * Re-parse a template from xf_template.template into template_parsed, persist, then
 * recompile compiled PHP (required after raw SQL edits — CompileService::recompile alone
 * is not enough).
 *
 * Usage:
 *   php xf-reparse-template.php /var/www/bareefers.org/forum 16 public extra.less
 */
use XF\Cli\App;
use XF\Service\Template\CompileService;

$root = $argv[1] ?? '/var/www/bareefers.org/forum';
$styleId = (int) ($argv[2] ?? 0);
$type = $argv[3] ?? 'public';
$title = $argv[4] ?? 'extra.less';

if ($styleId <= 0) {
    fwrite(STDERR, "Usage: php xf-reparse-template.php <forum_root> <style_id> [type] [title]\n");
    exit(1);
}

require $root . '/src/XF.php';
\XF::start($root);
$app = \XF::setupApp(App::class);

/** @var \XF\Entity\Template|null $template */
$template = $app->finder('XF:Template')
    ->where('style_id', $styleId)
    ->where('title', $title)
    ->where('type', $type)
    ->fetchOne();

if (!$template) {
    fwrite(STDERR, "Template not found: style_id={$styleId} type={$type} title={$title}\n");
    exit(1);
}

$error = null;
$reparsed = $template->reparseTemplate(true, $error);
if ($error) {
    fwrite(STDERR, "Parse issue: {$error}\n");
}

if ($template->hasErrors()) {
    foreach ($template->getErrors() as $k => $msg) {
        fwrite(STDERR, "Entity error [{$k}]: {$msg}\n");
    }
    exit(1);
}

$template->save(false);

$compile = $app->service(CompileService::class);
$compile->deleteCompiled($template);
$compile->recompile($template);

echo 'OK: re-parsed + saved + recompiled '
    . "{$type}:{$title} (style_id={$styleId}, template_id={$template->template_id}"
    . ($reparsed ? ', ast updated' : ', ast unchanged')
    . ")\n";
