<?php

class EWRatendo_Model_Upcoming extends XenForo_Model
{
	public function getUpcomingEvents()
	{
		$results = $this->fetchAllKeyed("
			SELECT EWRatendo_events.*, xf_user.*,
				IF(xf_user.username IS NULL, EWRatendo_events.username, xf_user.username) AS username
			FROM EWRatendo_events
				LEFT JOIN xf_user ON (xf_user.user_id = EWRatendo_events.user_id)
			WHERE EWRatendo_events.event_endtime >= ?
			ORDER BY EWRatendo_events.event_strtime
		", 'event_strtime', XenForo_Application::$time);

		$recurs = $this->fetchAllKeyed("
			SELECT EWRatendo_events.*, EWRatendo_recurs.*, xf_user.*,
				IF(xf_user.username IS NULL, EWRatendo_events.username, xf_user.username) AS username
			FROM EWRatendo_recurs
				INNER JOIN EWRatendo_events ON (EWRatendo_events.event_id = EWRatendo_recurs.event_id)
				LEFT JOIN xf_user ON (xf_user.user_id = EWRatendo_events.user_id)
			WHERE EWRatendo_recurs.event_endtime >= ?
			ORDER BY EWRatendo_recurs.event_strtime
		", 'event_strtime', XenForo_Application::$time);

		$results = array_merge($results, $recurs);

		$strtime = array();
		foreach ($results AS $key => $row) {
			$strtime[$key] = $row['event_strtime'];
		}
		array_multisort($strtime, SORT_ASC, $results);

		$events = array();

		$time2w = strtotime("+2 weeks");
		$time1m = strtotime("+1 month");
		$time3m = strtotime("+3 months");
		$time6m = strtotime("+6 months");
		$time1y = strtotime("+1 year");

		$on = (string) new XenForo_Phrase('on_');
		$within1m = (string) new XenForo_Phrase('within_x_y', array('count' => 1, 'range' => new XenForo_Phrase('months')));
		$within3m = (string) new XenForo_Phrase('within_x_y', array('count' => 3, 'range' => new XenForo_Phrase('months')));
		$within6m = (string) new XenForo_Phrase('within_x_y', array('count' => 6, 'range' => new XenForo_Phrase('months')));
		$within1y = (string) new XenForo_Phrase('within_x_y', array('count' => 1, 'range' => new XenForo_Phrase('year')));
		$further = (string) new XenForo_Phrase('far_into_the_future');

		foreach ($results AS $event)
		{
			$event = $this->getModelFromCache('EWRatendo_Model_Events')->formatDates($event);
			$event['show_venue'] = true;

			switch (true)
			{
				case ($event['event_strtime'] < $time2w):	$events[$on.$event['key']][] = $event;	break;
				case ($event['event_strtime'] < $time1m):	$events[$within1m][] = $event;			break;
				case ($event['event_strtime'] < $time3m):	$events[$within3m][] = $event;			break;
				case ($event['event_strtime'] < $time6m):	$events[$within6m][] = $event;			break;
				case ($event['event_strtime'] < $time1y):	$events[$within1y][] = $event;			break;
				default:									$events[$further][] = $event;			break;
			}
		}

		return $events;
	}
}