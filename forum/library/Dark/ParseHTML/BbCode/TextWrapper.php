<?php


class Dark_ParseHTML_BbCode_TextWrapper extends XenForo_BbCode_TextWrapper
{
	public $user_id;
	
	public function __toString()
	{
		$this->_parser->_formatter->user_id = $this->user_id;
		//return $this->_parser->render($this->_text, $this->_extraStates);
		return parent::__toString();
	}
}
