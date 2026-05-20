<?php

namespace XFMG\Reaction;

use XF\Mvc\Entity\Entity;
use XF\Reaction\AbstractHandler;

class Album extends AbstractHandler
{
	public function reactionsCounted(Entity $entity)
	{
		return ($entity->album_state == 'visible');
	}
}
