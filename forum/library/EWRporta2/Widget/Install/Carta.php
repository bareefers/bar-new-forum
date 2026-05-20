<?php

class EWRporta2_Widget_Install_Carta
{
	public static function installCode($existingWidget, $widgetData)
	{
		if (!$existingWidget)
		{
			$addonModel = XenForo_Model::create('XenForo_Model_AddOn');
		
			if (!$addon = $addonModel->getAddOnById('EWRcarta'))
			{
				throw new XenForo_Exception('Unable to install Carta widget; missing prerequisite Addon.', true);
			}
			
			if ($addon['version_id'] < 40)
			{
				throw new XenForo_Exception('Unable to install Carta widget; prerequisite Addon is out of date.', true);
			}
		}
	}
}