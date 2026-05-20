<?php

namespace XFMG\EmbedResolver;

use XF\EmbedResolver\AbstractHandler;
use XF\Mvc\Entity\Entity;

class Album extends AbstractHandler
{
	public function getTemplateData(Entity $content): array
	{
		/** @var \XFMG\Entity\Album $album */
		$album = $content;
		$mediaItems = $album->MediaCache->filterViewable();

		// Show up to 10 thumbs or 9 plus an indicator that there are X more
		$showXMore = $mediaItems->count() > 10;
		$length = $showXMore ? 9 : 10;
		$mediaItems = $mediaItems->slice(0, $length);

		return [
			'album' => $album,
			'mediaItems' => $mediaItems,
			'showXMore' => $showXMore,
			'placeholders' => array_fill(0, (10 - $mediaItems->count() - ($showXMore ? 1 : 0)), true),
		];
	}

	public function getEntityWith(): array
	{
		$visitor = \XF::visitor();

		return ['Category', 'Category.Permissions|' . $visitor->permission_combination_id];
	}
}
