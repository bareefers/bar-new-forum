<?php

namespace XFMG\ActivityLog;

use XF\ActivityLog\AbstractHandler;
use XF\Mvc\Entity\Entity;
use XFMG\Entity\MediaItem;

/**
 * @extends AbstractHandler<MediaItem>
 */
class MediaHandler extends AbstractHandler
{
	protected function getReplyMetrics(Entity $content): array
	{
		return $this->getReplyMetricsSimple(
			'xf_mg_comment',
			'comment_date',
			'content_type = ? AND content_id = ?',
			['xfmg_media', $content->getEntityId()]
		);
	}
}
