<?php

class EWRutiles_ControllerAdmin_Utiles extends XenForo_ControllerAdmin_Abstract
{
	public function actionIndex()
	{
		return $this->responseView('EWRutiles_ViewAdmin_Index', 'EWRutiles_Index');
	}
	
	public function actionAccountSpam()
	{
		$start = $this->_input->filterSingle('page', XenForo_Input::UINT) < 1 ? 1 : $this->_input->filterSingle('page', XenForo_Input::UINT);
		$stop = 10;

		$viewParams = array(
			'start' => $start,
			'stop' => $stop,
			'count' => $this->getModelFromCache('EWRutiles_Model_SpamFinder')->getAccountsCount(),
			'users' => $this->getModelFromCache('EWRutiles_Model_SpamFinder')->findAccounts($start, $stop),
		);

		return $this->responseView('EWRutiles_ViewAdmin_AccountSpam', 'EWRutiles_AccountSpam', $viewParams);
	}

	public function actionProfileSpam()
	{
		$start = $this->_input->filterSingle('page', XenForo_Input::UINT) < 1 ? 1 : $this->_input->filterSingle('page', XenForo_Input::UINT);
		$stop = 10;

		$viewParams = array(
			'start' => $start,
			'stop' => $stop,
			'count' => $this->getModelFromCache('EWRutiles_Model_SpamFinder')->getProfilesCount(),
			'messages' => $this->getModelFromCache('EWRutiles_Model_SpamFinder')->findProfiles($start, $stop),
		);

		return $this->responseView('EWRutiles_ViewAdmin_ProfileSpam', 'EWRutiles_ProfileSpam', $viewParams);
	}
	
	public function actionSubmitSpam()
	{
		$this->_assertPostOnly();
		
		$input = $this->_input->filter(array(
			'username' => XenForo_Input::STRING,
			'email' => XenForo_Input::STRING,
			'ip' => XenForo_Input::STRING,
			'stopforumspam' => XenForo_Input::UINT,
			'bannedips' => XenForo_Input::UINT,
		));
		
		if ($input['stopforumspam'])
		{
			$this->getModelFromCache('EWRutiles_Model_StopForumSpam')->submitSpammer($input);
		}
		
		if ($input['bannedips'])
		{
			$this->getModelFromCache('XenForo_Model_Banning')->banIp($input['ip']);
		}
	
		return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildAdminLink('utiles'));
	}
}