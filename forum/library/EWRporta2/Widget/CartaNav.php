<?php

class EWRporta2_Widget_CartaNav extends XenForo_Model
{
	public function getCachedData($widget, &$options)
	{
		if ((!$addon = $this->getModelFromCache('XenForo_Model_AddOn')->getAddOnById('EWRcarta')) || empty($addon['active']))
		{
			return 'killWidget';
		}

		return $this->getModelFromCache('EWRcarta_Model_Lists')->getSideList();
	}
}