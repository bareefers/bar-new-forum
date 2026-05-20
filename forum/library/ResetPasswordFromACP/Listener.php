<?php

class ResetPasswordFromACP_Listener
{
	public static function templateHook($hookName, &$contents, array $hookParams, XenForo_Template_Abstract $template)
	{
		if ($hookName == 'admin_user_edit_panes')
		{
			$contents = str_replace('id="ctrl_password" />', 'id="ctrl_password" /><label>' . new XenForo_Phrase('reset_from_acp_reset_password') . '? <input type="checkbox" name="reset_password" value="1" /></label>', $contents);
		}
	}
	
	public static function extendControllers($class, array &$extend)
	{
		if ($class == 'XenForo_ControllerAdmin_User')
		{
			$extend[] = 'ResetPasswordFromACP_ControllerAdmin_User';
		}
	}
}