<?php

class EWRporta2_Listener_NavTabs
{
	public static function listen(array &$extraTabs, $selectedTabId)
	{
		$permsModel = XenForo_Model::create('EWRporta2_Model_Perms');
		$perms = $permsModel->getPermissions();
			
		$extraTabs['articles'] = array(
			'title' => new XenForo_Phrase('porta2_home'),
			'href' => XenForo_Link::buildPublicLink('full:articles'),
			'position' => 'home',
			'linksTemplate' => 'EWRporta2_Navtabs',
			'perms' => $perms,
		);
	}
}