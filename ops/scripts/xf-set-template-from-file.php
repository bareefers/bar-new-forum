<?php
/**
 * Replace a template body from a UTF-8 file with real newlines (LF).
 * Persists via Template entity (setTemplateUnchecked + save) — do not raw SQL UPDATE.
 *
 * Usage:
 *   sudo -u www-data php xf-set-template-from-file.php /path/to/forum <style_id> <type> <title> /path/to/file.less
 */
use XF\Cli\App;
use XF\Service\Template\CompileService;

$root = $argv[1] ?? '';
$styleId = (int) ($argv[2] ?? 0);
$type = $argv[3] ?? '';
$title = $argv[4] ?? '';
$path = $argv[5] ?? '';

if ($styleId <= 0 || $type === '' || $title === '' || $path === '' || !is_dir($root)) {
    fwrite(STDERR, "Usage: php xf-set-template-from-file.php <forum_root> <style_id> <type> <title> <path.less>\n");
    exit(1);
}

if (!is_readable($path)) {
    fwrite(STDERR, "Not readable: {$path}\n");
    exit(1);
}

$contents = file_get_contents($path);
if ($contents === false) {
    fwrite(STDERR, "Failed to read: {$path}\n");
    exit(1);
}
$contents = str_replace("\r\n", "\n", str_replace("\r", "\n", $contents));
if (strlen(trim($contents)) < 50) {
    fwrite(STDERR, "Refusing empty/tiny template file (check path and line endings)\n");
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

$template->setTemplateUnchecked($contents);
if ($template->hasErrors()) {
    foreach ($template->getErrors() as $k => $msg) {
        fwrite(STDERR, "After setTemplateUnchecked [{$k}]: {$msg}\n");
    }
    exit(1);
}
$template->save(false);
if ($template->hasErrors()) {
    foreach ($template->getErrors() as $k => $msg) {
        fwrite(STDERR, "After save [{$k}]: {$msg}\n");
    }
    exit(1);
}

$compile = $app->service(CompileService::class);
$compile->deleteCompiled($template);
$compile->recompile($template);

echo "OK: set + saved + recompiled {$type}:{$title} (style_id={$styleId}, template_id={$template->template_id})\n";
