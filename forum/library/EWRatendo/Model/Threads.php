<?php

class EWRatendo_Model_Threads extends XenForo_Model
{
	public function buildThread($event)
	{
		if (!in_array($event['event_node'], XenForo_Application::get('options')->EWRatendo_eventforums)) { return false; }
		if (!$forum = $this->getModelFromCache('XenForo_Model_Forum')->getForumById($event['event_node']))
		{
			return false;
		}

		$writer = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread');
		$writer->setOption(XenForo_DataWriter_Discussion::OPTION_ADJUST_TITLE_CASE, false);
		$writer->set('user_id', $event['user_id']);
		$writer->set('username', $event['username']);
		$writer->set('title', $event['thread_title']);
		$writer->set('node_id', $forum['node_id']);
			$postWriter = $writer->getFirstMessageDw();
			$postWriter->set('message', $event['event_description']);
		$writer->save();

		$thread = $writer->getMergedData();

		$dw = XenForo_DataWriter::create('EWRatendo_DataWriter_Events');
		$dw->setExistingData($event);
		$dw->set('thread_id', $thread['thread_id']);
		$dw->save();

		return $thread['thread_id'];
	}

	public function updateThread($event)
	{
		if (!$thread = $this->getModelFromCache('XenForo_Model_Thread')->getThreadById($event['thread_id']))
		{
			$dw = XenForo_DataWriter::create('EWRatendo_DataWriter_Events');
			$dw->setExistingData($event);
			$dw->set('thread_id', '0');
			$dw->save();

			return false;
		}

		$writer = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread');
		$writer->setExistingData($thread['thread_id']);
		$writer->setOption(XenForo_DataWriter_Discussion::OPTION_ADJUST_TITLE_CASE, false);
		$writer->set('title', $event['thread_title']);
		
		if (!empty($event['prefix_id']))
		{
			$writer->set('prefix_id', $event['prefix_id']);
		}
		
		$writer->save();

		return true;
	}

	public function closeThread($threadID)
	{
		if (!$thread = $this->getModelFromCache('XenForo_Model_Thread')->getThreadById($threadID))
		{
			return false;
		}

		$visitor = XenForo_Visitor::getInstance();

		$postWriter = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_Post');
		$postWriter->set('user_id', $visitor['user_id']);
		$postWriter->set('username', $visitor['username']);
		$postWriter->set('message', new XenForo_Phrase('auto_create_event_thread_deleted'));
		$postWriter->set('thread_id', $thread['thread_id']);
		$postWriter->save();

		$writer = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread');
		$writer->setExistingData($thread['thread_id']);
		$writer->set('discussion_open', 0);
		$writer->save();

		return true;
	}
}