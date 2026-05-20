<?php

namespace XFMG\Service\Comment;

use XF\App;
use XF\Service\AbstractService;
use XFMG\Entity\Comment;

class Approver extends AbstractService
{
	/**
	 * @var Comment
	 */
	protected $comment;

	/**
	 * @var int
	 */
	protected $notifyRunTime = 3;

	public function __construct(App $app, Comment $comment)
	{
		parent::__construct($app);
		$this->comment = $comment;
	}

	public function getComment(): Comment
	{
		return $this->comment;
	}

	public function setNotifyRunTime(int $time)
	{
		$this->notifyRunTime = $time;
	}

	public function approve(): bool
	{
		if ($this->comment->comment_state != 'moderated')
		{
			return false;
		}

		$this->comment->comment_state = 'visible';
		$this->comment->save();

		$this->onApprove();
		return true;
	}

	protected function onApprove()
	{
		if ($this->comment->isLastComment())
		{
			/** @var Notifier $notifier */
			$notifier = $this->service('XFMG:Comment\Notifier', $this->comment);
			$notifier->notifyAndEnqueue($this->notifyRunTime);
		}
	}
}
