<?php

class EWRatendo_Model_Birthdays extends XenForo_Model
{
	public function getBirthdays($month, $day)
	{
		$options = XenForo_Application::get('options');
		$cutoff = strtotime('-'.$options->EWRatendo_birthdaycutoff.' months');

		$birthdays = $this->_getDb()->fetchAll("
			SELECT * 
				FROM xf_user
				LEFT JOIN xf_user_profile ON (xf_user_profile.user_id = xf_user.user_id)
				LEFT JOIN xf_user_option ON (xf_user_option.user_id = xf_user.user_id)
			WHERE xf_user_profile.dob_month = ?
				AND xf_user_profile.dob_day = ?
				AND xf_user_option.show_dob_date != '0'
				AND xf_user.is_banned = '0'
				AND xf_user.last_activity > ?
			ORDER BY xf_user.username
		", array($month, $day, $cutoff));

		foreach ($birthdays AS &$user)
		{
			$user = array_merge($user, $this->getModelFromCache('XenForo_Model_UserProfile')->getUserBirthdayDetails($user));
		}

		return $birthdays;
	}

	public function getBirthdayCount($month)
	{
		$options = XenForo_Application::get('options');
		$cutoff = strtotime('-'.$options->EWRatendo_birthdaycutoff.' months');

		$birthdays = $this->fetchAllKeyed("
			SELECT COUNT(*) AS count, xf_user_profile.dob_day
				FROM xf_user
				LEFT JOIN xf_user_profile ON (xf_user_profile.user_id = xf_user.user_id)
				LEFT JOIN xf_user_option ON (xf_user_option.user_id = xf_user.user_id)
			WHERE xf_user_profile.dob_month = ?
				AND xf_user_option.show_dob_date != '0'
				AND xf_user.is_banned = '0'
				AND xf_user.last_activity > ?
			GROUP BY xf_user_profile.dob_day
		", 'dob_day', array($month, $cutoff));

		return $birthdays;
	}
}