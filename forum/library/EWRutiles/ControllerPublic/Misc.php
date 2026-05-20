<?php

class EWRutiles_ControllerPublic_Misc extends XFCP_EWRutiles_ControllerPublic_Misc
{
	public function actionDownVotes()
	{
		$perms = $this->getModelFromCache('EWRutiles_Model_Perms')->getPermissions();
		if (!$perms['review']) { return $this->responseNoPermission(); }

		$timeNow = XenForo_Application::$time;
		$timeExp = $timeNow - (XenForo_Application::get('options')->EWRutiles_downvote_expiration * 3600);

		$start = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
		$stop = 20;
		$count = $this->getModelFromCache('EWRutiles_Model_DownVotes')->getFullCount($timeExp);

		$this->canonicalizeRequestUrl(XenForo_Link::buildPublicLink('misc/downvotes', null, array('page' => $start)));
		$this->canonicalizePageNumber($start, $stop, $count, 'misc/downvotes');
		list($threads, $users) = $this->getModelFromCache('EWRutiles_Model_DownVotes')->getFullVotes($timeExp, $start, $stop);

		$viewParams = array(
			'start' => $start,
			'stop' => $stop,
			'count' => $count,
			'threads' => $threads,
			'users' => $users,
		);

		return $this->responseView('EWRutiles_ViewPublic_DownVotes', 'EWRutiles_DownVotes', $viewParams);
	}
}