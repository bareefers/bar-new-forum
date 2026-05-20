<?php

class EWRporta2_Widget_TorneoRanks extends XenForo_Model
{
	public function getCachedData($widget, &$options)
	{
		if ((!$addon = $this->getModelFromCache('XenForo_Model_AddOn')->getAddOnById('EWRtorneo')) || empty($addon['active']))
		{
			return 'killWidget';
		}
		
		if (empty($options['torneoranks_league']) ||
			!$league = $this->getModelFromCache('EWRtorneo_Model_Leagues')->getLeagueById($options['torneoranks_league'])) 
		{
			return false;
		}
		
		$listParams = array(
			'type' => 'league',
			'where' => $options['torneoranks_league'],
		);
		
		$league['ranks'] = $this->getModelFromCache('EWRtorneo_Model_Ranks')->getRanksList(1, $options['torneoranks_limit'], $listParams);

		return $league;
	}
}