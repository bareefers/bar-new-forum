<?php

class EWRutiles_Model_Perms extends XenForo_Model
{
	public function getPermissions(array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		$perms['vote'] = (XenForo_Permission::hasPermission($viewingUser['permissions'], 'EWRutiles', 'canVote') ? true : false);
		$perms['review'] = (XenForo_Permission::hasPermission($viewingUser['permissions'], 'EWRutiles', 'canReview') ? true : false);
		$perms['admin'] = (XenForo_Permission::hasPermission($viewingUser['permissions'], 'EWRutiles', 'canAdmin') ? true : false);
		
		$perms['username'] = XenForo_Permission::hasPermission($viewingUser['permissions'], 'EWRutiles', 'canUsername');

		return $perms;
	}
}