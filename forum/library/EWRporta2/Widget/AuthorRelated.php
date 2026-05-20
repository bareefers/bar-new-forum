<?php

class EWRporta2_Widget_AuthorRelated extends XenForo_Model
{
	public function getUncachedData($widget, $options)
	{
		if (empty($widget['tParams']['author']))
		{
			return 'killWidget';
		}
		
		$params = array(
			'author' => $widget['tParams']['author']['user_id'],
			'sort' => $options['authorrelated_sort'],
			'exclude' => array(),
		);
		
		if (!empty($widget['tParams']['articles']))
		{
			$params['exclude'] += array_keys($widget['tParams']['articles']);
		}
		
		if (!empty($widget['tParams']['features']))
		{
			$params['exclude'] += array_keys($widget['tParams']['features']);
		}
		
		if (!empty($widget['tParams']['thread']))
		{
			$params['exclude'][] = $widget['tParams']['thread']['thread_id'];
		}
		
		$articles = $this->getModelFromCache('EWRporta2_Model_Articles')->getArticlesSimple(1, $options['authorrelated_limit'], $params);
		$articles = $this->getModelFromCache('EWRporta2_Model_Articles')->prepareArticles($articles);
		
		return $articles;
	}
}