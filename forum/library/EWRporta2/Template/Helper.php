<?php

class EWRporta2_Template_Helper
{
	public static function getAuthorImage($author)
	{
		$image = XenForo_Application::$externalDataPath . "/authors/$author[user_id].jpg";
		
		if (file_exists($image))
		{
			return XenForo_Application::$externalDataUrl . "/authors/$author[user_id].jpg?$author[author_time]";
		}
	
		return "styles/8wayrun/EWRporta2_author.png";
	}
	
	public static function getFeatureImage($feature)
	{
		$image = XenForo_Application::$externalDataPath . "/features/$feature[thread_id].jpg";
		
		if (file_exists($image))
		{
			return XenForo_Application::$externalDataUrl . "/features/$feature[thread_id].jpg?$feature[feature_time]";
		}
	
		return "styles/default/xenforo/logo.png";
	}
}