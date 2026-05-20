<?php
$root = $argv[1] ?? '/var/www/bareefers.org/forum';
$styleId = (int)($argv[2] ?? 16);
$outDir = $argv[3] ?? '/var/tmp/bar-style-backups';

if (!is_dir($root)) {
    fwrite(STDERR, "Invalid forum root\n");
    exit(1);
}

require $root . '/src/XF.php';
\XF::start($root);
$app = \XF::setupApp(\XF\Cli\App::class);

if (!is_dir($outDir) && !mkdir($outDir, 0775, true)) {
    fwrite(STDERR, "Failed to create output dir: $outDir\n");
    exit(1);
}

$ts = date('Ymd-His');
$targets = ['extra.less', 'extra_footer'];

foreach ($targets as $title) {
    /** @var \XF\Entity\Template|null $tpl */
    $tpl = $app->finder('XF:Template')
        ->where('style_id', $styleId)
        ->where('type', 'public')
        ->where('title', $title)
        ->fetchOne();

    if (!$tpl) {
        fwrite(STDERR, "Template not found: $title\n");
        continue;
    }

    $file = rtrim($outDir, '/')."/{$title}.{$ts}.bak";
    file_put_contents($file, $tpl->template);
    $hash = hash_file('sha256', $file);
    echo "$file  sha256=$hash\n";
}

