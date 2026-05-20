<?php

namespace XFMG\EmbedResolver;

use XF\EmbedResolver\AbstractHandler;
use XF\Mvc\Entity\Entity;

class Media extends AbstractHandler
{
	public function getTemplateData(Entity $content): array
	{
		return [
			'mediaItem' => $content,
		];
	}

	public function getEntityWith(): array
	{
		$visitor = \XF::visitor();

		return ['Category', 'Category.Permissions|' . $visitor->permission_combination_id];
	}
}
