<?php

class EWRatendo_Listener_Controller
{
    public static function controller($class, array &$extend)
    {
		switch ($class)
		{
			case 'XenForo_ControllerPublic_Forum':
				$extend[] = 'EWRatendo_ControllerPublic_Forum';
				break;
		}
    }
}