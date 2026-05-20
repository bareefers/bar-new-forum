<?php

class EWRatendo_ViewPublic_EventsView extends XenForo_ViewPublic_Base
{
	public function renderHtml()
	{
		if (!empty($this->_params['event']['event_description']))
		{
			$bbCodeParser = new XenForo_BbCode_Parser(XenForo_BbCode_Formatter_Base::create('Base', array('view' => $this)));
			$this->_params['event']['HTML'] = new XenForo_BbCode_TextWrapper($this->_params['event']['event_description'], $bbCodeParser);
		}

		if (!empty($this->_params['event']['event_info']))
		{
			$this->_params['event']['HTML'] = $this->_params['event']['event_info'];
		}
	}
}