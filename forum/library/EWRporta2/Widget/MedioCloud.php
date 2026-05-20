<?php

class EWRporta2_Widget_MedioCloud extends XenForo_Model
{
	public function getCachedData($widget, &$options)
	{
		if ((!$addon = $this->getModelFromCache('XenForo_Model_AddOn')->getAddOnById('EWRmedio')) || empty($addon['active']))
		{
			return 'killWidget';
		}

		$cloud['keywords'] = $this->getModelFromCache('EWRmedio_Model_Keywords')->getKeywordCloud($options['mediocloud_limit'], $options['mediocloud_minsize'], $options['mediocloud_maxsize']);

		if ($options['mediocloud_animated'] && $cloud['keywords'])
		{
			$cloud['animated'] = $this->getModelFromCache('EWRmedio_Model_Keywords')->getAnimatedCloud($cloud['keywords']);
		}

		return $cloud;
	}
}