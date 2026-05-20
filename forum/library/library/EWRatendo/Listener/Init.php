<?php

class EWRatendo_Listener_Init
{
	public static function listen(XenForo_Dependencies_Abstract $dependencies, array $data)
	{
		XenForo_DataWriter_User::$usernameChangeUpdates['permanent']['ewratendo_event_username'] = array('EWRatendo_events', 'username', 'user_id');
	}
}