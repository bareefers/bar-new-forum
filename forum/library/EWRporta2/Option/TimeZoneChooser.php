<?php

class EWRporta2_Option_TimeZoneChooser
{
	public static function renderOption(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
	{
		$preparedOption['formatParams'] = array(
			'0' => new XenForo_Phrase('criteria_in_visitor_timezone')
		) + XenForo_Helper_TimeZone::getTimeZones();

		return XenForo_ViewAdmin_Helper_Option::renderOptionTemplateInternal(
			'option_list_option_select', $view, $fieldPrefix, $preparedOption, $canEdit
		);
	}
}