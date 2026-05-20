<?php

namespace XFMG\Cron;

use XFMG\Repository\Album;
use XFMG\Repository\Media;

class Views
{
	public static function runViewUpdate()
	{
		$app = \XF::app();

		/** @var Media $mediaRepo */
		$mediaRepo = $app->repository('XFMG:Media');
		$mediaRepo->batchUpdateMediaViews();

		/** @var Album $albumRepo */
		$albumRepo = $app->repository('XFMG:Album');
		$albumRepo->batchUpdateAlbumViews();
	}
}
