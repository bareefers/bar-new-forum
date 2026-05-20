<?php

namespace XFMG\Tag;

use XF\Mvc\Entity\Entity;
use XF\Tag\AbstractHandler;
use XFMG\Entity\Album;
use XFMG\Entity\Category;
use XFMG\Entity\MediaItem;

/**
 * @extends AbstractHandler<\XFMG\Entity\MediaItem>
 */
class Media extends AbstractHandler
{
	public function getPermissionsFromContext(Entity $entity)
	{
		$mediaItem = null;
		$container = null;

		if ($entity instanceof MediaItem)
		{
			$mediaItem = $entity;
		}
		else if ($entity instanceof Category || $entity instanceof Album)
		{
			$container = $entity;
		}
		else
		{
			throw new \InvalidArgumentException("Entity must be a media item");
		}

		$visitor = \XF::visitor();

		if ($mediaItem)
		{
			if ($mediaItem->user_id == $visitor->user_id
				&& $mediaItem->hasPermission('manageOthersTagsOwnMedia')
			)
			{
				$removeOthers = true;
			}
			else
			{
				$removeOthers = $mediaItem->hasPermission('manageAnyTag');
			}

			$edit = $mediaItem->canEditTags();
			$minTags = $mediaItem->min_tags;
		}
		else
		{
			$removeOthers = false;
			$edit = $container ? $container->canEditTags() : false;
			$minTags = $container ? $container->min_tags : 0;
		}

		return [
			'edit' => $edit,
			'removeOthers' => $removeOthers,
			'minTotal' => $minTags,
		];
	}

	public function getContentVisibility(Entity $entity)
	{
		return $entity->media_state == 'visible';
	}

	public function getTemplateData(Entity $entity, array $options = [])
	{
		return [
			'mediaItem' => $entity,
			'options' => $options,
		];
	}

	public function getEntityWith($forView = false)
	{
		return [];
	}

	public function canUseInlineModeration(Entity $entity, &$error = null)
	{
		return $entity->canUseInlineModeration($error);
	}
}
