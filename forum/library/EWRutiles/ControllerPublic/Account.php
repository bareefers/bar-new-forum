<?php

class EWRutiles_ControllerPublic_Account extends XFCP_EWRutiles_ControllerPublic_Account
{
	public $perms;
	
	public function actionUsername()
	{
		$userId = XenForo_Visitor::getUserId();
	
		$auth = $this->getModelFromCache('XenForo_Model_User')->getUserAuthenticationObjectByUserId($userId);
		if (!$auth) { return $this->responseNoPermission(); }
		
		if (!$this->perms['username']) { return $this->responseNoPermission(); }
		
		$lastChange = $this->getModelFromCache('EWRutiles_Model_UserNames')->getLastChangeByUserID($userId);
		$nextChange = $lastChange + ($this->perms['username'] * 86400);
		$canChange = XenForo_Application::$time > $nextChange ? true : false;
		
		$viewParams = array(
			'perms' => $this->perms,
			'hasPassword' => $auth->hasPassword(),
			'history' => $this->getModelFromCache('EWRutiles_Model_UserNames')->getUserNamesByUserID($userId),
			'lastChange' => $lastChange,
			'nextChange' => $nextChange,
			'canChange' => $canChange
		);
		
		return $this->_getWrapper(
			'account', 'username',
			$this->responseView(
				'EWRutiles_ViewPublic_AccountUsername',
				'EWRutiles_AccountUsername',
				$viewParams
			)
		);
	}
	
	public function actionUsernameSave()
	{
		$this->_assertPostOnly();
		
		$visitor = XenForo_Visitor::getInstance();
		$username = $this->_input->filterSingle('username', XenForo_Input::STRING);

		$auth = $this->getModelFromCache('XenForo_Model_User')->getUserAuthenticationObjectByUserId($visitor['user_id']);
		if (!$auth || !$auth->hasPassword()) { return $this->responseNoPermission(); }
		
		$lastChange = $this->getModelFromCache('EWRutiles_Model_UserNames')->getLastChangeByUserID($visitor['user_id']);
		$nextChange = $lastChange + ($this->perms['username'] * 86400);
		
		if (!(XenForo_Application::$time > $nextChange)) { return $this->responseNoPermission(); }
		
		if (!$auth->authenticate($visitor['user_id'], $this->_input->filterSingle('password', XenForo_Input::STRING)))
		{
			return $this->responseError(new XenForo_Phrase('your_existing_password_is_not_correct'));
		}
		
		if ($username !== $visitor['username'])
		{			
			$writer = XenForo_DataWriter::create('XenForo_DataWriter_User');
			$writer->setExistingData($visitor['user_id']);
			$writer->set('username', $username);
			$writer->preSave();

			if ($dwErrors = $writer->getErrors())
			{
				return $this->responseError($dwErrors);
			}
			
			$writer->save();

			$this->getModelFromCache('EWRutiles_Model_UserNames')->insertUserName($visitor['user_id'], $visitor['username'], $username);
		}		
		
		return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildPublicLink('account/username'));
	}

	protected function _preDispatch($action)
	{
		parent::_preDispatch($action);

		$this->perms = $this->getModelFromCache('EWRutiles_Model_Perms')->getPermissions();
	}
}