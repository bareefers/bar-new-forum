<?php

class EWRporta2_Widget_Attachments extends XenForo_Model
{
	public function getCachedData($widget, &$options)
	{
		$forumCheck = '';
		if ($options['attachments_source'][0] != 0)
		{
			$forums = implode(',', $options['attachments_source']);
			$forumCheck = "AND xf_thread.node_id IN (".$forums.")";
		}

		return $this->getAttachments(1, $options['attachments_limit'], $forumCheck);
	}
	
	public function getAttachments($start, $stop, $forumCheck = '')
	{
		$start = ($start - 1) * $stop;
		
		$attachments = $this->_getDb()->fetchAll("
			SELECT xf_attachment.*, xf_attachment_data.*, xf_user.*, xf_post.post_id
			FROM xf_attachment
				INNER JOIN xf_attachment_data ON (xf_attachment_data.data_id = xf_attachment.data_id AND xf_attachment_data.thumbnail_width > 0)
				INNER JOIN xf_post ON (xf_post.post_id = xf_attachment.content_id AND xf_attachment.content_type = 'post')
				INNER JOIN xf_user ON (xf_user.user_id = xf_post.user_id)
				INNER JOIN xf_thread ON (xf_thread.thread_id = xf_post.thread_id)
			WHERE xf_post.message_state = 'visible' AND xf_thread.discussion_state = 'visible'
				$forumCheck
			ORDER BY xf_attachment.attach_date DESC
			LIMIT ?, ?
		", array($start, $stop));
		
		foreach ($attachments AS &$attachment)
		{
			$attachment = $this->getModelFromCache('XenForo_Model_Attachment')->prepareAttachment($attachment);
			$attachment['alignment'] = $attachment['thumbnail_width'] > $attachment['thumbnail_height'] ? 'Vert' : 'Horz';
			$attachment['canView'] = $this->getModelFromCache('XenForo_Model_Attachment')->canViewAttachment($attachment);
		}
		
		return $attachments;
	}
	
	public function getAttachmentsCount($forumCheck = '')
	{
		$count = $this->_getDb()->fetchRow("
			SELECT COUNT(*) AS total
			FROM xf_attachment
				INNER JOIN xf_attachment_data ON (xf_attachment_data.data_id = xf_attachment.data_id AND xf_attachment_data.thumbnail_width > 0)
				INNER JOIN xf_post ON (xf_post.post_id = xf_attachment.content_id AND xf_attachment.content_type = 'post')
				INNER JOIN xf_thread ON (xf_thread.thread_id = xf_post.thread_id)
			WHERE xf_post.message_state = 'visible' AND xf_thread.discussion_state = 'visible'
				$forumCheck
		");

		return $count['total'];
	}
}