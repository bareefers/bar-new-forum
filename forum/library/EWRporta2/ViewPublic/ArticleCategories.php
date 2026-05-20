<?php

class EWRporta2_ViewPublic_ArticleCategories extends XenForo_ViewPublic_Base
{
	public function renderJson()
	{
		$output = $this->_renderer->getDefaultOutputArray(get_class($this), $this->_params, $this->_templateName);
		
		$output['_redirectMessage'] = new XenForo_Phrase('redirect_changes_saved_successfully');
		$output['thread_id'] = $this->_params['thread']['thread_id'];
		
		return XenForo_ViewRenderer_Json::jsonEncodeForOutput($output);
	}
}