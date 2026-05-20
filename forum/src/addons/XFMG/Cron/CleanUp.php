<?php

namespace XFMG\Cron;

use XFMG\Repository\Media;
use XFMG\Repository\MediaNote;

class CleanUp
{
	public static function runDailyCleanUp()
	{
		$app = \XF::app();

		/** @var Media $mediaRepo */
		$mediaRepo = $app->repository('XFMG:Media');
		$mediaRepo->pruneMediaViewLogs();
	}

	public static function runHourlyCleanUp()
	{
		$app = \XF::app();

		/** @var Media $mediaRepo */
		$mediaRepo = $app->repository('XFMG:Media');
		$mediaRepo->pruneTempMedia();
		$mediaRepo->pruneTempAttachmentExif();

		/** @var MediaNote $noteRepo */
		$noteRepo = $app->repository('XFMG:MediaNote');
		$noteRepo->pruneUnapprovedTags();
	}
}
