<?php

class EWRporta2_ViewPublic_Thread_ArticlePromote extends XenForo_ViewPublic_Base
{
	public function renderHtml()
	{
		$this->_params['editorTemplate'] = XenForo_ViewPublic_Helper_Editor::getEditorTemplate(
			$this, 'article_excerpt', $this->_params['article']['article_excerpt'],
			array('disable' => XenForo_Application::get('options')->EWRporta2_promote_wysiwyg ? false : true)
		);
	}
}