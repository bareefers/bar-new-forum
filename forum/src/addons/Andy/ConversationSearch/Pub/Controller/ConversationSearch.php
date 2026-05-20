<?php

namespace Andy\ConversationSearch\Pub\Controller;

use XF\Pub\Controller\AbstractController;

class ConversationSearch extends AbstractController
{
	public function actionIndex()
	{
		// get visitor
		$visitor = \XF::visitor();
		
		// get permission
		if (!$visitor->hasPermission('conversationSearch', 'view'))
		{
			return $this->noPermission();
		}
		
		// send to template	
		return $this->view('Andy\ConversationSearch:Index', 'andy_conversationsearch');
	}
	
	public function actionResults()
	{
		// get visitor
		$visitor = \XF::visitor();
		
		// check condition
		if (!$visitor->hasPermission('conversationSearch', 'view'))
		{
			return $this->noPermission();
		}
        
		// get options
		$options = \XF::options();
		
		// get options from Admin CP -> Options -> Conversation search -> Limit
		$limit = $options->conversationSearchLimit;

		// assert post only
		$this->assertPostOnly();
		
		// get type
		$type = $this->filter('type', 'str');
		
		// get keywords
		$keywords = $this->filter('keywords', 'str');
        
		// get postedBy
		$postedBy = $this->filter('posted_by', 'str');
        
        // check condition
        if (empty($keywords) AND empty($postedBy))
        {
            return $this->error(\XF::phrase('please_specify_search_query_or_name_of_member'));
        }
		
		// check condition
		if (!empty($postedBy))
		{
            // get result
            $finder = \XF::finder('XF:User');
            $result = $finder
                ->where('username', $postedBy)
                ->fetchOne();
			
			// check condition
			if (empty($result['user_id']))
			{
				return $this->error(\XF::phrase('conversationsearch_php_username_not_found'));
			}
            
			// check condition
			if (!empty($result['user_id']))
			{
				$userId = $result['user_id'];
			}
		}
        
        // get db
        $db = \XF::db();
		
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
                $finder = \XF::finder('XF:ConversationUser');
                $results = $finder
                    ->where('owner_user_id', $visitor->user_id)
                    ->where('Master.title', 'LIKE', $finder->escapeLike($keywords, '%?%'))
                    ->order('Master.start_date', 'DESC')
                    ->limit($limit)
                    ->fetch();
            }
            
            // check condition
            if (!empty($postedBy))
            {
                // get results
                $finder = \XF::finder('XF:ConversationUser');
                $results = $finder
                    ->where('owner_user_id', $visitor->user_id)
                    ->where('Master.user_id', $userId)
                    ->where('Master.title', 'LIKE', $finder->escapeLike($keywords, '%?%'))
                    ->order('Master.start_date', 'DESC')
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
			return $this->view('Andy\ConversationSearch:Results', 'andy_conversationsearch_results_title', $viewParams);
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
                // run query
                $results = $db->fetchAll("
                SELECT xf_conversation_message.conversation_id,
                xf_conversation_message.message_id
                FROM xf_conversation_message
                WHERE EXISTS(
                    SELECT * 
                    FROM xf_conversation_user
                    WHERE xf_conversation_user.conversation_id = xf_conversation_message.conversation_id
                    AND xf_conversation_user.owner_user_id = ?)
                AND xf_conversation_message.message LIKE ?
                ORDER BY xf_conversation_message.message_date DESC
                LIMIT ?
                ", array($visitor->user_id, "%$keywords%", $limit));
            }

            // check condition
            if (!empty($postedBy))
            {
                // run query
                $results = $db->fetchAll("
                SELECT xf_conversation_message.conversation_id,
                xf_conversation_message.message_id
                FROM xf_conversation_message
                WHERE EXISTS(
                    SELECT * 
                    FROM xf_conversation_user
                    WHERE xf_conversation_user.conversation_id = xf_conversation_message.conversation_id
                    AND xf_conversation_user.owner_user_id = ?)
                AND xf_conversation_message.message LIKE ?
                AND xf_conversation_message.user_id = ?
                ORDER BY xf_conversation_message.message_date DESC
                LIMIT ?
                ", array($visitor->user_id, "%$keywords%", $userId, $limit));
            }

            // foreach condition 
            foreach ($results AS $k => $v)
            {
                $conditions[] = ['message_id', $v['message_id']];
            }
                
            // foreach condition 
            foreach ($results AS $k => $v)
            {
                // get newResults
                $finder = \XF::finder('XF:ConversationMessage');
                $results = $finder
                    ->whereOr($conditions)
                    ->order('message_date', 'DESC')
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
			return $this->view('Andy\ConversationSearch:Results', 'andy_conversationsearch_results_message', $viewParams);
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
                $finder = \XF::finder('XF:ConversationUser');
                $results = $finder
                    ->where('owner_user_id', $visitor->user_id)
                    ->where('Master.user_id', $userId)
                    ->order('Master.start_date', 'DESC')
                    ->limit($limit)
                    ->fetch();

                // get resultsCount
                $resultsCount = count($results);

                // get viewParams
                $viewParams = [
                    'type' => $type,
                    'postedBy' => $postedBy,
                    'limit' => $limit,
                    'results' => $results,
                    'resultsCount' => $resultsCount
                ];
                
                // send to template
                return $this->view('Andy\ConversationSearch:Results', 'andy_conversationsearch_results_title_username', $viewParams);
            }
            
            // check condition
            if ($type == 'message')
            {
                // run query
                $results = $db->fetchAll("
                SELECT xf_conversation_message.conversation_id,
                xf_conversation_message.message_id
                FROM xf_conversation_message
                WHERE EXISTS(
                    SELECT * 
                    FROM xf_conversation_user
                    WHERE xf_conversation_user.conversation_id = xf_conversation_message.conversation_id
                    AND xf_conversation_user.owner_user_id = ?)
                AND xf_conversation_message.user_id = ?
                ORDER BY xf_conversation_message.message_date DESC
                LIMIT ?
                ", array($visitor->user_id, $userId, $limit));

                // foreach condition 
                foreach ($results AS $k => $v)
                {
                    $conditions[] = ['message_id', $v['message_id']];
                }

                // foreach condition 
                foreach ($results AS $k => $v)
                {
                    // get newResults
                    $finder = \XF::finder('XF:ConversationMessage');
                    $results = $finder
                        ->whereOr($conditions)
                        ->order('message_date', 'DESC')
                        ->fetch();
                }

                // get resultsCount
                $resultsCount = count($results);

                // get viewParams
                $viewParams = [
                    'type' => $type,
                    'postedBy' => $postedBy,
                    'limit' => $limit,
                    'results' => $results,
                    'resultsCount' => $resultsCount
                ];
                
                // send to template
                return $this->view('Andy\ConversationSearch:Results', 'andy_conversationsearch_results_message_username', $viewParams);
            }
		}
	}
}