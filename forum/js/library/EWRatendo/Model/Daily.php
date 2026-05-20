<?php

class EWRatendo_Model_Daily extends XenForo_Model
{
	public function getDaily($day, $month, $year)
	{
		$first = mktime(0, 0, 0, $month, $day, $year);
		$last = mktime(23, 59, 59, $month, $day, $year);

		$events = $this->getEventsMonth($first, $last, $day);

		return $events;
	}

	public function getEventsMonth($first, $last, $day)
	{
		$cutoff = XenForo_Application::get('options')->EWRatendo_cutoff;
		$events = array();

		$results = $this->getModelFromCache('EWRatendo_Model_Events')->getEventsRange($first, $last);

		foreach ($results AS $event)
		{
			$strtime = new DateTime(date('r', $event['event_strtime']));
			$strtime->setTimezone(new DateTimeZone($event['event_timezone']));
			$strday = $strtime->format('j');

			$endtime = new DateTime(date('r', $event['event_endtime']));
			$endtime->setTimezone(new DateTimeZone($event['event_timezone']));
			list($endday, $endhour) = explode('.', $endtime->format('j.G'));

			if ($strday > $day) { continue; }
			if ($strday < $day && $endday < $day) { continue; }
			if ($endday == $day && $endhour <= $cutoff)
			{
				continue;
			}

			$event = $this->getModelFromCache('EWRatendo_Model_Events')->formatDates($event);
			$events[] = $event;
		}

		return $events;
	}
}