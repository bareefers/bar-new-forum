<?php

class EWRatendo_ControllerPublic_Events_Monthly extends XenForo_ControllerPublic_Abstract
{
	public $perms;

	public function actionIndex()
	{
		if ($dateSelect = $this->_input->filterSingle('date_select', XenForo_Input::STRING))
		{
			list($month, $year) = explode('.', $dateSelect);
		}
		else
		{
			list($month, $year) = explode('.', date('n.Y'));

			$month = $this->_input->filterSingle('month', XenForo_Input::UINT) ? $this->_input->filterSingle('month', XenForo_Input::UINT) : $month;
			$year = $this->_input->filterSingle('year', XenForo_Input::UINT) ? $this->_input->filterSingle('year', XenForo_Input::UINT) : $year;
		}

		list($mNow, $dNow, $yNow, $wNow) = explode('.', date('n.j.Y.W', XenForo_Application::$time));
		$today = array('month' => $mNow, 'day' => $dNow, 'year' => $yNow, 'week' => $wNow);

		$mCurr = array('month' => $month, 'year' => $year, 'week' => $wNow);
		$mPrev = array('month' => $month-1 ? $month-1 : 12, 'year' => $month-1 ? $year : $year-1);
		$mNext = array('month' => $month+1 > 12 ? 1 : $month+1, 'year' => $month+1 > 12 ? $year+1 : $year);
		$mCurr['title'] = new XenForo_Phrase('month_'.$mCurr['month']).' '.$mCurr['year'];
		$mPrev['title'] = new XenForo_Phrase('month_'.$mPrev['month']).' '.$mPrev['year'];
		$mNext['title'] = new XenForo_Phrase('month_'.$mNext['month']).' '.$mNext['year'];
		$mCurr['dates'] = $this->getModelFromCache('EWRatendo_Model_Monthly')->getMonthly($mCurr['month'], $mCurr['year']);
		$mPrev['dates'] = $this->getModelFromCache('EWRatendo_Model_Monthly')->getMonthly($mPrev['month'], $mPrev['year'], false);
		$mNext['dates'] = $this->getModelFromCache('EWRatendo_Model_Monthly')->getMonthly($mNext['month'], $mNext['year'], false);

		$months = array();
		for($i = 1; $i <= 12; $i++)
		{
			$months[] = array(
				'number' => $i,
				'select' => $i == $month ? true : false,
				'phrase' => new XenForo_Phrase('month_'.$i),
			);
		}

		$years = array();
		for($i = $yNow-1; $i <= $yNow+3; $i++)
		{
			$years[] = array(
				'number' => $i,
				'select' => $i == $year ? true : false,
			);
		}

		$prev = array('month' => $mPrev['month'], 'year' => $mPrev['year']);
		$next = array('month' => $mNext['month'], 'year' => $mNext['year']);

		$viewParams = array(
			'canPost' => $this->perms['post'],
			'months' => $months,
			'years' => $years,
			'prev' => $prev,
			'next' => $next,
			'today' => $today,
			'selec' => $mCurr,
			'mCurr' => $mCurr,
			'mPrev' => $mPrev,
			'mNext' => $mNext,
		);

		return $this->responseView('EWRatendo_ViewPublic_Monthly', 'EWRatendo_Monthly', $viewParams);
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