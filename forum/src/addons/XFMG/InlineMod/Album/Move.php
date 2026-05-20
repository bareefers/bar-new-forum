<?php

namespace XFMG\InlineMod\Album;

use XF\Http\Request;
use XF\InlineMod\AbstractAction;
use XF\Mvc\Controller;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;
use XFMG\Entity\Category;
use XFMG\InlineMod\AlertSendableTrait;
use XFMG\Service\Album\Mover;

use function count, intval;

/**
 * @extends AbstractAction<\XFMG\Entity\Album>
 */
class Move extends AbstractAction
{
	use AlertSendableTrait;

	/** @var  Category */
	protected $targetCategory;
	protected $targetCategoryId;

	public function getTitle()
	{
		return \XF::phrase('xfmg_move_albums...');
	}

	protected function canApplyInternal(AbstractCollection $entities, array $options, &$error)
	{
		$result = parent::canApplyInternal($entities, $options, $error);

		if ($result)
		{
			if ($options['target_category_id'])
			{
				$category = $this->getTargetCategory($options['target_category_id']);
				if (!$category)
				{
					return false;
				}

				if ($options['check_category_viewable'] && !$category->canView($error))
				{
					return false;
				}

				if ($options['check_all_same_category'])
				{
					$allSame = true;
					foreach ($entities AS $entity)
					{
						if ($entity->category_id != $options['target_category_id'])
						{
							$allSame = false;
							break;
						}
					}

					if ($allSame)
					{
						$error = \XF::phraseDeferred('xfmg_all_selected_albums_already_in_destination_category');
						return false;
					}
				}
			}
		}

		return $result;
	}

	protected function canApplyToEntity(Entity $entity, array $options, &$error = null)
	{
		return $entity->canMove($error);
	}

	protected function applyInternal(AbstractCollection $entities, array $options)
	{
		if ($options['alert'])
		{
			// we must do this here since the service only moves a single album at a time
			$contentIds = [$options['target_category_id']];
			$permissionCombinationIds = [];
			foreach ($entities AS $entity)
			{
				if (!$entity->user_id || !$entity->User)
				{
					continue;
				}

				if ($entity->category_id)
				{
					$contentIds[] = $entity->category_id;
				}

				$permissionCombinationIds[] = $entity->User->permission_combination_id;
			}

			Mover::cacheContentPermissions(
				'xfmg_category',
				$contentIds,
				$permissionCombinationIds
			);
		}

		parent::applyInternal($entities, $options);
	}
	protected function applyToEntity(Entity $entity, array $options)
	{
		$category = $this->getTargetCategory($options['target_category_id']);
		if ($category)
		{
			if ($category->category_type != 'album')
			{
				throw new \InvalidArgumentException(\XF::phrase('xfmg_cannot_move_album_into_non_album_category'));
			}
		}

		/** @var Mover $mover */
		$mover = $this->app()->service('XFMG:Album\Mover', $entity);

		if ($options['alert'] && $entity->canSendModeratorActionAlert())
		{
			$mover->setSendAlert(true, $options['alert_reason']);
		}

		if ($options['notify_watchers'])
		{
			$mover->setNotifyWatchers();
		}

		$mover->move($category);

		if ($category)
		{
			$this->returnUrl = $this->app()->router()->buildLink('media/categories', $category);
		}
		else
		{
			$this->returnUrl = $this->app()->router()->buildLink('media/albums');
		}
	}

	public function getBaseOptions()
	{
		return [
			'target_category_id' => 0,
			'check_category_viewable' => true,
			'check_all_same_category' => true,
			'notify_watchers' => false,
			'alert' => false,
			'alert_reason' => '',
		];
	}

	public function renderForm(AbstractCollection $entities, Controller $controller)
	{
		/** @var \XFMG\Repository\Category $categoryRepo */
		$categoryRepo = $this->app()->repository('XFMG:Category');
		$categories = $categoryRepo->getViewableCategories();

		$viewParams = [
			'albums' => $entities,
			'total' => count($entities),
			'categoryTree' => $categoryRepo->createCategoryTree($categories),
			'first' => $entities->first(),
			'canSendAlert' => $this->canSendAlert($entities),
		];
		return $controller->view('XFMG:Public:InlineMod\Album\Move', 'xfmg_inline_mod_album_move', $viewParams);
	}

	public function getFormOptions(AbstractCollection $entities, Request $request)
	{
		return [
			'target_category_id' => $request->filter('target_category_id', 'uint'),
			'notify_watchers' => $request->filter('notify_watchers', 'bool'),
			'alert' => $request->filter('author_alert', 'bool'),
			'alert_reason' => $request->filter('author_alert_reason', 'str'),
		];
	}

	/**
	 * @param integer $categoryId
	 *
	 * @return null|Category
	 */
	protected function getTargetCategory($categoryId)
	{
		$categoryId = intval($categoryId);

		if ($this->targetCategoryId && $this->targetCategoryId == $categoryId)
		{
			return $this->targetCategory;
		}
		if (!$categoryId)
		{
			return null;
		}

		$category = $this->app()->em()->find('XFMG:Category', $categoryId);
		if (!$category)
		{
			throw new \InvalidArgumentException("Invalid target category ($categoryId)");
		}

		$this->targetCategoryId = $categoryId;
		$this->targetCategory = $category;

		return $this->targetCategory;
	}
}
