<?php

namespace XFMG\Reaction;

use XF\Mvc\Entity\Entity;
use XF\Reaction\AbstractHandler;

class Media extends AbstractHandler
{
	public function reactionsCounted(Entity $entity)
	{
		return ($entity->media_state == 'visible');
	}
}
