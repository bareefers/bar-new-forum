<?php

class EWRporta2_Widget_StatusLegacy extends XenForo_Model
{
	public function getCachedData($widget, &$options)
	{
		return $this->_getDb()->fetchAll("
			SELECT *, IF(xf_user.username IS NULL, xf_profile_post.username, xf_user.username) AS username
				FROM xf_profile_post
				INNER JOIN (
					SELECT profile_post_id FROM xf_profile_post
					WHERE message_state = 'visible'	AND profile_user_id = user_id
					ORDER BY post_date DESC
				) AS post2 USING (profile_post_id)
				LEFT JOIN xf_user ON (xf_user.user_id = xf_profile_post.user_id)
			GROUP BY profile_user_id
			ORDER BY post_date DESC
			LIMIT ?
		", $options['statuslegacy_limit']);
	}
}