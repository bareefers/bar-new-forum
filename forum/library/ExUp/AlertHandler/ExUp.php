<?php

/**
 * Handles alerts of user upgrades.
 *
 * @package ExUp
 */
class ExUp_AlertHandler_ExUp extends XenForo_AlertHandler_Abstract
{
	/**
	 * Fetches the content required by alerts.
	 *
	 * @param array $contentIds
	 * @param XenForo_Model_Alert $model Alert model invoking this
	 * @param integer $userId User ID the alerts are for
	 * @param array $viewingUser Information about the viewing user (keys: user_id, permission_combination_id, permissions)
	 *
	 * @return array
	 */
	public function getContentByIds(array $contentIds, $model, $userId, array $viewingUser)
	{
		$userModel = $model->getModelFromCache('XenForo_Model_User');

		$visitor = XenForo_Visitor::getInstance()->toArray();
		$users = array();

		foreach ($contentIds AS $key => $contentId)
		{
			if ($contentId == $visitor['user_id'])
			{
				$users[$visitor['user_id']] = $visitor;
				unset($contentIds[$key]);
				break;
			}
		}

		return $users + $userModel->getUsersByIds($contentIds);
	}
}