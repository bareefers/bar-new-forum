<?php

class EWRporta2_ControllerPublic_Forum extends XFCP_EWRporta2_ControllerPublic_Forum
{
	private $xp2perms;
	
	public function actionIndex()
	{
		$response = parent::actionIndex();
		
		if ($this->xp2perms['filter'] || $this->xp2perms['arrange'])
		{
			$response->params['setting'] = $this->getModelFromCache('EWRporta2_Model_Settings')->getSettingById(XenForo_Visitor::getUserId());
		}
		$response->params['fbTemplate'] = 'EWRporta2_ArticleList';
		
		return $response;
	}
	
	public function actionAddThread()
	{
		$response = parent::actionAddThread();
		
		if ($this->_input->filterSingle('article_promote', XenForo_Input::UINT))
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				$response->redirectTarget.'promote-article',
				new XenForo_Phrase('your_thread_has_been_posted')
			);
		}
		
		return $response;
	}

	protected function _preDispatch($action)
	{
		parent::_preDispatch($action);

		$this->xp2perms = $this->getModelFromCache('EWRporta2_Model_Perms')->getPermissions();
	}
}