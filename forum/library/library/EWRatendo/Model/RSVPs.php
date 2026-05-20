<?php

class EWRatendo_Model_RSVPs extends XenForo_Model
{
	public function getRSVPById($rsvpID)
	{
		if (!$rsvp = $this->_getDb()->fetchRow("SELECT * FROM EWRatendo_rsvps WHERE rsvp_id = ?", $rsvpID))
		{
			return false;
		}

		return $rsvp;
	}

	public function getRSVPsByEvent(&$event)
	{
		$rsvps = array('yes' => array(), 'maybe' => array(), 'no' => array(), 'user');
		$event['event_rsvps'] = 0;
		$event['event_guests'] = 0;

		if (!$guests = $this->_getDb()->fetchAll("
			SELECT EWRatendo_rsvps.*, xf_user.*
				FROM EWRatendo_rsvps
				LEFT JOIN xf_user ON (xf_user.user_id = EWRatendo_rsvps.user_id)
			WHERE event_id = ?
			ORDER BY xf_user.username
		", $event['event_id']))
		{
			return false;
		}

		foreach ($guests AS $guest)
		{
			switch ($guest['rsvp_state'])
			{
				case "yes":		$event['event_guests'] += $guest['rsvp_guests'];
								$event['event_rsvps']++;
								$rsvps['yes'][] = $guest;		break;
				case "maybe":	$rsvps['maybe'][] = $guest;		break;
				case "no":		$rsvps['no'][] = $guest;		break;
			}

			if ($guest['user_id'] == XenForo_Visitor::getUserId())
			{
				$rsvps['user'] = $guest;
			}
		}

		return $rsvps;
	}

	public function updateRSVP($input)
	{
		$dw = XenForo_DataWriter::create('EWRatendo_DataWriter_RSVPs');
		if (!empty($input['rsvp_id']) && $rsvp = $this->getRSVPById($input['rsvp_id']))
		{
			$dw->setExistingData($rsvp);
		}
		$dw->bulkSet(array(
			'event_id' => $input['event_id'],
			'rsvp_state' => $input['rsvp_state'],
			'rsvp_guests' => $input['rsvp_guests'],
			'rsvp_message' => $input['message'],
		));
		$dw->save();

		return true;
	}

	public function unloadRSVPs($input)
	{
		$this->_getDb()->query("
			DELETE FROM EWRatendo_rsvps
			WHERE event_id = ?
		", $input['event_id']);

		return $input;
	}
}