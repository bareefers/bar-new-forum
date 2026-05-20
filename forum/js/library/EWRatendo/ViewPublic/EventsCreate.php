<?php

class EWRatendo_ViewPublic_EventsCreate extends XenForo_ViewPublic_Base
{
	public function renderHtml()
	{
		$this->_params['editorTemplate'] = XenForo_ViewPublic_Helper_Editor::getEditorTemplate(
			$this, 'event_description'
		);
	}
}