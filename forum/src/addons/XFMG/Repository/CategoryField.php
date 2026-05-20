<?php

namespace XFMG\Repository;

use XF\Entity\AbstractField;
use XF\Repository\AbstractFieldMap;
use XFMG\Entity\Category;

class CategoryField extends AbstractFieldMap
{
	protected function getMapEntityIdentifier()
	{
		return 'XFMG:CategoryField';
	}

	protected function getAssociationsForField(AbstractField $field)
	{
		return $field->getRelation('CategoryFields');
	}

	protected function updateAssociationCache(array $cache)
	{
		$categoryIds = array_keys($cache);
		$categories = $this->em->findByIds('XFMG:Category', $categoryIds);

		foreach ($categories AS $category)
		{
			/** @var Category $category */
			$category->field_cache = $cache[$category->category_id];
			$category->saveIfChanged();
		}
	}
}
