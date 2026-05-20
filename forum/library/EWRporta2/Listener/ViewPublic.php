<?php

class EWRporta2_Listener_ViewPublic
{
    public static function viewPublic($class, array &$extend)
    {
		if ($class == 'XenForo_ViewPublic_Thread_ViewPosts')
		{
			$extend[] = 'EWRporta2_ViewPublic_Thread_ViewPosts';
		}
	
		$options = XenForo_Application::get('options');
		$classes = array();
		
		foreach ($options->EWRporta2_classes AS $key => $value)
		{
			$classes[] = $key;
		}
		
		foreach (explode("\n", $options->EWRporta2_views) AS $value)
		{
			if (!empty($value))
			{
				$classes[] = $value;
			}
		}
		
		if (in_array($class, $classes))
		{
			$extend[] = 'EWRporta2_ViewPublic_Global';
		}
    }
}