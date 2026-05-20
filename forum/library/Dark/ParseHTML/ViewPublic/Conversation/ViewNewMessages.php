<?php

class Dark_ParseHTML_ViewPublic_Conversation_ViewNewMessages extends XFCP_Dark_ParseHTML_ViewPublic_Conversation_ViewNewMessages
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
	}

}