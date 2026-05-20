<?php

class EWRporta2_ViewPublic_AuthorEdit extends XenForo_ViewPublic_Base
{
	public function renderHtml()
	{
		$this->_params['editorTemplate'] = XenForo_ViewPublic_Helper_Editor::getEditorTemplate(
			$this, 'author_byline', $this->_params['author']['author_byline']
		);
	}
}