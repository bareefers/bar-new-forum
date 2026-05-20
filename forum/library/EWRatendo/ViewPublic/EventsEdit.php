<?php

class EWRatendo_ViewPublic_EventsEdit extends XenForo_ViewPublic_Base
{
	public function renderHtml()
	{
		if (!$this->_params['event']['thread_id'])
		{
			$this->_params['editorTemplate'] = XenForo_ViewPublic_Helper_Editor::getEditorTemplate(
				$this, 'event_description', $this->_params['event']['event_description']
			);
		}
	}
}