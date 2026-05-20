<?php

/**
 * Controller for managing user upgrades.
 *
 * @package ExUp
 */
class ExUp_ControllerAdmin_UserUpgrade extends XFCP_ExUp_ControllerAdmin_UserUpgrade
{
	/**
	 * Displays a form to manually upgrade a user with the specified upgrade,
	 * or actually upgrades the user.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionManual()
	{
		$response = parent::actionManual();

		if (!$this->_request->isPost() && !empty($response) && isset($response->params))
		{
			$username = $this->_input->filterSingle('username', XenForo_Input::STRING);
			
			if ($username)
				$response->params['username'] = $username;
		}
		
		return $response;
	}
	
	/**
	 * Displays a list of expired upgrades, either across all upgrades or a specific one.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionExpired()
	{
		$userUpgradeId = $this->_input->filterSingle('user_upgrade_id', XenForo_Input::UINT);
		$username = $this->_input->filterSingle('username', XenForo_Input::STRING);
		$userId = 0;
		if (!empty($username))
		{
			$user = $this->_getUserModel()->getUserByName($username);
			if (!empty($user))
				$userId = $user['user_id'];
		}
		
		$userUpgradeModel = $this->_getUserUpgradeModel();
		
		$page = $this->_input->filterSingle('page', XenForo_Input::UINT);
		$perPage = 20;
		
		$fetchOptions = array(
			'page' => $page,
			'perPage' => $perPage
		);
		
		$conditions = array();
		if (!empty($username))
		{
			$conditions['user_id'] = $userId;
		}
		
		if ($userUpgradeId)
		{
			$upgrade = $this->_getUserUpgradeOrError($userUpgradeId);
			
			$conditions['user_upgrade_id'] = $upgrade['user_upgrade_id'];
			
			$viewParams = array(
				'upgrade' => $upgrade,
				'upgradeRecords' => $userUpgradeModel->getUserUpgradeRecords($conditions, $fetchOptions),
				'username' => $username,
				'totalRecords' => $userUpgradeModel->countUserUpgradeRecords($conditions),
				'perPage' => $perPage,
				'page' => $page
			);
			
			return $this->responseView('XenForo_ViewAdmin_UserUpgrade_ActiveSingle', 'exup_user_upgrade_expired_single', $viewParams);
		}
		else
		{
			$fetchOptions['join'] = XenForo_Model_UserUpgrade::JOIN_UPGRADE;
			
			$viewParams = array(
				'upgradeRecords' => $userUpgradeModel->getUserUpgradeRecords($conditions, $fetchOptions),
				'username' => $username,
				'totalRecords' => $userUpgradeModel->countUserUpgradeRecords($conditions),
				'perPage' => $perPage,
				'page' => $page
			);
			
			return $this->responseView('XenForo_ViewAdmin_UserUpgrade_Active', 'exup_user_upgrade_expired', $viewParams);
		}
	}
	
	/**
	 * @return XenForo_Model_User
	 */
	protected function _getUserModel()
	{
		return $this->getModelFromCache('XenForo_Model_User');
	}
}