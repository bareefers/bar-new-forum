<?php

class EWRatendo_DataWriter_Events extends XenForo_DataWriter
{
	protected $_existingDataErrorPhrase = 'requested_page_not_found';

	protected function _getFields()
	{
		return array(
			'EWRatendo_events' => array(
				'thread_id'				=> array('type' => self::TYPE_UINT, 'required' => false, 'default' => '0'),
				'user_id'				=> array('type' => self::TYPE_UINT, 'required' => true),
				'username'				=> array('type' => self::TYPE_STRING, 'required' => true),
				'event_id'				=> array('type' => self::TYPE_UINT, 'autoIncrement' => true),
				'event_strtime'			=> array('type' => self::TYPE_UINT, 'required' => true),
				'event_endtime'			=> array('type' => self::TYPE_UINT, 'required' => true),
				'event_timezone'		=> array('type' => self::TYPE_STRING, 'required' => true),
				'event_title'			=> array('type' => self::TYPE_STRING, 'required' => true),
				'event_venue'			=> array('type' => self::TYPE_STRING, 'required' => true),
				'event_address'			=> array('type' => self::TYPE_STRING, 'required' => false),
				'event_citystate'		=> array('type' => self::TYPE_STRING, 'required' => false),
				'event_zipcode'			=> array('type' => self::TYPE_STRING, 'required' => false),
				'event_rsvp'			=> array('type' => self::TYPE_UINT, 'required' => true, 'default' => '1'),
				'event_guests'			=> array('type' => self::TYPE_UINT, 'required' => true, 'default' => '0'),
				'event_recur_count'		=> array('type' => self::TYPE_UINT, 'required' => true, 'default' => '0'),
				'event_recur_units'		=> array('type' => self::TYPE_STRING, 'required' => true, 'default' => 'none',
					'allowedValues' => array('none', 'days', 'weeks', 'months')
				),
				'event_recur_expire'		=> array('type' => self::TYPE_UINT, 'required' => true, 'default' => '0'),
				'event_description'			=> array('type' => self::TYPE_STRING, 'required' => false, 'default' => ''),
				'event_state'		=> array('type' => self::TYPE_STRING, 'required' => true, 'default' => 'visible',
					'allowedValues' => array('visible', 'moderated', 'deleted')
				),
			)
		);
	}

	protected function _getExistingData($data)
	{
		if (!$eventID = $this->_getExistingPrimaryKey($data, 'event_id'))
		{
			return false;
		}

		return array('EWRatendo_events' => $this->getModelFromCache('EWRatendo_Model_Events')->getEventById($eventID));
	}

	protected function _getUpdateCondition($tableName)
	{
		return 'event_id = ' . $this->_db->quote($this->getExisting('event_id'));
	}

	protected function _preSave()
	{
		if (!$this->_existingData && !$this->get('user_id'))
		{
			$visitor = XenForo_Visitor::getInstance();
			$this->set('user_id', $visitor['user_id']);
			$this->set('username', ($visitor['user_id'] ? $visitor['username'] : $_SERVER['REMOTE_ADDR']));
		}
	}

	protected function _postSave()
	{
		$this->_updateModerationQueue();
	}

	protected function _postDelete()
	{
		$this->getModelFromCache('XenForo_Model_ModerationQueue')->deleteFromModerationQueue('event', $this->get('event_id'));
	}

	protected function _updateModerationQueue()
	{
		if (!$this->isChanged('event_state'))
		{
			return;
		}

		if ($this->get('event_state') == 'moderated')
		{
			$this->getModelFromCache('XenForo_Model_ModerationQueue')->insertIntoModerationQueue('event', $this->get('event_id'), XenForo_Application::$time);
		}
		else if ($this->getExisting('event_state') == 'moderated')
		{
			$this->getModelFromCache('XenForo_Model_ModerationQueue')->deleteFromModerationQueue('event', $this->get('event_id'));
		}
	}
}