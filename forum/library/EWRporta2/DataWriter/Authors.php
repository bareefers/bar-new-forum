<?php

class EWRporta2_DataWriter_Authors extends XenForo_DataWriter
{
	protected $_existingDataErrorPhrase = 'porta2_requested_author_not_found';

	protected function _getFields()
	{
		return array(
			'EWRporta2_authors' => array(
				'user_id'			=> array('type' => self::TYPE_UINT, 'required' => true),
				'author_time'		=> array('type' => self::TYPE_UINT, 'default' => 0),
				'author_name'		=> array('type' => self::TYPE_STRING, 'default' => ''),
				'author_byline'		=> array('type' => self::TYPE_STRING, 'default' => ''),
				'author_status'		=> array('type' => self::TYPE_STRING, 'default' => ''),
				'author_order'		=> array('type' => self::TYPE_UINT, 'default' => 0)
			)
		);
	}

	protected function _getExistingData($data)
	{
		if (!$authorID = $this->_getExistingPrimaryKey($data, 'user_id'))
		{
			return false;
		}

		return array('EWRporta2_authors' => $this->getModelFromCache('EWRporta2_Model_Authors')->getAuthorById($authorID));
	}

	protected function _getUpdateCondition($tableName)
	{
		return 'user_id = ' . $this->_db->quote($this->getExisting('user_id'));
	}
}