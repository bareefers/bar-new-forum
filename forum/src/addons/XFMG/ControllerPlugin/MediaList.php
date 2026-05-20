<?php

namespace XFMG\ControllerPlugin;

use XF\Entity\User;
use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Reply\View;
use XFMG\Entity\Category;
use XFMG\Entity\MediaItem;
use XFMG\Repository\Media;

use function count, in_array;

class MediaList extends AbstractList
{
	public function renderMediaListRss(ArrayCollection $mediaItems, $feedTitle, $feedDescription, $feedLink, string $link = '')
	{
		$viewParams = [
			'mediaItems' => $mediaItems,
			'feedTitle' => $feedTitle,
			'feedDescription' => $feedDescription,
			'feedLink' => $feedLink,
			'link' => $link,
		];
		return $this->view('XFMG:Rss\Index', '', $viewParams);
	}

	public function getMediaListData(array $sourceCategoryIds, $page = 1, ?User $user = null)
	{
		$mediaRepo = $this->getMediaRepo();

		$allowOwnPending = is_callable([$this->controller, 'hasContentPendingApproval'])
			? $this->controller->hasContentPendingApproval()
			: true;

		if ($user)
		{
			$mediaFinder = $mediaRepo->findMediaForUser($user, $sourceCategoryIds, [
				'allowOwnPending' => $allowOwnPending,
			]);
		}
		else
		{
			$mediaFinder = $mediaRepo->findMediaForIndex($sourceCategoryIds, [
				'allowOwnPending' => $allowOwnPending,
			]);
		}

		$filters = $this->getFilterInput();
		$this->applyFilters($mediaFinder, $filters);
		$isDateLimited = (!$user && $this->options()->xfmgMediaIndexLimit && empty($filters['no_date_limit']));

		if ($isDateLimited)
		{
			$mediaFinder->limitByDate($this->options()->xfmgMediaIndexLimit);
		}

		$totalItems = $mediaFinder->total();

		$page = $this->filterPage($page);
		$perPage = $this->options()->xfmgMediaPerPage;

		if ($this->responseType() == 'rss')
		{
			$page = 1;
			$perPage *= 2;
			// we generally want a larger number of items and only those from page 1
		}

		$mediaFinder->limitByPage($page, $perPage);
		$mediaItems = $mediaFinder->fetchDeferred()->filterViewable();

		if (!empty($filters['owner_id']) && !$user)
		{
			$ownerFilter = $this->em()->find('XF:User', $filters['owner_id']);
		}
		else
		{
			$ownerFilter = null;
		}

		$canInlineMod = ($user && \XF::visitor()->user_id == $user->user_id);
		if (!$canInlineMod)
		{
			foreach ($mediaItems AS $mediaItem)
			{
				/** @var MediaItem $mediaItem */
				if ($mediaItem->canUseInlineModeration())
				{
					$canInlineMod = true;
					break;
				}
			}
		}

		$mediaEndOffset = ($page - 1) * $perPage + count($mediaItems);
		$showDateLimitDisabler = ($isDateLimited && $mediaEndOffset >= $totalItems);

		return [
			'mediaItems' => $mediaItems,
			'filters' => $filters,
			'ownerFilter' => $ownerFilter,
			'canInlineMod' => $canInlineMod,
			'showDateLimitDisabler' => $showDateLimitDisabler,

			'totalItems' => $totalItems,
			'page' => $page,
			'perPage' => $perPage,

			'user' => $user,
		] + $this->getMediaListMessages();
	}

	public function getMediaListMessages()
	{
		$transcoding = false;
		$pendingApproval = false;

		$session = $this->session();
		if ($session->keyExists('xfmgTranscoding'))
		{
			$session->remove('xfmgTranscoding');
			$transcoding = true;
		}
		if ($session->keyExists('xfmgPendingApproval'))
		{
			$session->remove('xfmgPendingApproval');
			$pendingApproval = true;
		}

		return [
			'transcoding' => $transcoding,
			'pendingApproval' => $pendingApproval,
		];
	}

	protected $defaultSort = 'media_date';

	public function getAvailableSorts()
	{
		$sorts = parent::getAvailableSorts();

		return ['media_date' => 'media_date'] + $sorts;
	}

	protected function apply(array $filters, ?Category $category = null, ?User $user = null)
	{
		if ($user)
		{
			return $this->redirect($this->buildLink(
				'media/users',
				$user,
				$filters
			));
		}
		else
		{
			return $this->redirect($this->buildLink(
				$category ? 'media/categories' : 'media',
				$category,
				$filters
			));
		}
	}

	public function getFilterInput()
	{
		$filters = parent::getFilterInput();

		$input = $this->filter([
			'type' => 'str',
			'no_date_limit' => 'bool',
			'featured' => 'bool',
		]);

		if ($input['type'] && in_array($input['type'], ['image', 'audio', 'video', 'embed']))
		{
			$filters['type'] = $input['type'];
		}

		if ($input['no_date_limit'])
		{
			$filters['no_date_limit'] = $input['no_date_limit'];
		}

		if ($input['featured'])
		{
			$filters['featured'] = $input['featured'];
		}

		return $filters;
	}

	public function applyFilters(Finder $finder, array $filters)
	{
		parent::applyFilters($finder, $filters);

		if (!empty($filters['type']))
		{
			$finder->where('media_type', $filters['type']);
		}

		if (!empty($filters['featured']))
		{
			$finder->where('featured', 1);
		}
	}

	public function actionFilters(?Category $category = null, ?User $user = null)
	{
		$reply = parent::actionFilters($category, $user);

		if ($reply instanceof View)
		{
			$reply->setParam('showTypeFilters', true);

			if ($user)
			{
				$reply->setParam('action', $this->buildLink('media/users/filters', $user));
			}
			else
			{
				$reply->setParam('action', $this->buildLink($category ? 'media/categories/filters' : 'media/filters', $category));
			}

			$reply->setParam('type', 'media');
		}

		return $reply;
	}

	/**
	 * @return Media
	 */
	protected function getMediaRepo()
	{
		return $this->repository('XFMG:Media');
	}

	/**
	 * @return \XFMG\Repository\Category
	 */
	protected function getCategoryRepo()
	{
		return $this->repository('XFMG:Category');
	}
}
