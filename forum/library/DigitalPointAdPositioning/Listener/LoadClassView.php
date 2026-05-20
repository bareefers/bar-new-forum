<?php
class DigitalPointAdPositioning_Listener_LoadClassView
{
	public static function loadClassListener($class, &$extend)
	{
		if ($class === 'XenForo_ViewPublic_Thread_View')
		{
			$extend[] = 'DigitalPointAdPositioning_ViewPublic_Thread_View';
		}
		elseif ($class === 'XenForo_ViewPublic_Conversation_View')
		{
			$extend[] = 'DigitalPointAdPositioning_ViewPublic_Conversation_View';
		}
	}
}