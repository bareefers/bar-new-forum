<?php

class EWRporta2_Model_Settings extends XenForo_Model
{
	public function getSettingById($settingID)
	{
		if (!$setting = $this->_getDb()->fetchRow("
			SELECT *
			FROM EWRporta2_settings
			WHERE user_id = ?
		", $settingID))
		{
			return false;
		}
		
		$setting['setting_filter'] = @unserialize($setting['setting_filter']);
		$setting['setting_arrange'] = @unserialize($setting['setting_arrange']);
		$setting['setting_options'] = @unserialize($setting['setting_options']);

		return $setting;
	}
	
	public function updateSetting($input)
	{
		$dw = XenForo_DataWriter::create('EWRporta2_DataWriter_Settings');

		if (!empty($input['user_id']) && $setting = $this->getSettingById($input['user_id']))
		{
			$dw->setExistingData($setting);
		}
		
		$dw->bulkSet($input);
		$dw->save();
		
		return $dw->getMergedData();
	}
}