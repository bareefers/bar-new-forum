<?php

class EWRutiles_Listener_Template
{	
	public static function template_hook($hookName, &$contents, array $hookParams, XenForo_Template_Abstract $template)
	{
		$options = XenForo_Application::get('options');
				
		switch ($hookName)
		{
			case 'account_wrapper_sidebar_settings':
			{
				if ($options->EWRutiles_username_change)
				{
					$params = $template->getParams();
					$hookParams['selectedKey'] = $params['selectedKey'];

					if (XenForo_Permission::hasPermission($params['visitor']['permissions'], 'EWRutiles', 'canUsername'))
					{
						$contents = $template->create('EWRutiles_AccountUsername_Wrapper', $hookParams) . $contents;
					}
				}
				
				break;
			}
			case 'thread_view_pagenav_before':
			{
				$permsModel = XenForo_Model::create('EWRutiles_Model_Perms');
				$perms = $permsModel->getPermissions();

				if ($perms['vote'])
				{
					$params = $template->getParams();
					$hookParams['forum'] = $params['forum'];

					if (!in_array($hookParams['forum']['node_id'], $options->EWRutiles_downvote_disable))
					{
						$votesModel = XenForo_Model::create('EWRutiles_Model_DownVotes');
						$hookParams['downVotes'] = $votesModel->getVoteCount($hookParams['thread']['thread_id']);
						$hookParams['reqVotes'] = $options->EWRutiles_downvote_requirement;
						$hookParams['perms'] = $perms;

						$contents .= $template->create('EWRutiles_DownVoteControl', $hookParams);
					}
				}
				
				break;
			}
			case 'forum_view_pagenav_before':
			{
				$modModel = XenForo_Model::create('XenForo_Model_Moderator');
				$conditions = array('content' => array('node', $hookParams['forum']['node_id']));
				$hookParams['moderators'] = $modModel->getContentModerators($conditions);
				$hookParams['modCount'] = count($hookParams['moderators']);

				$contents .= $template->create('EWRutiles_ForumModerators', $hookParams);
				break;
			}
			case 'help_sidebar_links':
			{
				$params = $template->getParams();
				$hookParams['selected'] = $params['selected'];
				$contents .= $template->create('EWRutiles_StaffList_Help', $hookParams);
				break;
			}
			case 'navigation_tabs_help':
			{
				$contents .= $template->create('EWRutiles_StaffList_NavTabs', $hookParams);
				break;
			}
		}
	}
	
	public static function template_post_render($templateName, &$contents, array &$containerData, XenForo_Template_Abstract $template)
	{
		if ($templateName == 'register_form')
		{
			$field = XenForo_Application::get('options')->EWRutiles_registration_timefield;
		
			$input = '<input type="hidden" value="'.XenForo_Application::$time.'" name="'.$field.'" /></form>';
			$contents = str_replace('</form>', $input, $contents);
		}
	}
}