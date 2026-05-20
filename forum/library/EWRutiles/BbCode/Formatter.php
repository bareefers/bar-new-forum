<?php

class EWRutiles_BbCode_Formatter extends XFCP_EWRutiles_BbCode_Formatter
{
    protected $_tags;

    public function getTags()
    {
        $this->_tags = parent::getTags();

        $this->_tags['notice'] = array(
			'trimLeadingLinesAfter' => 2,
			'callback' => array($this, 'renderTagNotice'),
        );

        return $this->_tags;
    }

	public function renderTagNotice(array $tag, array $rendererStates)
	{
		$content = $this->renderSubTree($tag['children'], $rendererStates);
		$notice = $tag['option'];
		if ($content === '')
		{
			return '';
		}

		if ($this->_view)
		{
			$template = $this->_view->createTemplateObject('EWRutiles_BbCode_Notice', array(
				'content' => $content,
				'notice' => $notice
			));
			return $template->render();
		}
		else
		{
			$name = '<div>' . new XenForo_Phrase('notice') . ($notice ? ': '.$notice : '') . '</div>';
			return '<blockquote>' . $name . $content . '</blockquote>';
		}
	}
}