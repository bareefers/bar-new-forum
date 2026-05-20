<?php
class Dark_ParseHTML_ViewPublic_Thread_Preview extends XFCP_Dark_ParseHTML_ViewPublic_Thread_Preview
{
	public function renderHtml()
	{
		$response = parent::renderHtml();
		
		$previewLength = XenForo_Application::get('options')->discussionPreviewLength;

		if ($previewLength && !empty($this->_params['post']))
		{
			$formatter = Dark_ParseHTML_BbCode_Formatter_Ritsu::create('Dark_ParseHTML_BbCode_Formatter_Ritsu', array('view' => $this));
			$formatter->user_id = XenForo_Visitor::getUserId();
			$parser = new Dark_ParseHTML_BbCode_Parser($formatter);		

			$this->_params['post']['messageParsed'] = $parser->render($this->_params['post']['message']);
		}
		
		return $response;
	}
}