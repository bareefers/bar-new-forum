<?php

class EWRporta2_Widget_RioLive extends XenForo_Model
{
	public function getCachedData($widget, &$options)
	{
		if ((!$addon = $this->getModelFromCache('XenForo_Model_AddOn')->getAddOnById('EWRrio')) || empty($addon['active']))
		{
			return 'killWidget';
		}

		$params = array(
			'approved_channels' => $options['riolive_channels'],
			'approved_games' => $options['riolive_games'],
			'category_id' => $options['riolive_category'],
			'game_id' => $options['riolive_game'],
			'featured' => $options['riolive_featured'],
		);

		return $this->getModelFromCache('EWRrio_Model_Streams')->getStreams(1, $options['riolive_limit'], $params);
	}
}