<?php

class EWRporta2_Widget_ViewPublic_ArticlesMain extends XenForo_ViewPublic_Base
{
	public function renderHtml()
	{
		$options = XenForo_Application::get('options');
		$attachModel = XenForo_Model::create('XenForo_Model_Attachment');
		$bbCodeParser = new XenForo_BbCode_Parser(XenForo_BbCode_Formatter_Base::create('Base', array('view' => $this)));
		$bbCodeOptions = array('viewAttachments' => true);
		
		foreach($this->_params['wUncached'] AS &$article)
		{
			if ($options->EWRporta2_articles_stripnl)
			{
				$article['article_excerpt'] = str_ireplace("\n", ' ', $article['article_excerpt']);
			}
			
			if ($article['attach_count'])
			{
				$article['attachments'] = $attachModel->getAttachmentsByContentId('post', $article['first_post_id']);
				$article['attachments'] = $attachModel->prepareAttachments($article['attachments']);
			}
			
			$bbCodeOptions['attachments'] = !empty($article['attachments']) ? $article['attachments'] : null;
			$article['messageHtml'] = new XenForo_BbCode_TextWrapper($article['article_excerpt'], $bbCodeParser, $bbCodeOptions);
		}
	}
}