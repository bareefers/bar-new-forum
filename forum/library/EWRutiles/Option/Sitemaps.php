<?php

abstract class EWRutiles_Option_Sitemaps
{
	public static function verifyOption(array &$options, XenForo_DataWriter $dw, $fieldName)
	{
		if (!XenForo_Application::autoload('EWRmedio_ControllerPublic_Media'))
		{
			$options['media'] = 0;
		}

		if (!XenForo_Application::autoload('EWRcarta_ControllerPublic_Wiki'))
		{
			$options['wiki'] = 0;
		}

		return true;
	}
}