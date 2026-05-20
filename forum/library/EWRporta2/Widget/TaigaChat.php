<?php

class EWRporta2_Widget_TaigaChat extends XenForo_Model
{
	public function getUncachedData($widget, &$options)
	{
		if ((!$addon = $this->getModelFromCache('XenForo_Model_AddOn')->getAddOnById('TaigaChat')) || empty($addon['active']))
		{
			return 'killWidget';
		}

		$response = new XenForo_ControllerResponse_View();
		$response->viewName = 'derp';
		$response->params = array();
		
		Dark_TaigaChat_Helper_Global::getTaigaChatStuff($response, 'index');
		return $response->params;
	}
}