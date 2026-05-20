<?php

class EWRporta2_Widget_ArticlesRandom extends XenForo_Model
{
	public function getUncachedData(&$widget, $options)
	{
		$limit = XenForo_Application::$time - 86400 * $options['articlesrandom_limit'];
	
		if (!$article = $this->_getDb()->fetchRow("
			SELECT EWRporta2_articles.*, xf_thread.*, xf_forum.*, xf_user.*, xf_post.message, xf_post.attach_count,
				IF(NOT ISNULL(xf_user.user_id), xf_user.username, xf_thread.username) AS username
			FROM EWRporta2_articles
				INNER JOIN xf_thread ON (xf_thread.thread_id = EWRporta2_articles.thread_id)
				INNER JOIN xf_forum ON (xf_forum.node_id = xf_thread.node_id)
				INNER JOIN xf_post ON (xf_post.post_id = xf_thread.first_post_id)
				LEFT JOIN xf_user ON (xf_user.user_id = xf_thread.user_id)
			WHERE EWRporta2_articles.article_date < ? AND EWRporta2_articles.article_date > ?
				AND xf_thread.discussion_state = 'visible'
			ORDER BY RAND()
		", array(XenForo_Application::$time, $limit)))
		{
			return 'killWidget';
		}
		
		$article = $this->getModelFromCache('EWRporta2_Model_Articles')->parseArticle($article);
		
		if (!$article['canViewContent'] = $this->getModelFromCache('XenForo_Model_Thread')->canViewThreadAndContainer($article, $article))
		{
			return 'killWidget';
		}
		
		$widget['parseCode'] = array(
			'source' => 'wUncached',
			'messageKey' => 'article_excerpt'
		);
		
		return array('article' => $article);
	}
}