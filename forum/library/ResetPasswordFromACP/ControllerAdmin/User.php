<?php

class ResetPasswordFromACP_ControllerAdmin_User extends XFCP_ResetPasswordFromACP_ControllerAdmin_User
{
	public function actionSave()
	{
		$parent = parent::actionSave();
		
		$this->_assertPostOnly();

		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$resetPassword = $this->_input->filterSingle('reset_password', XenForo_Input::UINT);

		if ($userId && $resetPassword)
		{	
			$this->getModelFromCache('XenForo_Model_UserConfirmation')->resetPassword($userId);
		}
		
		return $parent;
	}
}