<?php

class EWRatendo_Listener_Template
{
	public static function template_hook($hookName, &$contents, array $hookParams, XenForo_Template_Abstract $template)
	{
		if ($hookName === 'forum_view_pagenav_before'
			&& in_array($hookParams['forum']['node_id'], XenForo_Application::get('options')->EWRatendo_eventforums))
		{
			$eventModel = XenForo_Model::create('EWRatendo_Model_Events');
			
			$hookParams['cutoff'] = !empty(XenForo_Application::get('options')->EWRatendo_eventforums2[$hookParams['forum']['node_id']]['cutoff']) ? 
										XenForo_Application::get('options')->EWRatendo_eventforums2[$hookParams['forum']['node_id']]['cutoff'] : 0;
			$hookParams['events'] = $eventModel->getCurrentEvents('+'.$hookParams['cutoff'].' days', $hookParams['forum']['node_id']);

			$contents .= $template->create('EWRatendo_ForumView', $hookParams);
		}

		if ($hookName === 'thread_view_pagenav_before'
			&& in_array($hookParams['thread']['node_id'], XenForo_Application::get('options')->EWRatendo_eventforums))
		{
			$eventModel = XenForo_Model::create('EWRatendo_Model_Events');

			if ($hookParams['event'] = $eventModel->getEventByThread($hookParams['thread']['thread_id']))
			{
				$rsvpsModel = XenForo_Model::create('EWRatendo_Model_RSVPs');

				$hookParams['visitor'] = XenForo_Visitor::getInstance()->toArray();
				$hookParams['canRSVP'] = (XenForo_Permission::hasPermission($hookParams['visitor']['permissions'], 'EWRatendo', 'canRSVP') ? true : false);
				$hookParams['rsvps'] = $rsvpsModel->getRSVPsByEvent($hookParams['event']);

				$contents .= $template->create('EWRatendo_EventsView', $hookParams);
			}
		}
	}
	
	public static function template_post_render($templateName, &$contents, array &$containerData, XenForo_Template_Abstract $template)
	{
		$options = XenForo_Application::get('options');
		$viewingUser = XenForo_Visitor::getInstance()->toArray();
		$hookParams['forum']['node_id'] = !empty($containerData['quickNavSelected']) ? substr($containerData['quickNavSelected'], 5) : '';
	
		if ($templateName == 'forum_view' && !empty($containerData['topctrl'])
			&& XenForo_Permission::hasPermission($viewingUser['permissions'], 'EWRatendo', 'canPost')
			&& in_array($hookParams['forum']['node_id'], $options->EWRatendo_eventforums))
		{
			$topctrl = !empty(XenForo_Application::get('options')->EWRatendo_eventforums2[$hookParams['forum']['node_id']]['topctrl']) ? true : false;
		
			if ($topctrl)
			{
				$containerData['topctrl'] = $template->create('EWRatendo_ForumView_TopCtrl', $hookParams);
			}
			else
			{
				$containerData['topctrl'] .= $template->create('EWRatendo_ForumView_TopCtrl', $hookParams);
			}
		}
	}
}