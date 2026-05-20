<?php

class EWRporta2_Model_Articles extends XenForo_Model
{
	public function getArticleIconsByPost($post)
	{
		$icons = array(
			'attach' => array(),
			'image' => array(),
			'medio' => array(),
		);
		
		if ($post['attachments'] = $this->getModelFromCache('XenForo_Model_Attachment')->getAttachmentsByContentId('post', $post['post_id']))
		{
			$post['attachments'] = $this->getModelFromCache('XenForo_Model_Attachment')->prepareAttachments($post['attachments']);

			foreach ($post['attachments'] AS $attach)
			{
				if (!empty($attach['thumbnailUrl']))
				{
					$icons['attach'][] = $attach;
				}
			}
		}
		
		if (XenForo_Application::get('options')->EWRporta2_promote_image)
		{
			if (preg_match_all('#\[img\](.+?)\[/img\]#i', $post['message'], $matches))
			{
				foreach ($matches[1] AS $match)
				{
					$url = explode('/', str_ireplace('http://', '', $match));
					$icons['image'][] = array(
						'host' => reset($url),
						'file' => end($url),
						'url' => $match,
					);
				}
			}
		}
		
		if (XenForo_Application::autoload('EWRmedio_Model_Media'))
		{
			if (preg_match_all('#\[medio\.*?](\d+)\[/medio\]#i', $post['message'], $matches))
			{
				$icons['medio'] = $this->getModelFromCache('EWRmedio_Model_Media')->getMediasByIDs($matches[1]);
			}
		}
		
		return $icons;
	}
	
	public function getArticleParams($params)
	{
		$joins = "";
		$wheres = "";
	
		if (!empty($params['category']))
		{
			$joins .= " INNER JOIN EWRporta2_catlinks ON (EWRporta2_catlinks.thread_id = EWRporta2_articles.thread_id)";
			$wheres .= " AND EWRporta2_catlinks.category_id IN (" . $this->_getDb()->quote($params['category']) . ")";
		}
	
		if (!empty($params['author']))
		{
			$wheres .= " AND xf_user.user_id IN (" . $this->_getDb()->quote($params['author']) . ")";
		}
	
		if (!empty($params['forums']))
		{
			$wheres .= " AND xf_thread.node_id IN (" . $this->_getDb()->quote($params['forums']) . ")";
		}
		
		if (!empty($params['exclude']))
		{
			$wheres .= " AND EWRporta2_articles.thread_id NOT IN (" . $this->_getDb()->quote($params['exclude']) . ")";
		}
		
		if (empty($wheres))
		{
			$wheres .= " AND (EWRporta2_articles.article_exclude = '0' OR EWRporta2_articles.article_exclude IS NULL)";
		}
		
		return array($joins, $wheres);
	}
	
	public function getFilterParams($params)
	{
		$joins2 = "";
		$wheres2 = "";
		
		if (!empty($params['filter']))
		{
			$joins2 .= " LEFT JOIN EWRporta2_catlinks AS filter ON (filter.thread_id = EWRporta2_articles.thread_id)";
			$joins2 .= " INNER JOIN EWRporta2_categories AS cat ON (cat.category_id = filter.category_id AND cat.category_type = 'category')";
			$wheres2 .= " AND filter.category_id NOT IN (" . $this->_getDb()->quote($params['filter']) . ")";
		}
		
		return array($joins2, $wheres2);
	}

	public function getArticlesCount($params = array())
	{
		$options = XenForo_Application::get('options');
		
		list($joins, $wheres) = $this->getArticleParams($params);
		list($joins2, $wheres2) = $this->getFilterParams($params);
		
		$count = $this->_getDb()->fetchRow("SELECT COUNT(*) AS total FROM (
			SELECT xf_thread.thread_id
			FROM EWRporta2_articles
				INNER JOIN xf_thread ON (xf_thread.thread_id = EWRporta2_articles.thread_id)
				LEFT JOIN xf_user ON (xf_user.user_id = xf_thread.user_id)
				$joins
				$joins2
			WHERE EWRporta2_articles.article_date < ?
				AND xf_thread.discussion_state = 'visible'
				$wheres
				$wheres2
			GROUP BY xf_thread.thread_id
		) q", array(XenForo_Application::$time));
		
		if ($options->EWRporta2_promote_autoforums[0] != 0)
		{
			$count2 = $this->_getDb()->fetchRow("SELECT COUNT(*) AS total FROM (
				SELECT xf_thread.thread_id
				FROM xf_thread
					LEFT JOIN EWRporta2_articles ON (EWRporta2_articles.thread_id = xf_thread.thread_id)
					LEFT JOIN xf_user ON (xf_user.user_id = xf_thread.user_id)
					$joins
				WHERE xf_thread.post_date < ?
					AND xf_thread.discussion_state = 'visible'
					AND xf_thread.node_id IN (".$this->_getDb()->quote($options->EWRporta2_promote_autoforums).")
					AND EWRporta2_articles.article_date IS NULL
					$wheres
				GROUP BY xf_thread.thread_id
			) q", array(XenForo_Application::$time));
			
			$count['total'] += $count2['total'];
		}

		return $count['total'];
	}
	
	public function getArticles($start = 1, $stop = 20, $params = array())
	{
		$options = XenForo_Application::get('options');
		$start = ($start - 1) * $stop;
		
		list($joins, $wheres) = $this->getArticleParams($params);
		list($joins2, $wheres2) = $this->getFilterParams($params);
		
		if ($options->EWRporta2_promote_autoforums[0] != 0)
		{
			$articles = $this->fetchAllKeyed("
				(
					SELECT EWRporta2_articles.*, xf_thread.*, xf_forum.*, xf_user.*, xf_post.message, xf_post.attach_count,
						IF(NOT ISNULL(xf_user.user_id), xf_user.username, xf_thread.username) AS username, xf_thread.post_date AS order_date
					FROM xf_thread
						INNER JOIN xf_forum ON (xf_forum.node_id = xf_thread.node_id)
						INNER JOIN xf_post ON (xf_post.post_id = xf_thread.first_post_id)
						LEFT JOIN xf_user ON (xf_user.user_id = xf_thread.user_id)
						LEFT JOIN EWRporta2_articles ON (EWRporta2_articles.thread_id = xf_thread.thread_id)
						$joins
					WHERE xf_thread.post_date < ?
						AND xf_thread.discussion_state = 'visible'
						AND xf_thread.node_id IN (".$this->_getDb()->quote($options->EWRporta2_promote_autoforums).")
						AND EWRporta2_articles.article_date IS NULL
						$wheres
					GROUP BY xf_thread.thread_id
					ORDER BY xf_thread.post_date DESC
				) UNION ALL (
					SELECT EWRporta2_articles.*, xf_thread.*, xf_forum.*, xf_user.*, xf_post.message, xf_post.attach_count,
						IF(NOT ISNULL(xf_user.user_id), xf_user.username, xf_thread.username) AS username, EWRporta2_articles.article_date AS order_date
					FROM EWRporta2_articles
						INNER JOIN xf_thread ON (xf_thread.thread_id = EWRporta2_articles.thread_id)
						INNER JOIN xf_forum ON (xf_forum.node_id = xf_thread.node_id)
						INNER JOIN xf_post ON (xf_post.post_id = xf_thread.first_post_id)
						LEFT JOIN xf_user ON (xf_user.user_id = xf_thread.user_id)
						$joins
						$joins2
					WHERE EWRporta2_articles.article_date < ?
						AND xf_thread.discussion_state = 'visible'
						$wheres
						$wheres2
					GROUP BY xf_thread.thread_id
					ORDER BY EWRporta2_articles.article_date DESC
				)
				ORDER BY order_date DESC
				LIMIT ?, ?
			", 'thread_id', array(XenForo_Application::$time, XenForo_Application::$time, $start, $stop));
			
			foreach ($articles AS &$article)
			{
				$article['article_date'] = $article['order_date'];
			}
		}
		else
		{
			$articles = $this->fetchAllKeyed("
				SELECT EWRporta2_articles.*, xf_thread.*, xf_forum.*, xf_user.*, xf_post.message, xf_post.attach_count,
					IF(NOT ISNULL(xf_user.user_id), xf_user.username, xf_thread.username) AS username
				FROM EWRporta2_articles
					INNER JOIN xf_thread ON (xf_thread.thread_id = EWRporta2_articles.thread_id)
					INNER JOIN xf_forum ON (xf_forum.node_id = xf_thread.node_id)
					INNER JOIN xf_post ON (xf_post.post_id = xf_thread.first_post_id)
					LEFT JOIN xf_user ON (xf_user.user_id = xf_thread.user_id)
					$joins
					$joins2
				WHERE EWRporta2_articles.article_date < ?
					AND xf_thread.discussion_state = 'visible'
					$wheres
					$wheres2
				GROUP BY xf_thread.thread_id
				ORDER BY EWRporta2_articles.article_date DESC
				LIMIT ?, ?
			", 'thread_id', array(XenForo_Application::$time, $start, $stop));
		}
		
		foreach ($articles AS &$article)
		{
			$article = $this->parseArticle($article);
		}
		
		$catlinks = $this->getModelFromCache('EWRporta2_Model_Catlinks')->getCategoryLinks(array_keys($articles));
		
		foreach ($catlinks AS $catlink)
		{
			switch ($catlink['category_type'])
			{
				case 'category':
					$articles[$catlink['thread_id']]['categories']['cats'][$catlink['category_id']] = $catlink;
					break;
				case 'tag':
					$articles[$catlink['thread_id']]['categories']['tags'][$catlink['category_id']] = $catlink;
					break;
			}
		}
		
		return $articles;
	}
	
	public function parseArticle($article)
	{
		$options = XenForo_Application::get('options');
		$article['article_icon'] = @unserialize($article['article_icon']);
		$article['article_options'] = @unserialize($article['article_options']);
		$article['categories'] = array('cats' => array(), 'tags' => array());
	
		if (empty($article['article_custom']) || empty($article['article_excerpt']))
		{
			$message = explode('[prebreak]', $article['message']);
			
			if (!empty($message[1]))
			{
				$article['article_excerpt'] = $message[0];
			}
			else
			{
				$article['article_excerpt'] = XenForo_Helper_String::wholeWordTrim($message[0], $options->EWRporta2_articles_excerpt);
			}
		}
	
		if (empty($article['article_custom']) || empty($article['article_title']))
		{
			$article['article_title'] = $article['title'];
		}
		
		if (!empty($article['article_icon']['type']))
		{
			switch ($article['article_icon']['type'])
			{
				case 'disable':																								break;
				case 'avatar':		$iconFound = true;																		break;
				case 'attach':		$iconFound = $this->parseArticleAttach($article, $article['article_icon']['data']);		break;
				case 'image':		$iconFound = $this->parseArticleImage($article, $article['article_icon']['data']);		break;
				case 'medio':		$iconFound = $this->parseArticleMedio($article, $article['article_icon']['data']);		break;
			}
		}
		else
		{
			$iconFound = $this->parseArticleDefault($article);
		}
		
		if (empty($iconFound))
		{
			$article['article_icon']['type'] = false;
		}
		
		$article['article_excerpt'] = preg_replace('#\n{3,}#', "\n\n", trim($article['article_excerpt']));
		
		return $article;
	}
	
	public function parseArticleDefault(&$article)
	{
		$options = XenForo_Application::get('options');
		
		if ($options->EWRporta2_promote_iconsearch && !$this->parseArticleAttach($article))
		{
			if ($options->EWRporta2_promote_image && preg_match('#\[img\](.+?)\[/img\]#i', $article['message'], $matches))
			{
				$article['article_icon']['data'] = $matches[1];
				$this->parseArticleImage($article, $article['article_icon']['data']);
			}
			else
			{
				$article['article_icon']['type'] = 'avatar';
			}
		}
		
		return true;
	}
	
	public function parseArticleAttach(&$article, $data = false)
	{
		$article['attachments'] = $this->getModelFromCache('XenForo_Model_Attachment')->getAttachmentsByContentId('post', $article['first_post_id']);
		
		if (!$data || empty($article['attachments'][$data]))
		{
			$attach = reset($article['attachments']);
			$data = $attach ? $attach['attachment_id'] : false;
		}
						
		if ($data)
		{
			$article['attachments'][$data] = $this->getModelFromCache('XenForo_Model_Attachment')->prepareAttachment($article['attachments'][$data]);
			
			$article['article_icon'] = array('type' => 'attach', 'data' => $article['attachments'][$data]);
			$article['article_excerpt'] = str_ireplace('[attach]'.$data.'[/attach]', '', $article['article_excerpt']);
			$article['article_excerpt'] = str_ireplace('[attach=full]'.$data.'[/attach]', '', $article['article_excerpt']);
			
			return true;
		}
		else
		{
			$article['article_icon']['type'] = 'avatar';
		}
		
		return false;
	}
	
	public function parseArticleImage(&$article, $data)
	{
		if (XenForo_Application::get('options')->EWRporta2_promote_image)
		{
			$url = explode('/', str_ireplace('http://', '', $data));
			$image = array(
				'host' => reset($url),
				'file' => end($url),
				'url' => $data,
			);
			
			$article['article_icon'] = array('type' => 'image', 'data' => $image);
			$article['article_excerpt'] = str_ireplace('[img]'.$data.'[/img]', '', $article['article_excerpt']);
			
			return true;
		}
		
		return false;
	}
	
	public function parseArticleMedio(&$article, $data)
	{
		if (XenForo_Application::autoload('EWRmedio_Model_Media'))
		{
			$media = $this->_getDb()->fetchRow("SELECT * FROM EWRmedio_media WHERE EWRmedio_media.media_id = ?", array($data));
			
			if ($media)
			{
				$article['article_icon'] = array('type' => 'medio', 'data' => $media);
				$article['article_excerpt'] = str_ireplace('[medio]'.$data.'[/medio]', '', $article['article_excerpt']);
				$article['article_excerpt'] = str_ireplace('[medio=full]'.$data.'[/medio]', '', $article['article_excerpt']);
			
				return true;
			}
		}
		
		return false;
	}
	
	public function getArticlesSimple($start = 1, $stop = 20, $params = array())
	{
		$options = XenForo_Application::get('options');
		$start = ($start - 1) * $stop;
		
		list($joins, $wheres) = $this->getArticleParams($params);
		switch ($params['sort'])
		{
			case 'lastpost':	$order = 'xf_thread.last_post_date';		break;
			case 'replies':		$order = 'xf_thread.reply_count';			break;
			case 'views':		$order = 'xf_thread.view_count';			break;
			default:			$order = 'EWRporta2_articles.article_date';	break;
		}
		
		$articles = $this->fetchAllKeyed("
			SELECT EWRporta2_articles.*, xf_thread.*, xf_forum.*, xf_user.*
			FROM EWRporta2_articles
				INNER JOIN xf_thread ON (xf_thread.thread_id = EWRporta2_articles.thread_id)
				INNER JOIN xf_forum ON (xf_forum.node_id = xf_thread.node_id)
				LEFT JOIN xf_user ON (xf_user.user_id = xf_thread.user_id)
				$joins
			WHERE EWRporta2_articles.article_date < ?
				AND xf_thread.discussion_state = 'visible'
				$wheres
			GROUP BY xf_thread.thread_id
			ORDER BY $order DESC
			LIMIT ?, ?
		", 'thread_id', array(XenForo_Application::$time, $start, $stop));
		
		foreach ($articles AS $key => &$article)
		{
			if (empty($article['article_custom']) || empty($article['article_title']))
			{
				$article['article_title'] = $article['title'];
			}
		}
		
		return $articles;
	}
	
	public function prepareArticles($articles)
	{
		if (!XenForo_Application::get('options')->EWRporta2_articles_permissions)
		{
			foreach($articles AS $key => &$article)
			{
				if (!$article['canViewContent'] = $this->getModelFromCache('XenForo_Model_Thread')->canViewThreadAndContainer($article, $article))
				{
					unset($articles[$key]);
				}
			}
		}
	
		return $articles;
	}
	
	public function getArticleByThreadIdOrAuto($threadID)
	{
		$options = XenForo_Application::get('options');
		$article = $this->getArticleByThreadId($threadID);
		
		if (!$article && $options->EWRporta2_promote_autoforums[0] != 0)
		{
			$thread = $this->getModelFromCache('XenForo_Model_Thread')->getThreadById($threadID);
			
			if (!in_array($thread['node_id'], $options->EWRporta2_promote_autoforums))
			{
				return false;
			}
			
			$article = array(
				'thread_id' => $thread['thread_id'],
				'article_options' => $options->EWRporta2_promote_defaults,
				'article_date' => $thread['post_date'],
			);
		}
		else if (!$article)
		{
			return false;
		}
		
		$article['comment_count'] = array_filter(array(
			!empty($article['article_options']['local']),
			$options->EWRporta2_promote_disqus && !empty($article['article_options']['disqus']) ? true : false,
			$options->EWRporta2_promote_facebook && !empty($article['article_options']['facebook']) ? true : false,
		));

		return $article;
	}
	
	public function getArticleByThreadId($threadID)
	{
		if (!$article = $this->_getDb()->fetchRow("
			SELECT * FROM EWRporta2_articles WHERE thread_id = ?
		", $threadID))
		{
			return false;
		}
		
		$article['article_icon'] = @unserialize($article['article_icon']);
		$article['article_options'] = @unserialize($article['article_options']);

		return $article;
	}
	
	public function getArticleByThreadPost($thread, $post)
	{
		$options = XenForo_Application::get('options');
		
		if (!$article = $this->getArticleByThreadId($thread['thread_id']))
		{
			$article['article_date'] = $thread['post_date'];
			$article['article_options'] = $options->EWRporta2_promote_defaults;
		}
		else
		{
			$article['article_options'] = $article['article_options'];
		}
		
		$article['article_title'] = !empty($article['article_title']) ? $article['article_title'] : $thread['title'];
		$article['article_excerpt'] = !empty($article['article_excerpt']) ? $article['article_excerpt'] : $post['message'];
		
		$datetime = new DateTime(date('r', $article['article_date']));
		
		if ($options->EWRporta2_promote_timezone)
		{
			$datetime->setTimezone(new DateTimeZone($options->EWRporta2_promote_timezone));
		}
		else
		{
			$visitor = XenForo_Visitor::getInstance();
			$datetime->setTimezone(new DateTimeZone($visitor['timezone']));
		}
		
		$datetime = explode('.', $datetime->format($options->EWRporta2_promote_24hour ? 'Y-m-d.H.i.A.e' : 'Y-m-d.h.i.A.e'));

		$article['datetime'] = array(
			'date' => $datetime[0],
			'hour' => $datetime[1],
			'mins' => $datetime[2],
			'meri' => $datetime[3],
			'zone' => $datetime[4]
		);
		
		return $article;
	}
	
	public function updateArticle($input)
	{
		$dw = XenForo_DataWriter::create('EWRporta2_DataWriter_Articles');

		if ($existing = $this->getArticleByThreadId($input['thread_id']))
		{
			$dw->setExistingData($existing);
		}
		
		if (is_array($input['article_date']))
		{
			$input['article_date'] = strtotime(implode(' ', array(
				$input['article_date']['date'],
				($input['article_date']['meri'] == 'PM' ? $input['article_date']['hour'] + 12 : $input['article_date']['hour'])
				. ":" . str_pad($input['article_date']['mins'], 2, "0", STR_PAD_LEFT),
				$input['article_date']['zone']
			)));
		}
		
		if (!empty($input['article_custom']))
		{
			$dw->set('article_excerpt', $input['article_excerpt']);
		}
		
		$dw->bulkSet($input);
		$dw->save();
		$article = $dw->getMergedData();
		
		if (!$existing)
		{
			$visitor = XenForo_Visitor::getInstance();
			$thread = $this->getModelFromCache('XenForo_Model_Thread')->getThreadById($article['thread_id']);
			
			if ($user = $this->getModelFromCache('XenForo_Model_User')->getUserById($thread['user_id']))
			{
				if ($visitor['user_id'] != $user['user_id'] && XenForo_Model_Alert::userReceivesAlert($user, 'article', 'promote'))
				{
					XenForo_Model_Alert::alert(
						$user['user_id'],
						$visitor['user_id'],
						$visitor['username'],
						'article',
						$article['thread_id'],
						'promote'
					);
				}
			}
		}
		
		return $article;
	}
	
	public function deleteArticle($input)
	{
		$dw = XenForo_DataWriter::create('EWRporta2_DataWriter_Articles');
		$dw->setExistingData($input);
		$dw->delete();
		
		$this->getModelFromCache('XenForo_Model_Alert')->deleteAlerts('article', $input['thread_id'], null, 'promote');
		
		return true;
	}
}