<?php

namespace XFMG\Service\Album;

use XF\App;
use XF\Service\AbstractService;
use XFMG\Entity\Album;

class Approver extends AbstractService
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

	public function getAlbum()
	{
		return $this->album;
	}

	public function approve()
	{
		if ($this->album->album_state == 'moderated')
		{
			$this->album->album_state = 'visible';
			$this->album->save();

			$this->onApprove();
			return true;
		}
		else
		{
			return false;
		}
	}

	protected function onApprove()
	{
	}
}
