<?php

class Dark_ParseHTML_ViewPublic_Conversation_View extends XFCP_Dark_ParseHTML_ViewPublic_Conversation_View
{
	public function renderHtml()
	{
	
		$bbCodeParser = new Dark_ParseHTML_BbCode_Parser(Dark_ParseHTML_BbCode_Formatter_Ritsu::create('Dark_ParseHTML_BbCode_Formatter_Ritsu', array('view' => $this)));
		$bbCodeOptions = array(
			'states' => array(
				'viewAttachments' => $this->_params['canViewAttachments']
			)
		);
		Dark_ParseHTML_ViewPublic_Helper_Message::bbCodeWrapMessages($this->_params['messages'], $bbCodeParser, $bbCodeOptions);

			
		// pre v1.2		
		if(XenForo_Application::get('options')->currentVersionId < 1020031){
		

			if (!empty($this->_params['canReplyConversation']))
			{
				$this->_params['qrEditor'] = XenForo_ViewPublic_Helper_Editor::getQuickReplyEditor($this, 'message');
			}
		
		// >= v1.2
		} else {
			
			if (!empty($this->_params['canReplyConversation']))
			{
				$draft = isset($this->_params['conversation']['draft_message']) ? $this->_params['conversation']['draft_message'] : '';

				$this->_params['qrEditor'] = XenForo_ViewPublic_Helper_Editor::getQuickReplyEditor(
					$this, 'message', $draft,
					array(
						'extraClass' => 'NoAutoComplete',  
						'autoSaveUrl' => XenForo_Link::buildPublicLink('conversations/save-draft', $this->_params['conversation']),
						'json' => array('placeholder' => 'reply_placeholder')
					)
				);
			}
		
		// end version check			
		}
		
		
	}
}