<?php

class EWRporta2_Widget_Option_PollBlock
{
	public static function renderOption(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
	{
		$preparedOption['formatParams'] = self::getPollOptions($preparedOption['option_value']);

		return XenForo_ViewAdmin_Helper_Option::renderOptionTemplateInternal(
			'option_list_option_select', $view, $fieldPrefix, $preparedOption, $canEdit
		);
	}
	
	public static function getPollOptions($selectedPoll)
	{
		$pollModel = XenForo_Model::create('EWRporta2_Widget_PollBlock');

		$polls = $pollModel->getPolls(20);
		$options = array();
		
		foreach ($polls AS $poll)
		{
			$options[] = array(
				'label' => $poll['question'],
				'value' => $poll['poll_id'],
				'selected' => ($selectedPoll == $poll['poll_id'])
			);
		}

		return $options;
	}
}