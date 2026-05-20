<?php

namespace XFMG\Api\Controller;

use XF\Api\Controller\AbstractController;
use XF\Api\ControllerPlugin\ReactionPlugin;
use XF\Mvc\Entity\Entity;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\Exception;
use XFMG\Finder\Comment;
use XFMG\Finder\MediaItem;
use XFMG\Service\Album\Deleter;
use XFMG\Service\Album\Editor;

use function intval;

class Album extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertApiScopeByRequestMethod('media');
	}

	public function actionGet(ParameterBag $params)
	{
		$album = $this->assertViewableAlbum($params->album_id);

		if ($this->filter('with_media', 'bool'))
		{
			$mediaData = $this->getMediaInAlbumPaginated($album, $this->filterPage());
			$mediaData['media_pagination'] = $mediaData['pagination'];
			unset($mediaData['pagination']);
		}
		else
		{
			$mediaData = [];
		}
		if ($this->filter('with_comments', 'bool'))
		{
			$commentData = $this->getCommentsInAlbumPaginated($album, $this->filterPage(0, 'comment_page'));
			$commentData['comment_pagination'] = $commentData['pagination'];
			unset($commentData['pagination']);
		}
		else
		{
			$commentData = [];
		}

		$result = [
			'album' => $album->toApiResult(Entity::VERBOSITY_VERBOSE),
		];
		$result += $mediaData + $commentData;

		return $this->apiResult($result);
	}

	public function actionGetMedia(ParameterBag $params)
	{
		$album = $this->assertViewableAlbum($params->album_id);

		$commentData = $this->getMediaInAlbumPaginated($album, $this->filterPage());

		return $this->apiResult($commentData);
	}

	protected function getMediaInAlbumPaginated(\XFMG\Entity\Album $album, $page = 1, $perPage = null)
	{
		$perPage = intval($perPage);
		if ($perPage <= 0)
		{
			$perPage = $this->options()->xfmgCommentsPerPage;
		}

		$finder = $this->setupMediaFinder($album);
		$total = $finder->total();

		$this->assertValidApiPage($page, $perPage, $total);

		$media = $finder->limitByPage($page, $perPage)->fetch();
		$mediaResults = $media->toApiResults();

		return [
			'media' => $mediaResults,
			'pagination' => $this->getPaginationData($mediaResults, $page, $perPage, $total),
		];
	}

	/**
	 * @param \XFMG\Entity\Album $album
	 * @return MediaItem
	 */
	protected function setupMediaFinder(\XFMG\Entity\Album $album)
	{
		/** @var MediaItem $finder */
		$finder = $this->finder('XFMG:MediaItem');
		$finder
			->inAlbum($album->album_id)
			->orderByDate()
			->with('api');

		if (\XF::isApiCheckingPermissions())
		{
			$finder->applyVisibilityLimit();
		}

		return $finder;
	}

	public function actionGetComments(ParameterBag $params)
	{
		$album = $this->assertViewableAlbum($params->album_id);

		$commentData = $this->getCommentsInAlbumPaginated($album, $this->filterPage());

		return $this->apiResult($commentData);
	}

	protected function getCommentsInAlbumPaginated(\XFMG\Entity\Album $album, $page = 1, $perPage = null)
	{
		$perPage = intval($perPage);
		if ($perPage <= 0)
		{
			$perPage = $this->options()->xfmgCommentsPerPage;
		}

		$finder = $this->setupCommentFinder($album);
		$total = $finder->total();

		$this->assertValidApiPage($page, $perPage, $total);

		$comments = $finder->limitByPage($page, $perPage)->fetch();
		$commentResults = $comments->toApiResults();

		return [
			'comments' => $commentResults,
			'pagination' => $this->getPaginationData($commentResults, $page, $perPage, $total),
		];
	}

	/**
	 * @param \XFMG\Entity\Album $album
	 * @return Comment
	 */
	protected function setupCommentFinder(\XFMG\Entity\Album $album)
	{
		return $this->repository('XFMG:Comment')->findCommentsForApi($album);
	}

	public function actionPost(ParameterBag $params)
	{
		$album = $this->assertViewableAlbum($params->album_id);

		if (\XF::isApiCheckingPermissions() && !$album->canEdit($error))
		{
			return $this->noPermission($error);
		}

		$editor = $this->setupAlbumEdit($album);

		if (\XF::isApiCheckingPermissions())
		{
			$editor->checkForSpam();
		}

		if (!$editor->validate($errors))
		{
			return $this->error($errors);
		}

		$editor->save();

		return $this->apiSuccess([
			'album' => $album->toApiResult(Entity::VERBOSITY_VERBOSE),
		]);
	}

	/**
	 * @param \XFMG\Entity\Album $album
	 *
	 * @return Editor
	 */
	protected function setupAlbumEdit(\XFMG\Entity\Album $album)
	{
		$input = $this->filter([
			'title' => '?str',
			'description' => '?str',
			'author_alert' => 'bool',
			'author_alert_reason' => 'str',
		]);

		/** @var Editor $editor */
		$editor = $this->service('XFMG:Album\Editor', $album);

		if ($input['title'] !== null)
		{
			$editor->setTitle($input['title']);
		}
		if ($input['description'] !== null)
		{
			$editor->setDescription($input['description']);
		}

		if ($album->canChangePrivacy() || \XF::isApiBypassingPermissions())
		{
			// TODO: privacy change support
		}

		if ($input['author_alert'] && $album->canSendModeratorActionAlert())
		{
			$editor->setSendAlert(true, $input['author_alert_reason']);
		}

		return $editor;
	}

	public function actionPostReact(ParameterBag $params)
	{
		$album = $this->assertViewableAlbum($params->album_id);

		/** @var ReactionPlugin $reactPlugin */
		$reactPlugin = $this->plugin('XF:Api:Reaction');
		return $reactPlugin->actionReact($album);
	}

	public function actionDelete(ParameterBag $params)
	{
		$album = $this->assertViewableAlbum($params->album_id);

		if (\XF::isApiCheckingPermissions() && !$album->canDelete('soft', $error))
		{
			return $this->noPermission($error);
		}

		$type = 'soft';
		$reason = $this->filter('reason', 'str');

		if ($this->filter('hard_delete', 'bool'))
		{
			$this->assertApiScope('media:delete_hard');

			if (\XF::isApiCheckingPermissions() && !$album->canDelete('hard', $error))
			{
				return $this->noPermission($error);
			}

			$type = 'hard';
		}

		/** @var Deleter $deleter */
		$deleter = $this->service('XFMG:Album\Deleter', $album);

		if ($this->filter('author_alert', 'bool') && $album->canSendModeratorActionAlert())
		{
			$deleter->setSendAlert(true, $this->filter('author_alert_reason', 'str'));
		}

		$deleter->delete($type, $reason);

		return $this->apiSuccess();
	}

	/**
	 * @param int $id
	 * @param string|array $with
	 *
	 * @return \XFMG\Entity\Album
	 *
	 * @throws Exception
	 */
	protected function assertViewableAlbum($id, $with = 'api')
	{
		return $this->assertViewableApiRecord('XFMG:Album', $id, $with);
	}
}
