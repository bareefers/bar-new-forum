<?php

class EWRporta2_DataWriter_Categories extends XenForo_DataWriter
{
	protected $_existingDataErrorPhrase = 'porta2_requested_category_not_found';

	protected function _getFields()
	{
		return array(
			'EWRporta2_categories' => array(
				'style_id'			=> array('type' => self::TYPE_UINT, 'default' => 0),
				'category_id'		=> array('type' => self::TYPE_UINT, 'autoIncrement' => true),
				'category_name'		=> array('type' => self::TYPE_STRING, 'required' => true),
				'category_desc'		=> array('type' => self::TYPE_STRING, 'default' => ''),
				'category_type'		=> array('type' => self::TYPE_STRING, 'required' => true, 'default' => 'tag',
						'allowedValues' => array('category', 'tag')
				),
			)
		);
	}

	protected function _getExistingData($data)
	{
		if (!$categoryID = $this->_getExistingPrimaryKey($data, 'category_id'))
		{
			return false;
		}

		return array('EWRporta2_categories' => $this->getModelFromCache('EWRporta2_Model_Categories')->getCategoryById($categoryID));
	}

	protected function _getUpdateCondition($tableName)
	{
		return 'category_id = ' . $this->_db->quote($this->getExisting('category_id'));
	}
	
	protected function _postDelete()
	{
		$db = $this->_db;
		$db->delete('EWRporta2_catlinks', 'category_id = ' . $db->quote($this->get('category_id')));
	}
}