<?php
	
class Dark_ParseHTML_ViewPublic_Thread_View extends XFCP_Dark_ParseHTML_ViewPublic_Thread_View
{	
	public function renderHtml()
	{
		
		$formatter = Dark_ParseHTML_BbCode_Formatter_Ritsu::create('Dark_ParseHTML_BbCode_Formatter_Ritsu', array('view' => $this));
		$bbCodeParser = new Dark_ParseHTML_BbCode_Parser($formatter);
		
		$bbCodeOptions = array(
			'states' => array(
				'viewAttachments' => $this->_params['canViewAttachments']
			),
			'contentType' => 'post',
			'contentIdKey' => 'post_id'
		);
		Dark_ParseHTML_ViewPublic_Helper_Message::bbCodeWrapMessages($this->_params['posts'], $bbCodeParser, $bbCodeOptions);
		
		
		// pre v1.2		
		if(XenForo_Application::get('options')->currentVersionId < 1020031){
			
			if (!empty($this->_params['canQuickReply']))
			{
				$this->_params['qrEditor'] = XenForo_ViewPublic_Helper_Editor::getQuickReplyEditor($this, 'message');
			}

		// >= 1.2
		} else {
			
					
			if (!empty($this->_params['canQuickReply']))
			{
				$draft = isset($this->_params['thread']['draft_message']) ? $this->_params['thread']['draft_message'] : '';

				$this->_params['qrEditor'] = XenForo_ViewPublic_Helper_Editor::getQuickReplyEditor(
					$this, 'message', $draft,
					array(
						'autoSaveUrl' => XenForo_Link::buildPublicLink('threads/save-draft', $this->_params['thread']),
						'json' => array('placeholder' => 'reply_placeholder')
					)
				);
			}
		
			
		// end version check
		}
			
	}	
}