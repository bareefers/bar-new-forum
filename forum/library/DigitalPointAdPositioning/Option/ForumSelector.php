<?php

abstract class DigitalPointAdPositioning_Option_ForumSelector
{
	/**
	 * Renders the forum chooser option.
	 *
	 * @param XenForo_View $view View object
	 * @param string $fieldPrefix Prefix for the HTML form field name
	 * @param array $preparedOption Prepared option info
	 * @param boolean $canEdit True if an "edit" link should appear
	 *
	 * @return XenForo_Template_Abstract Template object
	 */
	public static function renderOption(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
	{
		$preparedOption['formatParams'] = array();
				
		$nodes = XenForo_Model::create('XenForo_Model_Node')->getAllNodes();

		foreach ($nodes as $key => $node) {
			if ($node['node_type_id'] == 'Forum' || $node['node_type_id'] == 'Category') $preparedOption['options'][$node['node_id']] = array('name' => $node['title'], 'checked' => in_array($node['node_id'], $preparedOption['option_value']), 'category' => $node['node_type_id'] == 'Category');
		}

		return XenForo_ViewAdmin_Helper_Option::renderOptionTemplateInternal(
			'option_template_AdPositioning_MultiSelect',
			$view, $fieldPrefix, $preparedOption, $canEdit
		);
	}
}