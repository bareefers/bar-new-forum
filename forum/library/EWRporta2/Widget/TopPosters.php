<?php

class EWRporta2_Widget_TopPosters extends XenForo_Model
{
	public function getCachedData($widget, &$options)
	{
		$forumCheck = '';
		if ($options['topposters_source'][0] != 0)
		{
			$forums = implode(',', $options['topposters_source']);
			$forumCheck = "AND xf_thread.node_id IN (".$forums.")";
		}
		$date = XenForo_Application::$time - (3600 * $options['topposters_hours']);
	
		$users = $this->_getDb()->fetchAll("
			SELECT xf_user.*, COUNT(xf_post.user_id) AS count
			FROM xf_post
				INNER JOIN xf_user ON (xf_user.user_id = xf_post.user_id)
				INNER JOIN xf_thread ON (xf_thread.thread_id = xf_post.thread_id)
			WHERE xf_post.message_state = 'visible' AND xf_thread.discussion_state = 'visible'
				AND user_state = 'valid' AND xf_post.post_date > ?
				$forumCheck
			GROUP BY xf_user.user_id
			ORDER BY count DESC
			LIMIT ?
		", array($date, $options['topposters_limit']));
		
        return $users;
	}
}