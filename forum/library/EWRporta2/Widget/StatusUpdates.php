<?php

class EWRporta2_Widget_StatusUpdates extends XenForo_Model
{
	public function getUncachedData($widget, &$options)
	{
		$visitor = XenForo_Visitor::getInstance();
		$profilePostLimit = $options['statusupdates_limit'];
	
		if ($profilePostLimit && $visitor->canViewProfilePosts())
		{
			$profilePostModel = $this->getModelFromCache('XenForo_Model_ProfilePost');
			$profilePosts = $profilePostModel->getLatestProfilePosts(
				array(
					'deleted' => false,
					'moderated' => false
				), array(
					'limit' => max($profilePostLimit * 2, 10),
					'join' =>
						XenForo_Model_ProfilePost::FETCH_USER_POSTER |
						XenForo_Model_ProfilePost::FETCH_USER_RECEIVER |
						XenForo_Model_ProfilePost::FETCH_USER_RECEIVER_PRIVACY,
					'permissionCombinationId' => $visitor->permission_combination_id
				)
			);
			foreach ($profilePosts AS $id => &$profilePost)
			{
				$receivingUser = $profilePostModel->getProfileUserFromProfilePost($profilePost);
				if (!$profilePostModel->canViewProfilePostAndContainer($profilePost, $receivingUser))
				{
					unset($profilePosts[$id]);
				}

				$profilePost = $profilePostModel->prepareProfilePost($profilePost, $receivingUser);
				if (!empty($profilePost['isIgnored']))
				{
					unset($profilePosts[$id]);
				}
			}
			$profilePosts = array_slice($profilePosts, 0, $profilePostLimit, true);
		}
		else
		{
			$profilePosts = array();
		}

		return $profilePosts;
	}
}