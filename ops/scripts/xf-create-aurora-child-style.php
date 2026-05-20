<?php
/**
 * Create a XenForo child style (inherits parent templates/properties; overrides live only on the child).
 *
 * Usage (as www-data on the forum server):
 *   php xf-create-aurora-child-style.php [/path/to/forum] [parent_style_id] ["Style title"]
 *
 * Example:
 *   sudo -u www-data php xf-create-aurora-child-style.php /var/www/bareefers.org/forum 16 "Aurora — BAR custom"
 *
 * After creation: ACP → Appearance → Styles → set default style, or keep Aurora default and select
 * this style while editing. Move any custom templates you care about from the parent into this child
 * (or re-apply extra.less here only) so upgrades to Aurora stay isolated.
 */
$root = $argv[1] ?? '/var/www/bareefers.org/forum';
$parentId = (int)($argv[2] ?? 16);
$title = $argv[3] ?? 'Aurora — BAR custom';

if (!is_dir($root . '/src')) {
    fwrite(STDERR, "Invalid forum root: $root\n");
    exit(1);
}

require $root . '/src/XF.php';
\XF::start($root);

$app = \XF::app();
$em = $app->em();

$existing = $em->getFinder('XF:Style')->where('title', $title)->fetchOne();
if ($existing) {
    echo "Already exists: style_id={$existing->style_id} title=" . $existing->title . "\n";
    exit(0);
}

$parent = $em->find('XF:Style', $parentId);
if (!$parent) {
    fwrite(STDERR, "Parent style_id=$parentId not found.\n");
    exit(1);
}

$style = $em->create('XF:Style');
$style->parent_id = $parentId;
$style->title = $title;
$style->description = 'Child of ' . $parent->title . ' (BAR customizations)';
$style->user_selectable = 1;
$style->enable_variations = (bool) $parent->enable_variations;

try {
    $style->save();
} catch (\XF\Mvc\Entity\ValidationException $e) {
    fwrite(STDERR, "Validation failed:\n" . json_encode($e->getErrors(), JSON_PRETTY_PRINT) . "\n");
    exit(1);
}

echo "Created child style_id={$style->style_id} parent_id={$style->parent_id} title=" . $style->title . "\n";
echo "ACP: Appearance → Styles → edit this style for templates/extra.less. Set default style in Options if desired.\n";
