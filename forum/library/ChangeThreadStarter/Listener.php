<?php

class ChangeThreadStarter_Listener
{
	public static function extendControllers($class, array &$extend)
	{
		switch ($class)
		{
			case 'XenForo_ControllerPublic_Thread':
				// Extend thread controller
				$extend[] = 'ChangeThreadStarter_ControllerPublic_Thread';
				
				break;
		}
	}
	
	public static function extendDataWriters($class, array &$extend)
	{
		switch ($class)
		{
			case 'XenForo_DataWriter_Discussion_Thread':
				// Extend thread writer
				$extend[] = 'ChangeThreadStarter_DataWriter_Discussion_Thread';
				
				break;
		}
	}
}