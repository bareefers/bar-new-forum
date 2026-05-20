<?php

class EWRporta2_ViewPublic_AuthorList extends XenForo_ViewPublic_Base
{
	public function renderHtml()
	{
		$bbCodeParser = new XenForo_BbCode_Parser(XenForo_BbCode_Formatter_Base::create('Base', array('view' => $this)));
		
		foreach($this->_params['authors']['active'] AS &$author)
		{
			$author['html'] = new XenForo_BbCode_TextWrapper($author['author_byline'], $bbCodeParser);
		}
	}
}