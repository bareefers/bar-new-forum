<?php

class ChangeThreadStarter_ControllerPublic_Thread extends XFCP_ChangeThreadStarter_ControllerPublic_Thread
{
	public function actionIndex()
	{
		$parent = parent::actionIndex();
		
		$visitor = XenForo_Visitor::getInstance();
		$canChangeThreadStarter = $visitor->hasPermission('general', 'canChangeThreadStarter');
		
		$parent->params['canChangeThreadStarter'] = $canChangeThreadStarter;
		
		return $parent;
	}
	
	public function actionChangeStarter()
	{
		$threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);

		$ftpHelper = $this->getHelper('ForumThreadPost');
		list($threadFetchOptions, $forumFetchOptions) = $this->_getThreadForumFetchOptions();
		list($thread, $forum) = $ftpHelper->assertThreadValidAndViewable($threadId, $threadFetchOptions, $forumFetchOptions);

		$visitor = XenForo_Visitor::getInstance();
		$canChangeThreadStarter = $visitor->hasPermission('general', 'canChangeThreadStarter');
		
		if (!$canChangeThreadStarter)
		{
			throw $this->getNoPermissionResponseException();
		}
		
	    if ($this->isConfirmedPost())
	    {
	    	$username = $this->_input->filterSingle('username', XenForo_Input::STRING);
	    	
	    	$userModel = $this->_getUserModel();
	    	$user = $userModel->getUserByName($username);
	    	
	    	if (!$user)
	    	{
				return $this->responseError(new XenForo_Phrase('cts_user_not_found_or_not_specified'));
	    	}	    	

	    	$existingUser = $userModel->getUserById($thread['user_id']);
	    	
	    	$postModel = $this->_getPostModel();
	    	$post = $postModel->getPostById($thread['first_post_id']);
	    	
	    	$likes = unserialize($post['like_users']);
	    	
	    	$likeModel = $this->_getLikeModel();
		    $userLikes = $likeModel->getContentLikeByLikeUser('post', $post['post_id'], $user['user_id']);
		    
			if ($userLikes)
			{
				$likeModel->unlikeContent($userLikes);
			}
	    	
	    	$threadData = array(
	    		'user_id' => $user['user_id'],
	    		'username' => $user['username']
	    	);
	    	
	    	if (!$thread['reply_count'])
	    	{
				$threadData += array(
					'last_post_user_id' => $user['user_id'],
					'last_post_username' => $user['username']
				);
	    	}
	    	
	    	$postData = array(
	    		'user_id' => $user['user_id'],
	    		'username' => $user['username'],
	    		'like_users' => serialize($likes)
	    	);
	    	
	    	$userData = array(
	    		'message_count' => $user['message_count'] + 1
	    	);
	    	
	    	$userWriter = XenForo_DataWriter::create('XenForo_DataWriter_User');
	    	$userWriter->setExistingData($user['user_id']);

		    if ($existingUser)
		    {
			    $existingUserData = array(
				    'message_count' => $existingUser['message_count'] - 1
			    );

			    $existingUserWriter = XenForo_DataWriter::create('XenForo_DataWriter_User');
			    $existingUserWriter->setExistingData($existingUser['user_id']);

			    if ($likes)
			    {
				    $existingUserData += array(
					    'like_count' => $existingUser['like_count'] - count($likes)
				    );
			    }

			    $existingUserWriter->bulkSet($existingUserData);
			    $existingUserWriter->save();
		    }

            $userData += array(
                'like_count' => $user['like_count'] + count($likes)
            );
	    	
	    	$userWriter->bulkSet($userData);
	    	$userWriter->save();
	    	
	    	$postDataWriter = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_Post');
	    	$postDataWriter->setExistingData($post['post_id']);
	    	$postDataWriter->bulkSet($postData);
	    	$postDataWriter->save();
	    	
	    	$threadDataWriter = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread');
	    	$threadDataWriter->setExistingData($thread['thread_id']);
	    	$threadDataWriter->bulkSet($threadData);
	    	$threadDataWriter->save();
	    	
			$redirectLink = XenForo_Link::buildPublicLink('threads', $thread);
			$redirectMessage = new XenForo_Phrase('cts_thread_starter_changed');

			return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, $redirectLink, $redirectMessage);
		}
		else
		{
			$viewParams = array(
				'thread' => $thread
			);
			
			return $this->responseView(
				'ChangeThreadStarter_ViewPublic_ThreadChangeStarter',
				'cts_change_thread_starter_form',
				$viewParams
			);			
		}	
	}
	
	/**
	 * @return XenForo_Model_User
	 */
	protected function _getUserModel()
	{
		return $this->getModelFromCache('XenForo_Model_User');
	}
	
	/**
	 * @return XenForo_Model_Post
	 */
	protected function _getPostModel()
	{
		return $this->getModelFromCache('XenForo_Model_Post');
	}	
	
	/**
	 * @return XenForo_Model_Like
	 */
	protected function _getLikeModel()
	{
		return $this->getModelFromCache('XenForo_Model_Like');
	}	
}