<?php

class EWRatendo_DataWriter_Rsvps extends XenForo_DataWriter
{
	protected $_existingDataErrorPhrase = 'requested_page_not_found';

	protected function _getFields()
	{
		return array(
			'EWRatendo_rsvps' => array(
				'event_id'			=> array('type' => self::TYPE_UINT, 'required' => true),
				'user_id'			=> array('type' => self::TYPE_UINT, 'required' => true),
				'rsvp_id'			=> array('type' => self::TYPE_UINT, 'autoIncrement' => true),
				'rsvp_state'		=> array('type' => self::TYPE_STRING, 'required' => true, 'default' => 'no',
					'allowedValues' => array('yes', 'maybe', 'no')
				),
				'rsvp_guests'		=> array('type' => self::TYPE_UINT, 'required' => true),
				'rsvp_date'			=> array('type' => self::TYPE_UINT, 'required' => true),
				'rsvp_message'	=> array('type' => self::TYPE_STRING, 'required' => false),
			)
		);
	}

	protected function _getExistingData($data)
	{
		if (!$rsvpID = $this->_getExistingPrimaryKey($data, 'rsvp_id'))
		{
			return false;
		}

		return array('EWRatendo_rsvps' => $this->getModelFromCache('EWRatendo_Model_RSVPs')->getRSVPByID($rsvpID));
	}

	protected function _getUpdateCondition($tableName)
	{
		return 'rsvp_id = ' . $this->_db->quote($this->getExisting('rsvp_id'));
	}

	protected function _preSave()
	{
		if (!$this->_existingData)
		{
			$visitor = XenForo_Visitor::getInstance();
			$this->set('user_id', $visitor['user_id']);
			$this->set('rsvp_date', XenForo_Application::$time);
		}
	}
}