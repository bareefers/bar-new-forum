<?php

class EWRutiles_Model_SoftDeleted extends XenForo_Model
{
	public function deleteSofts()
	{
		$options = XenForo_Application::get('options');
		$cutoff = $options->EWRutiles_softdelete_cutoff;
		$today = XenForo_Application::$time;

		$this->deleteThreads($cutoff, $today);
		$this->deletePosts($cutoff, $today);
		$this->deleteProfilePosts($cutoff, $today);

		return true;
	}

	public function deleteThreads($cutoff, $today)
	{
		$threads = $this->_getDb()->fetchAll("
			SELECT *
				FROM xf_thread
				LEFT JOIN xf_deletion_log ON (xf_deletion_log.content_id = xf_thread.thread_id AND xf_deletion_log.content_type = 'thread')
			WHERE xf_thread.discussion_state = 'deleted'
		");

		foreach ($threads AS $thread)
		{
			$thread['delete_age'] = floor(($today - $thread['delete_date']) / (60 * 60 * 24));

			if ($thread['delete_age'] > $cutoff)
			{
				$this->getModelFromCache('XenForo_Model_Thread')->deleteThread($thread['thread_id'], 'hard');
				XenForo_Helper_Cookie::clearIdFromCookie($thread['thread_id'], 'inlinemod_threads');
			}
		}

		return true;
	}

	public function deletePosts($cutoff, $today)
	{
		$posts = $this->_getDb()->fetchAll("
			SELECT *
				FROM xf_post
				LEFT JOIN xf_deletion_log ON (xf_deletion_log.content_id = xf_post.post_id AND xf_deletion_log.content_type = 'post')
			WHERE xf_post.message_state = 'deleted'
		");

		foreach ($posts AS $post)
		{
			$post['delete_age'] = floor(($today - $post['delete_date']) / (60 * 60 * 24));

			if ($post['delete_age'] > $cutoff)
			{
				$this->getModelFromCache('XenForo_Model_Post')->deletePost($post['post_id'], 'hard');
				XenForo_Helper_Cookie::clearIdFromCookie($post['post_id'], 'inlinemod_posts');
			}
		}

		return true;
	}

	public function deleteProfilePosts($cutoff, $today)
	{
		$profile_posts = $this->_getDb()->fetchAll("
			SELECT *
				FROM xf_profile_post
				LEFT JOIN xf_deletion_log ON (xf_deletion_log.content_id = xf_profile_post.profile_post_id AND xf_deletion_log.content_type = 'profile_post')
			WHERE xf_profile_post.message_state = 'deleted'
		");

		foreach ($profile_posts AS $profile_post)
		{
			$profile_post['delete_age'] = floor(($today - $profile_post['delete_date']) / (60 * 60 * 24));

			if ($profile_post['delete_age'] > $cutoff)
			{
				$dw = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_ProfilePost');
				$dw->setExistingData($profile_post['profile_post_id']);
				$dw->delete();

				XenForo_Helper_Cookie::clearIdFromCookie($profile_post['profile_post_id'], 'inlinemod_profilePosts');
				$this->getModelFromCache('XenForo_Model_DeletionLog')->removeDeletionLog('profile_post', $profile_post['content_id']);
			}
		}

		return true;
	}
}