<?php

class EWRporta2_Widget_Install_TaigaChat
{
	public static function installCode($existingWidget, $widgetData)
	{
		if (!$existingWidget)
		{
			$addonModel = XenForo_Model::create('XenForo_Model_AddOn');
		
			if (!$addon = $addonModel->getAddOnById('TaigaChat'))
			{
				throw new XenForo_Exception('Unable to install TaigaChat widget; missing prerequisite Addon.', true);
			}
			
			if ($addon['version_id'] < 34)
			{
				throw new XenForo_Exception('Unable to install TaigaChat widget; prerequisite Addon is out of date.', true);
			}
		}
	}
}