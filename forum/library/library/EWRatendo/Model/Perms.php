<?php

class EWRatendo_Model_Perms extends XenForo_Model
{
	public function getPermissions(array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		$perms['post'] = (XenForo_Permission::hasPermission($viewingUser['permissions'], 'EWRatendo', 'canPost') ? true : false);
		$perms['bypass'] = (XenForo_Permission::hasPermission($viewingUser['permissions'], 'EWRatendo', 'canBypass') ? true : false);
		$perms['rsvp'] = (XenForo_Permission::hasPermission($viewingUser['permissions'], 'EWRatendo', 'canRSVP') ? true : false);
		$perms['mod'] = (XenForo_Permission::hasPermission($viewingUser['permissions'], 'EWRatendo', 'canMod') ? true : false);

		return $perms;
	}
}