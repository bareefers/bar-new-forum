<?php

class ExUp_Listener_Cog
{
	// controller calss extensions
	public static function loadClassControllerAdminUserUpgrade($class, array &$extend)
	{
		$extend[] = 'ExUp_ControllerAdmin_UserUpgrade';	
	}
	
	// controller calss extensions
	public static function loadClassUserUpgradeProcessorPayPal($class, array &$extend)
	{
		$extend[] = 'ExUp_UserUpgradeProcessor_PayPal';	
	}
	
	// model calss extensions
	public static function loadClassModelUserUpgrade($class, array &$extend)
	{
		$extend[] = 'ExUp_Model_UserUpgrade';	
	}
}