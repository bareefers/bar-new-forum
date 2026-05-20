<?php

class EWRutiles_ControllerAdmin_User extends XenForo_ControllerAdmin_Abstract
{
	public function actionIndex()
	{
		return $this->responseView('EWRutiles_ViewAdmin_Index', 'EWRutiles_Index');
	}
	
	public function actionDeleteAccountSpam()
	{
		$userID = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$user = $this->getRecordOrError($userID, $this->getModelFromCache('XenForo_Model_User'), 'getFullUserById', 'requested_user_not_found');

		$writer = XenForo_DataWriter::create('XenForo_DataWriter_User', XenForo_DataWriter::ERROR_EXCEPTION);
		$writer->setExistingData($user);
		$writer->preDelete();

		$this->getHelper('Admin')->checkSuperAdminEdit($user);

		if ($this->isConfirmedPost())
		{
			if ($this->getModelFromCache('XenForo_Model_User')->isUserSuperAdmin($user))
			{
				$visitorPassword = $this->_input->filterSingle('visitor_password', XenForo_Input::STRING);
				$this->getHelper('Admin')->assertVisitorPasswordCorrect($visitorPassword);
			}

			return $this->_deleteData('XenForo_DataWriter_User', 'user_id', XenForo_Link::buildAdminLink('utiles/account-spam'));
		}
		else
		{
			$user['is_super_admin'] = $this->getModelFromCache('XenForo_Model_User')->isUserSuperAdmin($user);

			$viewParams = array(
				'user' => $user,
				'type' => 'account'
			);

			return $this->responseView('EWRutiles_ViewAdmin_DeleteSpam',  'EWRutiles_DeleteSpam', $viewParams);
		}
	}
	
	public function actionDeleteProfileSpam()
	{
		$userID = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$user = $this->getRecordOrError($userID, $this->getModelFromCache('XenForo_Model_User'), 'getFullUserById', 'requested_user_not_found');

		$writer = XenForo_DataWriter::create('XenForo_DataWriter_User', XenForo_DataWriter::ERROR_EXCEPTION);
		$writer->setExistingData($user);
		$writer->preDelete();

		$this->getHelper('Admin')->checkSuperAdminEdit($user);

		if ($this->isConfirmedPost())
		{
			if ($this->getModelFromCache('XenForo_Model_User')->isUserSuperAdmin($user))
			{
				$visitorPassword = $this->_input->filterSingle('visitor_password', XenForo_Input::STRING);
				$this->getHelper('Admin')->assertVisitorPasswordCorrect($visitorPassword);
			}

			return $this->_deleteData('XenForo_DataWriter_User', 'user_id', XenForo_Link::buildAdminLink('utiles/profile-spam'));
		}
		else
		{
			$user['is_super_admin'] = $this->getModelFromCache('XenForo_Model_User')->isUserSuperAdmin($user);

			$viewParams = array(
				'user' => $user,
				'type' => 'profile'
			);

			return $this->responseView('EWRutiles_ViewAdmin_DeleteSpam',  'EWRutiles_DeleteSpam', $viewParams);
		}
	}
	
	public function actionSubmit()
	{
		$userID = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$user = $this->getRecordOrError($userID, $this->getModelFromCache('XenForo_Model_User'), 'getFullUserById', 'requested_user_not_found');
		
		$IPs = $this->getModelFromCache('XenForo_Model_Ip')->getIpsByUserId($userID);
		end($IPs);
		$user['ip'] = current($IPs);
		
		$viewParams = array(
			'spam' => $user,
		);

		return $this->responseView('EWRutiles_ViewAdmin_SubmitSpam', 'EWRutiles_SubmitSpam', $viewParams);
	}
}