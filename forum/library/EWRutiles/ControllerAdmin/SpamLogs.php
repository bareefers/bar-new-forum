<?php

class EWRutiles_ControllerAdmin_SpamLogs extends XenForo_ControllerAdmin_Abstract
{
	public function actionIndex()
	{
		$start = $this->_input->filterSingle('page', XenForo_Input::UINT) < 1 ? 1 : $this->_input->filterSingle('page', XenForo_Input::UINT);
		$stop = 20;

		$viewParams = array(
			'start' => $start,
			'stop' => $stop,
			'count' => $this->getModelFromCache('EWRutiles_Model_SpamLogs')->getSpamLogsCount(),
			'logs' => $this->getModelFromCache('EWRutiles_Model_SpamLogs')->getSpamLogs($start, $stop),
		);

		return $this->responseView('EWRutiles_ViewAdmin_SpamLogs', 'EWRutiles_SpamLogs', $viewParams);
	}

	public function actionClear()
	{
		if ($this->isConfirmedPost())
		{
			$this->getModelFromCache('EWRutiles_Model_SpamLogs')->clearRegistrationLog();

			return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildAdminLink('utiles/spamlogs'));
		}
		else
		{
			$viewParams = array();
			return $this->responseView('EWRutiles_ViewAdmin_SpamLogs_Clear', 'EWRutiles_SpamLogs_Clear', $viewParams);
		}
	}
	
	public function actionSubmit()
	{
		$spamLogID = $this->_input->filterSingle('spamlog_id', XenForo_Input::UINT);

		if (!$spamLog = $this->getModelFromCache('EWRutiles_Model_SpamLogs')->getSpamLogById($spamLogID))
		{
			return $this->responseRedirect(XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT, XenForo_Link::buildAdminLink('utiles/spamlogs'));
		}
		
		$viewParams = array(
			'spam' => $spamLog,
		);

		return $this->responseView('EWRutiles_ViewAdmin_SubmitSpam', 'EWRutiles_SubmitSpam', $viewParams);
	}
}