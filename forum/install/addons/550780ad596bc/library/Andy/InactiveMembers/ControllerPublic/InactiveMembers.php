<?php

class Andy_InactiveMembers_ControllerPublic_InactiveMembers extends XenForo_ControllerPublic_Abstract
{	
	public function actionIndex()
	{
		//########################################
		// display main menu
		//########################################		
			
		//########################################
		// permissions
		//########################################	
			
		// get userId
		$userId = XenForo_Visitor::getUserId();
		
		// throw error if no userId
		if ($userId == '')
		{
			throw $this->getNoPermissionResponseException();
		}	
				
		// get user group permission
		if (!XenForo_Visitor::getInstance()->hasPermission('inactiveMembersGroupID', 'inactiveMembersID'))
		{
			throw $this->getNoPermissionResponseException();
		}
		
		//########################################
		// get email of visitor
		// needed in order to create link for
		// sample email
		//########################################
				
		// get userId
		$userId = XenForo_Visitor::getUserId();		

		// get database
		$db = XenForo_Application::get('db');
				
		// run query
		$emailVisitor = $db->fetchOne("
		SELECT email
		FROM xf_user
		WHERE user_id = " . $userId . "
		LIMIT 1
		");	
		
		//########################################
		// get emails
		//########################################	
		
		// get options from Admin CP -> Options -> Inactive Members -> Minimum Days
		$minimumDays = XenForo_Application::get('options')->inactiveMembersMinimumDays;
		
		// get options from Admin CP -> Options -> Inactive Members -> Maximum Days
		$maximumDays = XenForo_Application::get('options')->inactiveMembersMaximumDays;	
		
		// convert to UNIX timestamp
		$datelineMin = time() - (86400 * $minimumDays);
		
		$datelineMax = time() - (86400 * $maximumDays);
		
		// get options from Admin CP -> Options -> Inactive Members -> Minumum Posts
		$minimumPosts = XenForo_Application::get('options')->inactiveMembersMinimumPosts;
		
		// get options from Admin CP -> Options -> Inactive Members -> Maximum Posts
		$maximumPosts = XenForo_Application::get('options')->inactiveMembersMaximumPosts;		

		// run query
		$users = $db->fetchAll("
		SELECT xf_user.username, xf_user.email
		FROM xf_user
		INNER JOIN xf_user_option ON xf_user_option.user_id = xf_user.user_id
		WHERE xf_user.user_state = 'valid'
		AND xf_user.is_banned = 0
		AND xf_user.last_activity < " . $datelineMin . "
		AND xf_user.last_activity > " . $datelineMax . "
		AND xf_user.message_count >= " . $minimumPosts . "
		AND xf_user.message_count <= " . $maximumPosts . "
		AND xf_user_option.receive_admin_email = 1
		");
		
		// get emailCount
		$emailCount = count($users);	
		
		// prepare viewParams for template
		$viewParams = array(
			'emailCount' => $emailCount,
			'emailVisitor' => $emailVisitor,
			'minimumDays' => $minimumDays,
			'maximumDays' => $maximumDays,
			'minimumPosts' => $minimumPosts,
			'maximumPosts' => $maximumPosts
		); 
		
		// send to template for display
		return $this->responseView('Andy_InactiveMembers_ViewPublic_InactiveMembers', 'andy_inactivemembers', $viewParams);
	}
	
	public function actionSendSample()
	{
		//########################################
		// send sample email
		//########################################
		
		//########################################
		// permissions
		//########################################	
			
		// get userId
		$userId = XenForo_Visitor::getUserId();
		
		// throw error if no userId
		if ($userId == '')
		{
			throw $this->getNoPermissionResponseException();
		}	
				
		// get user group permission
		if (!XenForo_Visitor::getInstance()->hasPermission('inactiveMembersGroupID', 'inactiveMembersID'))
		{
			throw $this->getNoPermissionResponseException();
		}
		
		//########################################
		// this will select threads not older
		// than this many "days"
		//########################################			
		
		// get options from Admin CP -> Options -> Inactive Members -> Days
		$days = XenForo_Application::get('options')->inactiveMembersDays;		
		
		// calculate dateline
		$dateline = time() - (86400 * $days);									
		
		//########################################
		// only these forums will be selected 
		// for the most popular thread titles
		//########################################		
		
		// get options from Admin CP -> Options -> Inactive Members -> Include Forums
		$includeForums = XenForo_Application::get('options')->inactiveMembersIncludeForums;	
		
		if ($includeForums == '')
		{
			throw new XenForo_Exception(new XenForo_Phrase('inactivemembers_error_forums'), true);
		}
		
		// remove trailing comma
		$includeForums = rtrim($includeForums, ',');					
		
		$nodeIds = explode(',', $includeForums);		
		
		// create whereclause of excluded forums
		$whereclause1 = 'AND (xf_thread.node_id = ' . implode(' OR xf_thread.node_id = ', $nodeIds);
		$whereclause1 = $whereclause1 . ')';
		
		//########################################
		// the number of title rows is determined 
		// by this "limit"
		//########################################			
		
		// get options from Admin CP -> Options -> Inactive Members -> Limit
		$limit = XenForo_Application::get('options')->inactiveMembersLimit;		
		
		//########################################
		// get threads
		//########################################
		
		// get database
		$db = XenForo_Application::get('db');			
		
		// run query
		$threads = $db->fetchAll("
		SELECT xf_thread.title, thread_id, xf_node.title AS forumTitle
		FROM xf_thread
		INNER JOIN xf_node ON xf_node.node_id = xf_thread.node_id
		WHERE xf_thread.post_date > " . $dateline . "
		AND xf_thread.discussion_state = 'visible'
		$whereclause1
		ORDER BY xf_thread.view_count DESC
		LIMIT " . $limit . "
		");	
		
		//########################################
		// get username and email of visitor
		//########################################
				
		// get $userId
		$userId = XenForo_Visitor::getUserId();		
		
		// run query
		$to = $db->fetchRow("
		SELECT username
		FROM xf_user
		WHERE user_id = " . $userId . "
		LIMIT 1
		");
		
		// run query
		$users = $db->fetchAll("
		SELECT username, email
		FROM xf_user
		WHERE user_id = " . $userId . "
		LIMIT 1
		");					
		
		//########################################
		// start message
		//########################################
		
		// get options from Admin CP -> Options -> Basic Board Information -> Board Title
		$boardTitle = XenForo_Application::get('options')->boardTitle;
		
		// get options from Admin CP -> Options -> Inactive Members -> Language
		$language = XenForo_Application::get('options')->inactiveMembersLanguage;			
		
		// set language
		if ($language > 0)
		{
			XenForo_Phrase::setLanguageId($language);	
		}
		
		// get subject
		$subject = new XenForo_Phrase('inactivemembers_subject');					
		
		// message
		$message = new XenForo_Phrase('inactivemembers_message') . '<br /><br />';
		
		// get forumPhrase
		$forumPhrase = new XenForo_Phrase('inactivemembers_forum');
		
		// get titlePhrase
		$titlePhrase = new XenForo_Phrase('inactivemembers_title');
		
		// get webRoot
		$webRoot = XenForo_Link::buildPublicLink('full:index');

		// remove index.php if not using full friendly url's
		$replace_src = 'index.php';
		$replace_str = '';
		$text = $webRoot;					
		$webRoot = str_replace($replace_src, $replace_str, $text);		
		
		// replace {username} in message
		$message = str_replace('{username}', $to['username'], $message);
		
		// start table
		$message .= '
<table cellspacing="5">
<tr>
<th align="left">' . $forumPhrase . '</th>
<th align="left">' . $titlePhrase . '</th>
';
		// foreach threads
		foreach ($threads as $thread)
		{
			// build link
			$link = XenForo_Link::buildPublicLink('threads', $thread);
			
			// message details
			$message .= '<tr><td>' . $thread['forumTitle'] . '</td>';
			$message .= '<td>' . '<a href="' . $webRoot . $link . '">' . $thread['title'] . '</a></td></tr>';
		}
		
		// end table
		$message .= '</tr></table>';
		
		//########################################
		// add unsubscribe and forgot password
		//########################################

		// message
		$message .= '<br /><br />' . new XenForo_Phrase('inactivemembers_unsubscribe');
		
		// create unsubscribeLink
		$unsubscribeLink = $webRoot . XenForo_Link::buildPublicLink('account/contact-details');
		
		// add unsubscribeLink to message
		$message = str_replace('{unsubscribe_link}', $unsubscribeLink, $message);	

		// create forgotPasswordLink
		$forgotPasswordLink = $webRoot . XenForo_Link::buildPublicLink('lost-password/');
		
		// add forgotPasswordLink
		$message = str_replace('{forgotpassword_link}', $forgotPasswordLink, $message);	
		
		//########################################
		// replace {board_title} in message
		//########################################		
		
		$message = str_replace('{board_title}', $boardTitle, $message);			
	
		//########################################
		// send email
		//########################################
		
		// set to zero, no PHP time limit is imposed
		set_time_limit(0);

		// foreach users
		foreach ($users as $user)
		{
			// prepare params                    
			$params = array(
				'subject' => $subject,
				'message' => $message
			);
				
			// prepare mail variable
			$mail = XenForo_Mail::create('inactivemembers_contact', $params);
			
			// send mail
			$mail->queue($user['email'], $user['username']);
		}
		
		//########################################
		// return confirmation message
		//########################################
		
		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('inactivemembers/sampleconfirm')
		);
	}
	
	public function actionSendBatch()
	{
		//########################################
		// send batch emails
		//########################################
		
		//########################################
		// permissions
		//########################################	
			
		// get userId
		$userId = XenForo_Visitor::getUserId();
		
		// throw error if no userId
		if ($userId == '')
		{
			throw $this->getNoPermissionResponseException();
		}	
				
		// get user group permission
		if (!XenForo_Visitor::getInstance()->hasPermission('inactiveMembersGroupID', 'inactiveMembersID'))
		{
			throw $this->getNoPermissionResponseException();
		}
		
		//########################################
		// select threads not older
		// than this many "days"
		//########################################			
		
		// get options from Admin CP -> Options -> Inactive Members -> Days
		$days = XenForo_Application::get('options')->inactiveMembersDays;		
		
		// calculate dateline
		$dateline = time() - (86400 * $days);							
		
		//########################################
		// only these forums will queried for the
		// most popular thread titles.
		//########################################		
		
		// get options from Admin CP -> Options -> Inactive Members -> Include Forums
		$includeForums = XenForo_Application::get('options')->inactiveMembersIncludeForums;	
		
		if ($includeForums == '')
		{
			throw new XenForo_Exception(new XenForo_Phrase('inactivemembers_error_forums'), true);
		}	
		
		// remove trailing comma
		$includeForums = rtrim($includeForums, ',');				
		
		$nodeIds = explode(',', $includeForums);		
		
		// create whereclause1 of included forums
		$whereclause1 = 'AND (xf_thread.node_id = ' . implode(' OR xf_thread.node_id = ', $nodeIds);
		$whereclause1 = $whereclause1 . ')';	
		
		//########################################
		// the number of title rows is determined 
		// by this "limit"
		//########################################			
		
		// get options from Admin CP -> Options -> Inactive Members -> Limit
		$limit = XenForo_Application::get('options')->inactiveMembersLimit;
		
		//########################################
		// get threads
		//########################################
		
		// get database
		$db = XenForo_Application::get('db');			
		
		// run query
		$threads = $db->fetchAll("
		SELECT xf_thread.title, thread_id, xf_node.title AS forumTitle
		FROM xf_thread
		INNER JOIN xf_node ON xf_node.node_id = xf_thread.node_id
		WHERE xf_thread.post_date > " . $dateline . "
		AND xf_thread.discussion_state = 'visible'
		$whereclause1
		ORDER BY xf_thread.view_count DESC
		LIMIT " . $limit . "
		");	
		
		//########################################
		// get emails
		//########################################	
		
		// get options from Admin CP -> Options -> Inactive Members -> Minimum Days
		$minimumDays = XenForo_Application::get('options')->inactiveMembersMinimumDays;
		
		// get options from Admin CP -> Options -> Inactive Members -> Maximum Days
		$maximumDays = XenForo_Application::get('options')->inactiveMembersMaximumDays;	
		
		// convert to UNIX timestamp
		$datelineMin = time() - (86400 * $minimumDays);	
		$datelineMax = time() - (86400 * $maximumDays);
		
		// get options from Admin CP -> Options -> Inactive Members -> Minumum Posts
		$minimumPosts = XenForo_Application::get('options')->inactiveMembersMinimumPosts;
		
		// get options from Admin CP -> Options -> Inactive Members -> Maximum Posts
		$maximumPosts = XenForo_Application::get('options')->inactiveMembersMaximumPosts;	
		
		//########################################
		// Exclude user_id's
		//########################################	
		
		// get options from Admin CP -> Options -> Inactive Members -> Exclude User ID
		$excludeUserId = XenForo_Application::get('options')->inactiveMembersExcludeUserId;
		
		if ($excludeUserId)
		{
			// remove trailing comma
			$excludeUserId = rtrim($excludeUserId, ',');					
			
			$excludeUserIds = explode(',', $excludeUserId);		
			
			// create whereclause of excluded user_id's
			$whereclause2 = 'AND (xf_user.user_id <> ' . implode(' AND xf_user.user_id <> ', $excludeUserIds);
			$whereclause2 = $whereclause2 . ')';
		}
		else
		{
			$whereclause2 = '';
		}												
		
		// run query
		$users = $db->fetchAll("
		SELECT xf_user.username, xf_user.email
		FROM xf_user
		INNER JOIN xf_user_option ON xf_user_option.user_id = xf_user.user_id
		WHERE xf_user.user_state = 'valid'
		AND xf_user.is_banned = 0
		AND xf_user.last_activity < " . $datelineMin . "
		AND xf_user.last_activity > " . $datelineMax . "
		AND xf_user.message_count >= " . $minimumPosts . "
		AND xf_user.message_count <= " . $maximumPosts . "
		AND xf_user_option.receive_admin_email = 1 
		$whereclause2
		");
			
		//########################################
		// start message
		//########################################
		
		// get options from Admin CP -> Options -> Basic Board Information -> Board Title
		$boardTitle = XenForo_Application::get('options')->boardTitle;		
		
		// get options from Admin CP -> Options -> Inactive Members -> Language
		$language = XenForo_Application::get('options')->inactiveMembersLanguage;			
		
		// set language
		if ($language > 0)
		{
			XenForo_Phrase::setLanguageId($language);	
		}
		
		// get subject
		$subject = new XenForo_Phrase('inactivemembers_subject');
		
		// get forumPhrase
		$forumPhrase = new XenForo_Phrase('inactivemembers_forum');
		
		// get titlePhrase
		$titlePhrase = new XenForo_Phrase('inactivemembers_title');
		
		// get webRoot
		$webRoot = XenForo_Link::buildPublicLink('full:index');

		// remove index.php if not using full friendly url's
		$replace_src = 'index.php';
		$replace_str = '';
		$text = $webRoot;					
		$webRoot = str_replace($replace_src, $replace_str, $text);									
		
		// message
		$message = new XenForo_Phrase('inactivemembers_message') . '<br /><br />';		
		
		// start table
		$message .= '
<table cellspacing="5">
<tr>
<th align="left">Forum</th>
<th align="left">Title</th>
';
		// foreach threads
		foreach ($threads as $thread)
		{
			// build link
			$link = XenForo_Link::buildPublicLink('threads', $thread);
			
			// message details
			$message .= '<tr><td>' . $thread['forumTitle'] . '</td>';
			$message .= '<td>' . '<a href="' . $webRoot . $link . '">' . $thread['title'] . '</a></td></tr>';
		}
		
		// end table
		$message .= '</tr></table>';
		
		//########################################
		// add unsubscribe and forgot password
		//########################################

		// message
		$message .= '<br /><br />' . new XenForo_Phrase('inactivemembers_unsubscribe');
		
		// create unsubscribeLink
		$unsubscribeLink = $webRoot . XenForo_Link::buildPublicLink('account/contact-details');
		
		// add unsubscribeLink to message
		$message = str_replace('{unsubscribe_link}', $unsubscribeLink, $message);	

		// create forgotPasswordLink
		$forgotPasswordLink = $webRoot . XenForo_Link::buildPublicLink('lost-password/');
		
		// add forgotPasswordLink
		$message = str_replace('{forgotpassword_link}', $forgotPasswordLink, $message);	
		
		//########################################
		// replace {board_title} in message
		//########################################		
		
		$message = str_replace('{board_title}', $boardTitle, $message);			

		//########################################
		// send email
		//########################################
		
		// set to zero, no PHP time limit is imposed
		set_time_limit(0);	
		
		echo new XenForo_Phrase('inactivemembers_email_sent_to');	
		
		// declare messageDefault
		$messageDefault = $message;

		// foreach users
		foreach ($users as $user)
		{
			// replace {username} in message
			$message = str_replace('{username}', $user['username'], $messageDefault);				
			
			// prepare params                    
			$params = array(
				'subject' => $subject,
				'message' => $message
			);
				
			// prepare mail variable
			$mail = XenForo_Mail::create('inactivemembers_contact', $params);
			
			// send mail
			$mail->queue($user['email'], $user['username']);
			
			echo $user['username'] . '<br />';
			flush();
		}
		
		echo '<br />' . new XenForo_Phrase('inactivemembers_finished_press_back_button_to_return_to_previous_page');
		exit();					
	}
	
	public function actionSampleConfirm()
	{	
		return $this->responseMessage(new XenForo_Phrase('inactivemembers_sendsample_confirm'));
	}	
}