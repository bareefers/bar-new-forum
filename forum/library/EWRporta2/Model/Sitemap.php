<?php

class EWRporta2_Model_Sitemap extends XenForo_Model
{
	public function getArticles($params = array())
	{
		$options = XenForo_Application::get('options');
		list($joins, $wheres) = $this->getModelFromCache('EWRporta2_Model_Articles')->getArticleParams($params);
		
		$articles = $this->fetchAllKeyed("
			SELECT EWRporta2_articles.*, xf_thread.*, xf_post.*, xf_user.*
			FROM EWRporta2_articles
				INNER JOIN xf_thread ON (xf_thread.thread_id = EWRporta2_articles.thread_id)
				INNER JOIN xf_post ON (xf_post.post_id = xf_thread.first_post_id)
				LEFT JOIN xf_user ON (xf_user.user_id = xf_thread.user_id)
				$joins
			WHERE EWRporta2_articles.article_date < ?
				AND EWRporta2_articles.article_date > ?
				AND xf_thread.discussion_state = 'visible'
				$wheres
			GROUP BY xf_thread.thread_id
			ORDER BY EWRporta2_articles.article_date DESC
		", 'thread_id', array(XenForo_Application::$time, (XenForo_Application::$time - 172800)));
		
		foreach ($articles AS &$article)
		{
			$article = $this->getModelFromCache('EWRporta2_Model_Articles')->parseArticle($article);
			$article['categories'] = array();
		}
		
		$catlinks = $this->getModelFromCache('EWRporta2_Model_Catlinks')->getCategoryLinks(array_keys($articles));
		
		foreach ($catlinks AS $catlink)
		{
			$articles[$catlink['thread_id']]['categories'][] = $catlink['category_name'];
		}
		
		return $articles;
	}
	
	public function getSitemapPreamble()
	{
		return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
			. '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" '
			. 'xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" '
			. 'xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">';
	}

	public function getSitemapSuffix()
	{
		return "\n" . '</urlset>';
	}

	public function buildSitemapEntry(array $article, array $language)
	{
		switch ($article['article_icon']['type'])
		{
			case 'avatar':	$image = XenForo_Template_Helper_Core::helperAvatarUrl($article, 'm');								break;
			case 'attach':	$image = XenForo_Link::buildPublicLink('canonical:attachments', $article['article_icon']['data']);	break;
			case 'image':	$image = $article['article_icon']['data']['url'];													break;
			case 'medio':	$image = EWRmedio_Template_Helper::getMedioLowUrl($article['article_icon']['data']);				break;
			default:		$image = '';
		}
	
		return '
	<url>
		<loc>' . htmlspecialchars(XenForo_Link::buildPublicLink('canonical:threads', $article), ENT_QUOTES, 'UTF-8') . '</loc>
		' . ($image ? '<image:image><image:loc>' . htmlspecialchars($image) . '</image:loc></image:image>' : '') . '
		<news:news>
			<news:publication>
				<news:name>' . htmlspecialchars(XenForo_Application::get('options')->boardTitle, ENT_QUOTES, 'UTF-8') . '</news:name>
				<news:language>' . $language['language_code'] . '</news:language>
			</news:publication>
			<news:publication_date>' . gmdate(DateTime::W3C, $article['article_date']) . '</news:publication_date>
			<news:title>' . htmlspecialchars($article['article_title'], ENT_QUOTES, 'UTF-8') . '</news:title>
			<news:keywords>' . htmlspecialchars(implode(',', $article['categories']), ENT_QUOTES, 'UTF-8') . '</news:keywords>
		</news:news>
	</url>';
	}
}