<?php

class EWRporta2_Widget_PollBlock extends XenForo_Model
{
	public function getCachedData($widget, &$options)
	{
		$poll = $this->getModelFromCache('XenForo_Model_Poll')->getPollById($options['pollblock_poll']);
		$thread = $this->getModelFromCache('XenForo_Model_Thread')->getThreadById($poll['content_id']);
		$forum = $this->getModelFromCache('XenForo_Model_Forum')->getForumById($thread['node_id']);
		
		return array(
			'thread' => $thread,
			'forum' => $forum
		);
	}
	
	public function getUncachedData($widget, &$options)
	{
		$poll = $this->getModelFromCache('XenForo_Model_Poll')->getPollById($options['pollblock_poll']);
		$thread = $widget['wCached']['thread'];
		$forum = $widget['wCached']['forum'];
	
		if ($poll)
		{
			$canVote = $this->getModelFromCache('XenForo_Model_Thread')->canVoteOnPoll($poll, $thread, $forum);
			
			$poll = $this->getModelFromCache('XenForo_Model_Poll')->preparePoll($poll, $canVote);
			$poll['canEdit'] = $this->getModelFromCache('XenForo_Model_Thread')->canEditPoll($poll, $thread, $forum);
		}
		
		return $poll;
	}
	
	public function getPolls($limit)
	{
		return $this->fetchAllKeyed('
			SELECT *
				FROM xf_poll
			WHERE content_type = ?
			ORDER BY poll_id DESC
			LIMIT ?
		', 'poll_id', array('thread', $limit));
	}
}