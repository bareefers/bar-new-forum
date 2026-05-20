<?php

class EWRporta2_ViewPublic_Global extends XFCP_EWRporta2_ViewPublic_Global
{
	public function prepareParams()
	{
		if (empty($this->_templateName)) { return; }
	
		$params = $this->_params;
		$layoutsModel = XenForo_Model::create('EWRporta2_Model_Layouts');
		$fbTemplate = !empty($params['fbTemplate']) ? $params['fbTemplate'] : false;
		$setting = !empty($params['setting']) ? $params['setting'] : false;
		
		if ($layouts = $layoutsModel->getLayoutByTemplate($this->_templateName, $fbTemplate))
		{
			foreach ($layouts AS $layout)
			{
				if (!empty($layout['layout_eval']))
				{
					if (eval('return '.$layout['layout_eval'].';'))
					{
						$found = $layout; break;
					}
					
					continue;
				}
				
				$found = $layout; break;
			}
		}
		
		if (empty($found)) { return; }
		
		$widgets = array(
			'sidebar' => array(),
			'header' => array(),
			'footer' => array(),
			'left' => array(),
			'above' => array(),
			'below' => array(),
			'b-left' => array(),
			'b-right' => array()
		);
	
		$visitor = XenForo_Visitor::getInstance();
		$permsModel = XenForo_Model::create('EWRporta2_Model_Perms');
		$perms = $permsModel->getPermissions();
		$widlinksModel = XenForo_Model::create('EWRporta2_Model_Widlinks');
		$widlinks = $widlinksModel->getWidlinksByLayoutId($found['layout_id']);
		
		if ($perms['arrange'] && $setting && $layout['layout_id'] == 'article_list')
		{
			$widlinks = $widlinksModel->arrangeWidlinks($widlinks, $setting['setting_arrange']);
		}
		
		$bbCodeParser = new XenForo_BbCode_Parser(XenForo_BbCode_Formatter_Base::create('Base', array('view' => $this)));
		$bbCodeOptions = array('viewAttachments' => true);
		
		foreach ($widlinks AS $widlink)
		{
			if ($widlink['widlink_position'] == 'disabled') { continue; }
			
			$widlink['template_params'] = $params;

			if (!empty($widlink['groups']))
			{
				$groups = explode(',', $widlink['groups']);
				$member = false;

				foreach ($groups AS $group)
				{
					if ($visitor->isMemberOf($group)) { $member = true; break; }
				}

				if ($widlink['display'] == 'hide' && $member) { continue; }
				if ($widlink['display'] == 'show' && !$member) { continue; }
			}
			
			if ($viewParams = $widlinksModel->getWidlinkParams($widlink))
			{
				$viewParams['perms'] = $perms;
			
				if (!empty($viewParams['parseCode']))
				{
					if (!empty($viewParams['parseCode']['stripLines']))
					{
						foreach ($viewParams[$viewParams['parseCode']['source']] AS &$message)
						{
							$message = str_ireplace("\n", ' ', $message);
						}
					}
			
					$bbCodeOptions += $viewParams['parseCode'];
					XenForo_ViewPublic_Helper_Message::bbCodeWrapMessages($viewParams[$viewParams['parseCode']['source']], $bbCodeParser, $bbCodeOptions);
				}
			
				$template = $this->createTemplateObject('EWRwidget_'.$widlink['widget_id'], $viewParams);
				$widgets[$widlink['widlink_position']][] = $template;
			}
		}
		
		$this->_params['layout'] = $layout;
		$this->_params['widgets'] = $widgets;
			
		return;
	}
}