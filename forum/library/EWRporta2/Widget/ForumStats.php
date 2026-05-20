<?php

class EWRporta2_Widget_ForumStats extends XenForo_Model
{
	public function getUncachedData($widget, &$options)
	{
		$boardTotals = $this->getModelFromCache('XenForo_Model_DataRegistry')->get('boardTotals');
		
		if (!$boardTotals)
		{
			$boardTotals = $this->getModelFromCache('XenForo_Model_Counters')->rebuildBoardTotalsCounter();
		}
		
		$boardTotals['most_users'] = XenForo_Application::getSimpleCacheData('mostUsers');

		return $boardTotals;
	}
}