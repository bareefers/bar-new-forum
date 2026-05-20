<?php
  
class Dark_ParseHTML_BbCode_Formatter_AutoLink extends XFCP_Dark_ParseHTML_BbCode_Formatter_AutoLink
{	
	public $user_id;
	
	public function getTags()
	{
		if ($this->_tags !== null)
		{
			return $this->_tags;
		}
		
		$tags = parent::getTags();

		$tags['parsehtml'] = array(
				'hasOption' => false,
				'plainChildren' => true,
				'stopSmilies' => true,
				'stopLineBreakConversion' => true,
				'trimLeadingLinesAfter' => 1,
				'callback' => array($this, 'autoLinkTag')
			);

		
		return $tags;
	}
	
	public function renderTagParseHtml(array $tag, array $rendererStates)
	{
		return $this->renderTagUnparsed($tag, $rendererStates);
	}
	
	public function __construct()
	{
		parent::__construct();
		$this->_disableAutoLink[]='parsehtml';
	}	
}
