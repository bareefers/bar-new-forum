<?php
  
class BoardActiveCron_CronEntry_BoardOnOff
{
	public static function boardOn()
	{
		if (XenForo_Application::get('options')->boardActiveCronOn)
		{
			$db = XenForo_Application::getDb();
			
			$db->update('xf_option', array('option_value' => 1), 'option_id = "boardActive"');
			
			XenForo_Model::create('XenForo_Model_Option')->rebuildOptionCache();			
		}
	}
	
	public static function boardOff()
	{
		if (XenForo_Application::get('options')->boardActiveCronOn)
		{
			$db = XenForo_Application::getDb();
			
			$db->update('xf_option', array('option_value' => 0), 'option_id = "boardActive"');
			
			XenForo_Model::create('XenForo_Model_Option')->rebuildOptionCache();
		}
	}
}