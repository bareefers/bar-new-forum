<?php

class EWRutiles_Listener_Controller
{
    public static function controller($class, array &$extend)
    {
		$options = XenForo_Application::get('options');
		
		switch ($class)
		{
			case 'XenForo_ControllerAdmin_User':
				if ($options->EWRutiles_username_change) { $extend[] = 'EWRutiles_ControllerAdmin_UserXF'; }
				break;
			case 'XenForo_ControllerPublic_Account':
				if ($options->EWRutiles_username_change) { $extend[] = 'EWRutiles_ControllerPublic_Account'; }
				break;
			case 'XenForo_ControllerPublic_Help':
				$extend[] = 'EWRutiles_ControllerPublic_Help';
				break;
			case 'XenForo_ControllerPublic_Misc':
				$extend[] = 'EWRutiles_ControllerPublic_Misc';
				break;
			case 'XenForo_ControllerPublic_Register':
				$extend[] = 'EWRutiles_ControllerPublic_Register';
				break;
			case 'XenForo_ControllerPublic_Thread':
				$extend[] = 'EWRutiles_ControllerPublic_Thread';
				break;
		}
    }
}