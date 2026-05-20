<?php

namespace XFMG\Notifier\Media;

use XF\App;
use XF\Entity\User;
use XF\Notifier\AbstractNotifier;
use XFMG\Entity\MediaItem;

class Mention extends AbstractNotifier
{
	/**
	 * @var MediaItem
	 */
	protected $mediaItem;

	public function __construct(App $app, MediaItem $mediaItem)
	{
		parent::__construct($app);

		$this->mediaItem = $mediaItem;
	}

	public function canNotify(User $user)
	{
		return ($this->mediaItem->isVisible() && $user->user_id != $this->mediaItem->user_id);
	}

	public function sendAlert(User $user)
	{
		$mediaItem = $this->mediaItem;

		return $this->basicAlert(
			$user,
			$mediaItem->user_id,
			$mediaItem->username,
			'xfmg_media',
			$mediaItem->media_id,
			'mention'
		);
	}
}
