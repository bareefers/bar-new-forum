<?php

class EWRutiles_ControllerPublic_Thread extends XFCP_EWRutiles_ControllerPublic_Thread
{
	public function actionDownVotes()
	{
		$perms = $this->getModelFromCache('EWRutiles_Model_Perms')->getPermissions();
		if (!$perms['review']) { return $this->responseNoPermission(); }

		$ftpHelper = $this->getHelper('ForumThreadPost');
		$threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);
		list($thread, $forum) = $ftpHelper->assertThreadValidAndViewable($threadId);

		switch (XenForo_Application::get('options')->EWRutiles_downvote_action['action'])
		{
			case 'lock':	$action = new XenForo_Phrase('locked');		break;
			case 'mod':		$action = new XenForo_Phrase('moderated');	break;
			case 'soft':	$action = new XenForo_Phrase('deleted');	break;
			case 'move':	$action = new XenForo_Phrase('moved');		break;
		}

		$viewParams = array(
			'thread' => $thread,
			'action' => $action,
			'downvotes' => $this->getModelFromCache('EWRutiles_Model_DownVotes')->getVotesByThread($thread['thread_id']),
			'nodeBreadCrumbs' => $ftpHelper->getNodeBreadCrumbs($forum),
		);

		return $this->responseView('EWRutiles_ViewPublic_ViewVotes', 'EWRutiles_ViewVotes', $viewParams);
	}

	public function actionDownVote()
	{
		$perms = $this->getModelFromCache('EWRutiles_Model_Perms')->getPermissions();
		if (!$perms['vote']) { return $this->responseNoPermission(); }

		$input = $this->_input->filter(array(
			'thread_id' => XenForo_Input::UINT,
			'vote_id' => XenForo_Input::UINT,
		));

		$ftpHelper = $this->getHelper('ForumThreadPost');
		list($thread, $forum) = $ftpHelper->assertThreadValidAndViewable($input['thread_id']);

		if ($this->_request->isPost())
		{
			$this->getModelFromCache('EWRutiles_Model_DownVotes')->updateVote($input);
		}
		else
		{
			switch (XenForo_Application::get('options')->EWRutiles_downvote_action['action'])
			{
				case 'lock':	$action = new XenForo_Phrase('locked');		break;
				case 'mod':		$action = new XenForo_Phrase('moderated');	break;
				case 'soft':	$action = new XenForo_Phrase('deleted');	break;
				case 'move':	$action = new XenForo_Phrase('moved');		break;
			}

			$viewParams = array(
				'thread' => $thread,
				'action' => $action,
				'downvote' => $this->getModelFromCache('EWRutiles_Model_DownVotes')->getVoteByUser(XenForo_Visitor::getUserId(), $thread['thread_id']),
				'nodeBreadCrumbs' => $ftpHelper->getNodeBreadCrumbs($forum),
			);

			return $this->responseView('EWRutiles_ViewPublic_DownVote', 'EWRutiles_DownVote', $viewParams);
		}

		return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildPublicLink('threads', $thread));
	}
}