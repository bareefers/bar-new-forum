<?php

namespace XFMG\TrendingContent;

use XF\Mvc\Entity\AbstractCollection;
use XF\TrendingContent\AbstractHandler;
use XFMG\Entity\MediaItem;

/**
 * @extends AbstractHandler<MediaItem>
 */
class MediaHandler extends AbstractHandler
{
	public function getEntityWith(string $style): array
	{
		$visitor = \XF::visitor();

		return ['Category.Permissions|' . $visitor->permission_combination_id];
	}

	public function filterContent(AbstractCollection $content): AbstractCollection
	{
		return $content->filter(
			function (MediaItem $item): bool
			{
				return $item->canView() && !$item->isIgnored();
			}
		);
	}
}
