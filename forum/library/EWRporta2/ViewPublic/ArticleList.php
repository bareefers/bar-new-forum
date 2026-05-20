<?php

class EWRporta2_ViewPublic_ArticleList extends XenForo_ViewPublic_Base
{
	public function renderHtml()
	{
		$options = XenForo_Application::get('options');
		$attachModel = XenForo_Model::create('XenForo_Model_Attachment');
		$bbCodeParser = new XenForo_BbCode_Parser(XenForo_BbCode_Formatter_Base::create('Base', array('view' => $this)));
		$bbCodeOptions = array('viewAttachments' => true);

		if (!empty($this->_params['author']))
		{
			$this->_params['author']['html'] = new XenForo_BbCode_TextWrapper($this->_params['author']['author_byline'], $bbCodeParser);
		}
		
		foreach($this->_params['articles'] AS &$article)
		{
			if ($options->EWRporta2_articles_stripnl)
			{
				$article['article_excerpt'] = str_ireplace("\n", ' ', $article['article_excerpt']);
			}
		
			if (stripos($article['article_excerpt'], '[/attach]') !== false && $article['attach_count'])
			{
				$article['attachments'] = $attachModel->getAttachmentsByContentId('post', $article['first_post_id']);
				$article['attachments'] = $attachModel->prepareAttachments($article['attachments']);
			}
			
			$bbCodeOptions['attachments'] = !empty($article['attachments']) ? $article['attachments'] : null;
			$article['messageHtml'] = new XenForo_BbCode_TextWrapper($article['article_excerpt'], $bbCodeParser, $bbCodeOptions);
		}
	}
}