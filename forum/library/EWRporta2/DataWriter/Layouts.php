<?php

class EWRporta2_DataWriter_Layouts extends XenForo_DataWriter
{
	protected $_existingDataErrorPhrase = 'porta2_requested_layout_not_found';

	protected function _getFields()
	{
		return array(
			'EWRporta2_layouts' => array(
				'layout_id'					=> array('type' => self::TYPE_STRING, 'required' => true, 'verification' => array('$this', '_verifyLayoutId')),
				'layout_title'				=> array('type' => self::TYPE_STRING, 'required' => true),
				'layout_template'			=> array('type' => self::TYPE_STRING, 'required' => true),
				'layout_eval'				=> array('type' => self::TYPE_STRING, 'default' => ''),
				'layout_priority'			=> array('type' => self::TYPE_UINT, 'required' => true),
				'layout_sidebar'			=> array('type' => self::TYPE_BOOLEAN, 'default' => 0),
				'layout_protected'			=> array('type' => self::TYPE_BOOLEAN, 'default' => 0),
				'active'					=> array('type' => self::TYPE_BOOLEAN, 'default' => 1)
			)
		);
	}

	protected function _getExistingData($data)
	{
		if (!$layoutID = $this->_getExistingPrimaryKey($data, 'layout_id'))
		{
			return false;
		}

		return array('EWRporta2_layouts' => $this->getModelFromCache('EWRporta2_Model_Layouts')->getLayoutById($layoutID));
	}

	protected function _getUpdateCondition($tableName)
	{
		return 'layout_id = ' . $this->_db->quote($this->getExisting('layout_id'));
	}
	
	protected function _verifyLayoutId(&$layoutID)
	{
		if (preg_match('/[^a-zA-Z0-9_]/', $layoutID))
		{
			$this->error(new XenForo_Phrase('please_enter_an_id_using_only_alphanumeric'), 'widget_id');
			return false;
		}

		if ($this->isInsert() || $layoutID != $this->getExisting('layout_id'))
		{
			if ($this->getModelFromCache('EWRporta2_Model_Layouts')->getLayoutById($layoutID))
			{
				$this->error(new XenForo_Phrase('porta2_layout_ids_must_be_unique'), 'layout_id');
				return false;
			}
		}

		return true;
	}
	
	protected function _postSave()
	{
		if ($this->isUpdate() && $this->isChanged('layout_id'))
		{
			$db = $this->_db;
			$updateClause = 'layout_id = ' . $db->quote($this->getExisting('layout_id'));
			$updateValue = array('layout_id' => $this->get('layout_id'));

			$db->update('EWRporta2_widlinks', $updateValue, $updateClause);
		}
	}
	
	protected function _postDelete()
	{
		$db = $this->_db;
		$db->delete('EWRporta2_widlinks', 'layout_id = ' . $db->quote($this->get('layout_id')));
	}
}