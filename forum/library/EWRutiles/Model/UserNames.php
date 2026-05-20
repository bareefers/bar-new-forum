<?php

class EWRutiles_Model_UserNames extends XenForo_Model
{
	public function getUserNameByID($usernameID)
	{
		if (!$vote = $this->_getDb()->fetchRow("SELECT * FROM EWRutiles_usernames WHERE username_id = ?", $usernameID))
		{
			return false;
		}

		return $vote;
	}

	public function getUserNamesByIDs($usernameIDs)
	{
		if (!$usernames = $this->fetchAllKeyed("
			SELECT *
				FROM EWRutiles_usernames
			WHERE username_id IN (" . $this->_getDb()->quote($usernameIDs) . ")
		", 'username_id'))
		{
			return array();
		}

        return $usernames;
	}
	
	public function getUserNamesByUserID($userID)
	{
		if (!$usernames = $this->_getDb()->fetchAll("
			SELECT *
				FROM EWRutiles_usernames
			WHERE user_id = ?
			ORDER BY username_date DESC
		", $userID))
		{
			return array();
		}

        return $usernames;
	}
	
	public function getLastChangeByUserID($userID)
	{
        if (!$username = $this->_getDb()->fetchRow("
			SELECT username_date
				FROM EWRutiles_usernames
			WHERE user_id = ?
				AND username_self = 1
			ORDER BY username_date DESC
		", $userID))
		{
			return 0;
		}
		
		return $username['username_date'];
	}
	
	public function insertUserName($userID, $old, $new, $self = 1)
	{
		$dw = XenForo_DataWriter::create('EWRutiles_DataWriter_UserNames');
		$dw->set('user_id', $userID);
		$dw->set('username_old', $old);
		$dw->set('username_new', $new);
		$dw->set('username_self', $self);
		$dw->save();

		$this->getModelFromCache('XenForo_Model_NewsFeed')->publish(
			$userID,
			$old,
			'username_change',
			$dw->get('username_id'),
			'insert'
		);

		return true;
	}
}