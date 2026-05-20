<?php

class EWRutiles_ControllerAdmin_UserXF extends XFCP_EWRutiles_ControllerAdmin_UserXF
{
	public function actionSave()
	{
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$username = $this->_input->filterSingle('username', XenForo_Input::STRING);

		if ($userId)
		{
			$user = $this->_getUserOrError($userId);
		}
		
		$response = parent::actionSave();
		
		if ($user && $user['username'] !== $username)
		{
			$this->getModelFromCache('EWRutiles_Model_UserNames')->insertUserName($user['user_id'], $user['username'], $username, 0);
		}
		
		return $response;
	}
}