<?php

namespace XFMG\Service\Album;

use XF\App;
use XF\Entity\User;
use XF\Service\AbstractNotifier;
use XFMG\Entity\Album;

class Notifier extends AbstractNotifier
{
	/**
	 * @var Album
	 */
	protected $album;

	public function __construct(App $app, Album $album)
	{
		parent::__construct($app);

		$this->album = $album;
	}

	public static function createForJob(array $extraData)
	{
		$album = \XF::app()->find('XFMG:Album', $extraData['albumId']);
		if (!$album)
		{
			return null;
		}

		return \XF::service('XFMG:Album\Notifier', $album);
	}

	protected function getExtraJobData()
	{
		return [
			'albumId' => $this->album->album_id,
		];
	}

	protected function loadNotifiers()
	{
		$notifiers = [
			'mention' => $this->app->notifier('XFMG:Album\Mention', $this->album),
		];

		return $notifiers;
	}

	protected function loadExtraUserData(array $users)
	{
		$permCombinationIds = [];
		foreach ($users AS $user)
		{
			$id = $user->permission_combination_id;
			$permCombinationIds[$id] = $id;
		}

		$this->app->permissionCache()->cacheMultipleContentPermsForContent(
			$permCombinationIds,
			'xfmg_category',
			$this->album->category_id
		);
	}

	protected function canUserViewContent(User $user)
	{
		return \XF::asVisitor(
			$user,
			function () { return $this->album->canView(); }
		);
	}
}
