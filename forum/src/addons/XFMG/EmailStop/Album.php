<?php

namespace XFMG\EmailStop;

use XF\EmailStop\AbstractHandler;
use XF\Entity\User;
use XFMG\Repository\AlbumWatch;
use XFMG\Repository\CategoryWatch;
use XFMG\Repository\MediaWatch;

class Album extends AbstractHandler
{
	public function getStopOneText(User $user, $contentId)
	{
		/** @var \XFMG\Entity\Album|null $album */
		$album = \XF::em()->find('XFMG:Album', $contentId);
		$canView = \XF::asVisitor(
			$user,
			function () use ($album) { return $album && $album->canView(); }
		);

		if ($canView)
		{
			return \XF::phrase('stop_notification_emails_from_x', ['title' => $album->title]);
		}
		else
		{
			return null;
		}
	}

	public function getStopAllText(User $user)
	{
		return \XF::phrase('xfmg_stop_notification_emails_from_all_albums');
	}

	public function stopOne(User $user, $contentId)
	{
		/** @var \XFMG\Entity\Album $album */
		$album = \XF::em()->find('XFMG:Album', $contentId);
		if ($album)
		{
			/** @var AlbumWatch $albumWatchRepo */
			$albumWatchRepo = \XF::repository('XFMG:AlbumWatch');
			$albumWatchRepo->setWatchState($album, $user, 'update', ['send_email' => false]);
		}
	}

	public function stopAll(User $user)
	{
		/** @var MediaWatch $mediaWatchRepo */
		$mediaWatchRepo = \XF::repository('XFMG:MediaWatch');
		$mediaWatchRepo->setWatchStateForAll($user, 'update', ['send_email' => 0]);

		/** @var AlbumWatch $albumWatchRepo */
		$albumWatchRepo = \XF::repository('XFMG:AlbumWatch');
		$albumWatchRepo->setWatchStateForAll($user, 'update', ['send_email' => 0]);

		/** @var CategoryWatch $categoryWatchRepo */
		$categoryWatchRepo = \XF::repository('XFMG:CategoryWatch');
		$categoryWatchRepo->setWatchStateForAll($user, 'update', ['send_email' => 0]);
	}
}
