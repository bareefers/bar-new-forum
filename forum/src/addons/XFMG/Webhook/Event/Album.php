<?php

namespace XFMG\Webhook\Event;

use XF\Webhook\Event\AbstractHandler;
use XFMG\Webhook\Criteria\Album as AlbumCriteria;

class Album extends AbstractHandler
{
	public function getCriteriaClass(): string
	{
		return AlbumCriteria::class;
	}

	public function getDisplayOrder(): int
	{
		return 320;
	}
}
