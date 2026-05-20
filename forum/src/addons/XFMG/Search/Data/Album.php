<?php

namespace XFMG\Search\Data;

use XF\Http\Request;
use XF\Mvc\Entity\Entity;
use XF\Search\Data\AbstractData;
use XF\Search\Data\AutoCompletableInterface;
use XF\Search\Data\AutoCompletableTrait;
use XF\Search\IndexRecord;
use XF\Search\MetadataStructure;
use XF\Search\Query\Query;
use XF\Tree;
use XFMG\Repository\Category;
use XFMG\XF\Entity\User;

/**
 * @extends AbstractData<\XFMG\Entity\Album>
 * @implements AutoCompletableInterface<\XFMG\Entity\Album>
 */
class Album extends AbstractData implements AutoCompletableInterface
{
	use AutoCompletableTrait;

	public function getEntityWith($forView = false)
	{
		$get = ['User', 'Category'];
		if ($forView)
		{
			$visitor = \XF::visitor();
			$get[] = 'Category.Permissions|' . $visitor->permission_combination_id;
		}

		return $get;
	}

	public function getIndexData(Entity $entity)
	{
		$index = IndexRecord::create('xfmg_album', $entity->album_id, [
			'title' => $entity->title_,
			'message' => $entity->description_,
			'date' => $entity->create_date,
			'user_id' => $entity->user_id,
			'discussion_id' => $entity->album_id,
			'metadata' => $this->getMetaData($entity),
		]);

		if (!$entity->isVisible())
		{
			$index->setHidden();
		}

		return $index;
	}

	protected function getMetaData(\XFMG\Entity\Album $entity)
	{
		$metadata = [];

		$metadata['albumcat'] = $entity->category_id;

		return $metadata;
	}

	public function setupMetadataStructure(MetadataStructure $structure)
	{
		$structure->addField('albumcat', MetadataStructure::INT);
	}

	public function getResultDate(Entity $entity)
	{
		return $entity->create_date;
	}

	public function getSearchableContentTypes()
	{
		return ['xfmg_album'];
	}

	public function getTemplateData(Entity $entity, array $options = [])
	{
		return [
			'album' => $entity,
			'options' => $options,
		];
	}

	public function getSearchFormTab()
	{
		/** @var User $visitor */
		$visitor = \XF::visitor();
		if (
			!method_exists($visitor, 'canViewMedia')
			|| !$visitor->canViewMedia()
			|| !method_exists($visitor, 'canViewAlbums')
			|| !$visitor->canViewAlbums()
		)
		{
			return null;
		}

		return [
			'title' => \XF::phrase('xfmg_search_albums'),
			'order' => 205,
		];
	}

	public function getSectionContext()
	{
		return 'xfmg';
	}

	public function getSearchFormData()
	{
		return [
			'categoryTree' => $this->getSearchableCategoryTree(),
		];
	}

	/**
	 * @return Tree
	 */
	protected function getSearchableCategoryTree()
	{
		/** @var Category $categoryRepo */
		$categoryRepo = \XF::repository('XFMG:Category');
		$categoryTree = $categoryRepo->createCategoryTree($categoryRepo->getViewableCategories());

		return $categoryTree;
	}

	public function applyTypeConstraintsFromInput(Query $query, Request $request, array &$urlConstraints)
	{
		$categoryIds = $request->filter('c.categories', 'array-uint');
		$categoryIds = array_unique($categoryIds);
		if ($categoryIds && reset($categoryIds))
		{
			if ($request->filter('c.child_categories', 'bool'))
			{
				$categoryTree = $this->getSearchableCategoryTree();

				$searchCategoryIds = array_fill_keys($categoryIds, true);
				$categoryTree->traverse(function ($id, $category) use (&$searchCategoryIds)
				{
					if (isset($searchCategoryIds[$id]) || isset($searchCategoryIds[$category->parent_category_id]))
					{
						$searchCategoryIds[$id] = true;
					}
				});

				$categoryIds = array_unique(array_keys($searchCategoryIds));
			}
			else
			{
				unset($urlConstraints['child_categories']);
			}

			$query->withMetadata('albumcat', $categoryIds);
		}
		else
		{
			unset($urlConstraints['categories']);
			unset($urlConstraints['child_categories']);
		}
	}

	public function canUseInlineModeration(Entity $entity, &$error = null)
	{
		return $entity->canUseInlineModeration($error);
	}

	public function getAutoCompleteResult(
		Entity $entity,
		array $options = []
	): ?array
	{
		return $this->getSimpleAutoCompleteResult(
			$entity->title,
			$entity->getContentUrl(),
			$entity->description,
			$entity->User,
			$entity->username
		);
	}
}
