<?php

class EWRporta2_Model_Perms extends XenForo_Model
{
	public function getPermissions(array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		$perms['admin'] = (XenForo_Permission::hasPermission($viewingUser['permissions'], 'EWRporta2', 'canAdmin') ? true : false);
		$perms['moderate'] = (XenForo_Permission::hasPermission($viewingUser['permissions'], 'EWRporta2', 'canModerate') ? true : false);
		$perms['promote'] = (XenForo_Permission::hasPermission($viewingUser['permissions'], 'EWRporta2', 'canPromote') ? true : false);
		$perms['tag'] = (XenForo_Permission::hasPermission($viewingUser['permissions'], 'EWRporta2', 'canTag') ? true : false);
		$perms['tags'] = (XenForo_Permission::hasPermission($viewingUser['permissions'], 'EWRporta2', 'canTags') ? true : false);
		$perms['filter'] = (XenForo_Permission::hasPermission($viewingUser['permissions'], 'EWRporta2', 'canFilter') ? true : false);
		$perms['arrange'] = (XenForo_Permission::hasPermission($viewingUser['permissions'], 'EWRporta2', 'canArrange') ? true : false);

		return $perms;
	}
}