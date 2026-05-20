<?php

class EWRatendo_ControllerPublic_Events_Weekly extends XenForo_ControllerPublic_Abstract
{
	public $perms;

	public function actionIndex()
	{
		if ($dateSelect = $this->_input->filterSingle('date_select', XenForo_Input::STRING))
		{
			list($week, $year) = explode('.', $dateSelect);
		}
		else
		{
			list($week, $month, $year) = explode('.', date('W.n.Y'));
			$year = $week == 52 && $month == 1 ? $year-1 : $year;

			$week = $this->_input->filterSingle('week', XenForo_Input::UINT) ? $this->_input->filterSingle('week', XenForo_Input::UINT) : $week;
			$year = $this->_input->filterSingle('year', XenForo_Input::UINT) ? $this->_input->filterSingle('year', XenForo_Input::UINT) : $year;
		}

		$week = str_pad($week, 2, "0", STR_PAD_LEFT);
		$month = date('n', strtotime($year."W".$week));
		list($mNow, $dNow, $yNow, $wNow) = explode('.', date('n.j.Y.W', XenForo_Application::$time));
		$today = array('month' => $mNow, 'day' => $dNow, 'year' => $yNow, 'week' => $wNow);

		$wCurr = array('month' => $month, 'year' => $year, 'week' => $week);
		$mPrev = array('month' => $month-1 ? $month-1 : 12, 'year' => $month-1 ? $year : $year-1);
		$mNext = array('month' => $month, 'year' => $year);
		$mLate = array('month' => $month+1 > 12 ? 1 : $month+1, 'year' => $month+1 > 12 ? $year+1 : $year);
		$wCurr['title'] = new XenForo_Phrase('month_'.$wCurr['month']).' '.$wCurr['year'];
		$mPrev['title'] = new XenForo_Phrase('month_'.$mPrev['month']).' '.$mPrev['year'];
		$mNext['title'] = new XenForo_Phrase('month_'.$mNext['month']).' '.$mNext['year'];
		$mLate['title'] = new XenForo_Phrase('month_'.$mLate['month']).' '.$mLate['year'];
		$wCurr['dates'] = $this->getModelFromCache('EWRatendo_Model_Weekly')->getWeekly($wCurr['week'], $wCurr['year']);
		$mPrev['dates'] = $this->getModelFromCache('EWRatendo_Model_Monthly')->getMonthly($mPrev['month'], $mPrev['year'], false);
		$mNext['dates'] = $this->getModelFromCache('EWRatendo_Model_Monthly')->getMonthly($mNext['month'], $mNext['year'], false);
		$mLate['dates'] = $this->getModelFromCache('EWRatendo_Model_Monthly')->getMonthly($mLate['month'], $mLate['year'], false);

		$weeks = array();
		for($i = 1; $i <= 52; $i++)
		{
			$weeks[] = array(
				'number' => $i,
				'select' => $i == $week ? true : false,
				'phrase' => new XenForo_Phrase('week').': '.$i,
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

		if ($week == 52)
		{
			$prev = array('week' => $week-1, 'wYear' => $year);
			$next = array('week' => 1, 'wYear' => $year+1);
		}
		elseif ($week == 1)
		{
			$prev = array('week' => 52, 'wYear' => $year-1);
			$next = array('week' => $week+1, 'wYear' => $year);
		}
		else
		{
			$prev = array('week' => $week-1, 'wYear' => $year);
			$next = array('week' => $week+1, 'wYear' => $year);
		}

		$viewParams = array(
			'canPost' => $this->perms['post'],
			'weeks' => $weeks,
			'years' => $years,
			'prev' => $prev,
			'next' => $next,
			'today' => $today,
			'selec' => $wCurr,
			'wCurr' => $wCurr,
			'mPrev' => $mPrev,
			'mNext' => $mNext,
			'mLate' => $mLate,
		);

		return $this->responseView('EWRatendo_ViewPublic_Weekly', 'EWRatendo_Weekly', $viewParams);
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