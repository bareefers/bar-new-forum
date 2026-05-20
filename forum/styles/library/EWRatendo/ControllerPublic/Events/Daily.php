<?php

class EWRatendo_ControllerPublic_Events_Daily extends XenForo_ControllerPublic_Abstract
{
	public $perms;

	public function actionIndex()
	{
		if ($dateSelect = $this->_input->filterSingle('date_select', XenForo_Input::STRING))
		{
			list($daynum, $year) = explode('.', $dateSelect);
		}
		else
		{
			list($daynum, $year) = explode('.', date('z.Y'));
		}

		list($month, $day) = explode('.', date('n.j', strtotime('1 Jan '.$year.' +'.$daynum.' days')));

		$viewParams = array(
			'year' => $year,
			'month' => date('F', mktime(0, 0, 0, $month)),
			'day' => $day,
			'events' => $this->getModelFromCache('EWRatendo_Model_Daily')->getDaily($day, $month, $year),
		);

		return $this->responseView('EWRatendo_ViewPublic_Daily', 'EWRatendo_Daily', $viewParams);
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