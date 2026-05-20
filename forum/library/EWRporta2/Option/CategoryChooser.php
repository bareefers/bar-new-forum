<?php

class EWRporta2_Option_CategoryChooser
{
	public static function renderOption(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
	{
		$preparedOption['formatParams'] = self::getCategoryOptions(
			$preparedOption['option_value'],
			sprintf('(%s)', new XenForo_Phrase('unspecified'))
		);

		return XenForo_ViewAdmin_Helper_Option::renderOptionTemplateInternal(
			'EWRporta2_option_template_multiSelect', $view, $fieldPrefix, $preparedOption, $canEdit
		);
	}
	
	public static function getCategoryOptions($selectedCategory, $unspecifiedPhrase = false)
	{
		$categoriesModel = XenForo_Model::create('EWRporta2_Model_Categories');

		$categories = $categoriesModel->getAllCategories('category');
		$options = array();
		
		foreach ($categories AS $category)
		{
			$options[] = array(
				'label' => $category['category_name'],
				'value' => $category['category_id'],
				'selected' => ($selectedCategory == $category['category_id'])
			);
		}

		if ($unspecifiedPhrase)
		{
			$options = array_merge(array(array
			(
				'label' => $unspecifiedPhrase,
				'value' => 0,
				'selected' => ($selectedCategory == 0)
			)), $options);
		}

		return $options;
	}
}