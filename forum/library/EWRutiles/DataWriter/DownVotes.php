<?php

class EWRutiles_DataWriter_DownVotes extends XenForo_DataWriter
{
	protected $_existingDataErrorPhrase = 'requested_page_not_found';

	protected function _getFields()
	{
		return array(
			'EWRutiles_downvotes' => array(
				'thread_id'		=> array('type' => self::TYPE_UINT, 'required' => true),
				'user_id'		=> array('type' => self::TYPE_UINT, 'required' => true),
				'vote_id'		=> array('type' => self::TYPE_UINT, 'autoIncrement' => true),
				'vote_date'		=> array('type' => self::TYPE_UINT, 'required' => true),
			)
		);
	}

	protected function _getExistingData($data)
	{
		if (!$voteID = $this->_getExistingPrimaryKey($data, 'vote_id'))
		{
			return false;
		}

		return array('EWRutiles_downvotes' => $this->getModelFromCache('EWRutiles_Model_DownVotes')->getVoteByID($voteID));
	}

	protected function _getUpdateCondition($tableName)
	{
		return 'vote_id = ' . $this->_db->quote($this->getExisting('vote_id'));
	}

	protected function _preSave()
	{
		$visitor = XenForo_Visitor::getInstance();
		$this->set('user_id', $visitor['user_id']);
		$this->set('vote_date', XenForo_Application::$time);
	}
}