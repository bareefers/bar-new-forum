<?php

namespace XFMG\NewsFeed;

use XF\NewsFeed\AbstractHandler;

class MediaItem extends AbstractHandler
{
	public function getEntityWith()
	{
		$visitor = \XF::visitor();

		return ['User', 'Category', 'Category.Permissions|' . $visitor->permission_combination_id];
	}
}
