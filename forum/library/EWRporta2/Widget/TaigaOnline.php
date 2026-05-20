<?php

class EWRporta2_Widget_TaigaOnline extends XenForo_Model
{
	public function getUncachedData($widget, &$options)
	{
		if ((!$addon = $this->getModelFromCache('XenForo_Model_AddOn')->getAddOnById('TaigaChat')) || empty($addon['active']) ||
			!XenForo_Application::get('options')->dark_taigachat_sidebar)
		{
			return 'killWidget';
		}
		
		return array(
			'online' => $this->getModelFromCache('Dark_TaigaChat_Model_TaigaChat')->getActivityUserList()
		);
	}
}