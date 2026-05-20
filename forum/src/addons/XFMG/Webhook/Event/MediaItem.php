<?php

namespace XFMG\Webhook\Event;

use XF\Webhook\Event\AbstractHandler;
use XFMG\Webhook\Criteria\MediaItem as MediaItemCriteria;

class MediaItem extends AbstractHandler
{
	public function getCriteriaClass(): string
	{
		return MediaItemCriteria::class;
	}

	public function getDisplayOrder(): int
	{
		return 310;
	}
}
