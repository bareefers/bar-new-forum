<?php
/**
 * Export a style template body (decoded text, LF newlines) to a UTF-8 .less file.
 *
 * Usage:
 *   sudo -u www-data php xf-export-template-to-less-file.php /path/to/forum <style_id> <type> <title> /path/out.less
 */
use XF\Cli\App;

$root = $argv[1] ?? '';
$styleId = (int) ($argv[2] ?? 0);
$type = $argv[3] ?? '';
$title = $argv[4] ?? '';
$outPath = $argv[5] ?? '';

if ($styleId <= 0 || $type === '' || $title === '' || $outPath === '' || !is_dir($root)) {
    fwrite(STDERR, "Usage: php xf-export-template-to-less-file.php <forum_root> <style_id> <type> <title> <out.less>\n");
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

$text = str_replace("\r\n", "\n", str_replace("\r", "\n", $template->template));
if (file_put_contents($outPath, $text) === false) {
    fwrite(STDERR, "Write failed: {$outPath}\n");
    exit(1);
}
@chmod($outPath, 0644);

echo "OK: exported {$type}:{$title} (style_id={$styleId}, template_id={$template->template_id}) -> {$outPath}\n";
