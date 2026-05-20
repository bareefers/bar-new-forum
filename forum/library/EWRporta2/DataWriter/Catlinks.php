<?php

class EWRporta2_DataWriter_Catlinks extends XenForo_DataWriter
{
	protected $_existingDataErrorPhrase = 'porta2_requested_catlink_not_found';

	protected function _getFields()
	{
		return array(
			'EWRporta2_catlinks' => array(
				'thread_id'			=> array('type' => self::TYPE_UINT, 'required' => true),
				'user_id'			=> array('type' => self::TYPE_UINT, 'default' => 0),
				'category_id'		=> array('type' => self::TYPE_UINT, 'required' => true),
				'catlink_id'		=> array('type' => self::TYPE_UINT, 'autoIncrement' => true),
			)
		);
	}

	protected function _getExistingData($data)
	{
		if (!$catlinkID = $this->_getExistingPrimaryKey($data, 'catlink_id'))
		{
			return false;
		}

		return array('EWRporta2_catlinks' => $this->getModelFromCache('EWRporta2_Model_Categories')->getCatlinkById($catlinkID));
	}

	protected function _getUpdateCondition($tableName)
	{
		return 'catlink_id = ' . $this->_db->quote($this->getExisting('catlink_id'));
	}

	protected function _preSave()
	{
		if (!$this->_existingData)
		{
			$this->set('user_id', XenForo_Visitor::getUserId());
		}
	}
}