<?php

class EWRporta2_Listener_Template
{
	public static function template_hook($hookName, &$contents, array $hookParams, XenForo_Template_Abstract $template)
	{
		$options = XenForo_Application::get('options');
		$permsModel = XenForo_Model::create('EWRporta2_Model_Perms');
		$perms = $permsModel->getPermissions();
		
		switch ($hookName)
		{
			case 'account_alerts_extra':
			{
				$contents .= $template->create('EWRporta2_Account_Alerts', $template->getParams());
				break;
			}
			case 'thread_view_tools_links':
			{
				if ($perms['promote'])
				{
					$contents .= $template->create('EWRporta2_ThreadView_Tools', $hookParams);
				}
				break;
			}
			case 'thread_create_fields_extra':
			{
				if ($perms['promote'])
				{
					$hookParams['autoPromote'] = in_array($hookParams['forum']['node_id'], $options->EWRporta2_promote_autoforums);
					$contents .= $template->create('EWRporta2_ThreadCreate_Promote', $hookParams);
				}
				break;
			}
		}
	}
}