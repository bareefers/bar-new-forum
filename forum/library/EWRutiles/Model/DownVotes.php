<?php

class EWRutiles_Model_DownVotes extends XenForo_Model
{
	public function getFullCount($time)
	{
		if (!$count = $this->_getDb()->fetchRow("
			SELECT COUNT(xf_thread.thread_id) AS total
				FROM xf_thread
				INNER JOIN EWRutiles_downvotes ON (EWRutiles_downvotes.thread_id = xf_thread.thread_id)
			WHERE EWRutiles_downvotes.vote_date > ?
			GROUP BY xf_thread.thread_id
		", $time))
		{
			return false;
		}

		return $count['total'];
	}

	public function getFullVotes($time, $start, $stop)
	{
		$start = ($start - 1) * $stop;

		if (!$threads = $this->_getDb()->fetchAll("
			SELECT xf_thread.*, COUNT(EWRutiles_downvotes.vote_id) AS count, GROUP_CONCAT(xf_user.user_id) AS users, MAX(EWRutiles_downvotes.vote_date) AS vote_date
				FROM xf_thread
				INNER JOIN EWRutiles_downvotes ON (EWRutiles_downvotes.thread_id = xf_thread.thread_id)
				LEFT JOIN xf_user ON (xf_user.user_id = EWRutiles_downvotes.user_id)
			WHERE EWRutiles_downvotes.vote_date > ?
			GROUP BY xf_thread.thread_id
			ORDER BY vote_date DESC
			LIMIT ?, ?
		", array($time, $start, $stop)))
		{
			return array(array(), array());
		}

		$userIDs = array();
		foreach ($threads AS &$thread)
		{
			$thread['users'] = explode(',', $thread['users']);
			$userIDs = array_merge($userIDs, $thread['users']);
		}

		$users = $this->getModelFromCache('XenForo_Model_User')->getUsersByIds($userIDs);

		return array($threads, $users);
	}

	public function getVoteByID($voteID)
	{
		if (!$vote = $this->_getDb()->fetchRow("SELECT * FROM EWRutiles_downvotes WHERE vote_id = ?", $voteID))
		{
			return false;
		}

		return $vote;
	}

	public function getVotesByThread($threadID)
	{
		$timeNow = XenForo_Application::$time;
		$timeExp = $timeNow - (XenForo_Application::get('options')->EWRutiles_downvote_expiration * 3600);

		if (!$votes = $this->_getDb()->fetchAll("
			SELECT *
				FROM EWRutiles_downvotes
				LEFT JOIN xf_user ON (xf_user.user_id = EWRutiles_downvotes.user_id)
			WHERE EWRutiles_downvotes.thread_id = ?
				AND EWRutiles_downvotes.vote_date > ?
			ORDER BY vote_date DESC
		", array($threadID, $timeExp)))
		{
			return false;
		}

		return $votes;
	}

	public function getVoteCount($threadID)
	{
		$timeNow = XenForo_Application::$time;
		$timeExp = $timeNow - (XenForo_Application::get('options')->EWRutiles_downvote_expiration * 3600);

        $count = $this->_getDb()->fetchRow("
			SELECT COUNT(*) AS total
				FROM EWRutiles_downvotes
			WHERE thread_id = ?
				AND vote_date > ?
		", array($threadID, $timeExp));

		return $count['total'];
	}

	public function getVoteByUser($userID, $threadID)
	{
		if (!$vote = $this->_getDb()->fetchRow("
			SELECT *
				FROM EWRutiles_downvotes
			WHERE user_id = ? AND thread_id = ?
		", array($userID, $threadID)))
		{
			return false;
		}

		return $vote;
	}

	public function updateVote($input)
	{
		$dw = XenForo_DataWriter::create('EWRutiles_DataWriter_DownVotes');
		if (!empty($input['vote_id']) && $vote = $this->getVoteByID($input['vote_id']))
		{
			$dw->setExistingData($vote);
		}
		$dw->set('thread_id', $input['thread_id']);
		$dw->save();

		$downVotes = $this->getVoteCount($input['thread_id']);

		if ($downVotes >= XenForo_Application::get('options')->EWRutiles_downvote_requirement)
		{
			$this->downVoteThread($input['thread_id']);
		}

		return true;
	}

	public function downVoteThread($threadID)
	{
		$options = XenForo_Application::get('options');
		$action = $options->EWRutiles_downvote_action;

		$threadWriter = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread', XenForo_DataWriter::ERROR_SILENT);
		$threadWriter->setExistingData($threadID);

		$node = $threadWriter->get('node_id');
		$open = $threadWriter->get('discussion_open');
		$state = $threadWriter->get('discussion_state');

		if ($options->EWRutiles_downvote_user && $open && $state == 'visible' ||	($action['action'] == 'move' && $action['node_id'] == $node))
		{
			if ($user = $this->getModelFromCache('XenForo_Model_User')->getUserById($options->EWRutiles_downvote_user))
			{
				$writer = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_Post');
				$writer->set('user_id', $user['user_id']);
				$writer->set('username', $user['username']);
				$writer->set('message', new XenForo_Phrase('downvote_thread_deleted'));
				$writer->set('thread_id', $threadID);
				$writer->save();
			}
		}

		switch ($action['action'])
		{
			case 'lock':	$threadWriter->set('discussion_open', 0);											break;
			case 'mod':		$threadWriter->set('discussion_state', 'moderated');								break;
			case 'move':	$threadWriter->set('node_id', $action['node_id']);									break;
			case 'soft':	$this->getModelFromCache('XenForo_Model_Thread')->deleteThread($threadID, 'soft');	return true;
		}

		$threadWriter->save();

		return true;
	}

	public function deleteVotes()
	{
		$options = XenForo_Application::get('options');

		$timeNow = XenForo_Application::$time;
		$timeExp = $timeNow - ($options->EWRutiles_downvote_expiration * 3600 * 2);

		$this->_getDb()->query("
			DELETE FROM EWRutiles_downvotes
			WHERE vote_date < ?
		", $timeExp);

		return true;
	}
}