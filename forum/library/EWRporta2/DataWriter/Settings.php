<?php

class EWRporta2_DataWriter_Settings extends XenForo_DataWriter
{
	protected $_existingDataErrorPhrase = 'porta2_requested_setting_not_found';

	protected function _getFields()
	{
		return array(
			'EWRporta2_settings' => array(
				'user_id'			=> array('type' => self::TYPE_UINT, 'required' => true),
				'setting_filter'	=> array('type' => self::TYPE_SERIALIZED, 'default' => 'a:0:{}'),
				'setting_arrange'	=> array('type' => self::TYPE_SERIALIZED, 'default' => 'a:0:{}'),
				'setting_options'	=> array('type' => self::TYPE_SERIALIZED, 'default' => 'a:0:{}'),
			)
		);
	}

	protected function _getExistingData($data)
	{
		if (!$settingID = $this->_getExistingPrimaryKey($data, 'user_id'))
		{
			return false;
		}

		return array('EWRporta2_settings' => $this->getModelFromCache('EWRporta2_Model_Settings')->getSettingById($settingID));
	}

	protected function _getUpdateCondition($tableName)
	{
		return 'user_id = ' . $this->_db->quote($this->getExisting('user_id'));
	}
}