<?php

class EWRatendo_ControllerPublic_Events extends XenForo_ControllerPublic_Abstract
{
	public $perms;
	public $slugs;

	public function actionIndex()
	{
		$options = XenForo_Application::get('options');

		switch ($options->EWRatendo_default)
		{
			case "upcoming":	return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildPublicLink('events/upcoming'));
			case "weekly":		return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildPublicLink('events/weekly'));
			case "monthly":
				default:		return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildPublicLink('events/monthly'));
		}
	}

	public function actionLink()
	{
		if (!$this->perms['admin']) { return $this->responseNoPermission(); }

		$input = $this->_input->filter(array(
			'event' => XenForo_Input::UINT,
			'thread' => XenForo_Input::UINT,
		));

		if (!$input['event'] || !$input['thread'])
		{
			return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildPublicLink('events'));
		}

		$db = XenForo_Application::get('db');
		$db->query("
			UPDATE EWRatendo_events SET thread_id = ? WHERE event_id = ?
		", array($input['thread'], $input['event']));

		return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildPublicLink('events'));
	}

	public function actionUpcoming()
	{
		$viewParams = array(
			'canPost' => $this->perms['post'],
			'events' => $this->getModelFromCache('EWRatendo_Model_Upcoming')->getUpcomingEvents(),
		);

		return $this->responseView('EWRatendo_ViewPublic_Upcoming', 'EWRatendo_Upcoming', $viewParams);
	}

	public function actionHistory()
	{
		$start = $this->_input->filterSingle('page', XenForo_Input::UINT) < 1 ? 1 : $this->_input->filterSingle('page', XenForo_Input::UINT);
		$stop = 50;

		$viewParams = array(
			'canPost' => $this->perms['post'],
			'start' => $start,
			'stop' => $stop,
			'count' => $this->getModelFromCache('EWRatendo_Model_History')->getPastCount(),
			'events' => $this->getModelFromCache('EWRatendo_Model_History')->getPastEvents($start, $stop),
		);

		return $this->responseView('EWRatendo_ViewPublic_EventsHistory', 'EWRatendo_EventsHistory', $viewParams);
	}

	public function actionCreate()
	{
		if (!$this->perms['post']) { return $this->responseNoPermission(); }
		$input['location'] = str_replace('{location}', '', XenForo_Application::get('options')->EWRatendo_geoLocationUrl);

		if ($this->_request->isPost())
		{
			$input = $this->_input->filter(array(
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
				'event_node' => XenForo_Input::UINT,
				'create_thread' => XenForo_Input::UINT,
				'submit' => XenForo_Input::STRING,
			));

			if ($input['event_description'] = $this->getHelper('Editor')->getMessageText('event_description', $this->_input))
			{
				$input['bypass'] = $this->perms['bypass'];
				$event = $this->getModelFromCache('EWRatendo_Model_Events')->updateEvent($input);
				return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildPublicLink('events', $event));
			}
			else
			{
				throw new XenForo_Exception(new XenForo_Phrase('please_enter_valid_message'), true);
			}

			$input = $this->getModelFromCache('EWRatendo_Model_Events')->formatMaps($input);
		}
		else
		{
			$visitor = XenForo_Visitor::getInstance();
			$datetime = new DateTime(date('r', XenForo_Application::$time + 3600));
			$datetime->setTimezone(new DateTimeZone($visitor['timezone']));
			$input['event_date'] = $datetime->format('Y-m-d');
			$input['event_time'] = XenForo_Application::get('options')->EWRatendo_24hour ? $datetime->format('H') : $datetime->format('h');
			$input['event_ampm'] = $datetime->format('A');
			
			$expire = new DateTime(date('r', XenForo_Application::$time + 31536000));
			$expire->setTimezone(new DateTimeZone($visitor['timezone']));
			$input['event_recur_units'] = 'weeks';
			$input['event_recur_expire'] = $expire->format('Y-m-d');
			$input['event_expire'] = 1;
		}

		$forums = array();
		$selected = !empty($this->slugs[1]) ? $this->slugs[1] : false;
		$selected = isset($input['create_thread']) && empty($input['create_thread']) ? false : $selected;
		
		$input['event_rsvp'] = isset($input['event_rsvp']) && empty($input['event_rsvp']) ? false : true;

		foreach (XenForo_Application::get('options')->EWRatendo_eventforums AS $forum)
		{
			$forum = $this->getModelFromCache('XenForo_Model_Forum')->getForumById($forum);

			if ($forum && $this->getModelFromCache('XenForo_Model_Forum')->canPostThreadInForum($forum))
			{
				$forums[] = $forum;
			}
		}

		$viewParams = array(
			'forums' => $forums,
			'selected' => $selected,
			'input' => $input,
			'timeZones'	=> XenForo_Helper_TimeZone::getTimeZones(),
		);

		return $this->responseView('EWRatendo_ViewPublic_EventsCreate', 'EWRatendo_EventsCreate', $viewParams);
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
		$this->slugs = explode('/', $this->_routeMatch->getMinorSection());
	}
}