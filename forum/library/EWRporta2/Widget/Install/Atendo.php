<?php

class EWRporta2_Widget_Install_Atendo
{
	public static function installCode($existingWidget, $widgetData)
	{
		if (!$existingWidget)
		{
			$addonModel = XenForo_Model::create('XenForo_Model_AddOn');
		
			if (!$addon = $addonModel->getAddOnById('EWRatendo'))
			{
				throw new XenForo_Exception('Unable to install Atendo widget; missing prerequisite Addon.', true);
			}
		}
	}
}