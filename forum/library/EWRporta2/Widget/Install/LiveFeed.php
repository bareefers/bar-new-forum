<?php

class EWRporta2_Widget_Install_LiveFeed
{
	public static function installCode($existingWidget, $widgetData)
	{
		if (!$existingWidget)
		{
			$addonModel = XenForo_Model::create('XenForo_Model_AddOn');
		
			if (!$addon = $addonModel->getAddOnById('LiveFeed'))
			{
				throw new XenForo_Exception('Unable to install LiveFeed widget; missing prerequisite Addon.', true);
			}
			
			if ($addon['version_id'] < 27)
			{
				throw new XenForo_Exception('Unable to install LiveFeed widget; prerequisite Addon is out of date.', true);
			}
		}
	}
}