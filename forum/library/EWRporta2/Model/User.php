<?php

class EWRporta2_Model_User extends XFCP_EWRporta2_Model_User
{
	public function mergeUsers(array $target, array $source)
	{
		$response = parent::mergeUsers($target, $source);
		
		XenForo_Db::beginTransaction();
		
		$this->_getDb()->query("UPDATE EWRporta2_catlinks SET user_id = ? WHERE user_id = ?", array($target['user_id'], $source['user_id']));
		
		$this->_getDb()->query("DELETE FROM EWRporta2_settings WHERE user_id = ?", $source['user_id']);
		$this->_getDb()->query("DELETE FROM EWRporta2_authors WHERE user_id = ?", $source['user_id']);

		XenForo_Db::commit();
		
		return $response;
	}
}