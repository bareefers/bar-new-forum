<?php

class Tapatalk_Model_ThreadWatch extends XFCP_Tapatalk_Model_ThreadWatch
{
	/**
	 * Gets the total number of threads a user is watching.
	 * Strict condition as function getThreadsWatchedByUser do.
	 * @param integer $userId
	 *
	 * @return integer
	 */
	public function countLegalThreadsWatchedByUser($userId, $newOnly, array $fetchOptions = array())
	{
		$fetchOptions['readUserId'] = $userId;
		$fetchOptions['includeForumReadDate'] = true;

		$joinOptions = $this->_getThreadModel()->prepareThreadFetchOptions($fetchOptions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);
		if ($newOnly)
		{
			$cutoff = XenForo_Application::$time - (XenForo_Application::get('options')->readMarkingDataLifetime * 86400);
			$newOnlyClause = '
				AND thread.last_post_date > ' . $cutoff . '
				AND thread.last_post_date > COALESCE(thread_read.thread_read_date, 0)
				AND thread.last_post_date > COALESCE(forum_read.forum_read_date, 0)
			';
		}
		else
		{
			$newOnlyClause = '';
		}

		return $this->fetchAllKeyed($this->limitQueryResults(
			'SELECT COUNT(*)
				FROM xf_thread_watch AS thread_watch
				INNER JOIN xf_thread AS thread ON
					(thread.thread_id = thread_watch.thread_id)
				' . $joinOptions['joinTables'] . '
				WHERE thread_watch.user_id = ?
					AND thread.discussion_state = \'visible\'
					' . $newOnlyClause . '
				ORDER BY thread.last_post_date DESC
			', $limitOptions['limit'], $limitOptions['offset']
		), 'thread_id', $userId);
	}
}