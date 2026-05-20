<?php

class EWRutiles_Model_Sitemap extends XenForo_Model
{
	public function buildIndex()
	{
		XenForo_Visitor::setup(0);

		$options = XenForo_Application::get('options');
		$paths = array();

		if ($options->EWRutiles_sitemap_options['forums']) { $paths = array_merge($paths, $this->buildForums()); }
		if ($options->EWRutiles_sitemap_options['threads']) { $paths = array_merge($paths, $this->buildThreads()); }
		if ($options->EWRutiles_sitemap_options['members']) { $paths = array_merge($paths, $this->buildMembers()); }

		if ($options->EWRutiles_sitemap_options['media'] && XenForo_Application::autoload('EWRmedio_Model_Media'))
		{
			$paths = array_merge($paths, $this->buildMedia());
		}

		if ($options->EWRutiles_sitemap_options['wiki'] && XenForo_Application::autoload('EWRcarta_Model_Pages'))
		{
			$paths = array_merge($paths, $this->buildWiki());
		}

		list($document, $sub_node) = $this->addDocument('sitemapindex');

		foreach ($paths AS $path)
		{
			$this->addUrl($document, $sub_node, 'sitemap', $path, XenForo_Application::$time);
		}

		$file = fopen(XenForo_Helper_File::getExternalDataPath().'/sitemaps/index.xml', 'w');
		fwrite($file, $document->saveXML());
		fclose($file);

		if ($options->EWRutiles_sitemap_ping['bing']) { $this->pingUrl('http://www.bing.com/webmaster/ping.aspx?sitemap='); }
		if ($options->EWRutiles_sitemap_ping['google']) { $this->pingUrl('http://www.google.com/webmasters/tools/ping?sitemap='); }

		return true;
	}

	public function buildForums()
	{
		$paths = array();
		$forums = $this->getModelFromCache('XenForo_Model_Forum')->getForums();
		list($document, $sub_node) = $this->addDocument('urlset');

		foreach ($forums AS $forum)
		{
			if ($this->getModelFromCache('XenForo_Model_Forum')->canViewForum($forum))
			{
				$this->addUrl($document, $sub_node, 'url', XenForo_Link::buildPublicLink('canonical:forums', $forum), $forum['last_post_date']);
			}
		}

		$paths[] = $this->saveFile($document, 'forums', '1');

		return $paths;
	}

	public function buildThreads()
	{
		$paths = array();
		$limit = XenForo_Application::get('options')->EWRutiles_sitemap_limit;
		$loops = 1;

		$conditions = array(
			'deleted' => false,
			'moderated' => false,
		);

		$count = $this->getModelFromCache('XenForo_Model_Thread')->countThreads($conditions);

		for ($offset = 0; $offset <= $count; $offset += $limit)
		{
			$fetchOptions = array(
				'order' => 'post_date',
				'orderDirection' => 'asc',
				'limit' => $limit,
				'offset' => $offset,
			);

			$threads = $this->getModelFromCache('XenForo_Model_Thread')->getThreads($conditions, $fetchOptions);
			list($document, $sub_node) = $this->addDocument('urlset');

			foreach ($threads AS $thread)
			{
				if ($this->getModelFromCache('XenForo_Model_Forum')->canViewForum($thread))
				{
					$this->addUrl($document, $sub_node, 'url', XenForo_Link::buildPublicLink('canonical:threads', $thread), $thread['last_post_date']);
				}
			}

			$paths[] = $this->saveFile($document, 'threads', $loops++);
		}

		return $paths;
	}

	public function buildMembers()
	{
		$paths = array();
		$limit = XenForo_Application::get('options')->EWRutiles_sitemap_limit;
		$loops = 1;

		$conditions = array(
			'user_state' => 'valid',
			'is_banned' => false,
		);

		$count = $this->getModelFromCache('XenForo_Model_User')->countUsers($conditions);

		for ($offset = 0; $offset <= $count; $offset += $limit)
		{
			$fetchOptions = array(
				'order' => 'register_date',
				'orderDirection' => 'asc',
				'limit' => $limit,
				'offset' => $offset,
			);

			$members = $this->getModelFromCache('XenForo_Model_User')->getUsers($conditions, $fetchOptions);
			list($document, $sub_node) = $this->addDocument('urlset');

			foreach ($members AS $member)
			{
				if (!$member['is_banned'])
				{
					$this->addUrl($document, $sub_node, 'url', XenForo_Link::buildPublicLink('canonical:members', $member), $member['register_date']);
				}
			}

			$paths[] = $this->saveFile($document, 'members', $loops++);
		}

		return $paths;
	}

	public function buildMedia()
	{
		$paths = array();
		$count = $this->getModelFromCache('EWRmedio_Model_Lists')->getMediaCount();
		$limit = XenForo_Application::get('options')->EWRutiles_sitemap_limit / 10;
		$loops = 1;

		for ($offset = 0; $offset <= $count; $offset += $limit)
		{
			$medias = $this->getModelFromCache('EWRmedio_Model_Lists')->getMediaList($loops, $limit, 'date', 'ASC');
			list($document, $sub_node) = $this->addDocument('urlset', array('video' => true));

			foreach ($medias AS $media)
			{
				$this->addVideo($document, $sub_node, $media);
			}

			$paths[] = $this->saveFile($document, 'media', $loops++);
		}

		return $paths;
	}

	public function buildWiki()
	{
		$paths = array();
		$pages = $this->getModelFromCache('EWRcarta_Model_Lists')->getPageList();
		list($document, $sub_node) = $this->addDocument('urlset');

		foreach ($pages AS $page)
		{
			$this->addUrl($document, $sub_node, 'url', XenForo_Link::buildPublicLink('canonical:wiki', $page), $page['page_date']);
		}

		$paths[] = $this->saveFile($document, 'wiki', '1');

		return $paths;
	}

	public function addDocument($type, $options = array())
	{
		$document = new DOMDocument('1.0', 'utf-8');
		$document->formatOutput = true;

		$document->appendChild($sub_node = $document->createElement($type));
			$sub_node->appendChild($xns_node = $document->createAttribute('xmlns'));
				$xns_node->appendChild($document->createTextNode('http://www.sitemaps.org/schemas/sitemap/0.9'));

		if (!empty($options['image']))
		{
			$sub_node->appendChild($xmg_node = $document->createAttribute('xmlns:image'));
				$xmg_node->appendChild($document->createTextNode('http://www.google.com/schemas/sitemap-image/1.1'));
		}

		if (!empty($options['video']))
		{
			$sub_node->appendChild($xvd_node = $document->createAttribute('xmlns:video'));
				$xvd_node->appendChild($document->createTextNode('http://www.google.com/schemas/sitemap-video/1.1'));
		}

		return array($document, $sub_node);
	}

	public function addUrl(&$document, &$sub_node, $type, $loc, $lastmod)
	{
		$sub_node->appendChild($url_node = $document->createElement($type));
			$url_node->appendChild($document->createElement('loc', $loc));
			$url_node->appendChild($document->createElement('lastmod', date('c', $lastmod)));

		return true;
	}

	public function addVideo(&$document, &$sub_node, $media)
	{
		$sub_node->appendChild($url_node = $document->createElement('url'));
			$url_node->appendChild($document->createElement('loc', XenForo_Link::buildPublicLink('canonical:media', $media)));

			$url_node->appendChild($vid_node = $document->createElement('video:video'));
				$vid_node->appendChild($document->createElement('video:thumbnail_loc', XenForo_Application::get('options')->boardUrl.'/data/media/'.$media['media_id'].'.jpg'));
				$vid_node->appendChild($document->createElement('video:title', htmlspecialchars(substr($media['media_title'], 0, 90))));
				$vid_node->appendChild($document->createElement('video:description', htmlspecialchars(substr(XenForo_Helper_String::bbCodeStrip($media['media_description']), 0, 2000))));

				if ($media['service_movie'] == 'null')
				{
					$vid_node->appendChild($document->createElement('video:content_loc', htmlspecialchars($media['content_loc'])));
				}
				else
				{
					$vid_node->appendChild($ply_node = $document->createElement('video:player_loc', htmlspecialchars($media['service_movie'])));
						$ply_node->appendChild($emb_node = $document->createAttribute('allow_embed'));
							$emb_node->appendChild($document->createTextNode('yes'));
				}

				$vid_node->appendChild($document->createElement('video:duration', $media['media_duration']));
				$vid_node->appendChild($document->createElement('video:view_count', $media['media_views']));
				$vid_node->appendChild($document->createElement('video:publication_date', date('c', $media['media_date'])));

				foreach (explode(',', $media['media_keywords']) AS $tag)
				{
					$vid_node->appendChild($tag_node = $document->createElement('video:tag', htmlspecialchars(trim($tag))));
						$tag_node->appendChild($inf_node = $document->createAttribute('info'));
							$inf_node->appendChild($document->createTextNode(XenForo_Link::buildPublicLink('canonical:media_keyword', array('keyword_text' => trim($tag)))));
				}

				$vid_node->appendChild($cat_node = $document->createElement('video:category', htmlspecialchars($media['category_name'])));
					$cat_node->appendChild($inf_node = $document->createAttribute('info'));
						$inf_node->appendChild($document->createTextNode(XenForo_Link::buildPublicLink('canonical:media_category', $media)));
				$vid_node->appendChild($upl_node = $document->createElement('video:uploader', $media['username']));
					$upl_node->appendChild($inf_node = $document->createAttribute('info'));
						$inf_node->appendChild($document->createTextNode(XenForo_Link::buildPublicLink('canonical:members', $media)));

		return true;
	}

	public function saveFile($document, $type, $loops)
	{
		$path = XenForo_Helper_File::getExternalDataPath().'/sitemaps/'.$type.'_'.$loops.'.xml';

		$file = fopen($path, 'w');
		fwrite($file, $document->saveXML());
		fclose($file);

		return XenForo_Application::get('options')->boardUrl.'/data/sitemaps/'.$type.'_'.$loops.'.xml';
	}

	public function pingUrl($url)
	{
		$client = new Zend_Http_Client();

		$client->setConfig(array(
			'timeout' => 10,
			'useragent' => '8WR Admin Tools',
			'maxredirects' => 2,
			'keepalive' => true
		));

		$client->setUri($url.urlencode(XenForo_Application::get('options')->boardUrl.'/sitemap'));
		$client->request();

		return true;
	}
}