<?php

namespace XFMG\Entity;

use XF\Entity\AbstractFieldMap;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $category_id
 * @property string $field_id
 *
 * RELATIONS
 * @property-read MediaField|null $Field
 * @property-read Category|null $Category
 */
class CategoryField extends AbstractFieldMap
{
	public static function getContainerKey()
	{
		return 'category_id';
	}

	public static function getStructure(Structure $structure)
	{
		self::setupDefaultStructure($structure, 'xf_mg_category_field', 'XFMG:CategoryField', 'XFMG:MediaField');

		$structure->relations['Category'] = [
			'entity' => 'XFMG:Category',
			'type' => self::TO_ONE,
			'conditions' => 'category_id',
			'primary' => true,
		];

		return $structure;
	}
}
