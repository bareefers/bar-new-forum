<?php

class EWRporta2_ViewAdmin_WidlinkSave extends XenForo_ViewAdmin_Base
{
	public function renderJson()
	{
		$output = $this->_renderer->getDefaultOutputArray(get_class($this), $this->_params, $this->_templateName);
		$output['_redirectMessage'] = new XenForo_Phrase('redirect_changes_saved_successfully');
		$output['widlink_id'] = $this->_params['widlink']['widlink_id'];
		$output['widlink_title'] = $this->_params['widlink']['widlink_title'];
		
		return XenForo_ViewRenderer_Json::jsonEncodeForOutput($output);
	}
}