<?php

class EWRporta2_ControllerPublic_Articles extends XenForo_ControllerPublic_Abstract
{
	private $xp2perms;
	
	public function actionNews()
	{
		$this->_routeMatch->setResponseType('rss');
		$params = array();
		
		if ($categoryID = $this->_input->filterSingle('category', XenForo_Input::UINT))
		{
			$params['category'] = $category['category_id'];
		}
		
		$viewParams = array(
			'articles' => $this->getModelFromCache('EWRporta2_Model_Sitemap')->getArticles($params),
		);
		
		return $this->responseView('EWRporta2_ViewPublic_ArticleNews', '', $viewParams);
	}
	
	public function actionIndex()
	{
		$options = XenForo_Application::get('options');
		$skip = $this->_input->filterSingle('skip', XenForo_Input::UINT);
		$params = array(
			'skip' => $skip || !$options->EWRporta2_features_sections['index'] ? true : false,
		);
		
		if ($this->_routeMatch->getResponseType() == 'rss')
		{
			$viewParams = $this->_getRssParams();
			return $this->responseView('EWRporta2_ViewPublic_ArticleRss', '', $viewParams);
		}
		
		if ($options->EWRporta2_style)
		{
			$this->setViewStateChange('styleId', $options->EWRporta2_style);
		}
		
		$viewParams = $this->_getViewParams($params);
		
		if (!$skip)
		{
			$this->canonicalizeRequestUrl(XenForo_Link::buildPublicLink('articles', array('page' => $viewParams['start'])));
			$this->canonicalizePageNumber($viewParams['start'], $viewParams['stop'], $viewParams['count'], 'articles');
		}

		return $this->responseView('EWRporta2_ViewPublic_ArticleList', 'EWRporta2_ArticleList', $viewParams);
	}
	
	public function actionCategory()
	{
		$categoryID = $this->_input->filterSingle('action_id', XenForo_Input::UINT);

		if (!$category = $this->getModelFromCache('EWRporta2_Model_Categories')->getCategoryById($categoryID))
		{
			return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildPublicLink('articles'));
		}
		
		$options = XenForo_Application::get('options');
		$skip = $this->_input->filterSingle('skip', XenForo_Input::UINT);
		$params = array(
			'category' => $category['category_id'],
			'skip' => $skip || !$options->EWRporta2_features_sections['categories'] ? true : false,
		);
		
		if ($this->_routeMatch->getResponseType() == 'rss')
		{
			$viewParams = $this->_getRssParams($params);
			$viewParams['category'] = $category;
			return $this->responseView('EWRporta2_ViewPublic_ArticleRss', '', $viewParams);
		}
		
		if (!empty($category['style_id']))
		{
			$this->setViewStateChange('styleId', $category['style_id']);
		}
		elseif ($options->EWRporta2_style)
		{
			$this->setViewStateChange('styleId', $options->EWRporta2_style);
		}
		
		$viewParams = $this->_getViewParams($params);
		$viewParams['category'] = $category;
		
		if (!$this->_input->filterSingle('skip', XenForo_Input::UINT))
		{
			$this->canonicalizeRequestUrl(XenForo_Link::buildPublicLink('articles/category', $category, array('page' => $viewParams['start'])));
			$this->canonicalizePageNumber($viewParams['start'], $viewParams['stop'], $viewParams['count'], 'articles/category', $category);
		}
		
		return $this->responseView('EWRporta2_ViewPublic_ArticleList', 'EWRporta2_ArticleList', $viewParams);
	}
	
	public function actionAuthor()
	{
		$authorID = $this->_input->filterSingle('action_id', XenForo_Input::UINT);

		if (!$author = $this->getModelFromCache('EWRporta2_Model_Authors')->getAuthorById($authorID))
		{
			return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildPublicLink('articles/authors'));
		}
		
		$options = XenForo_Application::get('options');
		$skip = $this->_input->filterSingle('skip', XenForo_Input::UINT);
		$params = array(
			'author' => $author['user_id'],
			'skip' => $skip || !$options->EWRporta2_features_sections['authors'] ? true : false,
		);
		
		if ($this->_routeMatch->getResponseType() == 'rss')
		{
			$viewParams = $this->_getRssParams($params);
			$viewParams['author'] = $author;
			return $this->responseView('EWRporta2_ViewPublic_ArticleRss', '', $viewParams);
		}
		
		if (XenForo_Application::get('options')->EWRporta2_style)
		{
			$this->setViewStateChange('styleId', XenForo_Application::get('options')->EWRporta2_style);
		}
		
		$viewParams = $this->_getViewParams($params);
		$viewParams['author'] = $author;
		
		if (!$this->_input->filterSingle('skip', XenForo_Input::UINT))
		{
			$this->canonicalizeRequestUrl(XenForo_Link::buildPublicLink('articles/author', $author, array('page' => $viewParams['start'])));
			$this->canonicalizePageNumber($viewParams['start'], $viewParams['stop'], $viewParams['count'], 'articles/author', $author);
		}
		
		return $this->responseView('EWRporta2_ViewPublic_ArticleList', 'EWRporta2_ArticleList', $viewParams);
	}
	
	public function actionAuthorAdd()
	{
		if (!$this->xp2perms['admin']) { return $this->responseNoPermission(); }
		
		$viewParams = array(
			'author' => array('author_order' => 10, 'author_byline' => '')
		);
		
		return $this->responseView('EWRporta2_ViewPublic_AuthorEdit', 'EWRporta2_AuthorEdit', $viewParams);
	}
	
	public function actionAuthorEdit()
	{
		$authorID = $this->_input->filterSingle('action_id', XenForo_Input::UINT);

		if (!$author = $this->getModelFromCache('EWRporta2_Model_Authors')->getAuthorById($authorID))
		{
			return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildPublicLink('articles/authors'));
		}
		
		if (!$this->xp2perms['admin'] && $author['user_id'] !== XenForo_Visitor::getUserId()) { return $this->responseNoPermission(); }
		
		$viewParams = array(
			'author' => $author
		);
		
		return $this->responseView('EWRporta2_ViewPublic_AuthorEdit', 'EWRporta2_AuthorEdit', $viewParams);
	}
	
	public function actionAuthorSave()
	{
		$this->_assertPostOnly();
		
		$input = $this->_input->filter(array(
			'user_id' => XenForo_Input::UINT,
			'author_name' => XenForo_Input::STRING,
			'author_status' => XenForo_Input::STRING,
			'author_order' => XenForo_Input::UINT,
		));
		$input['author_byline'] = $this->getHelper('Editor')->getMessageText('author_byline', $this->_input);
		
		if (!$input['user_id'])
		{
			$username = $this->_input->filterSingle('username', XenForo_Input::STRING);
			
			if (!$user = $this->getModelFromCache('XenForo_Model_User')->getUserByName($username))
			{
				return $this->responseError(new XenForo_Phrase('requested_member_not_found'));
			}
			
			if ($this->getModelFromCache('EWRporta2_Model_Authors')->getAuthorById($user['user_id']))
			{
				return $this->responseError(new XenForo_Phrase('porta2_author_profile_for_user_exists'));
			}
			
			$input['user_id'] = $user['user_id'];
		}
		
		$fileTransfer = new Zend_File_Transfer_Adapter_Http();
		
		if ($fileTransfer->isUploaded('upload_file'))
		{
			$fileInfo = $fileTransfer->getFileInfo('upload_file');
			$fileName = $fileInfo['upload_file']['tmp_name'];
			
			$input['author_time'] = XenForo_Application::$time;
			$this->getModelFromCache('EWRporta2_Model_Authors')->updateAuthorImage($input, $fileName);
		}
			
		$author = $this->getModelFromCache('EWRporta2_Model_Authors')->updateAuthor($input);
		
		return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildPublicLink('articles/author', $author));
	}
	
	public function actionAuthorDelete()
	{
		$authorID = $this->_input->filterSingle('action_id', XenForo_Input::UINT);

		if (!$author = $this->getModelFromCache('EWRporta2_Model_Authors')->getAuthorById($authorID))
		{
			return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildPublicLink('articles'));
		}
		
		if (!$this->xp2perms['admin'] && $author['user_id'] !== XenForo_Visitor::getUserId()) { return $this->responseNoPermission(); }
		
		if ($this->_request->isPost())
		{
			$this->getModelFromCache('EWRporta2_Model_Authors')->deleteAuthor($author);
			
			return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildPublicLink('articles/authors'));
		}
		else
		{
			$viewParams = array(
				'author' => $author
			);

			return $this->responseView('EWRporta2_ViewAdmin_AuthorDelete', 'EWRporta2_AuthorDelete', $viewParams);
		}
	}
	
	public function actionAuthors()
	{
		if ($this->xp2perms['filter'] || $this->xp2perms['arrange'])
		{
			$setting = $this->getModelFromCache('EWRporta2_Model_Settings')->getSettingById(XenForo_Visitor::getUserId());
		}
		
		$viewParams = array(
			'perms' => $this->xp2perms,
			'authors' => $this->getModelFromCache('EWRporta2_Model_Authors')->getAllAuthors(),
			'setting' => !empty($setting) ? $setting : false,
			'fbTemplate' => 'EWRporta2_ArticleList',
		);
		
		return $this->responseView('EWRporta2_ViewPublic_AuthorList', 'EWRporta2_AuthorList', $viewParams);
	}
	
	public function actionAuthorsOrder()
	{
		if (!$this->xp2perms['admin']) { return $this->responseNoPermission(); }
		
		$authors = $this->getModelFromCache('EWRporta2_Model_Authors')->getAllAuthors();
		
		if ($this->_request->isPost())
		{
			$orders = $this->_input->filterSingle('author', array(XenForo_Input::UINT, 'array' => true));
			$this->getModelFromCache('EWRporta2_Model_Authors')->updateAuthorOrders($orders);
			
			return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildPublicLink('articles/authors'));
		}
		
		$viewParams = array(
			'authors' => $authors,
		);
			
		return $this->responseView('EWRporta2_ViewPublic_AuthorOrder', 'EWRporta2_AuthorOrder', $viewParams);
	}
	
	public function actionSettings()
	{
		if (!$userID = XenForo_Visitor::getUserId()) { return $this->responseNoPermission(); }
		
		if ($this->_request->isPost())
		{
			$input = $this->_input->filter(array(
				'setting_filter' => XenForo_Input::ARRAY_SIMPLE,
				'setting_options' => XenForo_Input::ARRAY_SIMPLE,
			));
			$input['user_id'] = $userID;
			$input['setting_arrange'] = array();
			
			$order = 1;
			$positions = $this->_input->filterSingle('positions', XenForo_Input::ARRAY_SIMPLE);
			
			foreach($positions AS $widlink => $position)
			{
				$input['setting_arrange'][$widlink] = array(
					'widlink_position' => $position,
					'widlink_order' => $order++,
				);
			}
			
			$setting = $this->getModelFromCache('EWRporta2_Model_Settings')->updateSetting($input);
			return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildPublicLink('articles'));
		}
		
		$categories = $this->getModelFromCache('EWRporta2_Model_Categories')->getAllCategories('category');
		$widlinks = $this->getModelFromCache('EWRporta2_Model_Widlinks')->getWidlinksByLayoutId('article_list');
		
		if ($setting = $this->getModelFromCache('EWRporta2_Model_Settings')->getSettingById($userID))
		{
			$widlinks = $this->getModelFromCache('EWRporta2_Model_Widlinks')->arrangeWidlinks($widlinks, $setting['setting_arrange']);
		
			foreach ($setting['setting_filter'] AS $category)
			{
				if (isset($categories[$category]))
				{
					$categories[$category]['selected'] = true;
				}
			}
		}
		
		$viewParams = array(
			'perms' => $this->xp2perms,
			'setting' => $setting,
			'categories' => $categories,
			'arranging' => !empty($setting['setting_arrange']),
			'widlinks' => $this->getModelFromCache('EWRporta2_Model_Widlinks')->sortWidlinksToLayout($widlinks),
		);
		
		return $this->responseView('EWRporta2_ViewPublic_Settings', 'EWRporta2_Settings', $viewParams);
	}
	
	public function actionFindTags()
	{
		$q = ltrim($this->_input->filterSingle('q', XenForo_Input::STRING, array('noTrim' => true)));

		if ($q !== '' && utf8_strlen($q) >= 2)
		{
			$tags = $this->getModelFromCache('EWRporta2_Model_Categories')->findTags($q, 10);
		}
		else
		{
			$tags = array();
		}

		$viewParams = array('tags' => $tags);

		return $this->responseView('EWRporta2_ViewPublic_FindTags', '', $viewParams);
	}

	private function _getViewParams($params = array())
	{
		$options = XenForo_Application::get('options');
		$start = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
		$stop = $options->EWRporta2_articles_limit;
		
		if (($this->xp2perms['filter'] || $this->xp2perms['arrange']) &&
			$setting = $this->getModelFromCache('EWRporta2_Model_Settings')->getSettingById(XenForo_Visitor::getUserId()))
		{
			if ($this->xp2perms['filter'])
			{
				$params['filter'] = $setting['setting_filter'];
			}
		}
		
		if (empty($params['skip']) && $options->EWRporta2_features_limit && empty($setting['setting_options']['feature']))
		{
			$features = $this->getModelFromCache('EWRporta2_Model_Features')->getFeatures($options->EWRporta2_features_limit, $params);
			$features = $this->getModelFromCache('EWRporta2_Model_Features')->prepareFeatures($features);
		
			if ($options->EWRporta2_articles_exclude)
			{
				$params['exclude'] = array_keys($features);
			}
		}
		else
		{
			$features = array();
		}
		
		$count = $this->getModelFromCache('EWRporta2_Model_Articles')->getArticlesCount($params);
		$articles = $this->getModelFromCache('EWRporta2_Model_Articles')->getArticles($start, $stop, $params);
		$articles = $this->getModelFromCache('EWRporta2_Model_Articles')->prepareArticles($articles);

		return array(
			'perms' => $this->xp2perms,
			'start' => $start,
			'stop' => $stop,
			'count' => $count,
			'articles' => $articles,
			'features' => $features,
			'setting' => !empty($setting) ? $setting : false,
		);
	}
	
	private function _getRssParams($params = array())
	{
		$options = XenForo_Application::get('options');
		
		$articles = $this->getModelFromCache('EWRporta2_Model_Articles')->getArticles(1, 10, $params);
		$articles = $this->getModelFromCache('EWRporta2_Model_Articles')->prepareArticles($articles);
		
		return array(
			'articles' => $articles,
		);
	}

	public static function getSessionActivityDetailsForList(array $activities)
	{
        $output = array();
        foreach ($activities as $key => $activity)
		{
			$output[$key] = new XenForo_Phrase('porta2_viewing_articles_index');
        }

        return $output;
	}

	protected function _preDispatch($action)
	{
		parent::_preDispatch($action);

		$this->xp2perms = $this->getModelFromCache('EWRporta2_Model_Perms')->getPermissions();
	}
}