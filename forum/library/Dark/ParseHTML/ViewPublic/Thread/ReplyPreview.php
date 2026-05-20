<?php

class Dark_ParseHTML_ViewPublic_Thread_ReplyPreview extends XFCP_Dark_ParseHTML_ViewPublic_Thread_ReplyPreview
{
	public function renderHtml()
	{
		$response = parent::renderHtml();
		
		$formatter = Dark_ParseHTML_BbCode_Formatter_Ritsu::create('Dark_ParseHTML_BbCode_Formatter_Ritsu', array('view' => $this));
		$formatter->user_id = XenForo_Visitor::getUserId();
			
		$bbCodeParser = new Dark_ParseHTML_BbCode_Parser($formatter);
				
		$this->_params['messageParsed'] = new XenForo_BbCode_TextWrapper($this->_params['message'], $bbCodeParser);
		
		return $response;
	}
}