<?php

class EWRporta2_Widget_UsersActive extends XenForo_Model
{
	public function getCachedData($widget, &$options)
	{
		$cutoff = XenForo_Application::$time - ($options['usersactive_cutoff'] * 3600);
		
		return $this->_getDb()->fetchAll("
			SELECT *
				FROM xf_user
			WHERE last_activity > ?
			ORDER BY last_activity DESC
		", $cutoff);
	}
	
	public function getUncachedData($widget, &$options)
	{
		$canBypassUserPrivacy = $this->getModelFromCache('XenForo_Model_User')->canBypassUserPrivacy();
		
		$this->standardizeViewingUserReference($viewingUser);
		if (!empty($viewingUser['following']))
		{
			$following = explode(',', $viewingUser['following']);
		}
		else
		{
			$following = array();
		}
		
		$count = 0;
		$unseen = array();
		$seen = array();
		
		foreach ($widget['wCached'] AS $key => $record)
		{
			if ($record['user_state'] != 'valid' || !$record['visible'])
			{
				if (!$canBypassUserPrivacy)
				{
					continue;
				}
			}
			
			if (in_array($record['user_id'], $following))
			{
				$record['followed'] = true;
			}
			
			$count++;
			
			if ($count > $options['usersactive_limit'])
			{
				$unseen[] = $record;
			}
			else
			{
				$seen[] = $record;
			}
		}
		
		return array(
			'total' => $count,
			'unseen' => $unseen,
			'seen' => $seen,
		);
	}
}