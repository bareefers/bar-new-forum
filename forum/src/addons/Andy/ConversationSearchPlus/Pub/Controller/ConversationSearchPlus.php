<?php

namespace Andy\ConversationSearchPlus\Pub\Controller;

use XF\Pub\Controller\AbstractController;

class ConversationSearchPlus extends AbstractController
{
	public function actionIndex()
	{
		// get visitor
		$visitor = \XF::visitor();
		
		// get permission
		if (!$visitor->hasPermission('conversationSearchPlus', 'view'))
		{
			return $this->noPermission();
		}
		
		// send to template	
		return $this->view('Andy\ConversationSearchPlus:Index', 'andy_conversationsearchplus');
	}
	
	public function actionResults()
	{
		// get visitor
		$visitor = \XF::visitor();
		
		// get permission
		if (!$visitor->hasPermission('conversationSearchPlus', 'view'))
		{
			return $this->noPermission();
		}
        
		// get options
		$options = \XF::options();
		
		// get options from Admin CP -> Options -> Basic board information -> Board Url
		$boardUrl = $options->boardUrl;
		
		// get options from Admin CP -> Options -> Basic board information -> Use full friendly URLs
		$useFriendlyUrls = $options->useFriendlyUrls;
		
		// get options from Admin CP -> Options -> Conversation search plus -> Limit
		$limit = $options->conversationSearchPlusLimit;

		// assert post only
		$this->assertPostOnly();
		
		// get type
		$type = $this->filter('type', 'str');
		
		// get keywords
		$keywords = $this->filter('keywords', 'str');
        
		// get postedBy
		$postedBy = $this->filter('posted_by', 'str');
		
		// check condition
		if (!empty($postedBy))
		{
            // get result
            $finder = \XF::finder('XF:User');
            $result = $finder
                ->where('username', $postedBy)
                ->fetchOne();
			
			// check condition
			if (empty($result))
			{
				return $this->error(\XF::phrase('conversationsearch_php_username_not_found'));
			}	
            
			// check condition
			if (!empty($result))
			{
				$userId = $result['user_id'];
			}
		}
		
		//########################################
		// latest conversations
		//########################################
		
        // check condition
		if ($type == 'title' AND empty($keywords) AND empty($postedBy))
		{
            // get results
            $finder = \XF::finder('XF:ConversationMaster');
            $results = $finder
                ->order('start_date', 'DESC')
                ->limit($limit)
                ->fetch();
            
            // get resultsCount
            $resultsCount = count($results);

			// get viewParams
			$viewParams = [
				'limit' => $limit,
				'results' => $results,
                'resultsCount' => $resultsCount
			];

			// send to template
			return $this->view('Andy\ConversationSearchPlus:Results', 'andy_conversationsearchplus_results_latest_conversations', $viewParams);
		}
        
		//########################################
		// latest messages
		//########################################
		
        // check condition
		if ($type == 'message' AND empty($keywords) AND empty($postedBy))
		{
            // get results
            $finder = \XF::finder('XF:ConversationMessage');
            $results = $finder
                ->order('message_id', 'DESC')
                ->limit($limit)
                ->fetch();
            
			// get resultsCount
			$resultsCount = count($results);

			// get viewParams
			$viewParams = [
				'limit' => $limit,
				'results' => $results,
                'resultsCount' => $resultsCount
			];

			// send to template
			return $this->view('Andy\ConversationSearchPlus:Results', 'andy_conversationsearchplus_results_latest_messages', $viewParams);
		}
		
		//########################################
		// title and keywords
		//########################################
		
        // check condition
		if ($type == 'title' AND !empty($keywords))
		{
            // check condition
            if (empty($postedBy))
            {
                // get results
                $finder = \XF::finder('XF:ConversationMaster');
                $results = $finder
                    ->where('title', 'LIKE', $finder->escapeLike($keywords, '%?%'))
                    ->order('conversation_id', 'DESC')
                    ->limit($limit)
                    ->fetch();
            }
            
            // check condition
            if (!empty($postedBy))
            {
                // get results
                $finder = \XF::finder('XF:ConversationMaster');
                $results = $finder
                    ->where('user_id', $userId)
                    ->where('title', 'LIKE', $finder->escapeLike($keywords, '%?%'))
                    ->order('conversation_id', 'DESC')
                    ->limit($limit)
                    ->fetch();
            }
            
            // get resultsCount
            $resultsCount = count($results);

			// get viewParams
			$viewParams = [
				'keywords' => $keywords,
				'postedBy' => $postedBy,
				'limit' => $limit,
				'results' => $results,
                'resultsCount' => $resultsCount
			];

			// send to template
			return $this->view('Andy\ConversationSearchPlus:Results', 'andy_conversationsearchplus_results_title', $viewParams);
		}
		
		//########################################
		// message and keywords
		//########################################

        // check condition
		if ($type == 'message' AND !empty($keywords))
		{
            // check condition
            if (empty($postedBy))
            {  
                // get results
                $finder = \XF::finder('XF:ConversationMessage');
                $results = $finder
                    ->where('message', 'LIKE', $finder->escapeLike($keywords, '%?%'))
                    ->order('message_id', 'DESC')
                    ->limit($limit)
                    ->fetch();
            }
            
            // check condition
            if (!empty($postedBy))
            {  
                // get results
                $finder = \XF::finder('XF:ConversationMessage');
                $results = $finder
                    ->where('user_id', $visitor['user_id'])
                    ->where('message', 'LIKE', $finder->escapeLike($keywords, '%?%'))
                    ->order('message_id', 'DESC')
                    ->limit($limit)
                    ->fetch();
            }
            
			// get resultsCount
			$resultsCount = count($results);

			// get viewParams
			$viewParams = [
				'keywords' => $keywords,
				'postedBy' => $postedBy,
				'limit' => $limit,
				'results' => $results,
                'resultsCount' => $resultsCount
			];

			// send to template
			return $this->view('Andy\ConversationSearchPlus:Results', 'andy_conversationsearchplus_results_message', $viewParams);
		}
        
		//########################################
		// username only
		//########################################
		
        // check condition
		if (!empty($postedBy) AND empty($keywords))
		{
            // check condition
            if ($type == 'title')
            {
                // get results
                $finder = \XF::finder('XF:ConversationMaster');
                $results = $finder
                    ->where('user_id', $userId)
                    ->order('start_date', 'DESC')
                    ->limit($limit)
                    ->fetch();

                // get resultsCount
                $resultsCount = count($results);

                // get viewParams
                $viewParams = [
                    'postedBy' => $postedBy,
                    'limit' => $limit,
                    'results' => $results,
                    'resultsCount' => $resultsCount
                ];

                // send to template
                return $this->view('Andy\ConversationSearchPlus:Results', 'andy_conversationsearchplus_results_title_username', $viewParams);
            }
  
            // check condition
            if ($type == 'message')
            {
                // get results
                $finder = \XF::finder('XF:ConversationMessage');
                $results = $finder
                    ->where('user_id', $userId)
                    ->order('message_date', 'DESC')
                    ->limit($limit)
                    ->fetch();

                // get resultsCount
                $resultsCount = count($results);

                // get viewParams
                $viewParams = [
                    'postedBy' => $postedBy,
                    'limit' => $limit,
                    'results' => $results,
                    'resultsCount' => $resultsCount
                ];

                // send to template
                return $this->view('Andy\ConversationSearchPlus:Results', 'andy_conversationsearchplus_results_message_username', $viewParams);
            }
        }    
	}
	
	public function actionConversation()
	{
		// get visitor
		$visitor = \XF::visitor();
		
		// get permission
		if (!$visitor->hasPermission('conversationSearchPlus', 'view'))
		{
			return $this->noPermission();
		}
		
		// get conversationId
		$conversationId = $this->filter('conversation_id', 'uint');

		// verify conversationId
		if ($conversationId == '')
		{
			return $this->error(\XF::phrase('conversationsearchplus_php_conversation_id_missing'));
		}
		
		// get options
		$options = \XF::options();
		
		// get options from Admin CP -> Options -> Conversation view plus -> Limit
		$limit = $options->conversationSearchPlusLimit;
        
        // get results1
        $finder = \XF::finder('XF:ConversationMaster');
        $results1 = $finder
            ->where('conversation_id', $conversationId)
            ->fetch();

        // get results2
        $finder = \XF::finder('XF:ConversationRecipient');
        $results2 = $finder
            ->where('conversation_id', $conversationId)
            ->order('User.username', 'ASC')
            ->fetch();
        
        // get results3
        $finder = \XF::finder('XF:ConversationMessage');
        $results3 = $finder
            ->where('conversation_id', $conversationId)
            ->order('message_id', 'ASC')
            ->limit($limit)
            ->fetch();

		// check condition
		if (empty($results1))
		{
			return $this->error(\XF::phrase('conversationsearchplus_php_conversation_id_not_found'));
		}
        
		// check condition
		if (empty($results2))
		{
			return $this->error(\XF::phrase('conversationsearchplus_php_conversation_id_not_found'));
		}
        
		// check condition
		if (empty($results3))
		{
			return $this->error(\XF::phrase('conversationsearchplus_php_conversation_id_not_found'));
		}

		// get viewParams
		$viewParams = [
            'conversationId' => $conversationId,
            'limit' => $limit,
            'results1' => $results1,
            'results2' => $results2,
            'results3' => $results3
		];

		// send to template
		return $this->view('Andy\ConversationSearchPlus:Conversation', 'andy_conversationsearchplus_conversation_view', $viewParams);
	}
	
	public function actionAttachments()
	{
		// get visitor
		$visitor = \XF::visitor();
		
		// get permission
		if (!$visitor->hasPermission('conversationSearchPlus', 'view'))
		{
			return $this->noPermission();
		}
		
		// get messageId
		$messageId = $this->filter('message_id', 'uint');
		
		// verify messageId
		if ($messageId == '')
		{
			return $this->error(\XF::phrase('conversationsearchplus_php_message_id_missing'));
		}
        
        // get results
        $finder = \XF::finder('XF:Attachment');
        $attachmentIds = $finder
            ->where('content_type', 'conversation_message')
            ->where('content_id', $messageId)
            ->fetchRaw();

        // define results
		$results = array();
		
        // foreach condition
		foreach ($attachmentIds as $value)
		{
			if ($value['thumbnail_width'] > 0)
			{
				// get dataId
				$dataId = $value['data_id'];

				// get path
				$path = sprintf('attachments/%d/%d-%s.jpg',
					floor($dataId / 1000),
					$dataId,
					$value['file_hash']
				);

				// get $thumbUrl
				$thumbUrl = $this->app()->applyExternalDataUrl($path);
				
				// get dataId
				$dataId = $value['data_id'];

				// get fileHash
				$fileHash = $value['file_hash'];
				
				// get internal_data path
				$internalDataPath = \XF::app()->config('internalDataPath') . '/attachments/';

				// get last folder
				$lastFolder = floor($dataId / 1000);

				// define full attachment file path
				$attachmentPath = $internalDataPath . $lastFolder . '/' . $dataId . '-' . $fileHash . '.data';				

				// get options
				$options = \XF::options();

				// get options from Admin CP -> Options -> Basic board information -> Board Url
				$boardUrl = $options->boardUrl;				

				// get results			
				$results[] = '<a href="' . $boardUrl . '/conversationsearchplus/image/?attachment_id=' . $value['attachment_id'] . '">' . $value['filename'] . '</a>' . '<br /><br />' . '<img src="' . $thumbUrl . '">' . '<br />';
	
			}

            // check condition
			if ($value['thumbnail_width'] == 0)
			{
				// get dataId
				$dataId = $value['data_id'];
				
				// get fileHash
				$fileHash = $value['file_hash'];
				
				// get internalDataPath
				$internalDataPath = \XF::app()->config('internalDataPath') . '/attachments/';

				// get lastFolder
				$lastFolder = floor($dataId / 1000);

				// get attachmentPath
				$attachmentPath = $internalDataPath . $lastFolder . '/' . $dataId . '-' . $fileHash . '.data';				

				// get options
				$options = \XF::options();

				// get options from Admin CP -> Options -> Basic board information -> Board Url
				$boardUrl = $options->boardUrl;

				// get results
				$results[] = '<a href="' . $boardUrl . '/conversationsearchplus/download/?attachment_id=' . $value['attachment_id'] . '">' . $value['filename'] . '</a>' . '<br />';
			}
		}

		// get viewParams
		$viewParams = [
			'messageId' => $messageId,
			'results' => $results
		];

		// send to template
		return $this->view('Andy\ConversationSearchPlus:Attachments', 'andy_conversationsearchplus_attachments', $viewParams);
	}
	
	public function actionDownload()
	{
		// get visitor
		$visitor = \XF::visitor();
		
		// get permission
		if (!$visitor->hasPermission('conversationSearchPlus', 'view'))
		{
			return $this->noPermission();
		}
		
		// get attachmentId
		$attachmentId = $this->filter('attachment_id', 'uint');
		
		// check condition
		if ($attachmentId == '')
		{
			return $this->error(\XF::phrase('conversationsearchplus_php_attachment_id_missing'));
		}
        
        // get data
        $finder = \XF::finder('XF:Attachment');
        $data = $finder
            ->where('content_type', 'conversation_message')
            ->where('attachment_id', $attachmentId)
            ->fetchOne();
		
		// get dataId
		$dataId = $data['data_id'];

		// get fileHash
		$fileHash = $data->Data['file_hash'];
		
		// get internalDataPath
		$internalDataPath = \XF::app()->config('internalDataPath') . '/attachments/';

		// get lastFolder
		$lastFolder = floor($dataId / 1000);

		// dget attachmentPath
		$attachmentPath = $internalDataPath . $lastFolder . '/' . $dataId . '-' . $fileHash . '.data';

		// set content-type
		header("Content-type:application/octet-stream");
		
		// get fileName
		$fileName = str_replace(' ', '-', $data['filename']);

		// set content-disposition
		header('Content-Disposition: attachment; filename=' . $fileName);
		
		// read file
		readfile($attachmentPath);
		
		// exit
		exit();
	}
	
	public function actionImage()
	{
		// get visitor
		$visitor = \XF::visitor();
		
		// get permission
		if (!$visitor->hasPermission('conversationSearchPlus', 'view'))
		{
			return $this->noPermission();
		}
		
		// get attachmentId
		$attachmentId = $this->filter('attachment_id', 'uint');
		
		// check condition
		if (empty($attachmentId))
		{
			return $this->error(\XF::phrase('conversationsearchplus_php_attachment_id_missing'));
		}
        
        // get data
        $finder = \XF::finder('XF:Attachment');
        $data = $finder
            ->where('content_type', 'conversation_message')
            ->where('attachment_id', $attachmentId)
            ->fetchOne();
		
		// get dataId
		$dataId = $data['data_id'];

		// get fileHash
		$fileHash = $data->Data['file_hash'];	
		
		// get internalDataPath
		$internalDataPath = \XF::app()->config('internalDataPath') . '/attachments/';

		// get last folder
		$lastFolder = floor($dataId / 1000);

		// get attachmentPath
		$attachmentPath = $internalDataPath . $lastFolder . '/' . $dataId . '-' . $fileHash . '.data';

		// get extension
		$extension = \XF\Util\File::getFileExtension($data['filename']);
		
		// check condition
		if ($extension == 'jpg')
		{
			header("Content-type:image/jpg");
		}
		
		// check condition
		if ($extension == 'jpeg')
		{
			header("Content-type:image/jpeg");
		}
		
		// check condition
		if ($extension == 'gif')
		{
			header("Content-type:image/gif");
		}
		
		// check condition
		if ($extension == 'png')
		{
			header("Content-type:image/png");
		}
		
		// get fileName
		$fileName = str_replace(' ', '-', $data['filename']);
		
		// display image
		echo file_get_contents($attachmentPath);
		
		// exit
		exit();
	}
    
    public function actionDelete()
    {
		// get visitor
		$visitor = \XF::visitor();				
		
		// check for user group permission
		if (!$visitor->hasPermission('conversationSearchPlus', 'view'))
		{
			return $this->noPermission();
		}
		
		// get conversationId
		$conversationId = $this->filter('conversation_id', 'uint');
        
        // get result
        $finder = \XF::finder('XF:ConversationMaster');
        $result = $finder
            ->where('conversation_id', $conversationId)
            ->fetchOne();
        
        // get title
        $title = $result['title'];
		
		// verify conversationId
		if ($conversationId == '')
		{
			return $this->error(\XF::phrase('conversationsearchplus_php_conversation_id_missing'));
		}
        
        // get viewParams
        $viewParams = [
            'title' => $title,
            'conversationId' => $conversationId
        ];
        
		// send to template
		return $this->view('Andy\ConversationSearchPlus:Delete', 'andy_conversationsearchplus_delete', $viewParams);
    }

    public function actionDeleteConfirmed()
    {  
		// get visitor
		$visitor = \XF::visitor();				
		
		// check for user group permission
		if (!$visitor->hasPermission('conversationSearchPlus', 'view'))
		{
			return $this->noPermission();
		}
		
		// get conversationId
		$conversationId = $this->filter('conversation_id', 'uint');

		// verify conversationId
		if ($conversationId == '')
		{
			return $this->error(\XF::phrase('conversationsearchplus_php_conversation_id_missing'));
		}
			
		//########################################
		// delete attachments
		//########################################

        // get results
        $finder = \XF::finder('XF:Attachment');
        $results = $finder
            ->where('content_type', 'conversation_message')
            ->where('content_id', $conversationId)
            ->fetch();

        // foreach condition
        foreach ($results as $result)
        {
            $result->delete();
        }

		//########################################
		// delete conversations
		//########################################
        
        // get results
        $finder = \XF::finder('XF:ConversationMaster');
        $results = $finder
            ->where('conversation_id', $conversationId)
            ->fetch();

        // foreach condition
        foreach ($results as $result)
        {
            $result->delete();
        }
        
        // get results
        $finder = \XF::finder('XF:ConversationMessage');
        $results = $finder
            ->where('conversation_id', $conversationId)
            ->fetch();

        // foreach condition
        foreach ($results as $result)
        {
            $result->delete();
        }
        
        // get results
        $finder = \XF::finder('XF:ConversationRecipient');
        $results = $finder
            ->where('conversation_id', $conversationId)
            ->fetch();

        // foreach condition
        foreach ($results as $result)
        {
            $result->delete();
        }

        // get results
        $finder = \XF::finder('XF:ConversationUser');
        $results = $finder
            ->where('conversation_id', $conversationId)
            ->fetch();

        // foreach condition
        foreach ($results as $result)
        {
            $result->delete();
        }
        
		//########################################
		// delete alert
		//########################################

        // get results
        $finder = \XF::finder('XF:UserAlert');
        $results = $finder
            ->where('content_type', 'conversation_message')
            ->where('content_id', $conversationId)
            ->fetch();

        // foreach condition
        foreach ($results as $result)
        {
            $result->delete();
        }
		
		// return redirect
		return $this->redirect($this->buildLink('conversationsearchplus'));
    }
	
	public function actionReport()
	{
		// get visitor
		$visitor = \XF::visitor();				
		
		// check for user group permission
		if (!$visitor->hasPermission('conversationSearchPlus', 'view'))
		{
			return $this->noPermission();
		}
		
		// get messageId
		$messageId = $this->filter('message_id', 'uint');
        
        // get viewParams
        $viewParams = [
            'messageId' => $messageId
        ];
        
		// send to template
		return $this->view('Andy\ConversationSearchPlus:Report', 'andy_conversationsearchplus_report', $viewParams);
	}
	
	public function actionReportSave()
	{
		// get visitor
		$visitor = \XF::visitor();				
		
		// check for user group permission
		if (!$visitor->hasPermission('conversationSearchPlus', 'view'))
		{
			return $this->noPermission();
		}
		
		// get messageId
		$messageId = $this->filter('message_id', 'uint');

		// get reportReason
		$reportReason = $this->filter('report_reason', 'str');
		
		// get starterUser
		$starterUser = \XF::app()->find('XF:User', $visitor->user_id);
		
		// get options
		$options = \XF::options();
		
		// get options from Admin CP -> Options -> User discouragement and discipline -> Send reports into forum
		$reportIntoForumId = $options->reportIntoForumId;
		
		// check condition
		if (!empty($options->reportWatchManagerUsernames))
		{
			// get options from Admin CP -> Options -> Report watch manager -> Usernames
			$usernames = $options->reportWatchManagerUsernames;
		}
		
		// check condition
		if (!empty($options->reportWatchManagerWatchState))
		{		
			// get options from Admin CP -> Options -> Report watch manager -> Watch state
			$watchState = $options->reportWatchManagerWatchState;
		}
		
		// check condition
		if (!empty($reportIntoForumId))
		{
			// get result
			$finder = \XF::finder('XF:Forum');
			$forum = $finder
				->where('node_id', $reportIntoForumId)
				->fetchOne();
			
			// get results
			$finder = \XF::finder('XF:ConversationMessage');
			$result = $finder
				->with('Conversation')
				->where('message_id', $messageId)
				->fetchOne();

			// get conversationTitle
			$conversationTitle = $result->Conversation->title;

			// get title
			$title = \XF::phrase('reported_content:') . ' ' . \XF::phrase('conversationsearchplus_php_conversation_message_in') . ' '  . "'" . $conversationTitle . "'";
			
			// get options from Admin CP -> Options -> Basic board information -> Board Url
			$boardUrl = $options->boardUrl;

			// get message
			$message = \XF::phrase('title:') . ' '. '[URL="' . $boardUrl . '/conversationsearchplus/conversation?conversation_id=' . $result->conversation_id . '"]' . $conversationTitle . '[/URL]' . '
' . \XF::phrase('conversationsearchplus_php_message_id:') . ' ' . $result->message_id . '
' . \XF::phrase('conversationsearchplus_php_username:') . ' ' . $result->username . '
' . \XF::phrase('message:') . ' ' . '[QUOTE]' . $result->message . '[/QUOTE]' . '
' . '
' . \XF::phrase('conversationsearchplus_php_reported_by:') . ' ' . $visitor->username . '
' . \XF::phrase('conversationsearchplus_php_report_reason:') . ' ' . '[QUOTE]' . $reportReason . '[/QUOTE]';

			// get thread
			$thread = \XF::asVisitor($starterUser, function() use ($forum, $title, $message)
			{
				$creator = \XF::service('XF:Thread\Creator', $forum);
				$creator->setContent($title, $message);
				$creator->setPrefix($forum['default_prefix_id']);
				$creator->setIsAutomated();
				return $creator->save();
			});
			
			// check condition
			if (!empty($usernames))
			{
				// get userId
				$userId = $visitor['user_id'];

				// check condition
				if (in_array($userId, $usernames))
				{	
					// set watchState
					$threadWatchRepo = \XF::app()->repository('XF:ThreadWatch');
					$threadWatchRepo->setWatchState($thread, $visitor, $watchState);
				}
			}
		}
        
        // return redirect
		return $this->redirect($this->buildLink('conversationsearchplus/conversation?conversation_id=' . $result->conversation_id));
	}
}