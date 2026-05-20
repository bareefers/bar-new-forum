<?php

class EWRutiles_ControllerPublic_Help extends XFCP_EWRutiles_ControllerPublic_Help
{	
	public function actionStaffList()
	{
		$contentMods = $this->getModelFromCache('XenForo_Model_Moderator')->getContentModerators();

		$moderatorIDs = array();
		$contentModeratorInfo = array();
		$contentModData = $this->getModelFromCache('XenForo_Model_Moderator')->addContentTitlesToModerators(
			$this->getModelFromCache('XenForo_Model_Moderator')->getContentModerators()
		);

		foreach ($contentModData AS $moderator)
		{
			$moderator['node_id'] = $moderator['content_id'];
			$contentModeratorInfo[$moderator['user_id']][] = $moderator;
		}

		$superModerators = array();
		$contentModerators = array();
		foreach ($this->getModelFromCache('XenForo_Model_Moderator')->getAllGeneralModerators() AS $moderator)
		{
			$moderatorIDs[] = $moderator['user_id'];
			
			if ($moderator['is_super_moderator'])
			{
				$superModerators[$moderator['user_id']] = $moderator;
			}

			if (isset($contentModeratorInfo[$moderator['user_id']]))
			{
				$moderator['content'] = $contentModeratorInfo[$moderator['user_id']];
				$contentModerators[$moderator['user_id']] = $moderator;
			}
		}
		
		$moderators = $this->getModelFromCache('XenForo_Model_User')->getUsersByIds($moderatorIDs);

		foreach ($superModerators AS &$moderator)
		{
			$moderator['last_activity'] = $moderators[$moderator['user_id']]['last_activity'];
		}

		foreach ($contentModerators AS &$moderator)
		{
			$moderator['last_activity'] = $moderators[$moderator['user_id']]['last_activity'];
		}
		
		$administrators = $this->getModelFromCache('XenForo_Model_Admin')->prepareAdminRecords($this->getModelFromCache('XenForo_Model_Admin')->getAllAdmins());
		
		$viewParams = array(
			'administrators' => $administrators,
			'superModerators' => $superModerators,
			'contentModerators' => $contentModerators,
		);
		
		return $this->_getWrapper('staffList',
			$this->responseView('EWRutiles_ViewPublic_StaffList', 'EWRutiles_StaffList', $viewParams)
		);
	}
}