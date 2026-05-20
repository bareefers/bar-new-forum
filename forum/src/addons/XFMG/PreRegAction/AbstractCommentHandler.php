<?php

namespace XFMG\PreRegAction;

use XF\Entity\PreRegAction;
use XF\Entity\User;
use XF\Mvc\Entity\Entity;
use XF\PreRegAction\AbstractHandler;
use XF\Repository\UserAlert;
use XFMG\Entity\Album;
use XFMG\Entity\Comment;
use XFMG\Entity\MediaItem;
use XFMG\Service\Comment\Creator;

abstract class AbstractCommentHandler extends AbstractHandler
{
	public function getDefaultActionData(): array
	{
		return [
			'message' => '',
		];
	}

	protected function canCompleteAction(PreRegAction $action, Entity $containerContent, User $newUser): bool
	{
		/** @var MediaItem|Album $containerContent */
		return $containerContent->canAddComment();
	}

	protected function executeAction(PreRegAction $action, Entity $containerContent, User $newUser)
	{
		/** @var MediaItem|Album $containerContent */

		$creator = $this->setupCommentCreate($action, $containerContent);
		$creator->checkForSpam();

		if (!$creator->validate())
		{
			return null;
		}

		$comment = $creator->save();

		if ($containerContent->content_type == 'xfmg_media')
		{
			\XF::repository('XFMG:MediaWatch')->autoWatchMediaItem($containerContent, $newUser, false);
		}
		else
		{
			\XF::repository('XFMG:AlbumWatch')->autoWatchAlbum($containerContent, $newUser, false);
		}

		$creator->sendNotifications();

		return $comment;
	}

	protected function setupCommentCreate(
		PreRegAction $action,
		Entity $containerContent
	): Creator
	{
		$creator = \XF::app()->service('XFMG:Comment\Creator', $containerContent);
		$creator->setMessage($action->action_data['message']);
		$creator->logIp($action->ip_address);

		return $creator;
	}

	protected function sendSuccessAlert(
		PreRegAction $action,
		Entity $containerContent,
		User $newUser,
		Entity $executeContent
	)
	{
		if (!($executeContent instanceof Comment))
		{
			return;
		}

		/** @var Comment $comment */
		$comment = $executeContent;

		/** @var UserAlert $alertRepo */
		$alertRepo = \XF::repository('XF:UserAlert');

		$alertRepo->alertFromUser(
			$newUser,
			null,
			'xfmg_comment',
			$comment->comment_id,
			'pre_reg',
			['welcome' => $action->isForNewUser()],
			['autoRead' => false]
		);
	}

	protected function getStructuredContentData(PreRegAction $preRegAction, Entity $containerContent): array
	{
		/** @var MediaItem|Album $containerContent */

		$phrase = $containerContent->content_type == 'xfmg_media'
			? 'xfmg_comment_on_media_x' : 'xfmg_comment_on_album_x';

		return [
			'title' => \XF::phrase($phrase, [
				'title' => $containerContent->title,
			]),
			'title_link' => $containerContent->getContentUrl(),
			'bb_code' => $preRegAction->action_data['message'],
		];
	}
}
