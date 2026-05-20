<?php

class EWRutiles_DataWriter_UserNames extends XenForo_DataWriter
{
	protected $_existingDataErrorPhrase = 'requested_page_not_found';

	protected function _getFields()
	{
		return array(
			'EWRutiles_usernames' => array(
				'username_id'		=> array('type' => self::TYPE_UINT, 'autoIncrement' => true),
				'user_id'			=> array('type' => self::TYPE_UINT, 'required' => true),
				'username_date'		=> array('type' => self::TYPE_UINT, 'required' => true),
				'username_old'		=> array('type' => self::TYPE_STRING, 'required' => true),
				'username_new'		=> array('type' => self::TYPE_STRING, 'required' => true),
				'username_self'		=> array('type' => self::TYPE_UINT, 'required' => true),
			)
		);
	}

	protected function _getExistingData($data)
	{
		if (!$usernameID = $this->_getExistingPrimaryKey($data, 'username_id'))
		{
			return false;
		}

		return array('EWRutiles_usernames' => $this->getModelFromCache('EWRutiles_Model_UserNames')->getUserNameByID($usernameID));
	}

	protected function _getUpdateCondition($tableName)
	{
		return 'username_id = ' . $this->_db->quote($this->getExisting('username_id'));
	}

	protected function _preSave()
	{
		$visitor = XenForo_Visitor::getInstance();
		$this->set('username_date', XenForo_Application::$time);
	}
}