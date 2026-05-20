<?php

class ExUp_Install_Construct
{
	public static function install($existingAddOn, $addOnData)
	{
		if (XenForo_Application::$versionId < 1020070)
		{
			throw new XenForo_Exception('This Add-On requires XenForo version 1.2.0 or newer.');
		}
		
		$db = XenForo_Application::get('db');
		
		if ($existingAddOn) // upgrade
		{
			//$versionId = $existingAddOn['version_id'];
		}
		else // fresh install
		{
			$addOnModel = XenForo_Model::create('XenForo_Model_AddOn');
			
			$upgrades = $addOnModel->fetchAllKeyed('
				SELECT *
				FROM xf_user_upgrade
			', 'user_upgrade_id');
			
			if (!empty($upgrades))
			{
				foreach ($upgrades AS $upgrade)
				{
					$records = $addOnModel->fetchAllKeyed('
						SELECT *
						FROM xf_user_upgrade_active
						WHERE user_upgrade_id = ?
					', 'user_upgrade_record_id', $upgrade['user_upgrade_id']);
					
					if (!empty($records))
					{
						// must update records one by one rather than in batches as other add-ons may have added their own info to the extra array of certain records
						foreach ($records AS $record)
						{
							$extra = unserialize($record['extra']);
							
							$extra['extra_group_ids'] = $upgrade['extra_group_ids'];
							$extra['recurring'] = $upgrade['recurring'];
							
							// VB import does not seem to save these extra values so must do so now
							if (!isset($extra['cost_amount']))
								$extra['cost_amount'] = $upgrade['cost_amount'];
							if (!isset($extra['cost_currency']))
								$extra['cost_currency'] = $upgrade['cost_currency'];
							if (!isset($extra['length_amount']))
								$extra['length_amount'] = $upgrade['length_amount'];
							if (!isset($extra['length_unit']))
								$extra['length_unit'] = $upgrade['length_unit'];
							
							$extra = serialize($extra);
					
							// save the current user groups so we can later know if the upgrade details have changed.
							// we use this in order to know whether to display the 'Extend Upgrade' button.
							$db->update('xf_user_upgrade_active',
								array('extra' => $extra),
								'user_upgrade_record_id = ' . $db->quote($record['user_upgrade_record_id'])
							);
						}
					}
				}
			}
			
			$db->query("ALTER TABLE xf_user_upgrade_active ADD notified_date INT UNSIGNED NOT NULL DEFAULT 0");
			
			// content type
			$db->query("
				INSERT IGNORE INTO xf_content_type (content_type, addon_id)
				VALUES ('exup', 'ExUp')
			");
			
			// alert handler
			$db->query("
				INSERT IGNORE INTO xf_content_type_field (content_type, field_name, field_value)
				VALUES ('exup', 'alert_handler_class', 'ExUp_AlertHandler_ExUp')
			");
			
			XenForo_Model::create('XenForo_Model_ContentType')->rebuildContentTypeCache();
		}
	}

	public static function uninstall()
	{	
		$db = XenForo_Application::get('db');
		
		$db->query("ALTER TABLE xf_user_upgrade_active DROP notified_date");
		
		$db->query("DELETE FROM xf_user_alert WHERE content_type = 'exup'");
		
		$db->query("DELETE FROM xf_content_type_field WHERE content_type = 'exup'");
		
		$db->query("DELETE FROM xf_content_type	WHERE addon_id = 'ExUp'");
		
		XenForo_Model::create('XenForo_Model_ContentType')->rebuildContentTypeCache();
	}
}