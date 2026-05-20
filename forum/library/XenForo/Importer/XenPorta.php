<?php

class XenForo_Importer_XenPorta extends XenForo_Importer_Abstract
{
	protected $_sourceDb;
	protected $_charset = 'windows-1252';
	protected $_config;

	public static function getName()
	{
		return 'XenPorta => XenPorta 2 (Pro)';
	}

	public function configure(XenForo_ControllerAdmin_Abstract $controller, array &$config)
	{
		if ($config)
		{
			if ($errors = $this->validateConfiguration($config))
			{
				return $controller->responseError($errors);
			}

			$this->_bootstrap($config);

			return true;
		}
		else
		{
			$viewParams = array('input' => array(
				'db' => array(
					'host' => 'localhost',
					'port' => '3306',
				)
			));
			
			return $controller->responseView('XenForo_ViewAdmin_Import_XenForo_Config', 'import_xenforo_config', $viewParams);
		}
	}

	public function validateConfiguration(array &$config)
	{
		$errors = array();

		try
		{
			$db = Zend_Db::factory('mysqli',
				array(
					'host' => $config['db']['host'],
					'port' => $config['db']['port'],
					'username' => $config['db']['username'],
					'password' => $config['db']['password'],
					'dbname' => $config['db']['dbname'],
					'charset' => 'utf8',
				)
			);
			$db->getConnection();
		}
		catch (Zend_Db_Exception $e)
		{
			$errors[] = new XenForo_Phrase('source_database_connection_details_not_correct_x', array('error' => $e->getMessage()));
		}

		if ($errors)
		{
			return $errors;
		}

		try
		{
			$db->query('SELECT user_id FROM xf_user LIMIT 1');
		}
		catch (Zend_Db_Exception $e)
		{
			if ($config['db']['dbname'] === '')
			{
				$errors[] = new XenForo_Phrase('please_enter_database_name');
			}
			else
			{
				$errors[] = new XenForo_Phrase('table_prefix_or_database_name_is_not_correct');
			}
		}

		return $errors;
	}

	public function getSteps()
	{
		return array(
			'categories' => array(
				'title' => 'Import XenPorta Categories'
			),
			'articles' => array(
				'title' => 'Import XenPorta Promotions',
				'depends' => array('categories')
			),
			'autopro' => array(
				'title' => 'Import Auto-Promoted Threads',
				'depends' => array('articles')
			),
			'catlinks' => array(
				'title' => 'Import XenPorta Catlinks',
				'depends' => array('articles')
			),
		);
	}

	public function stepCategories($start, array $options)
	{
		$categories = $this->_sourceDb->fetchAll('SELECT * FROM EWRporta_categories');
		$total = 0;

		XenForo_Db::beginTransaction();

		foreach ($categories AS $category)
		{
			switch ($category['category_type'])
			{
				case 'major':	$type = 'category';	break;
				case 'minor':	$type = 'tag';		break;
			}

			$dw = XenForo_DataWriter::create('EWRporta2_DataWriter_Categories');
			$dw->setImportMode(true);
			$dw->bulkSet(array(
				'style_id' => $category['style_id'],
				'category_id' => $category['category_id'],
				'category_name' => $this->_convertToUtf8($category['category_name']),
				'category_desc' => '',
				'category_type' => $type,
			));
			$dw->save();
			$new = $dw->getMergedData();
			
			$total++;
		}

		XenForo_Db::commit();

		$this->_session->incrementStepImportTotal($total);

		return true;
	}

	public function stepArticles($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 100,
			'max' => false
		), $options);

		$defaults = XenForo_Application::get('options')->EWRporta2_promote_defaults;
		$sDb = $this->_sourceDb;
		$next = 0;
		$total = 0;

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('
				SELECT MAX(xf_thread.thread_id)
				FROM EWRporta_promotes
					INNER JOIN xf_thread ON (xf_thread.thread_id = EWRporta_promotes.thread_id)
					INNER JOIN xf_post ON (xf_post.post_id = xf_thread.first_post_id)
			');
		}

		if (!$articles = $sDb->fetchAll($sDb->limit('
			SELECT EWRporta_promotes.*, xf_post.*
			FROM EWRporta_promotes
				INNER JOIN xf_thread ON (xf_thread.thread_id = EWRporta_promotes.thread_id)
				INNER JOIN xf_post ON (xf_post.post_id = xf_thread.first_post_id)
			WHERE EWRporta_promotes.thread_id >= ' . $sDb->quote($start) . '
			ORDER BY EWRporta_promotes.thread_id
		', $options['limit'])))
		{
			return true;
		}

		XenForo_Db::beginTransaction();

		foreach ($articles AS $article)
		{
			$next = $article['thread_id'] + 1;
			$total++;
			
			switch ($article['promote_icon'])
			{
				case 'avatar':
					$icon = array(
						'type' => 'avatar',
					);
					break;
				case 'attach':
					$icon = array(
						'type' => 'attach',
						'data' => $article['promote_data'],
					);
					break;
				case 'medio':
					$icon = array(
						'type' => 'medio',
						'data' => $article['promote_data'],
					);
					break;
				case 'default':
					if (preg_match('#\[medio\.*?](\d+)\[/medio\]#i', $article['message'], $matches))
					{
						$icon = array(
							'type' => 'medio',
							'data' => $matches[1],
						);
						break;
					}
					if ($article['attach_count'])
					{
						$attachModel = XenForo_Model::create('XenForo_Model_Attachment');
						$attachments = $attachModel->getAttachmentsByContentId('post', $article['post_id']);
						$attachments = $attachModel->prepareAttachments($attachments);
						
						foreach ($attachments AS $attach)
						{
							if ($attach['thumbnailUrl'])
							{
								$icon = array(
									'type' => 'attach',
									'data' => $attach['attachment_id'],
								);
								break 2;
							}
						}
					}
				default:
					$icon = array();
			}
			
			if (preg_match('#\[pre?break\](.*?)\[/pre?break\]#si', $article['message'], $matches))
			{
				$break = $matches[1];
			}
			else
			{
				$break = '';
			}

			$dw = XenForo_DataWriter::create('EWRporta2_DataWriter_Articles');
			$dw->setImportMode(true);
			$dw->bulkSet(array(
				'thread_id' => $article['thread_id'],
				'article_date' => $article['promote_date'],
				'article_title' => '',
				'article_break' => $break,
				'article_excerpt' => '',
				'article_icon' => $icon,
				'article_custom' => 0,
				'article_options' => $defaults,
			));
			$dw->save();
			$new = $dw->getMergedData();
		}

		XenForo_Db::commit();

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}
	
	public function stepAutoPro($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 100,
			'max' => false
		), $options);

		$defaults = XenForo_Application::get('options')->EWRporta2_promote_defaults;
		$forums = XenForo_Application::get('options')->EWRporta2_articles_autoforums;
		$sDb = $this->_sourceDb;
		$next = 0;
		$total = 0;

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('
				SELECT COUNT(xf_thread.thread_id)
				FROM xf_thread
					INNER JOIN xf_post ON (xf_post.post_id = xf_thread.first_post_id)
					LEFT JOIN EWRporta_promotes ON (EWRporta_promotes.thread_id = xf_thread.thread_id)
				WHERE xf_thread.node_id IN (' . $sDb->quote($forums) . ')
					AND EWRporta_promotes.thread_id IS NULL
			');
		}

		if (!$articles = $sDb->fetchAll($sDb->limit('
			SELECT xf_thread.*, xf_post.*
			FROM xf_thread
				INNER JOIN xf_post ON (xf_post.post_id = xf_thread.first_post_id)
				LEFT JOIN EWRporta_promotes ON (EWRporta_promotes.thread_id = xf_thread.thread_id)
			WHERE xf_thread.thread_id >= ' . $sDb->quote($start) . '
				AND xf_thread.node_id IN (' . $sDb->quote($forums) . ')
				AND EWRporta_promotes.thread_id IS NULL
			ORDER BY xf_thread.thread_id
		', $options['limit'])))
		{
			return true;
		}

		XenForo_Db::beginTransaction();

		foreach ($articles AS $article)
		{
			$next = $article['thread_id'] + 1;
			$total++;
			
			if (preg_match('#\[pre?break\](.*?)\[/pre?break\]#si', $article['message'], $matches))
			{
				$break = $matches[1];
			}
			else
			{
				$break = '';
			}

			$dw = XenForo_DataWriter::create('EWRporta2_DataWriter_Articles');
			$dw->setImportMode(true);
			$dw->bulkSet(array(
				'thread_id' => $article['thread_id'],
				'article_date' => $article['post_date'],
				'article_title' => '',
				'article_break' => $break,
				'article_excerpt' => '',
				'article_icon' => array(),
				'article_custom' => 0,
				'article_options' => $defaults,
			));
			$dw->save();
			$new = $dw->getMergedData();
		}

		XenForo_Db::commit();

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}

	public function stepCatLinks($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 100,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;
		$next = 0;
		$total = 0;

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('SELECT COUNT(catlink_id) FROM EWRporta_catlinks');
		}

		if (!$catlinks = $sDb->fetchAll($sDb->limit('
			SELECT *
				FROM EWRporta_catlinks
			WHERE catlink_id >= ' . $sDb->quote($start) . '
			ORDER BY catlink_id
		', $options['limit'])))
		{
			return true;
		}

		XenForo_Db::beginTransaction();

		foreach ($catlinks AS $link)
		{
			$next = $link['catlink_id'] + 1;
			$total++;

			$dw = XenForo_DataWriter::create('EWRporta2_DataWriter_Catlinks');
			$dw->setImportMode(true);
			$dw->bulkSet(array(
				'thread_id' => $link['thread_id'],
				'category_id' => $link['category_id'],
			));
			$dw->save();
		}

		XenForo_Db::commit();

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}

	protected function _bootstrap(array $config)
	{
		if ($this->_sourceDb)
		{
			return;
		}

		@set_time_limit(0);

		$this->_config = $config;

		$this->_sourceDb = Zend_Db::factory('mysqli',
			array(
				'host' => $config['db']['host'],
				'port' => $config['db']['port'],
				'username' => $config['db']['username'],
				'password' => $config['db']['password'],
				'dbname' => $config['db']['dbname'],
				'charset' => 'utf8',
			)
		);
	}

	protected function _convertToUtf8($string, $entities = null)
	{
		if (preg_match('/[\x80-\xff]/', $string))
		{
			if (function_exists('iconv'))
			{
				$string = @iconv($this->_charset, 'utf-8//IGNORE', $string);
			}
			else if (function_exists('mb_convert_encoding'))
			{
				$string = mb_convert_encoding($string, 'utf-8', $this->_charset);
			}
		}

		$string = utf8_unhtml($string, $entities);

		return preg_replace('/[\xF0-\xF4].../', '', $string);
	}
}