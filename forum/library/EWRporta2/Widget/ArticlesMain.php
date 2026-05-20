<?php

class EWRporta2_Widget_ArticlesMain extends XenForo_Model
{
	public function getUncachedData(&$widget, $options)
	{
		$params = array(
			'category' => $options['articlesmain_category'][0] != 0 ? $options['articlesmain_category'] : false,
			'author' => $options['articlesmain_author'][0] != 0 ? $options['articlesmain_author'] : false,
		);
		
		$count = $this->getModelFromCache('EWRporta2_Model_Articles')->getArticlesCount($params);
		$articles = $this->getModelFromCache('EWRporta2_Model_Articles')->getArticles(1, $options['articlesmain_limit'], $params);
		$articles = $this->getModelFromCache('EWRporta2_Model_Articles')->prepareArticles($articles);
		
		$widget['wOptions']['count'] = $count;
		$widget['parseCode'] = array(
			'source' => 'wUncached',
			'messageKey' => 'article_excerpt'
		);
		
		return $articles;
	}
}