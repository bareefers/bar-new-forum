<?php

class EWRporta2_Widget_Birthdays extends XenForo_Model
{
	public function getCachedData($widget, &$options)
	{
		date_default_timezone_set(XenForo_Application::get('options')->guestTimeZone);
		list($month, $day) = explode('.', date('n.j'));
		$cutoff = strtotime('-'.$options['birthdays_cutoff'].' months');
		
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
}