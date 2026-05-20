<?php

abstract class DigitalPointAdPositioning_Option_UsergroupSelector
{
	/**
	 * Renders the usergroup chooser option.
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
		$usergroups = XenForo_Model::create('XenForo_Model_UserGroup')->getAllUserGroups();

		foreach ($usergroups as $key => $usergroup) {
			$preparedOption['options'][$usergroup['user_group_id']] = array('name' => $usergroup['title'], 'checked' => in_array($usergroup['user_group_id'], (array)$preparedOption['option_value']));
		}
		
		return XenForo_ViewAdmin_Helper_Option::renderOptionTemplateInternal(
			'option_template_AdPositioning_MultiCheckbox',
			$view, $fieldPrefix, $preparedOption, $canEdit
		);
	}
}