<?php

class Dark_ParseHTML_ViewPublic_Thread_ViewPosts extends XFCP_Dark_ParseHTML_ViewPublic_Thread_ViewPosts
{
	public function renderHtml()
	{		
		$formatter = Dark_ParseHTML_BbCode_Formatter_Ritsu::create('Dark_ParseHTML_BbCode_Formatter_Ritsu', array('view' => $this));
		$bbCodeParser = new Dark_ParseHTML_BbCode_Parser($formatter);               
		$bbCodeOptions = array(
			'states' => array(
				'viewAttachments' => isset($this->_params['canViewAttachments']) ? $this->_params['canViewAttachments'] : true
			)
		);
		Dark_ParseHTML_ViewPublic_Helper_Message::bbCodeWrapMessages($this->_params['posts'], $bbCodeParser, $bbCodeOptions);
	}

	public function renderJson()
	{		
		$formatter = Dark_ParseHTML_BbCode_Formatter_Ritsu::create('Dark_ParseHTML_BbCode_Formatter_Ritsu', array('view' => $this));
		$bbCodeParser = new Dark_ParseHTML_BbCode_Parser($formatter);                
		$bbCodeOptions = array(
			'states' => array(
				'viewAttachments' => isset($this->_params['canViewAttachments']) ? $this->_params['canViewAttachments'] : true
			)
		);
		Dark_ParseHTML_ViewPublic_Helper_Message::bbCodeWrapMessages($this->_params['posts'], $bbCodeParser, $bbCodeOptions);

		$output = array('messagesTemplateHtml' => array());

		foreach ($this->_params['posts'] AS $postId => $post)
		{
			$output['messagesTemplateHtml']["#post-$postId"] =
				$this->createTemplateObject('post', array_merge($this->_params, array('post' => $post)))->render();
		}

		$template = $this->createTemplateObject('', array());

		$output['css'] = $template->getRequiredExternals('css');
		$output['js'] = $template->getRequiredExternals('js');

		return XenForo_ViewRenderer_Json::jsonEncodeForOutput($output);
	}
}