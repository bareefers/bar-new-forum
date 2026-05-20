<?php

class EWRatendo_Option_ForumChooser2
{
	public static function renderOption(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
	{
		$values = $preparedOption['option_value'];
		
		$nodeModel = XenForo_Model::create('XenForo_Model_Node');
		$nodes = $nodeModel->getAllNodes();
		
		$eventForums = XenForo_Application::get('options')->EWRatendo_eventforums;
		
		$forums = array();
		foreach ($eventForums AS $forum)
		{
			if ($forum == 0 || empty($nodes[$forum])) { continue; }
		
			$forums[] = array(
				'node_id' => $forum,
				'title' => $nodes[$forum]['title'],
				'topctrl' => !empty($values[$forum]) ? $values[$forum]['topctrl'] : 0,
				'cutoff' => !empty($values[$forum]) ? $values[$forum]['cutoff'] : 0,
			);
		}

		$editLink = $view->createTemplateObject('option_list_option_editlink', array(
			'preparedOption' => $preparedOption,
			'canEditOptionDefinition' => $canEdit
		));

		return $view->createTemplateObject('option_list_option_multi_EWRatendo2', array(
			'fieldPrefix' => $fieldPrefix,
			'listedFieldName' => $fieldPrefix . '_listed[]',
			'preparedOption' => $preparedOption,
			'editLink' => $editLink,
			'forums' => $forums
		));
	}

	public static function verifyOption(array &$options, XenForo_DataWriter $dw, $fieldName)
	{
		foreach ($options AS $key => &$option)
		{
			$option['topctrl'] = !empty($option['topctrl']) ? $option['topctrl'] : 0;
			$option['cutoff'] = !empty($option['cutoff']) ? $option['cutoff'] : 0;
		}

		return true;
	}
}