<?php

class EWRporta2_Widget_Features extends XenForo_Model
{
	public function getUncachedData($widget, &$options)
	{
		$params = array(
			'category' => $options['features_category'][0] != 0 ? $options['features_category'] : false,
			'author' => $options['features_author'][0] != 0 ? $options['features_author'] : false,
		);
		
		$features = $this->getModelFromCache('EWRporta2_Model_Features')->getFeatures($options['features_limit'], $params);
		$features = $this->getModelFromCache('EWRporta2_Model_Features')->prepareFeatures($features);
		
		return $features;
	}
}