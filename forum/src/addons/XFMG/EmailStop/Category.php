<?php

namespace XFMG\EmailStop;

use XF\EmailStop\AbstractHandler;
use XF\Entity\User;
use XFMG\Repository\AlbumWatch;
use XFMG\Repository\CategoryWatch;
use XFMG\Repository\MediaWatch;

class Category extends AbstractHandler
{
	public function getStopOneText(User $user, $contentId)
	{
		/** @var \XFMG\Entity\Category|null $category */
		$category = \XF::em()->find('XFMG:Category', $contentId);
		$canView = \XF::asVisitor(
			$user,
			function () use ($category) { return $category && $category->canView(); }
		);

		if ($canView)
		{
			return \XF::phrase('stop_notification_emails_from_x', ['title' => $category->title]);
		}
		else
		{
			return null;
		}
	}

	public function getStopAllText(User $user)
	{
		return \XF::phrase('stop_notification_emails_from_all_categories');
	}

	public function stopOne(User $user, $contentId)
	{
		/** @var \XFMG\Entity\Category $category */
		$category = \XF::em()->find('XFMG:Category', $contentId);
		if ($category)
		{
			/** @var CategoryWatch $categoryWatchRepo */
			$categoryWatchRepo = \XF::repository('XFMG:CategoryWatch');
			$categoryWatchRepo->setWatchState($category, $user, 'update', ['send_email' => false]);
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
