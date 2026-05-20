<?php

class EWRporta2_Option_AuthorChooser
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
		$authorsModel = XenForo_Model::create('EWRporta2_Model_Authors');

		$authors = $authorsModel->getAllAuthors('active');
		$options = array();
		
		foreach ($authors AS $author)
		{
			$options[] = array(
				'label' => $author['author_name'],
				'value' => $author['user_id'],
				'selected' => ($selectedCategory == $author['user_id'])
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