<?php

class EWRporta2_DataWriter_User extends XFCP_EWRporta2_DataWriter_User
{
	protected function _postDelete()
	{
		$response = parent::_postDelete();
		
		$db = $this->_db;
		$db->delete('EWRporta2_settings', 'user_id = ' . $db->quote($this->get('user_id')));

		return $response;
	}
}