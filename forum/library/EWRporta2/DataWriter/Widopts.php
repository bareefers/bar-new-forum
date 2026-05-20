<?php

class EWRporta2_DataWriter_Widopts extends XenForo_DataWriter
{
	protected $_existingDataErrorPhrase = 'porta2_requested_widopt_not_found';

	protected function _getFields()
	{
		return array(
			'EWRporta2_widopts' => array(
				'widget_id'					=> array('type' => self::TYPE_STRING, 'required' => true),
				'widopt_id'					=> array('type' => self::TYPE_UINT, 'autoIncrement' => true),
				'widopt_title'				=> array('type' => self::TYPE_STRING, 'required' => true),
				'widopt_values'				=> array('type' => self::TYPE_SERIALIZED, 'default' => 'a:0:{}'),
				'locked'					=> array('type' => self::TYPE_BOOLEAN, 'default' => 0),
				'display'					=> array('type' => self::TYPE_STRING, 'required' => true, 'default' => 'show',
						'allowedValues' => array('show', 'hide')
				),
				'groups'					=> array('type' => self::TYPE_STRING, 'default' => ''),
				'ctime'						=> array('type' => self::TYPE_STRING, 'default' => ''),
				'cdate'						=> array('type' => self::TYPE_UINT, 'default' => 0),
				'cache'						=> array('type' => self::TYPE_SERIALIZED, 'default' => 'a:0:{}'),
			)
		);
	}

	protected function _getExistingData($data)
	{
		if (!$widoptID = $this->_getExistingPrimaryKey($data, 'widopt_id'))
		{
			return false;
		}

		return array('EWRporta2_widopts' => $this->getModelFromCache('EWRporta2_Model_Widopts')->getWidoptById($widoptID));
	}

	protected function _getUpdateCondition($tableName)
	{
		return 'widopt_id = ' . $this->_db->quote($this->getExisting('widopt_id'));
	}
	
	protected function _postDelete()
	{
		$db = $this->_db;
		$db->update('EWRporta2_widlinks', array('widopt_id' => 0), 'widopt_id = ' . $db->quote($this->get('widopt_id')));
	}
}