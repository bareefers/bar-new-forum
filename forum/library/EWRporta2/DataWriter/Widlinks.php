<?php

class EWRporta2_DataWriter_Widlinks extends XenForo_DataWriter
{
	protected $_existingDataErrorPhrase = 'porta2_requested_widlink_not_found';

	protected function _getFields()
	{
		return array(
			'EWRporta2_widlinks' => array(
				'layout_id'					=> array('type' => self::TYPE_STRING, 'required' => true),
				'widget_id'					=> array('type' => self::TYPE_STRING, 'required' => true),
				'widopt_id'					=> array('type' => self::TYPE_STRING, 'required' => false),
				'widlink_id'				=> array('type' => self::TYPE_UINT, 'autoIncrement' => true),
				'widlink_title'				=> array('type' => self::TYPE_STRING, 'required' => true),
				'widlink_position'			=> array('type' => self::TYPE_STRING, 'required' => true, 'default' => 'disabled',
						'allowedValues' => array('sidebar', 'header', 'footer', 'left', 'above', 'below', 'b-left', 'b-right', 'disabled')
				),
				'widlink_order'				=> array('type' => self::TYPE_UINT, 'default' => 0),
			)
		);
	}

	protected function _getExistingData($data)
	{
		if (!$widlinkID = $this->_getExistingPrimaryKey($data, 'widlink_id'))
		{
			return false;
		}

		return array('EWRporta2_widlinks' => $this->getModelFromCache('EWRporta2_Model_Widlinks')->getWidlinkById($widlinkID));
	}

	protected function _getUpdateCondition($tableName)
	{
		return 'widlink_id = ' . $this->_db->quote($this->getExisting('widlink_id'));
	}
}