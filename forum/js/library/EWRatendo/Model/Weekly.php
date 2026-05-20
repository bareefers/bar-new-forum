<?php

class EWRatendo_Model_Weekly extends XenForo_Model
{
	public function getWeekly($week, $year, $getInfo = true)
	{
		$first = strtotime($year."W".$week);
		$last = strtotime("+7 days -1 seconds", $first);
		$month = date('n', $first);

		if ($getInfo)
		{
			$events = $this->getEventsWeek($first, $last, $week);
			$birthdays = $this->getModelFromCache('EWRatendo_Model_Birthdays')->getBirthdayCount($month);
		}

		$dates = array();

		for ($dow = 1; $dow <= 7; $dow++)
		{
			if (date('n', $first) != $month)
			{
				list($month, $year) = explode('.', date('n.Y', $first));
				$dates[] = array('spacer' => new XenForo_Phrase('month_'.$month).' '.$year);
			}

			list($day, $weekday) = explode('.', date('j.l', $first));
			$first = strtotime("+1 day", $first);

			if (XenForo_Application::get('options')->EWRatendo_birthdays)
			{
				$bdnow = $this->getModelFromCache('EWRatendo_Model_Birthdays')->getBirthdays($month, $day);
			}
			else
			{
				$bdnow = !empty($birthdays[$day]) ? $birthdays[$day]['count'] : false;
			}

			$dates[] = array(
				'month' => $month,
				'day' => $day,
				'year' => $year,
				'weekday' => new XenForo_Phrase('day_'.strtolower($weekday)),
				'events' => !empty($events[$dow]) ? $this->sortArray($events[$dow], 'order') : false,
				'birthdays' => $bdnow,
			);
		}

		return $dates;
	}

	public function getEventsWeek($first, $last, $week)
	{
		$options = XenForo_Application::get('options');
		$timeformat = $options->EWRatendo_timeformat;
		$cutoff = $options->EWRatendo_cutoff;
		$events = array();

		$results = $this->getModelFromCache('EWRatendo_Model_Events')->getEventsRange($first, $last);

		foreach ($results AS $event)
		{
			$strtime = new DateTime(date('r', $event['event_strtime']));
			$strtime->setTimezone(new DateTimeZone($event['event_timezone']));
			list($strdate, $strweek) = explode('.', $strtime->format('w.W'));
			$strdate = $strdate ? $strdate : 7;
			$event['formatted_strtime'] = $strtime->format($timeformat);
			$event['order'] = $strtime->format('G.i');

			$endtime = new DateTime(date('r', $event['event_endtime']));
			$endtime->setTimezone(new DateTimeZone($event['event_timezone']));
			list($enddate, $endweek, $endhour) = explode('.', $endtime->format('w.W.G'));
			$enddate = $enddate ? $enddate : 7;

			if ($strweek > $week) { continue; }
			if ($strweek < $week && $endweek < $week) { continue; }
			if ($strweek < $week) { $strdate = 1; }
			if ($endweek > $week)
			{
				$enddate = 7;
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

	public function sortArray($array, $on)
	{
		$new_array = array();
		$sortable_array = array();

		if (count($array) > 0)
		{
			foreach ($array as $k => $v)
			{
				if (is_array($v))
				{
					foreach ($v as $k2 => $v2)
					{
						if ($k2 == $on)
						{
							$sortable_array[$k] = $v2;
						}
					}
				}
				else
				{
					$sortable_array[$k] = $v;
				}
			}

			asort($sortable_array);

			foreach ($sortable_array as $k => $v)
			{
				$new_array[$k] = $array[$k];
			}
		}

		return $new_array;
	}
}