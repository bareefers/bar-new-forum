<?php

class EWRporta2_DataWriter_Features extends XenForo_DataWriter
{
	protected $_existingDataErrorPhrase = 'porta2_requested_feature_not_found';

	protected function _getFields()
	{
		return array(
			'EWRporta2_features' => array(
				'thread_id'			=> array('type' => self::TYPE_UINT, 'required' => true),
				'feature_date'		=> array('type' => self::TYPE_UINT, 'required' => true),
				'feature_time'		=> array('type' => self::TYPE_UINT, 'required' => true),
				'feature_title'		=> array('type' => self::TYPE_STRING, 'default' => ''),
				'feature_excerpt'	=> array('type' => self::TYPE_STRING, 'default' => ''),
				'feature_custom'	=> array('type' => self::TYPE_UINT, 'default' => 0, 'verification' => array('$this', '_verifyCustom')),
				'feature_exclude'	=> array('type' => self::TYPE_UINT, 'default' => 0),
			)
		);
	}

	protected function _getExistingData($data)
	{
		if (!$threadID = $this->_getExistingPrimaryKey($data, 'thread_id'))
		{
			return false;
		}

		return array('EWRporta2_features' => $this->getModelFromCache('EWRporta2_Model_Features')->getFeatureByThreadId($threadID));
	}

	protected function _getUpdateCondition($tableName)
	{
		return 'thread_id = ' . $this->_db->quote($this->getExisting('thread_id'));
	}

	protected function _verifyCustom($custom)
	{
		$excerpt = $this->get('feature_excerpt');
	
		if ($custom && empty($excerpt))
		{
			$this->error(new XenForo_Phrase('porta2_excerpt_fail'), 'feature_excerpt');
			return false;
		}

		return true;
	}
}