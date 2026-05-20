<?php

class EWRporta2_Widget_ArticlesSimple extends XenForo_Model
{
	public function getUncachedData(&$widget, $options)
	{
		$params = array(
			'category' => $options['articlessimple_category'][0] != 0 ? $options['articlessimple_category'] : false,
			'author' => $options['articlessimple_author'][0] != 0 ? $options['articlessimple_author'] : false,
		);
		
		$articles = $this->getModelFromCache('EWRporta2_Model_Articles')->getArticles(1, $options['articlessimple_limit'], $params);
		$articles = $this->getModelFromCache('EWRporta2_Model_Articles')->prepareArticles($articles);
		
		$widget['parseCode'] = array(
			'source' => 'wUncached',
			'messageKey' => 'article_excerpt',
			'stripLines' => $options['articlessimple_stripnl'],
		);
		
		return $articles;
	}
}