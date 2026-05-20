<?php

namespace XFMG\XF\Service\Attachment;

use XF\Entity\AttachmentData;
use XF\Finder\AttachmentFinder;
use XFMG\Finder\MediaItem;

class Preparer extends XFCP_Preparer
{
	public function optimizeExistingAttachment(AttachmentData $data): void
	{
		parent::optimizeExistingAttachment($data);

		if ($this->app->options()->imageOptimization !== 'optimize')
		{
			return;
		}

		$attachments = $this->finder(AttachmentFinder::class)
			->where('data_id', $data->data_id)
			->where('content_type', 'xfmg_media')
			->fetch();

		if (!$attachments)
		{
			return;
		}

		$mediaIds = [];
		foreach ($attachments AS $attachment)
		{
			$mediaIds[] = $attachment->content_id;
		}

		$media = $this->finder(MediaItem::class)
			->whereIds($mediaIds)
			->where('media_type', 'image')
			->fetch();

		if (!$media)
		{
			return;
		}

		foreach ($media AS $mediaItem)
		{
			$mediaItem->rebuildThumbnail();
		}
	}
}
