<?php

class EWRutiles_DataWriter_SpamLogs extends XenForo_DataWriter
{
	protected $_existingDataErrorPhrase = 'requested_page_not_found';

	protected function _getFields()
	{
		return array(
			'EWRutiles_spamlogs' => array(
				'spamlog_id'				=> array('type' => self::TYPE_UINT, 'autoIncrement' => true),
				'spamlog_date'				=> array('type' => self::TYPE_UINT, 'required' => true),
				'spamlog_engine'			=> array('type' => self::TYPE_STRING, 'required' => true),
				'spamlog_username'			=> array('type' => self::TYPE_STRING, 'required' => false),
				'spamlog_username_match'	=> array('type' => self::TYPE_UINT, 'required' => true),
				'spamlog_email'				=> array('type' => self::TYPE_STRING, 'required' => false),
				'spamlog_email_match'		=> array('type' => self::TYPE_UINT, 'required' => true),
				'spamlog_ip'				=> array('type' => self::TYPE_STRING, 'required' => false),
				'spamlog_ip_match'			=> array('type' => self::TYPE_UINT, 'required' => true),
				'spamlog_time'				=> array('type' => self::TYPE_UINT, 'required' => true),
			)
		);
	}

	protected function _getExistingData($data)
	{
		if (!$spamID = $this->_getExistingPrimaryKey($data, 'spamlog_id'))
		{
			return false;
		}

		return array('EWRutiles_spamlogs' => $this->getModelFromCache('EWRutiles_Model_SpamLogs')->getSpamLogByID($spamID));
	}

	protected function _getUpdateCondition($tableName)
	{
		return 'spamlog_id = ' . $this->_db->quote($this->getExisting('spamlog_id'));
	}

	protected function _preSave()
	{
		$this->set('spamlog_date', XenForo_Application::$time);
	}
}