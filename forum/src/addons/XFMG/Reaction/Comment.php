<?php

namespace XFMG\Reaction;

use XF\Mvc\Entity\Entity;
use XF\Reaction\AbstractHandler;

class Comment extends AbstractHandler
{
	public function reactionsCounted(Entity $entity)
	{
		return ($entity->comment_state == 'visible');
	}
}
