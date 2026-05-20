<?php

namespace XFMG\EmailStop;

use XF\EmailStop\AbstractHandler;
use XF\Entity\User;
use XFMG\Entity\MediaItem;
use XFMG\Repository\AlbumWatch;
use XFMG\Repository\CategoryWatch;
use XFMG\Repository\MediaWatch;

class Media extends AbstractHandler
{
	public function getStopOneText(User $user, $contentId)
	{
		/** @var MediaItem|null $mediaItem */
		$mediaItem = \XF::em()->find('XFMG:MediaItem', $contentId);
		$canView = \XF::asVisitor(
			$user,
			function () use ($mediaItem) { return $mediaItem && $mediaItem->canView(); }
		);

		if ($canView)
		{
			return \XF::phrase('stop_notification_emails_from_x', ['title' => $mediaItem->title]);
		}
		else
		{
			return null;
		}
	}

	public function getStopAllText(User $user)
	{
		return \XF::phrase('xfmg_stop_notification_emails_from_all_media');
	}

	public function stopOne(User $user, $contentId)
	{
		/** @var MediaItem $mediaItem */
		$mediaItem = \XF::em()->find('XFMG:MediaItem', $contentId);
		if ($mediaItem)
		{
			/** @var MediaWatch $mediaWatchRepo */
			$mediaWatchRepo = \XF::repository('XFMG:MediaWatch');
			$mediaWatchRepo->setWatchState($mediaItem, $user, 'update', ['send_email' => false]);
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
