<?php

class EWRatendo_Model_Events extends XenForo_Model
{
	public function getEventsRange($first, $last)
	{
		$visitor = XenForo_Visitor::getInstance();
		date_default_timezone_set($visitor['timezone']);

		$last = strtotime('+1 days', $last);

		$events = $this->_getDb()->fetchAll("
			SELECT EWRatendo_events.*, xf_user.*, IF(xf_user.username IS NULL, EWRatendo_events.username, xf_user.username) AS username
				FROM EWRatendo_events
				LEFT JOIN xf_user ON (xf_user.user_id = EWRatendo_events.user_id)
			WHERE ((EWRatendo_events.event_strtime >= ? AND EWRatendo_events.event_strtime <= ?)
				OR (EWRatendo_events.event_endtime >= ? AND EWRatendo_events.event_endtime <= ?)
				OR (EWRatendo_events.event_strtime <= ? AND EWRatendo_events.event_endtime >= ?))
				AND EWRatendo_events.event_state = 'visible'
			ORDER BY EWRatendo_events.event_strtime
		", array($first, $last, $first, $last, $first, $last));

		$recurs = $this->_getDb()->fetchAll("
			SELECT EWRatendo_events.*, xf_user.*, EWRatendo_recurs.*, IF(xf_user.username IS NULL, EWRatendo_events.username, xf_user.username) AS username
			FROM EWRatendo_recurs
				INNER JOIN EWRatendo_events ON (EWRatendo_events.event_id = EWRatendo_recurs.event_id)
				LEFT JOIN xf_user ON (xf_user.user_id = EWRatendo_events.user_id)
			WHERE ((EWRatendo_recurs.event_strtime >= ? AND EWRatendo_recurs.event_strtime <= ?)
				OR (EWRatendo_recurs.event_endtime >= ? AND EWRatendo_recurs.event_endtime <= ?)
				OR (EWRatendo_recurs.event_strtime <= ? AND EWRatendo_recurs.event_endtime >= ?))
				AND EWRatendo_events.event_state = 'visible'
			ORDER BY EWRatendo_recurs.event_strtime
		", array($first, $last, $first, $last, $first, $last));

		return array_merge($events, $recurs);
	}

	public function getCurrentEvents($range = '+1 week', $nodeid = false, $thread = true)
	{
		if (!$events = $this->_getDb()->fetchAll("
			SELECT EWRatendo_events.*, xf_user.*, xf_thread.*,
				IF(xf_user.username IS NULL, EWRatendo_events.username, xf_user.username) AS username
			FROM EWRatendo_events
				LEFT JOIN xf_user ON (xf_user.user_id = EWRatendo_events.user_id)
				LEFT JOIN xf_thread ON (xf_thread.thread_id = EWRatendo_events.thread_id)
			WHERE EWRatendo_events.event_endtime >= ?
				AND EWRatendo_events.event_strtime <= ?
				AND EWRatendo_events.event_state = 'visible'
			". ($thread ? 'AND EWRatendo_events.thread_id != "0"' : '') ."
			". ($nodeid ? 'AND xf_thread.node_id = "'.$nodeid.'"' : '') ."
			ORDER BY EWRatendo_events.event_strtime ASC
		", array(XenForo_Application::$time, strtotime($range))))
		{
			return false;
		}

		foreach ($events AS &$event)
		{
			$event = $this->formatDates($event);
		}

		return $events;
	}

	public function getEventById($eventID)
	{
		if (!$event = $this->_getDb()->fetchRow("
			SELECT *
				FROM EWRatendo_events
			WHERE event_id = ?
		", $eventID))
		{
			return false;
		}

		$event = $this->formatDates($event);
		$event = $this->formatEvent($event);
		$event = $this->formatMaps($event);

		return $event;
	}

	public function getEventsByIDs($eventIDs)
	{
		if (!$events = $this->fetchAllKeyed("
			SELECT *
				FROM EWRatendo_events
			WHERE event_id IN (" . $this->_getDb()->quote($eventIDs) . ")
		", 'event_id'))
		{
			return array();
		}

		foreach ($events AS &$event)
		{
			$event = $this->formatDates($event);
		}

        return $events;
	}

	public function getEventByThread($threadID)
	{
		if (!$event = $this->_getDb()->fetchRow("
			SELECT *
				FROM EWRatendo_events
			WHERE thread_id = ?
			ORDER BY event_strtime DESC
		", $threadID))
		{
			return false;
		}

		$event = $this->formatDates($event);
		$event = $this->formatEvent($event);
		$event = $this->formatMaps($event);

		return $event;
	}

	public function formatDates($event)
	{
		$options = XenForo_Application::get('options');
		$dateformat = $options->EWRatendo_dateformat;
		$timeformat = $options->EWRatendo_timeformat;

		$strtime = new DateTime(date('r', $event['event_strtime']));
		$strtime->setTimezone(new DateTimeZone($event['event_timezone']));
		$event['formatted_strtime'] = $strtime->format($dateformat.' '.$timeformat);
		$event['formatted_strshort'] = $strtime->format($timeformat);
		$event['formatted_timezone'] = $strtime->format('P T');
		$event['event_date'] = $strtime->format('Y-m-d');	
		$event['event_time'] = XenForo_Application::get('options')->EWRatendo_24hour ? $strtime->format('H') : $strtime->format('h');
		$event['event_mins'] = $strtime->format('i');
		$event['event_ampm'] = $strtime->format('A');
		$event['key'] = $strtime->format($dateformat);

		$endtime = new DateTime(date('r', $event['event_endtime']));
		$endtime->setTimezone(new DateTimeZone($event['event_timezone']));
		$event['formatted_endtime'] = $endtime->format($dateformat.' '.$timeformat);
		$event['event_length'] = ($event['event_endtime'] - $event['event_strtime']) / 3600;

		return $event;
	}

	public function formatEvent($event)
	{
		$perms = $this->getModelFromCache('EWRatendo_Model_Perms')->getPermissions();

		if ($event['event_endtime'] < XenForo_Application::$time)
		{
			$event['nowPast'] = true;
		}

		if ($event['user_id'] == XenForo_Visitor::getUserId() || $perms['mod'])
		{
			$event['canEdit'] = true;
		}

		return $event;
	}

	public function formatMaps($event)
	{
		$url = XenForo_Application::get('options')->EWRatendo_geoLocationUrl;
		if (strpos($url, '{location}') === false)
		{
			$url = 'http://maps.google.com/maps?q={location}&output=embed';
		}
		
		$location = $event['event_address'];
		$location .= (!empty($event['event_citystate']) ? ','.$event['event_citystate'] : '');
		$location .= (!empty($event['event_zipcode']) ? ','.$event['event_zipcode'] : '');
		
		$event['location'] = str_replace('{location}', urlencode($location), $url);

		return $event;
	}

	public function updateEvent($input)
	{
		$input['event_mins'] = str_pad($input['event_mins'], 2, "0", STR_PAD_LEFT);
		$start = $input['event_date']." ".$input['event_time'].":".$input['event_mins']." ".$input['event_ampm']." ".$input['event_timezone'];
		$expire = $input['event_recur_expire']." ".$input['event_time'].":".$input['event_mins']." ".$input['event_ampm']." ".$input['event_timezone'];
		
		$input['event_strtime'] = strtotime($start);
		$input['event_recur_expire'] = strtotime($expire);
		$input['event_recur_units'] = $input['event_recur'] ? $input['event_recur_units'] : 'none';
		$input['event_recur_expire'] = $input['event_expire'] ? $input['event_recur_expire'] : '0';
		$input['event_endtime'] = $input['event_strtime'] + ($input['event_length'] * 3600);

		$threadDate = new DateTime(date('r', $input['event_strtime']));
		$threadDate->setTimezone(new DateTimeZone($input['event_timezone']));
		$threadDate = $threadDate->format(XenForo_Application::get('options')->EWRatendo_dateformat);
		$threadCity = $input['event_citystate'] ? $input['event_citystate'] : $input['event_venue'];

		$dCount = strlen($threadDate);
		$cCount = strlen($threadCity);
		$input['event_title'] = XenForo_Helper_String::wholeWordTrim($input['event_title'], 90 - ($dCount + $cCount));

		$input['thread_title'] = XenForo_Application::get('options')->EWRatendo_threadformat;
		$input['thread_title'] = str_ireplace('{date}', $threadDate, $input['thread_title']);
		$input['thread_title'] = str_ireplace('{location}', $threadCity, $input['thread_title']);
		$input['thread_title'] = str_ireplace('{event}', $input['event_title'], $input['thread_title']);

		$dw = XenForo_DataWriter::create('EWRatendo_DataWriter_Events');
		if (!empty($input['event_id']) && $event = $this->getEventById($input['event_id']))
		{
			$dw->setExistingData($event);
		}
		$dw->bulkSet(array(
			'event_title' => $input['event_title'],
			'event_venue' => $input['event_venue'],
			'event_address' => $input['event_address'],
			'event_citystate' => $input['event_citystate'],
			'event_zipcode' => $input['event_zipcode'],
			'event_strtime' => $input['event_strtime'],
			'event_endtime' => $input['event_endtime'],
			'event_timezone' => $input['event_timezone'],
			'event_rsvp' => $input['event_rsvp'],
			'event_recur_count' => $input['event_recur_count'],
			'event_recur_units' => $input['event_recur_units'],
			'event_recur_expire' => $input['event_recur_expire'],
			'event_state' => empty($input['bypass']) ? 'moderated' : 'visible',
		));
		if (empty($input['create_thread']))
		{
			$dw->set('event_description', XenForo_Helper_String::autoLinkBbCode($input['event_description']));
		}
		$dw->save();
		$input['event_id'] = $dw->get('event_id');
		$input['thread_id'] = $dw->get('thread_id');

		if (!empty($input['thread_id']))
		{
			$this->getModelFromCache('EWRatendo_Model_Threads')->updateThread($input);
		}
		elseif (!empty($input['create_thread']))
		{
			$input['user_id'] = $dw->get('user_id');
			$input['username'] = $dw->get('username');
			$input['event_id'] = $dw->get('event_id');
			$input['thread_id'] = $this->getModelFromCache('EWRatendo_Model_Threads')->buildThread($input);
		}

		$this->getModelFromCache('EWRatendo_Model_Recurs')->buildRecursByEvent($input);

		return $input;
	}

	public function deleteEvent($input)
	{
		$this->_getDb()->query("
			DELETE FROM EWRatendo_rsvps
			WHERE event_id = ?
		", $input['event_id']);

		$this->_getDb()->query("
			DELETE FROM EWRatendo_recurs
			WHERE event_id = ?
		", $input['event_id']);

		$dw = XenForo_DataWriter::create('EWRatendo_DataWriter_Events');
		$dw->setExistingData($input);
		$input['thread_id'] = $dw->get('thread_id');
		$dw->delete();

		if ($input['thread_id'])
		{
			$this->getModelFromCache('EWRatendo_Model_Threads')->closeThread($input['thread_id']);
		}

		return true;
	}
}