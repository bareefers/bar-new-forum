<?php

class EWRatendo_ControllerPublic_Events_Event extends XenForo_ControllerPublic_Abstract
{
	public $perms;

	public function actionIndex()
	{
		$eventID = $this->_input->filterSingle('event_id', XenForo_Input::UINT);

		if (!$event = $this->getModelFromCache('EWRatendo_Model_Events')->getEventById($eventID))
		{
			return $this->responseRedirect(XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT, XenForo_Link::buildPublicLink('events'));
		}

		if ($this->_request->isPost())
		{
			$input = $this->_input->filter(array(
				'rsvp_id' => XenForo_Input::UINT,
				'rsvp_state' => XenForo_Input::STRING,
				'rsvp_guests' => XenForo_Input::UINT,
			));
			$input['event_id'] = $event['event_id'];
			$input['message'] = $this->getHelper('Editor')->getMessageText('message', $this->_input);

			$this->getModelFromCache('EWRatendo_Model_RSVPs')->updateRSVP($input);
		}

		if ($thread = $this->getModelFromCache('XenForo_Model_Thread')->getThreadById($event['thread_id']))
		{
			if ($thread['discussion_state'] == 'visible')
			{
				return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildPublicLink('threads', $event));
			}
		}

		$this->canonicalizeRequestUrl(XenForo_Link::buildPublicLink('events', $event));

		$viewParams = array(
			'canRSVP' => $this->perms['rsvp'],
			'rsvps' => $this->getModelFromCache('EWRatendo_Model_RSVPs')->getRSVPsByEvent($event),
			'event' => $event,
		);

		return $this->responseView('EWRatendo_ViewPublic_EventsView', 'EWRatendo_EventsView', $viewParams);
	}

	public function actionEdit()
	{
		$eventID = $this->_input->filterSingle('event_id', XenForo_Input::UINT);

		if (!$event = $this->getModelFromCache('EWRatendo_Model_Events')->getEventById($eventID))
		{
			return $this->responseRedirect(XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT, XenForo_Link::buildPublicLink('events'));
		}

		if (!$this->perms['mod'] && $event['user_id'] != XenForo_Visitor::getUserId()) { return $this->responseNoPermission(); }

		if ($this->_request->isPost())
		{
			$input = $this->_input->filter(array(
				'prefix_id' => XenForo_Input::UINT,
				'event_title' => XenForo_Input::STRING,
				'event_venue' => XenForo_Input::STRING,
				'event_address' => XenForo_Input::STRING,
				'event_citystate' => XenForo_Input::STRING,
				'event_zipcode' => XenForo_Input::STRING,
				'event_date' => XenForo_Input::STRING,
				'event_time' => XenForo_Input::UINT,
				'event_mins' => XenForo_Input::UINT,
				'event_ampm' => XenForo_Input::STRING,
				'event_length' => XenForo_Input::UINT,
				'event_timezone' => XenForo_Input::STRING,
				'event_rsvp' => XenForo_Input::UINT,
				'event_recur' => XenForo_Input::UINT,
				'event_recur_count' => XenForo_Input::UINT,
				'event_recur_units' => XenForo_Input::STRING,
				'event_expire' => XenForo_Input::UINT,
				'event_recur_expire' => XenForo_Input::STRING,
				'submit' => XenForo_Input::STRING,
			));
			$input['event_id'] = $event['event_id'];
			$input['event_description'] = $this->getHelper('Editor')->getMessageText('event_description', $this->_input);
			$input['bypass'] = $this->perms['bypass'];

			$event = $this->getModelFromCache('EWRatendo_Model_Events')->updateEvent($input);
			return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildPublicLink('events', $event));
		}

		if ($event['event_recur_count'] && $event['event_recur_units'] != 'none')
		{
			$event['recur_check'] = true;

			if ($event['event_recur_expire'])
			{
				$expire = new DateTime(date('r', $event['event_recur_expire']));
				$expire->setTimezone(new DateTimeZone($event['event_timezone']));
				$event['event_expire'] = $expire->format('Y-m-d');
			}
		}
		else
		{
			$event['event_recur_count'] = '1';
			$event['event_recur_units'] = 'weeks';
		}
		
		if (!empty($event['thread_id']))
		{
			$ftpHelper = $this->getHelper('ForumThreadPost');
			list($thread, $forum) = $ftpHelper->assertThreadValidAndViewable($event['thread_id']);
			$prefixes = $this->getModelFromCache('XenForo_Model_ThreadPrefix')->getUsablePrefixesInForums($forum['node_id']);
		}

		$viewParams = array(
			'event' => $event,
			'thread' => !empty($thread) ? $thread : array(),
			'prefixes' => !empty($prefixes) ? $prefixes : array(),
			'timeZones'	=> XenForo_Helper_TimeZone::getTimeZones(),
		);

		return $this->responseView('EWRatendo_ViewPublic_EventsEdit', 'EWRatendo_EventsEdit', $viewParams);
	}

	public function actionDelete()
	{
		$eventID = $this->_input->filterSingle('event_id', XenForo_Input::UINT);

		if ($event = $this->getModelFromCache('EWRatendo_Model_Events')->getEventById($eventID))
		{
			if (!$this->perms['mod'] && $event['user_id'] != XenForo_Visitor::getUserId()) { return $this->responseNoPermission(); }

			if ($this->_request->isPost())
			{
				$this->getModelFromCache('EWRatendo_Model_Events')->deleteEvent($event);
			}
			else
			{
				return $this->responseView('EWRatendo_ViewPublic_EventsDelete', 'EWRatendo_EventsDelete', array('event' => $event));
			}
		}

		return $this->responseRedirect(XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT, XenForo_Link::buildPublicLink('events'));
	}

	public function actionUnload()
	{
		$eventID = $this->_input->filterSingle('event_id', XenForo_Input::UINT);

		if ($event = $this->getModelFromCache('EWRatendo_Model_Events')->getEventById($eventID))
		{
			if (!$this->perms['mod'] && $event['user_id'] != XenForo_Visitor::getUserId()) { return $this->responseNoPermission(); }

			if ($this->_request->isPost())
			{
				$event = $this->getModelFromCache('EWRatendo_Model_RSVPs')->unloadRSVPs($event);
				return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildPublicLink('events', $event));
			}
			else
			{
				return $this->responseView('EWRatendo_ViewPublic_EventsUnload', 'EWRatendo_EventsUnload', array('event' => $event));
			}
		}

		return $this->responseRedirect(XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT, XenForo_Link::buildPublicLink('events'));
	}

	public function actionRsvp()
	{
		$this->_assertPostOnly();

		if (!$this->perms['rsvp']) { return $this->responseNoPermission(); }

		$eventID = $this->_input->filterSingle('event_id', XenForo_Input::UINT);

		if (!$event = $this->getModelFromCache('EWRatendo_Model_Events')->getEventById($eventID))
		{
			return $this->responseRedirect(XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT, XenForo_Link::buildPublicLink('events'));
		}

		$input = $this->_input->filter(array(
			'rsvp_id' => XenForo_Input::UINT,
			'rsvp_state' => XenForo_Input::STRING,
			'rsvp_guests' => XenForo_Input::UINT,
		));
		$input['event_id'] = $event['event_id'];
		$input['message'] = $this->getHelper('Editor')->getMessageText('message', $this->_input);

		$this->getModelFromCache('EWRatendo_Model_RSVPs')->updateRSVP($input);

		return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildPublicLink('events', $event));
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