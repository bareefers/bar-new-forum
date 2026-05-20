<?php

class EWRporta2_ControllerPublic_Thread extends XFCP_EWRporta2_ControllerPublic_Thread
{
	private $xp2perms;
	
	public function actionIndex()
	{
		$response = parent::actionIndex();
		
		if ($response instanceof XenForo_ControllerResponse_View &&
			$article = $this->getModelFromCache('EWRporta2_Model_Articles')->getArticleByThreadIdOrAuto($response->params['thread']['thread_id']))
		{
			if(!empty($article['article_options']['article']))
			{
				$response->params['perms'] = $this->xp2perms;
				$response->params['article'] = $article;
				$response->params['categories'] = $this->getModelFromCache('EWRporta2_Model_Catlinks')->getCatlinksByThread($article, false, true);
				$response->params['fbTemplate'] = 'EWRporta2_ArticleList';
				
				if(!empty($article['article_options']['author']))
				{
					$response->params['author'] = $this->getModelFromCache('EWRporta2_Model_Authors')->getAuthorById($response->params['thread']['user_id']);
				}
				
				return $this->responseView('EWRporta2_ViewPublic_ArticleView', 'EWRporta2_ArticleView', $response->params);
			}
		}
		
		return $response;
	}
	
	public function actionAddReply()
	{
		$response = parent::actionAddReply();
		
		if ($response instanceof XenForo_ControllerResponse_View)
		{
			$threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);
			$article = $this->getModelFromCache('EWRporta2_Model_Articles')->getArticleByThreadIdOrAuto($threadId);
			
			if(!empty($article['article_options']['comments']))
			{
				return $this->responseView(
					'XenForo_ViewPublic_Thread_ViewNewPosts',
					'EWRporta2_Thread_NewPosts',
					$response->params
				);
			}
		}
		
		return $response;
	}

	public function actionPromoteArticle()
	{
		if (!$this->xp2perms['promote']) { return $this->responseNoPermission(); }
		
		$threadID = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);
		$ftpHelper = $this->getHelper('ForumThreadPost');
		list($thread, $forum) = $ftpHelper->assertThreadValidAndViewable($threadID);
		$post = $this->getModelFromCache('XenForo_Model_Post')->getPostById($thread['first_post_id']);
		$article = $this->getModelFromCache('EWRporta2_Model_Articles')->getArticleByThreadPost($thread, $post);
		
		if ($this->_request->isPost())
		{
			$input = $this->_input->filter(array(
				'thread_id' => XenForo_Input::UINT,
				'article_options' => XenForo_Input::ARRAY_SIMPLE,
				'article_date' => XenForo_Input::ARRAY_SIMPLE,
				'article_icon' => XenForo_Input::ARRAY_SIMPLE,
				'article_break' => XenForo_Input::STRING,
				'article_custom' => XenForo_Input::UINT,
				'article_exclude' => XenForo_Input::UINT,
			));
			$input['article_title'] = $input['article_custom'] ? $this->_input->filterSingle('article_title', XenForo_Input::STRING) : '';
			$input['article_excerpt'] = $input['article_custom'] ? $this->getHelper('Editor')->getMessageText('article_excerpt', $this->_input) : '';
			
			$this->getModelFromCache('EWRporta2_Model_Articles')->updateArticle($input);
			$this->getModelFromCache('EWRporta2_Model_Catlinks')->updateCatlinks($this->_input->filter(array(
				'thread_id' => XenForo_Input::UINT,
				'catlinks' => XenForo_Input::ARRAY_SIMPLE,
				'oldlinks' => XenForo_Input::ARRAY_SIMPLE,
			)));
			
			return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildPublicLink('threads', $thread));
		}
		
		$hours = array("12","01","02","03","04","05","06","07","08","09","10","11");
		if (XenForo_Application::get('options')->EWRporta2_promote_24hour)
		{
			$hours[0] = "24";
			$hours = array_merge($hours, array("12","13","14","15","16","17","18","19","20","21","22","23"));
		}

		$icons = $this->getModelFromCache('EWRporta2_Model_Articles')->getArticleIconsByPost($post);
		
		$viewParams = array(
			'perms' => $this->xp2perms,
			'forum' => $forum,
			'thread' => $thread,
			'article' => $article,
			'icons' => $icons,
			'hours' => $hours,
			'catlinks' => $this->getModelFromCache('EWRporta2_Model_Catlinks')->getCatlinksByThread($thread, 'category'),
			'categories' => $this->getModelFromCache('EWRporta2_Model_Catlinks')->getCatlinksByThread($thread, 'category', false),
			'nodeBreadCrumbs' => $ftpHelper->getNodeBreadCrumbs($forum),
		);
		
		return $this->responseView('EWRporta2_ViewPublic_Thread_ArticlePromote', 'EWRporta2_Thread_ArticlePromote', $viewParams);
	}
	
	public function actionDeleteArticle()
	{
		if (!$this->xp2perms['promote']) { return $this->responseNoPermission(); }
		
		$threadID = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);
		$ftpHelper = $this->getHelper('ForumThreadPost');
		list($thread, $forum) = $ftpHelper->assertThreadValidAndViewable($threadID);
		$post = $this->getModelFromCache('XenForo_Model_Post')->getPostById($thread['first_post_id']);
		$article = $this->getModelFromCache('EWRporta2_Model_Articles')->getArticleByThreadPost($thread, $post);
		
		if ($this->_request->isPost())
		{
			$this->getModelFromCache('EWRporta2_Model_Articles')->deleteArticle($article);
			return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildPublicLink('threads', $thread));
		}
		
		$viewParams = array(
			'perms' => $this->xp2perms,
			'forum' => $forum,
			'thread' => $thread,
			'article' => $article,
			'nodeBreadCrumbs' => $ftpHelper->getNodeBreadCrumbs($forum),
		);
		
		return $this->responseView('EWRporta2_ViewPublic_Thread_ArticleDelete', 'EWRporta2_Thread_ArticleDelete', $viewParams);
	}
	
	public function actionPromoteFeature()
	{
		if (!$this->xp2perms['promote']) { return $this->responseNoPermission(); }
		
		$threadID = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);
		$ftpHelper = $this->getHelper('ForumThreadPost');
		list($thread, $forum) = $ftpHelper->assertThreadValidAndViewable($threadID);
		$post = $this->getModelFromCache('XenForo_Model_Post')->getPostById($thread['first_post_id']);
		$feature = $this->getModelFromCache('EWRporta2_Model_Features')->getFeatureByThreadPost($thread, $post);
		
		if ($this->_request->isPost())
		{
			$input = $this->_input->filter(array(
				'thread_id' => XenForo_Input::UINT,
				'feature_date' => XenForo_Input::ARRAY_SIMPLE,
				'feature_custom' => XenForo_Input::UINT,
				'feature_exclude' => XenForo_Input::UINT,
			));
			$input['feature_title'] = $input['feature_custom'] ? $this->_input->filterSingle('feature_title', XenForo_Input::STRING) : '';
			$input['feature_excerpt'] = $input['feature_custom'] ? $this->_input->filterSingle('feature_excerpt', XenForo_Input::STRING) : '';
			
			$fileTransfer = new Zend_File_Transfer_Adapter_Http();
			
			if ($fileTransfer->isUploaded('upload_file'))
			{
				$fileInfo = $fileTransfer->getFileInfo('upload_file');
				$fileName = $fileInfo['upload_file']['tmp_name'];
				
				$input['feature_time'] = XenForo_Application::$time;
				$this->getModelFromCache('EWRporta2_Model_Features')->updateFeatureImage($thread, $fileName);
			}
			else if (empty($feature['thread_id']))
			{
				throw new XenForo_Exception(new XenForo_Phrase('porta2_slider_image_fail'), 'upload_file');
			}
			
			$this->getModelFromCache('EWRporta2_Model_Features')->updateFeature($input);
			return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildPublicLink('threads', $thread));
		}
		
		$hours = array("12","01","02","03","04","05","06","07","08","09","10","11");
		if (XenForo_Application::get('options')->EWRporta2_promote_24hour)
		{
			$hours[0] = "24";
			$hours = array_merge($hours, array("12","13","14","15","16","17","18","19","20","21","22","23"));
		}
		
		$viewParams = array(
			'perms' => $this->xp2perms,
			'forum' => $forum,
			'thread' => $thread,
			'feature' => $feature,
			'hours' => $hours,
			'nodeBreadCrumbs' => $ftpHelper->getNodeBreadCrumbs($forum),
		);
		
		return $this->responseView('EWRporta2_ViewPublic_Thread_FeaturePromote', 'EWRporta2_Thread_FeaturePromote', $viewParams);
	}
	
	public function actionDeleteFeature()
	{
		if (!$this->xp2perms['promote']) { return $this->responseNoPermission(); }
		
		$threadID = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);
		$ftpHelper = $this->getHelper('ForumThreadPost');
		list($thread, $forum) = $ftpHelper->assertThreadValidAndViewable($threadID);
		$post = $this->getModelFromCache('XenForo_Model_Post')->getPostById($thread['first_post_id']);
		$feature = $this->getModelFromCache('EWRporta2_Model_Features')->getFeatureByThreadPost($thread, $post);
		
		if ($this->_request->isPost())
		{
			$this->getModelFromCache('EWRporta2_Model_Features')->deleteFeature($feature);
			return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildPublicLink('threads', $thread));
		}
		
		$viewParams = array(
			'perms' => $this->xp2perms,
			'forum' => $forum,
			'thread' => $thread,
			'feature' => $feature,
			'nodeBreadCrumbs' => $ftpHelper->getNodeBreadCrumbs($forum),
		);
		
		return $this->responseView('EWRporta2_ViewPublic_Thread_FeatureDelete', 'EWRporta2_Thread_FeatureDelete', $viewParams);
	}
	
	public function actionCategories()
	{
		if (!$this->xp2perms['moderate']) { return $this->responseNoPermission(); }
		
		$threadID = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);
		$ftpHelper = $this->getHelper('ForumThreadPost');
		list($thread, $forum) = $ftpHelper->assertThreadValidAndViewable($threadID);

		if (!$article = $this->getModelFromCache('EWRporta2_Model_Articles')->getArticleByThreadId($threadID))
		{
			$article = $this->getModelFromCache('EWRporta2_Model_Articles')->updateArticle(array(
				'thread_id' => $thread['thread_id'],
				'article_options' => XenForo_Application::get('options')->EWRporta2_promote_defaults,
				'article_date' => $thread['post_date'],
			));
		}
		
		if ($this->_request->isPost())
		{
			$input = $this->_input->filter(array(
				'thread_id' => XenForo_Input::UINT,
				'catlinks' => XenForo_Input::ARRAY_SIMPLE,
				'oldlinks' => XenForo_Input::ARRAY_SIMPLE,
			));
			
			$categories = $this->getModelFromCache('EWRporta2_Model_Catlinks')->updateCatlinks($input);
		
			if ($this->_noRedirect())
			{
				$viewParams = array(
					'perms' => $this->xp2perms,
					'thread' => $thread,
					'article' => $article,
					'categories' => $categories,
				);

				return $this->responseView('EWRporta2_ViewPublic_ArticleCategories', 'EWRporta2_Article_Categories', $viewParams);
			}
			
			return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildPublicLink('threads', $thread));
		}
		
		$viewParams = array(
			'forum' => $forum,
			'thread' => $thread,
			'article' => $article,
			'catlinks' => $this->getModelFromCache('EWRporta2_Model_Catlinks')->getCatlinksByThread($thread, 'category'),
			'categories' => $this->getModelFromCache('EWRporta2_Model_Catlinks')->getCatlinksByThread($thread, 'category', false),
			'nodeBreadCrumbs' => $ftpHelper->getNodeBreadCrumbs($forum),
		);
		
		return $this->responseView('EWRporta2_ViewPublic_Thread_Categories', 'EWRporta2_Thread_Categories', $viewParams);
	}
	
	public function actionTags()
	{
		if (!$this->xp2perms['moderate']) { return $this->responseNoPermission(); }
		
		$threadID = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);
		$ftpHelper = $this->getHelper('ForumThreadPost');
		list($thread, $forum) = $ftpHelper->assertThreadValidAndViewable($threadID);

		if (!$article = $this->getModelFromCache('EWRporta2_Model_Articles')->getArticleByThreadId($threadID))
		{
			$article = $this->getModelFromCache('EWRporta2_Model_Articles')->updateArticle(array(
				'thread_id' => $thread['thread_id'],
				'article_options' => XenForo_Application::get('options')->EWRporta2_promote_defaults,
				'article_date' => $thread['post_date'],
			));
		}
		
		if ($this->_request->isPost())
		{
			$input = $this->_input->filter(array(
				'thread_id' => XenForo_Input::UINT,
				'taglinks' => XenForo_Input::ARRAY_SIMPLE,
				'oldlinks' => XenForo_Input::ARRAY_SIMPLE,
			));
			
			$tags = $this->getModelFromCache('EWRporta2_Model_Catlinks')->updateTaglinks($input);
		
			if ($this->_noRedirect())
			{
				$viewParams = array(
					'thread' => $thread,
					'article' => $article,
					'tags' => $tags,
				);

				return $this->responseView('EWRporta2_ViewPublic_ArticleTags', 'EWRporta2_Article_Tags', $viewParams);
			}
			
			return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildPublicLink('threads', $thread));
		}
		
		$viewParams = array(
			'forum' => $forum,
			'thread' => $thread,
			'article' => $article,
			'taglinks' => $this->getModelFromCache('EWRporta2_Model_Catlinks')->getCatlinksByThread($thread, 'tag'),
			'nodeBreadCrumbs' => $ftpHelper->getNodeBreadCrumbs($forum),
		);
		
		return $this->responseView('EWRporta2_ViewPublic_Thread_Tags', 'EWRporta2_Thread_Tags', $viewParams);
	}
	
	public function actionAddTags()
	{
		$this->_assertPostOnly();
		if (!$this->xp2perms['tag']) { return $this->responseNoPermission(); }
		
		$threadID = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);
		$ftpHelper = $this->getHelper('ForumThreadPost');
		list($thread, $forum) = $ftpHelper->assertThreadValidAndViewable($threadID);

		if (!$article = $this->getModelFromCache('EWRporta2_Model_Articles')->getArticleByThreadId($threadID))
		{
			$article = $this->getModelFromCache('EWRporta2_Model_Articles')->updateArticle(array(
				'thread_id' => $thread['thread_id'],
				'article_options' => XenForo_Application::get('options')->EWRporta2_promote_defaults,
				'article_date' => $thread['post_date'],
			));
		}
		
		$input = $this->_input->filter(array(
			'thread_id' => XenForo_Input::UINT,
			'new_tags' => XenForo_Input::STRING,
		));
		$input['new_tags'] = explode(',', $input['new_tags']);
		
		$tags = $this->getModelFromCache('EWRporta2_Model_Catlinks')->updateTags($input);
		
		if ($this->_noRedirect())
		{
			$viewParams = array(
				'article' => $article,
				'thread' => $thread,
				'tags' => $tags,
			);

			return $this->responseView('EWRporta2_ViewPublic_ArticleTags', 'EWRporta2_Article_Tags', $viewParams);
		}
		
		return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildPublicLink('threads', $thread));
	}

	protected function _preDispatch($action)
	{
		parent::_preDispatch($action);

		$this->xp2perms = $this->getModelFromCache('EWRporta2_Model_Perms')->getPermissions();
	}
}