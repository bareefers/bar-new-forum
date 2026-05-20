<?php

namespace XFMG\Webhook\Event;

use XF\Webhook\Event\AbstractHandler;
use XFMG\Webhook\Criteria\Comment as CommentCriteria;

class Comment extends AbstractHandler
{
	public function getCriteriaClass(): string
	{
		return CommentCriteria::class;
	}

	public function getDisplayOrder(): int
	{
		return 330;
	}
}
