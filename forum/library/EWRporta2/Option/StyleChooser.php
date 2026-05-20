<?php

class EWRporta2_Option_StyleChooser
{
	public static function renderOption(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
	{
		$editLink = $view->createTemplateObject('option_list_option_editlink', array(
			'preparedOption' => $preparedOption,
			'canEditOptionDefinition' => $canEdit
		));

		$styleModel = XenForo_Model::create('XenForo_Model_Style');
		$styleOptions = $styleModel->getStylesForOptionsTag($preparedOption['option_value']);

		return $view->createTemplateObject('EWRporta2_option_template_styleChooser', array(
			'fieldPrefix' => $fieldPrefix,
			'listedFieldName' => $fieldPrefix . '_listed[]',
			'preparedOption' => $preparedOption,
			'formatParams' => $styleOptions,
			'editLink' => $editLink
		));
	}
}