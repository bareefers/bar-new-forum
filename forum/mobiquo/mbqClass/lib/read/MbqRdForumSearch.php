<?php
use Tapatalk\Bridge;

defined('MBQ_IN_IT') or exit;

MbqMain::$oClk->includeClass('MbqBaseRdForumSearch');

/**
 * forum search class
 */
Class MbqRdForumSearch extends MbqBaseRdForumSearch
{

    public function __construct()
    {
    }

    /**
     * forum advanced search
     *
     * @param  Array $filter search filter
     * @param  MbqDataPage $oMbqDataPage
     * @param  Array $mbqOpt
     * $mbqOpt['case'] = 'advanced' means advanced search
     * $mbqOpt['participated'] = true means get participated data
     * $mbqOpt['unread'] = true means get unread data
     * @return  Object  $oMbqDataPage, or plain string to return an error message
     */
    public function forumAdvancedSearch($filter, $oMbqDataPage, $mbqOpt)
    {
        $bridge = Bridge::getInstance();
        $return = null;

        switch ($mbqOpt['case']) {
            case 'getLatestTopic':
                $return = $this->_forumAdvancedSearchGetLatestTopic($filter, $oMbqDataPage, $mbqOpt, $bridge);
                break;
            case 'getUnreadTopic':
                $return = $this->_forumAdvancedSearchGetUnreadTopic($filter, $oMbqDataPage, $mbqOpt, $bridge);
                break;
            case 'getParticipatedTopic':
                $return = $this->_forumAdvancedSearchGetParticipatedTopic($filter, $oMbqDataPage, $mbqOpt, $bridge);
                break;
            case 'searchTopic':
                $return = $this->_forumAdvancedSearchSearchTopic($filter, $oMbqDataPage, $mbqOpt, $bridge);
                break;
            case 'searchPost':
                $return = $this->_forumAdvancedSearchSearchPost($filter, $oMbqDataPage, $mbqOpt, $bridge);
                break;
            case 'search':
                $return = $this->_forumAdvancedSearchSearch($filter, $oMbqDataPage, $mbqOpt, $bridge);
                break;
            default:
                MbqError::alert('', __METHOD__ . ',line:' . __LINE__ . '.' . MBQ_ERR_INFO_UNKNOWN_CASE);
                break;
        }

        return $return;
    }

    protected function _prepareSearchQuery(array $data, $option, &$urlConstraints = [])
    {
        $bridge = Bridge::getInstance();
        $searchRequest = new \XF\Http\Request($bridge->app()->inputFilterer(), $data, [], []);
        $input = $searchRequest->filter([
            'search_type' => 'str',
            'keywords' => 'str',
            'c' => 'array',
            'c.title_only' => 'uint',
            'c.newer_than' => 'datetime',
            'c.older_than' => 'datetime',
            'c.users' => 'str',
            'c.nodes' => 'array',
            'grouped' => 'bool',
            'order' => 'str',
            'c.threadid' => 'str'
        ]);

        $urlConstraints = $input['c'];

        $searcher = $bridge->app()->search();
        $query = $searcher->getQuery();

        if ($input['search_type'] && $searcher->isValidContentType($input['search_type']))
        {
            $typeHandler = $searcher->handler($input['search_type']);
            $query->forTypeHandler($typeHandler, $searchRequest, $urlConstraints);
        }

        if ($input['grouped'])
        {
            $query->withGroupedResults();
        }

        $input['keywords'] = $bridge->stringFormatter()->censorText($input['keywords'], '');
        if ($input['keywords'])
        {
            $query->withKeywords($input['keywords'], $input['c.title_only']);
        }

        if ($input['c.newer_than'])
        {
            $query->newerThan($input['c.newer_than']);
        }
        else
        {
            unset($urlConstraints['newer_than']);
        }
        if ($input['c.older_than'])
        {
            $query->olderThan($input['c.older_than']);
        }
        else
        {
            unset($urlConstraints['older_than']);
        }

        if ($input['c.users'])
        {
            $users = preg_split('/,\s*/', $input['c.users'], -1, PREG_SPLIT_NO_EMPTY);
            if ($users)
            {
                /** @var \XF\Repository\User $userRepo */
                $userRepo = $bridge->getUserRepo();
                $matchedUsers = $userRepo->getUsersByNames($users, $notFound);
                if ($notFound)
                {
                    $query->error('users',
                        \XF::phrase('following_members_not_found_x', ['members' => implode(', ', $notFound)])
                    );
                }
                else
                {
                    $query->byUserIds($matchedUsers->keys());
                    $urlConstraints['users'] = implode(', ', $users);
                }
            }
        }

        if ($input['c.threadid'] && is_numeric($input['c.threadid'])) {
             $query->withMetadata('thread', [$input['c.threadid']]);
        }

        $hideForumIds = [];
        // admin setting tapatalk hideForums
        $XFOption = $bridge->options();
        if ($XFOption->hideForums && is_array($XFOption->hideForums)) {
            $hideForumIds = array_unique($XFOption->hideForums);
        }
        if ($input['c.nodes']) {
            if (!is_array($input['c.nodes'])) $input['c.nodes'] = [$input['c.nodes']];
            $nodeIds = array_unique($input['c.nodes']);

            $nodeTree = $this->_getSearchableNodeTree();
            $searchNodeIds = array_fill_keys($nodeIds, true);
            $nodeTree->traverse(function($id, $node) use (&$searchNodeIds)
            {
                if (isset($searchNodeIds[$id]) || isset($searchNodeIds[$node->parent_node_id]))
                {
                    // if we're in the search node list, the user selected the node explicitly
                    // if the parent is in the list, then that node was selected via traversal so we're included too
                    $searchNodeIds[$id] = true;
                }

                // we still need to traverse children though, as children may be selected
            });

            $nodeIds = array_unique(array_keys($searchNodeIds));
            $nodeIds = array_diff($nodeIds, $hideForumIds);

            $query->withMetadata('node', $nodeIds);
            $urlConstraints['nodes'] = array_unique($input['c.nodes']);
        }

        if ($hideForumIds) {
            if ($hideForumIds && implode(',', $hideForumIds) != '0') {
                $query->withMetadata('node', $hideForumIds, 'none');
            }
        }

        // mbq add option
        if (isset($option['onlyPost']) && $option['onlyPost']) {
//            $content = 'post';
//            if ($content && $searcher->isValidContentType($content))
//            {
//                $query->inType($content);
//            }
        }else if (isset($option['onlyThread']) && $option['onlyThread']) {
            $content = 'thread';
            if ($content && $searcher->isValidContentType($content))
            {
                $query->inType($content);
            }
        }
        //

        if ($input['order'])
        {
            $query->orderedBy($input['order']);
        }

        return $query;
    }

    /**
     * @return \XF\Tree
     */
    protected function _getSearchableNodeTree()
    {
        $bridge = Bridge::getInstance();
        /** @var \XF\Repository\Node $nodeRepo */
        $nodeRepo = $bridge->getNodeRepo();
        $nodeTree = $nodeRepo->createNodeTree($nodeRepo->getNodeList());

        // only list nodes that are forums or contain forums
        $nodeTree = $nodeTree->filter(null, function($id, $node, $depth, $children, $tree)
        {
            return ($children || $node->node_type_id == 'Forum');
        });

        return $nodeTree;
    }

    protected function _runSearch(\XF\Search\Query\Query $query, array $constraints, $allowCached = true)
    {
        $bridge = Bridge::getInstance();
        $searchRepo = $bridge->getSearchRepo();
        /** @var \XF\Entity\Search $search */
        $search = $searchRepo->runSearch($query, $constraints, $allowCached);

        if (!$search)
        {
            return $bridge->errorToString(\XF::phrase('no_results_found'));
        }

        return $search;
    }

    protected function _processSearch(\XF\Entity\Search $search, $page, $perPage)
    {
        $bridge = Bridge::getInstance();
        $searcher = $bridge->app()->search();
        $resultSet = $searcher->getResultSet($search->search_results);

        $total = $resultSet->countResults();
        $resultSet->sliceResultsToPage($page, $perPage);

        if (!$resultSet->countResults())
        {
            return $bridge::XFPhrase('no_results_found');
        }

        $maxPage = ceil($search->result_count / $perPage);

        if ($search->search_order == 'date'
            && $search->result_count > $perPage
            && $page == $maxPage)
        {
            $lastResult = $resultSet->getLastResultData($lastResultType);
            $getOlderResultsDate = $searcher->handler($lastResultType)->getResultDate($lastResult);
        }
        else
        {
            $getOlderResultsDate = null;
        }

        $resultOptions = [
            'search' => $search,
            'term' => $search->search_query
        ];
        /** @var array $resultsWrapped */
        $resultsWrapped = $searcher->wrapResultsForRender($resultSet, $resultOptions);

        $modTypes = [];
        /** @var \XF\Search\RenderWrapper $wrapper */
        foreach ($resultsWrapped AS $wrapper)
        {
            $handler = $wrapper->getHandler();
            $entity = $wrapper->getResult();
            if ($handler->canUseInlineModeration($entity))
            {
                $type = $handler->getContentType();
                if (!isset($modTypes[$type]))
                {
                    $modTypes[$type] = $bridge->app()->getContentTypePhrase($type);
                }
            }
        }

        $result = [
            'search' => $search,
            'results' => $resultsWrapped,

            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,

            'modTypes' => $modTypes,
            'getOlderResultsDate' => $getOlderResultsDate
        ];

        return $result;
    }

    /**
     * @param $filter
     * @param Bridge $bridge
     * @return array
     */
    protected function _xfSearchInput($filter, $bridge)
    {
        $input = $filter;
        $xfSearchInput = [];
        $xfSearchInput['keywords'] = $input['keywords'] ? $input['keywords'] . ((substr($input['keywords'],-1) == '*') ? '' : '*') : ''; // search mod *
        $xfSearchInput['order'] = 'date';
        $xfSearchInput['grouped'] = !$input['showPosts'] ? 1 : 0;  // only show Thread not display post
        $xfSearchInput['search_type'] = !$input['showPosts'] ? 'thread' : 'post'; //  dev

        $xfSearchInput['c']['title_only'] = $input['titleOnly'];
        $xfSearchInput['c']['users'] = $input['searchUser']; // string ,
        if ($input['startedBy'] && $input['username']) {
            $xfSearchInput['c']['users'] = $input['username'];
        }
        if (!$input['keywords'] && !$xfSearchInput['c']['users'] && $input['username']) {
            // not search keyword, only search user
            $xfSearchInput['c']['users'] = $input['username'];
        }
        $xfSearchInput['c']['newer_than'] = $input['searchTime'];
        if ($input['searchTime']) {
            $newer_than_timestamp = time() - $input['searchTime'];
            $newer_than_date = getdate($newer_than_timestamp);
            $date = $newer_than_date['year']."-".$newer_than_date['mon']."-".$newer_than_date['mday'];
            if (!$date) {
                $date = 0;
            } else if (is_string($date)) {
                $date = trim($date);
                if ($date === strval(intval($date))) {
                    // date looks like an int, treat as timestamp
                    $date = intval($date);
                }
            }
            if (!is_int($date)) {
                $date = intval($date);
            }
            $xfSearchInput['c']['newer_than'] = $date;
        }
        $xfSearchInput['c']['min_reply_count'] = 0; // default
        $xfSearchInput['c']['prefixes'] = ''; //
        $xfSearchInput['c']['nodes'] = $input['onlyIn'];
        //construct only_in & not_in constraints
        if(isset($input['forumId']) && !empty($input['forumId'])) {
            $xfSearchInput['c']['nodes'] = array($input['forumId']);

            // overwrite onlyIn !
            $input['onlyIn'] = array($input['forumId']);
            $xfSearchInput['c']['noIn'] = array();
        }
        if(isset($input['notIn']) && !empty($input['notIn'])) {
            $xfSearchInput['c']['noIn'] = array_unique($input['notIn']);
        }
        if(isset($input['onlyIn']) && !empty($input['onlyIn'])) {
            $xfSearchInput['c']['nodes'] = array_unique($input['onlyIn']);
        }
        $xfSearchInput['c']['child_nodes'] = 1; // default

        if(isset($input['topicId']) && !empty($input['topicId'])) {
            $xfSearchInput['c']['threadid'] = (int)$input['topicId'];
        }

        return $xfSearchInput;
    }

    protected function _forumAdvancedSearchSearch($filter, $oMbqDataPage, $mbqOpt, Bridge $bridge, $searchOption = [])
    {
        $visitor = $bridge::visitor();
        if (!$visitor->canSearch()) {
            return false;
        }
        $supportFilters = $bridge->getSearchSupportFilters();

        foreach ($filter as $k => $v) {
            $bridge->_request->set($k, $v);
        }

        $input = $bridge->_request->filter($supportFilters);
        if (is_array($filter)) {
            foreach ($input as $k => $v) {
                $filter[$k] = $v;
            }
        } else {
            foreach ($input as $k => $v) {
                $filter->$k = $v;
            }
        }

        $constraints = [];
        $xfSearchInput = $this->_xfSearchInput($input, $bridge);

        $mbqOption = [];
        if ($input['showPosts']) {
            $mbqOption['onlyPost'] = 1;
        }else{
            $mbqOption['onlyThread'] = 1;
        }
        $mbqOption = array_merge($mbqOption, $searchOption);

        $search = null;
        if (isset($input['searchId']) && $input['searchId']) {
            $search = $bridge->getSearchRepo()->getSearchById($input['searchId']);
        }
        if (!$search) {
            $query = $this->_prepareSearchQuery($xfSearchInput, $mbqOption, $constraints);

            if ($query->getErrors()) {
                return $bridge->errorToString($query->getErrors());
            }
            if (!strlen($query->getKeywords()) && !$query->getUserIds()) {
                return $bridge->errorToString(\XF::phrase('please_specify_search_query_or_name_of_member'));
            }

            /** @var \XF\Entity\Search $search */
            $search = $this->_runSearch($query, $constraints);
        }

        if (!($search instanceof \XF\Entity\Search)){

            // error msg
            return $search;
        }
        /** @var MbqDataPage $oMbqDataPage */
        $curPage = $oMbqDataPage->curPage;
        $limit = $oMbqDataPage->numPerPage;

        $searchResults = $this->_processSearch($search, $curPage, $limit);

        if ($searchResults instanceof \XF\Phrase) {
            if ($searchResults->getName() == 'no_results_found') {
                $oMbqDataPage->datas = [];
                return $oMbqDataPage;
            }
        }
        $oMbqDataPage->datas = [];
        if (is_array($searchResults)) {
            $oMbqDataPage->searchId = isset($search['search_id']) ? $search['search_id'] : '';
            $oMbqDataPage->totalNum = isset($searchResults['total']) ? $searchResults['total'] : 0;
        }

        /** @var MbqRdEtForumTopic $oMbqRdEtForumTopic */
        $oMbqRdEtForumTopic = MbqMain::$oClk->newObj('MbqRdEtForumTopic');
        /** @var MbqRdEtForumPost $oMbqRdEtForumPost */
        $oMbqRdEtForumPost = MbqMain::$oClk->newObj('MbqRdEtForumPost');
        $newMbqOpt['oMbqEtForum'] = true;
        $newMbqOpt['oMbqEtForumTopic'] = true;
        $newMbqOpt['oMbqEtUser'] = true;
        $newMbqOpt['oMbqDataPage'] = $oMbqDataPage;


        if ($searchResults && is_array($searchResults) && isset($searchResults['results']) && $searchResults['results']) {
            /** @var \XF\Search\RenderWrapper $wrapper */
            foreach ($searchResults['results'] AS $wrapper) {
                $handler = $wrapper->getHandler();
                $type = $handler->getContentType();
                $entity = $wrapper->getResult();
                switch ($type) {
                    case 'post':
                        $newMbqOpt['case'] = 'byRow';
                        $mbqInit = $oMbqRdEtForumPost->initOMbqEtForumPost($entity, $newMbqOpt);
                        if ($mbqInit) $oMbqDataPage->datas[] = $mbqInit;
                        break;
                    case 'thread':
                        if (isset($mbqOption['onlyPost']) && $mbqOption['onlyPost']) {
                            if ($entity instanceof \XF\Entity\Thread && $entity) {
                                if ($entity->FirstPost) {
                                    $newMbqOpt['case'] = 'byRow';
                                    $mbqInit = $oMbqRdEtForumPost->initOMbqEtForumPost($entity->FirstPost, $newMbqOpt);
                                    if ($mbqInit) $oMbqDataPage->datas[] = $mbqInit;
                                }
                            }
                            continue;
                        }
                        $newMbqOpt['case'] = 'byRow';
                        $mbqInit = $oMbqRdEtForumTopic->initOMbqEtForumTopic($entity, $newMbqOpt);
                        if ($mbqInit) $oMbqDataPage->datas[] = $mbqInit;
                        break;
                }
            }
        }

        return $oMbqDataPage;
    }

    protected function _forumAdvancedSearchSearchPost($filter, $oMbqDataPage, $mbqOpt, Bridge $bridge)
    {
        $oMbqDataPage = MbqMain::$oClk->newObj('MbqDataPage');
        $oMbqDataPage->initByPageAndPerPage($filter['page'], $filter['perpage']);

        $searchOption = ['onlyPost' => 1];
        $searchPost = $this->_forumAdvancedSearchSearch($filter, $oMbqDataPage, $mbqOpt, $bridge, $searchOption);

        return $searchPost;
    }

    protected function _forumAdvancedSearchSearchTopic($filter, $oMbqDataPage, $mbqOpt, Bridge $bridge)
    {
//        $filter = array(
//            'keywords' => $in->keywords,
//            'searchid' => $in->searchId,
//            'page' => $in->oMbqDataPage->curPage,
//            'perpage' => $in->oMbqDataPage->numPerPage
//        );
//        $filter['showposts'] = 0;

        /** @var MbqDataPage $oMbqDataPage */
        $oMbqDataPage = MbqMain::$oClk->newObj('MbqDataPage');
        $oMbqDataPage->initByPageAndPerPage($filter['page'], $filter['perpage']);

        $searchOption = ['onlyThread' => 1];
        $searchTopic = $this->_forumAdvancedSearchSearch($filter, $oMbqDataPage, $mbqOpt, $bridge, $searchOption);
        return $searchTopic;
    }

    protected function _forumAdvancedSearchGetParticipatedTopic($filter, $oMbqDataPage, $mbqOpt, Bridge $bridge)
    {
        $visitor = $bridge::visitor();
        $userModel = $bridge->getUserRepo();
        $searchModel = $bridge->getSearchRepo();
        $threadRepo = $bridge->getThreadRepo();
        $visitorUserId = $visitor->user_id;

        if (!$visitor->canSearch($error))
        {
            // noPermission
            return $bridge->responseError('noPermission');
        }

        /** @var MbqDataPage $oMbqDataPage */
        $oMbqDataPage = MbqMain::$oClk->newObj('MbqDataPage');
        $oMbqDataPage->initByPageAndPerPage($filter['page'], $filter['perpage']);

        /** @var MbqRdEtForumTopic $oMbqRdEtForumTopic */
        $oMbqRdEtForumTopic = MbqMain::$oClk->newObj('MbqRdEtForumTopic');

        if (empty($filter['username']) && empty($filter['userid'])) {
            $filter['userid'] = $visitorUserId;
        }

        if (isset($filter['userid']) && !empty($filter['userid'])) {
            $requestuser = $userModel->findUserById($filter['userid']);
            $filter['username'] = $requestuser['username'];
        }

        if (empty($filter['username'])) {

            return $bridge->responseError('You need to login to do that');
        }

        if (!$userId = $this->_getUserId())
        {
            $bridge->errorAssertRegistrationRequired();
        }

        $start = $oMbqDataPage->startNum;
        $limit = $oMbqDataPage->numPerPage;
        $results = null;
        $total = 0;

        $searchId = isset($filter['searchid']) ? $filter['searchid'] : null;
        $search = null;
        if ($searchId) {
            /** @var \XF\Entity\Search $search */
            $search = $searchModel->getSearchById($searchId);

            if ($searchResults = $search->search_results) {
                if (!is_array($searchResults)) {
                    $searchResults = json_decode($searchResults, true);
                }

                $searchThreads = [];
                foreach ($searchResults as $tmpV) {
                    if ($tmpV[0] == 'thread') {
                        $searchThreads[] = $tmpV[1];
                    }
                }

                $total = count($searchThreads);
                $threads = null;
                if ($total > 0) {

                    $results = array_slice($searchThreads, $start, $limit);

                    $threads = $threadRepo->getThreadsByIds($results);
                    if ($threads) {
                        $threads = $threads->toArray();
                    }
                }
                $results = [];
                $results['total'] = $total;
                if ($threads) $results['threads'] = $threads;
            }
        }

        if (!$search) {
            $threadFinder = $bridge->getThreadRepo()->getThreadsByUserParticipated($userId);
            $results = $this->_getThreadResults($threadFinder, $start, $limit);
            $total = isset($results['total']) ? $results['total'] : 0;

            $searchId = 0;
        }

        $objMbqEtForumTopics = array();

        if ($results) {
            $processedThreadIds = array();
            if (isset($results['threads']) && $results['threads']) {
                /** @var \XF\Entity\Thread $thread */
                foreach ($results['threads'] as $thread) {
                    $oMbqTopic = $oMbqRdEtForumTopic->initOMbqEtForumTopic($thread, array('case' => 'byRow', 'oMbqEtUser' => true, 'oMbqEtForum' => true));
                    if (!in_array($oMbqTopic->topicId->oriValue, $processedThreadIds)) {
                        $objMbqEtForumTopics[] = $oMbqTopic;
                        $processedThreadIds[] = $oMbqTopic->topicId->oriValue;
                    }
                }
            }
        }

        $oMbqDataPage->totalNum = $total;
        $oMbqDataPage->searchId = $searchId;
        $oMbqDataPage->datas = $objMbqEtForumTopics;

        return $oMbqDataPage;
    }

    protected function _forumAdvancedSearchGetUnreadTopic($filter, $oMbqDataPage, $mbqOpt, Bridge $bridge)
    {
        $visitor = $bridge::visitor();
        $threadModel = $bridge->getThreadRepo();

        if (is_object($filter)) {
            $page = $filter->page;
            $perPage = $filter->perpage;
        }else{
            $page = isset($filter['page']) ? $filter['page'] : 1;
            $perPage = isset($filter['perpage']) ? $filter['perpage'] : 20;
        }
        /** @var MbqDataPage $oMbqDataPage */
        $oMbqDataPage = MbqMain::$oClk->newObj('MbqDataPage');
        $oMbqDataPage->initByPageAndPerPage($page, $perPage);

        /** @var MbqRdEtForumTopic $oMbqRdEtForumTopic */
        $oMbqRdEtForumTopic = MbqMain::$oClk->newObj('MbqRdEtForumTopic');

        $start = $oMbqDataPage->startNum;
        $limit = $oMbqDataPage->numPerPage;

        /** @var \XF\Finder\Thread $threadFinder */
        $threadFinder = $threadModel->findThreadsWithUnreadPostsAll($visitor->user_id);
        $results = $this->_getThreadResults($threadFinder, $start, $limit);
        $total = isset($results['total']) ? $results['total'] : 0;

        $objMbqEtForumTopics = array();
        if ($results) {
            if (isset($results['threads']) && $results['threads']) {
                /** @var \XF\Entity\Thread $thread */
                foreach ($results['threads'] as $thread) {
                    $oMbqTopic = $oMbqRdEtForumTopic->initOMbqEtForumTopic($thread, array('case' => 'byRow', 'oMbqEtUser' => true, 'oMbqEtForum' => true));
                    if ($oMbqTopic) {
                        $objMbqEtForumTopics[] = $oMbqTopic;
                    }
                }
            }
        }

        $oMbqDataPage->totalNum = $total;
        $oMbqDataPage->datas = $objMbqEtForumTopics;

        return $oMbqDataPage;
    }

    protected function _forumAdvancedSearchGetLatestTopic($filter, $oMbqDataPage, $mbqOpt, Bridge $bridge)
    {
        /** @var MbqDataPage $oMbqDataPage */
        $oMbqDataPage = MbqMain::$oClk->newObj('MbqDataPage');
        $oMbqDataPage->initByPageAndPerPage($filter['page'], $filter['perpage']);

        /** @var MbqRdEtForumTopic $oMbqRdEtForumTopic */
        $oMbqRdEtForumTopic = MbqMain::$oClk->newObj('MbqRdEtForumTopic');

        $visitor = $bridge::visitor();
        $threadModel = $bridge->getThreadRepo();
        $userId = $visitor->user_id;

        $start = $oMbqDataPage->startNum;
        $limit = $oMbqDataPage->numPerPage;

        $filter = $this->_changeFilterToObject($filter);

        /** @var \XF\Finder\Thread $threadFinder */
        $threadFinder = $threadModel->findThreadsWithLatestPostsAll();
        if (isset($filter->node) && $filter->node) {
            if (!is_array($filter->node)) {
                $nodesLimit = explode(' ', $filter->node);
            }else{
                $nodesLimit = $filter->node;
            }
            $threadFinder->where('node_id', $nodesLimit);
        }

        $results = $this->_getThreadResults($threadFinder, $start, $limit);
        $total = isset($results['total']) ? $results['total'] : 0;

        $objMbqEtForumTopics = array();
        if ($results) {
            if (isset($results['threads']) && $results['threads']) {
                /** @var \XF\Entity\Thread $thread */
                foreach ($results['threads'] as $thread) {
                    $oMbqTopic = $oMbqRdEtForumTopic->initOMbqEtForumTopic($thread, array('case' => 'byRow', 'oMbqEtUser' => true, 'oMbqEtForum' => true));
                    if ($oMbqTopic) {
                        $objMbqEtForumTopics[] = $oMbqTopic;
                    }
                }
            }
        }
        $oMbqDataPage->totalNum = $total;
        $oMbqDataPage->datas = $objMbqEtForumTopics;

        return $oMbqDataPage;
    }

    protected function _getUserId()
    {
        $bridge = Bridge::getInstance();
        $userId = $bridge->filter('user_id', 'uint');
        if (!$userId)
        {
            $user = $bridge::visitor();
        } else {
            $user = $bridge->getUserRepo()->findUserById($userId);
        }

        if ($user && ($user instanceof \XF\Entity\User)) {

            return $user->user_id;

        }
        return null;
    }

    /**
     * @param \XF\Finder\Thread $threadFinder
     * @param $start
     * @param $limit
     * @return array
     */
    protected function _getThreadResults(\XF\Finder\Thread $threadFinder, $start, $limit)
    {
        $bridge = Bridge::getInstance();
        $bridge->setSectionContext('forums');

        $visitor = $bridge::visitor();

        /** @var \XF\Finder\Forum $forumFinder */
        $forumFinder = $bridge->finder('XF:Forum')
            ->with('Node.Permissions|' . $visitor->permission_combination_id);

        // hideForums
        $XFOption = $bridge->options();
        $hideForums = array_unique($XFOption->hideForums);

        /** @var \XF\Entity\Forum $forum */
        $forums = $forumFinder->fetch();
        foreach ($forums AS $forumId => $forum)
        {
            // hideForums
            if (in_array($forum->node_id, $hideForums)) {
                unset($forums[$forum->node_id]);
                continue;
            }
            if (!$forum->canView() || !$visitor->hasNodePermission($forum->node_id, 'viewOthers'))
            {
                unset($forums[$forum->node_id]);
                $threadFinder->where('node_id', '<>' , $forum->node_id);
            }
        }

        $threadFinder
            ->with('Forum.Node.Permissions|' . $visitor->permission_combination_id)
            ->where('node_id', $forums->keys());

        $total = $threadFinder->total();
        $threads = $threadFinder->fetch($limit, $start);

        $result = [
            'total' => $total,
            'threads' => $threads->filterViewable(),
            'start' => $start,
            'limit' => $limit,
        ];

        return $result;
    }

    static function temp_cmp($id_a, $id_b)
    {
        return $GLOBALS['orderids'][$id_a] > $GLOBALS['orderids'][$id_b];
    }

    /**
     * is array change to Object
     * @param $filter
     * @return stdClass
     */
    protected function _changeFilterToObject($filter)
    {
        if (is_array($filter)) {
            $filterObj = new stdClass();
            foreach ($filter as $k => $v){
                $filterObj->$k = $v;
            }
            $filter = $filterObj;
        }

        return $filter;
    }


    function order_by_last_post($results = array())
    {
        if (empty($results)) return $results;

        $simple_results = array();
        foreach ($results as $result) {
            $thread_id = $result[1];
            $content = $result['content'];
            if (isset($simple_results[$content['last_post_date']]))
                $simple_results[$content['last_post_date'] + 1] = $thread_id;
            else
                $simple_results[$content['last_post_date']] = $thread_id;
        }
        krsort($simple_results);
        $return_result = array();
        foreach ($simple_results as $thread_id)
            foreach ($results as $result)
                if ($result[1] == $thread_id)
                    $return_result[] = $result;

        return $return_result;
    }
}