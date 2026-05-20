<?php

class EWRatendo_Model_History extends XenForo_Model
{
	public function getPastEvents($start, $stop)
	{
		$start = ($start - 1) * $stop;

		if (!$events = $this->_getDb()->fetchAll("
			SELECT *
				FROM EWRatendo_events
				LEFT JOIN xf_user ON (xf_user.user_id = EWRatendo_events.user_id)
			WHERE EWRatendo_events.event_endtime <= ?
			ORDER BY EWRatendo_events.event_strtime DESC
			LIMIT ?, ?
		", array(XenForo_Application::$time, $start, $stop)))
		{
			return array();
		}

		foreach ($events AS &$event)
		{
			$event = $this->getModelFromCache('EWRatendo_Model_Events')->formatDates($event);
		}

		return $events;
	}

	public function getPastCount()
	{
        $count = $this->_getDb()->fetchRow("
			SELECT COUNT(*) AS total
				FROM EWRatendo_events
			WHERE event_endtime <= ?
		", XenForo_Application::$time);

		return $count['total'];
	}
}