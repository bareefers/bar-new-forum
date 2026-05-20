<?php
class DigitalPointAdPositioning_ViewPublic_Conversation_View extends XFCP_DigitalPointAdPositioning_ViewPublic_Conversation_View
{

	public function renderHtml()
	{
		parent::renderHtml();
		
		if (XenForo_Application::get('options')->get('dppa_aftermessage'))
		{
			foreach($this->_params['messages'] as &$message)
			{
				if ($message['isNew'])
				{				
					$message['showAdUnderPost'] = true;
					break;
				}
			}
			$GLOBALS['showAdUnderPostCounter'] = 0;
		}
	}
}