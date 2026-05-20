<?php

class Dark_ParseHTML_ViewPublic_Conversation_ViewMessage extends XFCP_Dark_ParseHTML_ViewPublic_Conversation_ViewMessage
{
	public function renderHtml()
	{
		$response = parent::renderHtml();
		
		$bbCodeParser = new Dark_ParseHTML_BbCode_Parser(Dark_ParseHTML_BbCode_Formatter_Ritsu::create('Dark_ParseHTML_BbCode_Formatter_Ritsu', array('view' => $this)));
		$bbCodeOptions = array(
			'states' => array(
				'viewAttachments' => $this->_params['canViewAttachments']
			)
		);

		$this->_params['message']['messageHtml'] = Dark_ParseHTML_ViewPublic_Helper_Message::getBbCodeWrapper(
			$this->_params['message'], $bbCodeParser, $bbCodeOptions);
			
		return $response;
	}
}