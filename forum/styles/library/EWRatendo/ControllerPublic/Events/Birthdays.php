<?php

class EWRatendo_ControllerPublic_Events_Birthdays extends XenForo_ControllerPublic_Abstract
{
	public $perms;

	public function actionIndex()
	{
		if ($dateSelect = $this->_input->filterSingle('date_select', XenForo_Input::STRING))
		{
			list($day, $month) = explode('.', $dateSelect);
		}
		else
		{
			list($day, $month) = explode('.', date('j.n'));
		}

		$viewParams = array(
			'month' => date('F', mktime(0, 0, 0, $month)),
			'day' => $day,
			'birthdays' => $this->getModelFromCache('EWRatendo_Model_Birthdays')->getBirthdays($month, $day),
		);

		return $this->responseView('EWRatendo_ViewPublic_Birthdays', 'EWRatendo_Birthdays', $viewParams);
	}

	public static function getSessionActivityDetailsForList(array $activities)
	{
        $output = array();
        foreach ($activities as $key => $activity)
		{
			$output[$key] = new XenForo_Phrase('viewing_event_calendar');
        }

        return $output;
	}

	protected function _preDispatch($action)
	{
		parent::_preDispatch($action);

		$this->perms = $this->getModelFromCache('EWRatendo_Model_Perms')->getPermissions();

		$visitor = XenForo_Visitor::getInstance();
		date_default_timezone_set($visitor['timezone']);
	}
}