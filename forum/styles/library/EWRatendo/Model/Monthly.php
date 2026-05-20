<?php

class EWRatendo_Model_Monthly extends XenForo_Model
{
	public function getMonthly($month, $year, $getInfo = true)
	{
		$first = mktime(0, 0, 0, $month, 1, $year);
		list($weekday, $daynum, $count) = explode('.' ,date('w.z.t', $first));
		$weekday = $weekday ? $weekday : 7;
		$prev = date('t', strtotime('-7 days', $first));
		$last = mktime(23, 59, 59, $month, $count, $year);

		$events = $this->getEventsMonth($first, $last, $month, $count);

		if ($getInfo)
		{
			$birthdays = XenForo_Application::get('options')->EWRatendo_birthdays ? $this->getModelFromCache('EWRatendo_Model_Birthdays')->getBirthdayCount($month) : false;
		}

		for ($day = ($prev-$weekday+2); $day <= $prev; $day++)
		{
			$dates[] = array(
				'day' => $day
			);
		}

		for ($day = 1; $day <= $count; $day++, $weekday++, $daynum++)
		{
			if ($weekday > 7)
			{
				$dates[] = array('spacer' => true);
				$weekday = 1;
			}

			$week = date('W', mktime(0, 0, 0, $month, $day, $year));
			$wYear = $week == 52 && $month == 1 ? $year-1 : $year;

			$dates[] = array(
				'month' => $month,
				'day' => $day,
				'daynum' => $daynum,
				'year' => $year,
				'week' => $week,
				'wYear' => $wYear,
				'weekday' => $weekday,
				'events' => !empty($events[$day]) ? $events[$day] : false,
				'count' => !empty($events[$day]) ? count($events[$day]) : 0,
				'birthdays' => !empty($birthdays[$day]) ? $birthdays[$day]['count'] : false,
			);
		}

		for ($day = 1; $day <= (7-$weekday+1); $day++)
		{
			$dates[] = array(
				'day' => $day,
			);
		}

		return $dates;
	}

	public function getEventsMonth($first, $last, $month, $count)
	{
		$cutoff = XenForo_Application::get('options')->EWRatendo_cutoff;
		$events = array();

		$results = $this->getModelFromCache('EWRatendo_Model_Events')->getEventsRange($first, $last);

		foreach ($results AS $event)
		{
			$strtime = new DateTime(date('r', $event['event_strtime']));
			$strtime->setTimezone(new DateTimeZone($event['event_timezone']));
			list($strdate, $strmnth) = explode('.', $strtime->format('j.n'));

			$endtime = new DateTime(date('r', $event['event_endtime']));
			$endtime->setTimezone(new DateTimeZone($event['event_timezone']));
			list($enddate, $endmnth, $endhour) = explode('.', $endtime->format('j.n.G'));

			if ($strmnth > $month) { continue; }
			if ($strmnth < $month && $endmnth < $month) { continue; }
			if ($strmnth < $month) { $strdate = 1; }
			if ($endmnth > $month)
			{
				$enddate = $count;
				$endhour = 23;
			}

			for ($i = $strdate; $i <= $enddate; $i++)
			{
				if ($i == $enddate && $endhour <= $cutoff)
				{
					continue;
				}

				$events[$i][] = $event;
			}
		}

		return $events;
	}
}