<?php

class EWRatendo_ControllerPublic_Forum extends XFCP_EWRatendo_ControllerPublic_Forum
{
	public function actionCreateThread()
	{
		$response = parent::actionCreateThread();
		$topctrl = !empty(XenForo_Application::get('options')->EWRatendo_eventforums2[$response->params['forum']['node_id']]['topctrl']) ? true : false;

		if ($response instanceof XenForo_ControllerResponse_View && $topctrl
			&& in_array($response->params['forum']['node_id'], XenForo_Application::get('options')->EWRatendo_eventforums))
		{
			return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildPublicLink('events/create/'.$response->params['forum']['node_id']));
		}

		return $response;
	}
}