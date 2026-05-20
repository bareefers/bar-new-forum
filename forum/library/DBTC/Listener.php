<?php

class DBTC_Listener
{

	public static function Overview($class, array &$extend)
	{
		if  ($class == 'XenForo_ControllerPublic_Overview')
		{
			$extend[] = 'DBTC_ControllerPublic_Overview';
		}
	}	

	public static function Forum($class, array &$extend)
	{
		if  ($class == 'XenForo_ControllerPublic_Forum')
		{
			$extend[] = 'DBTC_ControllerPublic_Forum';
		}
	}	
	
	public static function _discussionPostSave($class, array &$extend)
	{
	if  ($class == 'XenForo_DataWriter_Discussion_Thread')
		{
			$extend[] = 'DBTC_DataWriter_Discussion_Thread';
		}
	}
	
	public static function Thread($class, array &$extend)
	{
	if  ($class == 'XenForo_ControllerPublic_Thread')
		{
			$extend[] = 'DBTC_ControllerPublic_Thread';
		}
	}
	
	/**
    * Listen to the "init_dependencies" code event.
    *
    * @param XenForo_Dependencies_Abstract $dependencies
    * @param array $data
    */
	
	public static function init(XenForo_Dependencies_Abstract $dependencies, array $data)
    {    
        //Get the static variable $helperCallbacks and add a new item in the array.
		/*
        XenForo_Template_Helper_Core::$helperCallbacks += array(
			'dbtcTree' => array('DBTC_Helpers_Display', 'renderDbtcTree')
		);*/
		XenForo_Template_Helper_Core::$helperCallbacks['dbtctree'] = array('DBTC_Helpers_Display', 'renderTree');
		XenForo_Template_Helper_Core::$helperCallbacks['dbtcstats'] = array('DBTC_Helpers_Display', 'renderStats');
		XenForo_Template_Helper_Core::$helperCallbacks['dbtcheader'] = array('DBTC_Helpers_Display', 'renderHeader');
		// Zend_Debug::dump('testing! LISTENER');
	}
}
?>