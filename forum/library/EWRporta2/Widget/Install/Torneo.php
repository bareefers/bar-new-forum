<?php

class EWRporta2_Widget_Install_Torneo
{
	public static function installCode($existingWidget, $widgetData)
	{
		if (!$existingWidget)
		{
			$addonModel = XenForo_Model::create('XenForo_Model_AddOn');
		
			if (!$addon = $addonModel->getAddOnById('EWRtorneo'))
			{
				throw new XenForo_Exception('Unable to install Torneo widget; missing prerequisite Addon.', true);
			}
			
			if ($addon['version_id'] < 5)
			{
				throw new XenForo_Exception('Unable to install Torneo widget; prerequisite Addon is out of date.', true);
			}
		}
	}
}