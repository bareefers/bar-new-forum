<?php

namespace XFMG\Job;

use XF\Mvc\Entity\Entity;
use XF\Service\FeaturedContent\Creator;
use XF\Service\FeaturedContent\Deleter;
use XFMG\Entity\MediaItem;
use XFMG\Repository\Media;
use XFMG\Service\Media\Watermarker;

class MediaItemAction extends AbstractBatchUpdateAction
{
	protected function getColumn()
	{
		return 'media_id';
	}

	protected function getClassIdentifier()
	{
		return 'XFMG:MediaItem';
	}

	protected function applyInternalItemChange(Entity $mediaItem)
	{
		/** @var MediaItem $mediaItem */

		/** @var Media $mediaRepo */
		$mediaRepo = $this->app->repository('XFMG:Media');

		if ($this->getActionValue('approve'))
		{
			$mediaItem->media_state = 'visible';
		}
		if ($this->getActionValue('unapprove'))
		{
			$mediaItem->media_state = 'moderated';
		}
		if ($this->getActionValue('soft_delete'))
		{
			$mediaItem->media_state = 'deleted';
		}
		if (!$this->getActionValue('remove_watermark')
			&& $this->getActionValue('add_watermark')
			&& $mediaItem->canAddWatermark(false)
		)
		{
			$tempWatermark = $mediaRepo->getWatermarkAsTempFile();

			/** @var Watermarker $watermarker */
			$watermarker = $this->app->service('XFMG:Media\Watermarker', $mediaItem, $tempWatermark);
			$watermarker->watermark(false);
		}
		if (!$this->getActionValue('add_watermark')
			&& $this->getActionValue('remove_watermark')
			&& $mediaItem->canRemoveWatermark(false)
		)
		{
			/** @var Watermarker $watermarker */
			$watermarker = \XF::service('XFMG:Media\Watermarker', $mediaItem);
			$watermarker->unwatermark(false);
		}

		if ($this->getActionValue('feature') && !$mediaItem->isFeatured())
		{
			$creator = $this->app->service(
				Creator::class,
				$mediaItem
			);
			$creator->save();
		}
		else if ($this->getActionValue('unfeature') && $mediaItem->isFeatured())
		{
			$deleter = $this->app->service(
				Deleter::class,
				$mediaItem->Feature
			);
			$deleter->delete();
		}
	}

	protected function getTypePhrase()
	{
		return \XF::phrase('xfmg_media_items');
	}
}
