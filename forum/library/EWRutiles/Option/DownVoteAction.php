<?php

abstract class EWRutiles_Option_DownVoteAction
{
	public static function renderOption(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
	{
		$value = $preparedOption['option_value'];

		$editLink = $view->createTemplateObject('option_list_option_editlink', array(
			'preparedOption' => $preparedOption,
			'canEditOptionDefinition' => $canEdit
		));

		$forumOptions = XenForo_Option_NodeChooser::getNodeOptions(
			$value['node_id'],
			sprintf('(%s)', new XenForo_Phrase('unspecified')),
			'Forum'
		);

		return $view->createTemplateObject('option_template_downVoteAction_EWRutiles', array(
			'fieldPrefix' => $fieldPrefix,
			'listedFieldName' => $fieldPrefix . '_listed[]',
			'preparedOption' => $preparedOption,
			'formatParams' => $forumOptions,
			'editLink' => $editLink
		));
	}

	public static function verifyOption(array &$options, XenForo_DataWriter $dw, $fieldName)
	{
		if ($options['action'] == 'move')
		{
			if ($options['node_id'])
			{
				if ($node = self::_getNodeModel()->getNodeById($options['node_id']))
				{
					if ($node['node_type_id'] === 'Forum')
					{
						return true;
					}
				}
			}

			$dw->error(new XenForo_Phrase('please_specify_valid_spam_forum'), $fieldName);
			return false;
		}

		return true;
	}

	protected static function _getNodeModel()
	{
		return XenForo_Model::create('XenForo_Model_Node');
	}
}