<?php

namespace XFMG\FeaturedContent;

use XF\FeaturedContent\AbstractHandler;
use XF\Mvc\Entity\Entity;

/**
 * @extends AbstractHandler<\XFMG\Entity\MediaItem>
 */
class Media extends AbstractHandler
{
	public function getContentImage(
		Entity $content,
		?string $sizeCode = null
	): ?string
	{
		if ($content->media_type === 'image')
		{
			return \XF::app()->router('public')->buildLink('media/full', $content);
		}

		return $content->poster_url;
	}

	public function getContentSnippet(Entity $content): string
	{
		return $this->getSnippetFromString($content->description);
	}

	public function getContentStructuredData(Entity $content): array
	{
		return $content->getStructuredData();
	}

	public function shouldAutoFeature(Entity $content): bool
	{
		return $content->Category->auto_feature ?? false;
	}

	public function getEntityWith(): array
	{
		$visitor = \XF::visitor();

		return [
			'Category',
			'Category.Permissions|' . $visitor->permission_combination_id,
			'User',
		];
	}
}
