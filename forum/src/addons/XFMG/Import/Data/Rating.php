<?php

namespace XFMG\Import\Data;

use XF\Import\Data\AbstractEmulatedData;
use XFMG\Entity\Album;
use XFMG\Entity\MediaItem;

/**
 * @mixin \XFMG\Entity\Rating
 */
class Rating extends AbstractEmulatedData
{
	public function getImportType()
	{
		return 'xfmg_rating';
	}

	protected function getEntityShortName()
	{
		return 'XFMG:Rating';
	}

	protected function postSave($oldId, $newId)
	{
		$content = null;

		if ($this->content_type == 'xfmg_album')
		{
			/** @var Album $content */
			$content = $this->em()->find(Album::class, $this->content_id);
		}
		else if ($this->content_type == 'xfmg_media')
		{
			/** @var MediaItem $content */
			$content = $this->em()->find(MediaItem::class, $this->content_id);
		}

		if (!$content)
		{
			return;
		}

		$content->rating_sum += $this->rating;
		$content->rating_count += 1;

		$content->updateRatingAverage();
		$content->save(false, false);

		$this->em()->detachEntity($content);
	}
}
