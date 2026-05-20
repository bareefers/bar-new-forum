<?php

require $argv[1] . '/src/XF.php';
\XF::start($argv[1]);
$app = \XF::setupApp(\XF\Pub\App::class);
$styleId = (int) ($argv[2] ?? 14);
$style = $app->container()->create('style', $styleId);
echo $style->getLastModified() . "\n";
