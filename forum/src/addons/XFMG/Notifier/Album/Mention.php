<?php

namespace XFMG\Notifier\Album;

use XF\App;
use XF\Entity\User;
use XF\Notifier\AbstractNotifier;
use XFMG\Entity\Album;

class Mention extends AbstractNotifier
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

	public function canNotify(User $user)
	{
		return ($this->album->isVisible() && $user->user_id != $this->album->user_id);
	}

	public function sendAlert(User $user)
	{
		$album = $this->album;

		return $this->basicAlert(
			$user,
			$album->user_id,
			$album->username,
			'xfmg_album',
			$album->album_id,
			'mention'
		);
	}
}
