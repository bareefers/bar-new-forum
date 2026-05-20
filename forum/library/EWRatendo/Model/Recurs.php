<?php

class EWRatendo_Model_Recurs extends XenForo_Model
{
	public function buildRecurs()
	{
		$cutoff = strtotime("+".XenForo_Application::get('options')->EWRatendo_recurrence." months");
		$this->updatePastRecurs();

		$this->_getDb()->query("TRUNCATE TABLE EWRatendo_recurs");

		$events = $this->_getDb()->fetchAll("
			SELECT *
				FROM EWRatendo_events
			WHERE event_recur_count != 0
				AND event_recur_units != 'none'
		");

		foreach ($events AS $event)
		{
			$expire = $cutoff;

			if ($event['event_recur_expire'])
			{
				$expire = min($cutoff, $event['event_recur_expire']);
			}

			$this->updateNewRecurs($event, $expire);
		}

		return true;
	}

	public function buildRecursByEvent($event)
	{
		$this->_getDb()->query("
			DELETE FROM EWRatendo_recurs
			WHERE event_id = ?
		", $event['event_id']);

		if ($event['event_recur_count'] && $event['event_recur_units'] != 'none')
		{
			$expire = strtotime('+'.XenForo_Application::get('options')->EWRatendo_recurrence.' months');

			if ($event['event_recur_expire'])
			{
				$expire = min($expire, $event['event_recur_expire']);
			}

			$this->updateNewRecurs($event, $expire);
		}

		return true;
	}

	public function updateNewRecurs($event, $cutoff)
	{
		$recur = '+'.$event['event_recur_count'].' '.$event['event_recur_units'];
		$recurs = array();

		while ($event['event_strtime'] < $cutoff)
		{
			$event['event_strtime'] = strtotime($recur, $event['event_strtime']);
			$event['event_endtime'] = strtotime($recur, $event['event_endtime']);
			$recurs[] = "('".$event['event_id']."','".$event['event_strtime']."','".$event['event_endtime']."')";
		}

		if ($recurs = implode(',', $recurs))
		{
			$this->_getDb()->query("
				INSERT INTO EWRatendo_recurs
					(event_id, event_strtime, event_endtime)
				VALUES ".$recurs);
		}

		return true;
	}

	public function updatePastRecurs()
	{
		$events = $this->_getDb()->fetchAll("
			SELECT *
				FROM EWRatendo_events
			WHERE event_recur_count != 0
				AND event_recur_units != 'none'
				AND event_endtime <= ?
		", XenForo_Application::$time);

		foreach ($events AS $event)
		{
			$dw = XenForo_DataWriter::create('EWRatendo_DataWriter_Events');

			if (XenForo_Application::get('options')->EWRatendo_archiveevents)
			{
				$dw2 = XenForo_DataWriter::create('EWRatendo_DataWriter_Events');
				$dw2->setExistingData($event);
				$dw2->set('thread_id', '0');
				$dw2->set('event_recur_count', '0');
				$dw2->set('event_recur_units', 'none');
				$dw2->save();

				unset($event['event_id']);
			}
			else
			{
				$this->_getDb()->query("
					DELETE FROM EWRatendo_rsvps
					WHERE event_id = ?
				", $event['event_id']);

				$dw->setExistingData($event);
			}

			if ($event['event_recur_expire'] && $event['event_recur_expire'] < (XenForo_Application::$time - 86400))
			{
				$event['event_recur_count'] = 0;
				$event['event_recur_units'] = 'none';
				$event['event_recur_expire'] = 0;

				$dw->bulkSet($event);
				$dw->save();
				continue;
			}

			$recur = '+'.$event['event_recur_count'].' '.$event['event_recur_units'];
			$event['event_strtime'] = strtotime($recur, $event['event_strtime']);
			$event['event_endtime'] = strtotime($recur, $event['event_endtime']);

			$dw->bulkSet($event);
			$dw->save();
			$event['event_id'] = $dw->get('event_id');

			if (!empty($event['thread_id']))
			{
				$strtime = new DateTime(date('r', $event['event_strtime']));
				$strtime->setTimezone(new DateTimeZone($event['event_timezone']));
				$threadDate = $strtime->format(XenForo_Application::get('options')->EWRatendo_dateformat);

				$threadCity = trim(preg_replace('/[\,\d\s-]+$/','', $event['event_citystate']));
				$threadCity = $threadCity ? $threadCity : $event['event_venue'];
				$event['thread_title'] = "[$threadDate] ".$event['event_title']." ($threadCity)";

				$this->getModelFromCache('EWRatendo_Model_Threads')->updateThread($event);
			}
		}

		return true;
	}
}