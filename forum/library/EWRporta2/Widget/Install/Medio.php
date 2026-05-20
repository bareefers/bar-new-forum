<?php

class EWRporta2_Widget_Install_Medio
{
	public static function installCode($existingWidget, $widgetData)
	{
		if (!$existingWidget)
		{
			$addonModel = XenForo_Model::create('XenForo_Model_AddOn');
		
			if (!$addon = $addonModel->getAddOnById('EWRmedio'))
			{
				throw new XenForo_Exception('Unable to install Medio widget; missing prerequisite Addon.', true);
			}
			
			if ($addon['version_id'] < 60)
			{
				throw new XenForo_Exception('Unable to install Medio widget; prerequisite Addon is out of date.', true);
			}
		}
	}
}