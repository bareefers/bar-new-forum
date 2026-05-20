<?php

class EWRporta2_Listener_Controller
{
    public static function controller($class, array &$extend)
    {
		switch ($class)
		{
			case 'XenForo_ControllerPublic_Forum':
				$extend[] = 'EWRporta2_ControllerPublic_Forum';
				break;
			case 'XenForo_ControllerPublic_Thread':
				$extend[] = 'EWRporta2_ControllerPublic_Thread';
				break;
		}
    }
}