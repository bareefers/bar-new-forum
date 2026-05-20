<?php

class EWRutiles_Model_SpamFinder extends XenForo_Model
{
	public function findAccounts($start, $stop)
	{
		$criteria = XenForo_Application::get('options')->spamUserCriteria;
		$start = ($start - 1) * $stop;

		$spam = $this->_getDb()->fetchAll("
			SELECT xf_user.user_id, xf_user.username, xf_user.message_count, xf_user.like_count, xf_user.register_date,
				xf_user.is_banned, xf_user_profile.homepage, xf_user_profile.signature, xf_user_profile.about
			FROM xf_user
			LEFT JOIN xf_user_profile ON (xf_user_profile.user_id = xf_user.user_id)
			WHERE
			 ".($criteria['message_count'] ? 'xf_user.message_count < '.$criteria['message_count'].' AND' : '')."
			 ".($criteria['like_count'] ? 'xf_user.like_count < '.$criteria['like_count'].' AND' : '')."
			 ".($criteria['register_date'] ? 'xf_user.register_date > '.(XenForo_Application::$time - $criteria['register_date'] * 86400).' AND' : '')."
			 ((homepage LIKE '%URL%' OR signature LIKE '%URL%' OR about LIKE '%URL%') OR
			 (homepage LIKE '%HTTP%' OR signature LIKE '%HTTP%' OR about LIKE '%HTTP%'))
			ORDER BY xf_user.user_id DESC
			LIMIT ?, ?
		", array($start, $stop));

		return $spam;
	}

	public function getAccountsCount()
	{
		$criteria = XenForo_Application::get('options')->spamUserCriteria;
		
        $count = $this->_getDb()->fetchRow("
			SELECT COUNT(*) AS total
			FROM xf_user
			LEFT JOIN xf_user_profile ON (xf_user_profile.user_id = xf_user.user_id)
			WHERE
			 ".($criteria['message_count'] ? 'xf_user.message_count < '.$criteria['message_count'].' AND' : '')."
			 ".($criteria['like_count'] ? 'xf_user.like_count < '.$criteria['like_count'].' AND' : '')."
			 ".($criteria['register_date'] ? 'xf_user.register_date > '.(XenForo_Application::$time - $criteria['register_date'] * 86400).' AND' : '')."
			 ((homepage LIKE '%URL%' OR signature LIKE '%URL%' OR about LIKE '%URL%') OR
			 (homepage LIKE '%HTTP%' OR signature LIKE '%HTTP%' OR about LIKE '%HTTP%'))
		");

		return $count['total'];
	}

	public function findProfiles($start, $stop)
	{
		$criteria = XenForo_Application::get('options')->spamUserCriteria;
		$start = ($start - 1) * $stop;

		$spam1 = $this->_getDb()->fetchAll("
			SELECT xf_user.user_id, xf_user.username, xf_user.message_count, xf_user.like_count, xf_user.register_date,
				xf_user.is_banned, xf_profile_post.post_date, xf_profile_post.message, xf_profile_user.username AS profile_username
			FROM xf_profile_post
			LEFT JOIN xf_user AS xf_user ON (xf_user.user_id = xf_profile_post.user_id)
			LEFT JOIN xf_user AS xf_profile_user ON (xf_profile_user.user_id = xf_profile_post.profile_user_id)
			WHERE
			 ".($criteria['message_count'] ? 'xf_user.message_count < '.$criteria['message_count'].' AND' : '')."
			 ".($criteria['like_count'] ? 'xf_user.like_count < '.$criteria['like_count'].' AND' : '')."
			 ".($criteria['register_date'] ? 'xf_user.register_date > '.(XenForo_Application::$time - $criteria['register_date'] * 86400).' AND' : '')."
			 (xf_profile_post.message LIKE '%URL%' OR xf_profile_post.message LIKE '%HTTP%')
			ORDER BY xf_user.user_id DESC
			LIMIT ?, ?
		", array($start, $stop));

		$spam2 = $this->_getDb()->fetchAll("
			SELECT xf_user.user_id, xf_user.username, xf_user.message_count, xf_user.like_count, xf_user.register_date,
				xf_user.is_banned, xf_profile_post_comment.comment_date AS post_date, xf_profile_post_comment.message,
				xf_profile_user.username AS profile_username
			FROM xf_profile_post_comment
			LEFT JOIN xf_user AS xf_user ON (xf_user.user_id = xf_profile_post_comment.user_id)
			LEFT JOIN xf_profile_post ON (xf_profile_post.profile_post_id = xf_profile_post_comment.profile_post_id)
			LEFT JOIN xf_user AS xf_profile_user ON (xf_profile_user.user_id = xf_profile_post.profile_user_id)
			WHERE
			 ".($criteria['message_count'] ? 'xf_user.message_count < '.$criteria['message_count'].' AND' : '')."
			 ".($criteria['like_count'] ? 'xf_user.like_count < '.$criteria['like_count'].' AND' : '')."
			 ".($criteria['register_date'] ? 'xf_user.register_date > '.(XenForo_Application::$time - $criteria['register_date'] * 86400).' AND' : '')."
			 (xf_profile_post_comment.message LIKE '%URL%' OR xf_profile_post_comment.message LIKE '%HTTP%')
			ORDER BY xf_user.user_id DESC
			LIMIT ?, ?
		", array($start, $stop));

		return array_merge($spam1, $spam2);
	}

	public function getProfilesCount()
	{
		$criteria = XenForo_Application::get('options')->spamUserCriteria;

        $count1 = $this->_getDb()->fetchRow("
			SELECT COUNT(*) AS total
			FROM xf_profile_post
			LEFT JOIN xf_user AS xf_user ON (xf_user.user_id = xf_profile_post.user_id)
			WHERE
			 ".($criteria['message_count'] ? 'xf_user.message_count < '.$criteria['message_count'].' AND' : '')."
			 ".($criteria['like_count'] ? 'xf_user.like_count < '.$criteria['like_count'].' AND' : '')."
			 ".($criteria['register_date'] ? 'xf_user.register_date > '.(XenForo_Application::$time - $criteria['register_date'] * 86400).' AND' : '')."
			 (xf_profile_post.message LIKE '%URL%' OR xf_profile_post.message LIKE '%HTTP%')
		");

        $count2 = $this->_getDb()->fetchRow("
			SELECT COUNT(*) AS total
			FROM xf_profile_post_comment
			LEFT JOIN xf_user AS xf_user ON (xf_user.user_id = xf_profile_post_comment.user_id)
			LEFT JOIN xf_profile_post ON (xf_profile_post.profile_post_id = xf_profile_post_comment.profile_post_id)
			WHERE
			 ".($criteria['message_count'] ? 'xf_user.message_count < '.$criteria['message_count'].' AND' : '')."
			 ".($criteria['like_count'] ? 'xf_user.like_count < '.$criteria['like_count'].' AND' : '')."
			 ".($criteria['register_date'] ? 'xf_user.register_date > '.(XenForo_Application::$time - $criteria['register_date'] * 86400).' AND' : '')."
			 (xf_profile_post_comment.message LIKE '%URL%' OR xf_profile_post_comment.message LIKE '%HTTP%')
		");

		return $count1['total'] + $count2['total'];
	}
}