<?php

class EWRatendo_DataWriter_Discussion_Thread extends XFCP_EWRatendo_DataWriter_Discussion_Thread
{
	protected function _discussionPostSave()
	{
		$response = parent::_discussionPostSave();

		if ($this->get('discussion_state') == 'deleted')
		{
			$this->deleteEvent();
		}

		return $response;
	}

	protected function _discussionPostDelete()
	{
		$response = parent::_discussionPostDelete();

		$this->deleteEvent();

		return $response;
	}

	protected function deleteEvent()
	{
		$options = XenForo_Application::get('options');
		$threadId = $this->get('thread_id');
		$forumId = $this->get('node_id');

		if (in_array($forumId, $options->EWRatendo_eventforums))
		{
			if ($event = $this->getModelFromCache('EWRatendo_Model_Events')->getEventByThread($threadId))
			{
				$this->getModelFromCache('EWRatendo_Model_Events')->deleteEvent($event);
			}
		}

		return true;
	}
}