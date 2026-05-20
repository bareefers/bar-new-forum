<?php

class EWRporta2_Widget_UsersOnline extends XenForo_Model
{
	public function getUncachedData($widget, &$options)
	{
		$visitor = XenForo_Visitor::getInstance();

		/** @var $sessionModel XenForo_Model_Session */
		$sessionModel = $this->getModelFromCache('XenForo_Model_Session');

		$activity = $sessionModel->getSessionActivityQuickList(
			$visitor->toArray(),
			array('cutOff' => array('>', $sessionModel->getOnlineStatusTimeout())),
			($visitor['user_id'] ? $visitor->toArray() : null)
		);
		
		$activity['most_users'] = XenForo_Application::getSimpleCacheData('mostUsers');

		if (empty($activity['most_users']) || $activity['total'] > $activity['most_users']['total'])
		{
			$activity['most_users'] = array('total' => $activity['total'], 'time' => XenForo_Application::$time);
            XenForo_Application::setSimpleCacheData('mostUsers', $activity['most_users']);
		}

		if (!$options['usersonline_staff'])
		{
			foreach ($activity['records'] AS &$user)
			{
				$user['is_staff'] = false;
			}
		}

		return $activity;
	}
}