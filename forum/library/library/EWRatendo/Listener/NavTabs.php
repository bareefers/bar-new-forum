<?php

class EWRatendo_Listener_NavTabs
{
	public static function listen(array &$extraTabs, $selectedTabId)
	{
		$permsModel = XenForo_Model::create('EWRatendo_Model_Perms');
		$perms = $permsModel->getPermissions();

		$extraTabs['events'] = array(
			'title' => new XenForo_Phrase('events'),
			'href' => XenForo_Link::buildPublicLink('full:events'),
			'position' => 'middle',
			'linksTemplate' => 'EWRatendo_Navtabs',
			'perms' => $perms,
		);
	}
}