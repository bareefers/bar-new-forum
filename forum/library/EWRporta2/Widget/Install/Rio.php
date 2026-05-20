<?php

class EWRporta2_Widget_Install_Rio
{
	public static function installCode($existingWidget, $widgetData)
	{
		if (!$existingWidget)
		{
			$addonModel = XenForo_Model::create('XenForo_Model_AddOn');
		
			if (!$addon = $addonModel->getAddOnById('EWRrio'))
			{
				throw new XenForo_Exception('Unable to install Rio widget; missing prerequisite Addon.', true);
			}
			
			if ($addon['version_id'] < 5)
			{
				throw new XenForo_Exception('Unable to install Rio widget; prerequisite Addon is out of date.', true);
			}
		}
	}
}