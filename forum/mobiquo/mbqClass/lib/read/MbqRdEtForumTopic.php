<?php
use Tapatalk\Bridge;
use XF\Legacy\Link as XenForoLink;

defined('MBQ_IN_IT') or exit;

MbqMain::$oClk->includeClass('MbqBaseRdEtForumTopic');

/**
 * forum topic read class
 */
Class MbqRdEtForumTopic extends MbqBaseRdEtForumTopic
{

    public function __construct()
    {
    }

    public function makeProperty(&$oMbqEtForumTopic, $pName, $mbqOpt = array())
    {
        switch ($pName) {
            default:
                MbqError::alert('', __METHOD__ . ',line:' . __LINE__ . '.' . MBQ_ERR_INFO_UNKNOWN_PNAME . ':' . $pName . '.');
                break;
        }
    }

    /**
     * get forum topic objs
     *
     * @param  Mixed $var
     * @param  Array $mbqOpt
     * $mbqOpt['case'] = 'byForum' means get data by forum obj.$var is the forum obj.
     * $mbqOpt['case'] = 'subscribed' means get subscribed data.$var is the user id.
     * $mbqOpt['case'] = 'byTopicIds' means get data by topic ids.$var is the ids.
     * $mbqOpt['case'] = 'byAuthor' means get data by author.$var is the MbqEtUser obj.
     * $mbqOpt['top'] = true means get sticky data.
     * $mbqOpt['notIncludeTop'] = true means get not sticky data.
     * $mbqOpt['oMbqDataPage'] = pagination class info.
     * $mbqOpt['ann'] = true means get anouncement data.
     * $mbqOpt['oFirstMbqEtForumPost'] = true means load oFirstMbqEtForumPost property of topic,default is true.This param used to prevent infinite recursion call for get oMbqEtForumTopic and oFirstMbqEtForumPost and make memory depleted
     * @return  Mixed
     */

    public function getObjsMbqEtForumTopic($var, $mbqOpt)
    {
        $bridge = Bridge::getInstance();

        $return = '';
        switch ($mbqOpt['case']) {
            case 'byForum':
                $return = $this->_getObjsMbqEtForumTopicByForum($var, $mbqOpt, $bridge);
                break;
            case 'subscribed':
                $return = $this->_getObjsMbqEtForumTopicSubscribed($var, $mbqOpt, $bridge);
                break;
            case 'awaitingModeration':
                $return = $this->_getObjsMbqEtForumTopicAwaitingModeration($var, $mbqOpt, $bridge);
                break;
            case 'deleted':
                $return = $this->_getObjsMbqEtForumTopicDeleted($var, $mbqOpt, $bridge);
                break;
            case 'byTopicIds':
                $return = $this->_getObjsMbqEtForumTopicByTopicIds($var, $mbqOpt, $bridge);
                break;
            case 'byAuthor':
                $return = $this->_getObjsMbqEtForumTopicByAuthor($var, $mbqOpt, $bridge);
                break;
        }
        return $return;
    }

    /**
     * @param $var
     * @param $mbqOpt
     * @param Bridge $bridge
     * @return MbqDataPage
     */
    protected function _getObjsMbqEtForumTopicByAuthor($var, $mbqOpt, Bridge $bridge)
    {
        /** @var MbqDataPage $oMbqDataPage */
        $oMbqDataPage= $mbqOpt['oMbqDataPage'];
        $oMbqDataPage->datas = [];
        if (!is_a($var, 'MbqEtUser')) {
            return $oMbqDataPage;
        }
        $start = $oMbqDataPage->startNum;
        $limit = $oMbqDataPage->numPerPage;

        /** @var MbqEtUser $oMbqEtUser */
        $oMbqEtUser = $var;
        $threadRepo = $bridge->getThreadRepo();
        /** @var \XF\Finder\Thread $threadFinder */
        $threadFinder = $threadRepo->findThreadsWithPostsByUser($oMbqEtUser->userId);

        $threads = $threadFinder->limit($limit, $start)->fetch()->filterViewable();
        $totalNum = $threadFinder->fetch()->filterViewable()->count();

        $objMbqEtForumTopicArray = [];
        foreach($threads as $thread)
        {
            $mbqTopic = $this->initOMbqEtForumTopic($thread, array('case' => 'byRow', 'oMbqEtUser' => true));
            if ($mbqTopic) $objMbqEtForumTopicArray[] = $mbqTopic;
        }

        $oMbqDataPage->datas = $objMbqEtForumTopicArray;
        $oMbqDataPage->totalNum = $totalNum;
        return $oMbqDataPage;
    }

    protected function _getObjsMbqEtForumTopicByTopicIds($var, $mbqOpt, Bridge $bridge)
    {
        $oMbqDataPage = $mbqOpt['oMbqDataPage'];
        $arrTids = explode(',', $var);
        $arrTids = is_array($arrTids) ? $arrTids : array($arrTids);

        $objsMbqEtForumTopic = array();
        /** @var MbqRdEtForumTopic $oMbqRdEtForumTopic */
        $oMbqRdEtForumTopic = MbqMain::$oClk->newObj('MbqRdEtForumTopic');

        $ix = 0;
        foreach ($arrTids as $tid) {
            $oMbqEtForumTopic = $oMbqRdEtForumTopic->initOMbqEtForumTopic($tid, array('case' => 'byTopicId'));

            if ($oMbqEtForumTopic != null) {
                $objsMbqEtForumTopic[] = $oMbqEtForumTopic;
                $ix++;
            }

            if ($ix >= 50) {
                break;
            }
        }
        $oMbqDataPage->totalNum = sizeof($objsMbqEtForumTopic);
        $oMbqDataPage->datas = $objsMbqEtForumTopic;

        return $oMbqDataPage;
    }

    /**
     * m_get_delete_topic
     *
     * ?
     * @param $var
     * @param $mbqOpt
     * @param Bridge $bridge
     * @return array
     */
    protected function _getObjsMbqEtForumTopicDeleted($var, $mbqOpt, Bridge $bridge)
    {
        $oMbqDataPage = $mbqOpt['oMbqDataPage'];
        $start = $oMbqDataPage->startNum;
        $limit = $oMbqDataPage->numPerPage;
        $objMbqEtForumTopic = array();

        /*
        $threadRepo = $bridge->getThreadRepo();
        $threadFinder = $threadRepo->getDeletedThreadFinder();
        $threads = $threadFinder->limit($limit, $start)->fetch()->filterViewable();
        $totalNum = $threadFinder->fetch()->filterViewable()->count();
        foreach($threads as $thread)
        {
            $objMbqEtForumTopic[] = $this->initOMbqEtForumTopic($thread, array('case' => 'byRow', 'oMbqEtUser' => true));
        }
        if (!$mbqOpt['oMbqDataPage']) {
            return $objMbqEtForumTopic;
        }
        $oMbqDataPage = $mbqOpt['oMbqDataPage'];
        $oMbqDataPage->totalNum = $totalNum;
        $oMbqDataPage->datas = $objMbqEtForumTopic;
        return $oMbqDataPage;
        */

        $objsMbqEtForumTopic = array();
        if ($mbqOpt['oMbqDataPage']) {
            $oMbqDataPage = $mbqOpt['oMbqDataPage'];
            $oMbqDataPage->totalNum = 0;
            $oMbqDataPage->datas = $objsMbqEtForumTopic;
            return $oMbqDataPage;
        } else {
            return $objsMbqEtForumTopic;
        }
    }

    protected function _getObjsMbqEtForumTopicAwaitingModeration($var, $mbqOpt, Bridge $bridge)
    {
        $oMbqDataPage = $mbqOpt['oMbqDataPage'];

        $approvalQueueRepo = $bridge->getApprovalQueueReop();
        $unapprovedItems = $approvalQueueRepo->findUnapprovedContent();
        $findTotalCount = $unapprovedItems->total();
        // mbq plugin only return content_type = thread
        $unapprovedItems = $unapprovedItems->where('content_type', '=', 'thread')->fetch();

        if ($findTotalCount != $bridge->app()->unapprovedCounts['total'])
        {
            $approvalQueueRepo->rebuildUnapprovedCounts();
        }
        $approvalQueueRepo->addContentToUnapprovedItems($unapprovedItems);
        $approvalQueueRepo->cleanUpInvalidRecords($unapprovedItems);
        $approvalList = $approvalQueueRepo->filterViewableUnapprovedItems($unapprovedItems);
        if ($approvalList) {
            $approvalList = $approvalList->toArray();
        }else{
            $approvalList = [];
        }

        $total_topic_num = count($approvalList);
        $objMbqEtForumTopic = array();
        $approvalList = array_slice($approvalList, $oMbqDataPage->startNum, $oMbqDataPage->numPerPage);

        foreach ($approvalList as $data) {
            if ($topicV = $this->initOMbqEtForumTopic($data['content_id'], array('case' => 'byTopicId', 'oMbqEtUser' => true))) {
                $objMbqEtForumTopic[] = $topicV;
            }
        }

        if (!$mbqOpt['oMbqDataPage']) {

            return $objMbqEtForumTopic;
        }

        $oMbqDataPage = $mbqOpt['oMbqDataPage'];
        $oMbqDataPage->totalNum = $total_topic_num;
        $oMbqDataPage->datas = $objMbqEtForumTopic;

        return $oMbqDataPage;
    }

    protected function _getObjsMbqEtForumTopicSubscribed($var, $mbqOpt, Bridge $bridge)
    {

        $oMbqDataPage = $mbqOpt['oMbqDataPage'];

        $start = $oMbqDataPage->startNum;
        $limit = $oMbqDataPage->numPerPage;

		$threadRepo = $bridge->getThreadRepo();
        $threadFinder = $threadRepo->getThreadFinderByUserWatched();
        $totalThreads = $threadFinder->total();
        $threads = $threadFinder->fetch($limit, $start)->filterViewable();

        $oMbqDataPage->datas = [];
        /** @var \XF\Entity\Thread $thread */
        foreach ($threads as &$thread) {

            // filtering hideForums
            $options = $bridge->options();
            $hideForums = $options->hideForums;
            if (in_array($thread['node_id'], $hideForums)) {
                // $totalThreads -= 1;
                continue;
            }

            // if ban this thread author user
            if ($thread->isIgnored()){
                // $totalThreads -= 1;
                continue;
            }

            $oMbqDataPage->datas[] = $this->initOMbqEtForumTopic($thread, array('case' => 'byRow', 'oMbqEtForum' => true, 'oMbqEtUser' => true));
        }

        //they do not return count, only num of pages so we need to play with it
        $oMbqDataPage->totalNum = $totalThreads;
        return $oMbqDataPage;
    }

    protected function _getObjsMbqEtForumTopicByForum($var, $mbqOpt, Bridge $bridge)
    {

        $oMbqEtForum = $var;
        $oMbqDataPage = $mbqOpt['oMbqDataPage'];
        $ann = isset($mbqOpt['ann']) && $mbqOpt['ann'];
        $top = isset($mbqOpt['top']) && $mbqOpt['top'];
        $objsMbqEtForumTopic = array();

        $forumId = $oMbqEtForum->forumId->oriValue;
        $visitor = $bridge::visitor();


        if ($bridge->isXenRenresourceForumId($forumId)) {
            return $this->_getObjsMbqEtForumTopicByForumWithXenRenresourceForum($var, $mbqOpt, $bridge);
        }


        //get announcement
        if ($ann) {
            $notices = array();
            $nodeModel = $bridge->getNodeRepo();
            $node = $nodeModel->findNodeById($forumId);
            $ann_author = $bridge->getUserRepo()->findUserById(1); // dev
            if (!empty($node)) {
                if ($bridge->options()->enableNotices) {

                    $user = $bridge::visitor()->toArray();
                    $noticeTokens = array(
                        '{name}' => $user['username'] !== '' ? $user['username'] : TT_GetPhraseString('guest'),
                        '{user_id}' => $user['user_id'],
                    );

                    $noticeModel = $bridge->getNoticeRepo();
                    $dismissedNoticeIds = $noticeModel->getDismissedNoticesForUser($visitor);

                    /** @var  $noticeLists */
                    $noticeLists = $noticeModel->getAllNoticeLists();

                    /** @var \XF\Entity\Notice $notice */
                    foreach ($noticeLists as $notice) {
                        $noticeId = $notice->notice_id;
                        $isDismissed = false;
                        foreach($dismissedNoticeIds as $dismissedNotice)
                        {
                            if($dismissedNotice['notice_id'] == $noticeId && $dismissedNotice['user_id'] == $visitor['user_id'])
                            {
                                $isDismissed = true;
                            }
                        }
                        if ($isDismissed) {
                            continue;
                        }
                        if(!$notice->active)
                        {
                            continue;
                        }
                        $matchesUser = $bridge->app()->criteria('XF:User', $notice['user_criteria'])->isMatched($visitor);
                        if (!$matchesUser) {
                            continue;
                        }

                        if (!TT_pageMatchesCriteria($notice['page_criteria'], $node)) {
                            continue;
                        }

                        $notices[$noticeId] = array(
                            'node_id' => $forumId,
                            'thread_id' => 'tpann_' . $noticeId,
                            'thread_type' => 'ann',
                            'user_id' => isset($ann_author['user_id']) ? $ann_author['user_id'] : 1,
                            'title' => $notice['title'],
                            'message' => str_replace(array_keys($noticeTokens), $noticeTokens, $notice['message']),
                            'wrap' => isset($notice['wrap']) ? $notice['wrap'] : '',
                            'dismissible' => ($notice['dismissible'] && $visitor->user_id)
                        );
                    }
                }
            }

            if (empty($notices)) {

                if (!$mbqOpt['oMbqDataPage']) {

                    return $objsMbqEtForumTopic;
                }

                $oMbqDataPage = $mbqOpt['oMbqDataPage'];
                $oMbqDataPage->totalNum = 0;
                $oMbqDataPage->datas = array();

                return $oMbqDataPage;

            }

            foreach ($notices as $thread) {
                $objsMbqEtForumTopic[] = $this->initOMbqEtForumTopic($thread, array('case' => 'byRow', 'oMbqEtForum' => $oMbqEtForum, 'oMbqEtUser' => true));
            }
            if (!$mbqOpt['oMbqDataPage']) {

                return $objsMbqEtForumTopic;
            }

            $oMbqDataPage = $mbqOpt['oMbqDataPage'];
            $oMbqDataPage->totalNum = sizeof($notices);
            $oMbqDataPage->datas = $objsMbqEtForumTopic;

            return $oMbqDataPage;

        } else {

            // normal
            $forumRepo = $bridge->getForumRepo();
            $forum = $forumRepo->assertForumValidAndViewable($forumId);
            if (!$forum) {
                return false;
            }
            $start = $oMbqDataPage->startNum;
            $limit = $oMbqDataPage->numPerPage;
            $threadModel = $bridge->getThreadRepo();

            if ($top) {
                // only Sticky topic
                $threadFinder = $threadModel->findStickyThreadsForForumView($forum);
                $totalThreads = $threadFinder->total();
                $threads = $threadFinder->limit($limit, $start)->fetch();
            } else {
                // normal all topic list ( sticky = 0)
                $threadFinder = $threadModel->findThreadsForForumView($forum)
                    ->where('sticky', '=', 0);
                $totalThreads = $threadFinder->total();
                $threads = $threadFinder->limit($limit, $start)->fetch();
            }

            /** @var \XF\Entity\Thread $thread */
            foreach ($threads as $thread) {
                $objTopic = $this->initOMbqEtForumTopic($thread, array('case' => 'byRow', 'oMbqEtForum' => $oMbqEtForum, 'oMbqEtUser' => true));
                if ($objTopic && ($objTopic instanceof MbqEtForumTopic)) {
                    $objsMbqEtForumTopic[] = $objTopic;
                }
            }
            if ($mbqOpt['oMbqDataPage']) {
                $oMbqDataPage = $mbqOpt['oMbqDataPage'];
                $oMbqDataPage->totalNum = $totalThreads;
                $oMbqDataPage->datas = $objsMbqEtForumTopic;
                return $oMbqDataPage;
            } else {
                return $objsMbqEtForumTopic;
            }
        }

    }

    // dev
    protected function _getObjsMbqEtForumTopicByForumWithXenRenresourceForum($var, $mbqOpt, Bridge $bridge)
    {
        $threadModel = $bridge->getThreadModel();
        $forumModel = $bridge->getForumModel();
        $prefixModel = $bridge->_getPrefixModel();
        $userModel = $bridge->getUserModel();

        $start = $oMbqDataPage->startNum;
        $limit = $oMbqDataPage->numPerPage;
        $ftpHelper = $bridge->getHelper('ForumThreadPost');
        $forumFetchOptions = array('readUserId' => $visitor['user_id']);
        $forum = $ftpHelper->assertForumValidAndViewable($forumId, $forumFetchOptions);

        $threadFetchConditions = $threadModel->getPermissionBasedThreadFetchConditions($forum) + array(
                'sticky' => 1
            );

        $unreadSticky = 0;
        $threads = $threadModel->getStickyThreadsInForum($forum['node_id'], $threadFetchConditions, array(
            'readUserId' => $visitor['user_id'],
            'watchUserId' => $visitor['user_id'],
            'postCountUserId' => $visitor['user_id']
        ));
        foreach ($threads AS &$thread) {
            $thread = $threadModel->prepareThread($thread, $forum, $permissions);
            if ($thread['isNew'])
                $unreadSticky++;
        }
        unset($thread);

        $threadFetchConditions['sticky'] = 0;
        $totalThreads = $threadModel->countThreadsInForum($forum['node_id'], $threadFetchConditions);
        $threadFetchOptions = array(
            'limit' => $limit,
            'offset' => $start,

            'join' => XenForo_Model_Thread::FETCH_USER | XenForo_Model_Thread::FETCH_FIRSTPOST,
            'readUserId' => $visitor['user_id'],
            'watchUserId' => $visitor['user_id'],
            'postCountUserId' => $visitor['user_id'],

            'order' => 'last_post_date',
            'orderDirection' => 'desc'
        );


        if ($top) {
            $threads = $threadModel->getStickyThreadsInForum($forum['node_id'], $threadFetchConditions, $threadFetchOptions);
            $totalThreads = count($threads);
        } else {
            $threads = $threadModel->getThreadsInForum($forum['node_id'], $threadFetchConditions, $threadFetchOptions);
        }

        $inlineModOptions = array();

        foreach ($threads AS &$thread) {
            $thread = $threadModel->prepareThread($thread, $forum, $permissions);
        }
        unset($thread);


        foreach ($threads as $thread) {
            $objsMbqEtForumTopic[] = $this->initOMbqEtForumTopic($thread, array('case' => 'byRow', 'oMbqEtForum' => $oMbqEtForum, 'oMbqEtUser' => true));
        }
        if ($mbqOpt['oMbqDataPage']) {
            $oMbqDataPage = $mbqOpt['oMbqDataPage'];
            $oMbqDataPage->totalNum = $totalThreads;
            $oMbqDataPage->datas = $objsMbqEtForumTopic;
            return $oMbqDataPage;
        } else {
            return $objsMbqEtForumTopic;
        }
    }



    /**
     * init one forum topic by condition
     *
     *
     * -----------------------------------------------------------------
     *
     *
     * @param $var
     * @param $mbqOpt
     * @return bool|MbqEtForumTopic|mixed|null
     */
    public function initOMbqEtForumTopic($var, $mbqOpt)
    {
        $bridge = Bridge::getInstance();

        $return = null;
        switch ($mbqOpt['case']) {
            case 'byRow':
                $return = $this->_initOMbqEtForumTopicByRow($var, $mbqOpt, $bridge);
                break;

            case 'byTopicId':
                $return = $this->_initOMbqEtForumTopicByTopicId($var, $mbqOpt, $bridge);
                break;
        }

        return $return;
    }

    /**
     * get Announcement
     *
     * @param array $var
     * @param $mbqOpt
     * @param Bridge $bridge
     * @return MbqEtForumTopic
     */
    protected function _initOMbqEtForumTopicByRowWithAnnouncement($var, $mbqOpt, Bridge $bridge)
    {
        /**
         * $var['thread_id'] is notice_id
         * $var['thread_type'] = 'ann'
         *
         * @var Array $notice */
        $notice = $var;

        $oMbqRdEtUser = MbqMain::$oClk->newObj('MbqRdEtUser');
        /** @var MbqEtForumTopic $oMbqEtForumTopic */
        $oMbqEtForumTopic = MbqMain::$oClk->newObj('MbqEtForumTopic');

        $oMbqEtForumTopic->topicId->setOriValue($notice['thread_id']);
        $oMbqEtForumTopic->topicTitle->setOriValue($notice['title']);
        $oMbqEtForumTopic->topicAuthorId->setOriValue($notice['user_id']);
        if(isset($mbqOpt['oMbqEtUser']))
        {
            $oMbqEtForumTopic->oAuthorMbqEtUser = $oMbqRdEtUser->initOMbqEtUser($oMbqEtForumTopic->topicAuthorId->oriValue, array('case' => 'byUserId'));
        }
        if(isset($notice['message']))
        {
            $oMbqEtForumTopic->shortContent->setOriValue($bridge->renderPostPreview($notice['message'], $oMbqEtForumTopic->topicAuthorId->oriValue, 200));
        }
        $oMbqEtForumTopic->mbqBind = $notice;

        return $oMbqEtForumTopic;
    }

    /**
     * @param $var
     * @param $mbqOpt
     * @param Bridge $bridge
     * @return MbqEtForumTopic
     */
    protected function _initOMbqEtForumTopicByRow($var, $mbqOpt, Bridge $bridge)
    {
        $thread = $var;
        $isMoved = false;
        $isMerged = false;
        $canMerge = true;
        $threadId = $thread['thread_id'];
        $threadType = isset($thread['thread_type']) ? $thread['thread_type'] : '';
        if (preg_match('/^tpann_\d+$/', $threadId))
        {
            $prefix_id = preg_split('/_/', $threadId);
            $threadId = $prefix_id[1];
            $threadType = 'ann';
        }

        // Announcement thread
        if ($threadType == 'ann') {
            return $this->_initOMbqEtForumTopicByRowWithAnnouncement($var, $mbqOpt, $bridge);
        }

        $threadRepo = $bridge->getThreadRepo();
        $postModel = $bridge->getPostRepo();
        $userModel = $bridge->getUserRepo();
        $visitor = $bridge::visitor();

        if (!is_object($var)) {
            $thread = $threadRepo->findThreadById($threadId);
        }

        if ($threadRepo->isRedirect($thread)) {
            $canMerge = false;
            if (!$thread->Redirect) {
                // no permission
                return false;
            }

            $redirectKey = $thread->Redirect->redirect_key;
            $parts = preg_split('/-/', $redirectKey);

            // reload thread !!!
            $threadId = $parts[1];
            $thread = $threadRepo->findThreadById($threadId);
            if (!$thread) {
                return false;
            }

            if (count($parts) < 4) {
                $isMoved = false;
                $isMerged = true;
            } else {
                $isMoved = true;
                $isMerged = false;
            }
        }

        /** @var MbqRdEtUser $oMbqRdEtUser */
        $oMbqRdEtUser = MbqMain::$oClk->newObj('MbqRdEtUser');
        /** @var MbqEtForumTopic $oMbqEtForumTopic */
        $oMbqEtForumTopic = MbqMain::$oClk->newObj('MbqEtForumTopic');

        $oMbqEtForumTopic->topicAuthorId->setOriValue($thread['user_id']);

        /** @var MbqEtUser $authUser */
        $authUser = $oMbqRdEtUser->initOMbqEtUser($oMbqEtForumTopic->topicAuthorId->oriValue, array('case' => 'byUserId'));
        if (isset($mbqOpt['oMbqEtUser'])) {
            $oMbqEtForumTopic->oAuthorMbqEtUser = $authUser;
        }

        $oMbqEtForumTopic->forumId->setOriValue($thread['node_id']);
        $oMbqEtForumTopic->topicId->setOriValue($thread['thread_id']);

        if (isset($mbqOpt['oMbqEtForum']) && $mbqOpt['oMbqEtForum']) {

            if ($mbqOpt['oMbqEtForum'] instanceof MbqEtForum) {
                $oMbqEtForumTopic->oMbqEtForum = $mbqOpt['oMbqEtForum'];
            } else {
                /** @var MbqRdEtForum $oMbqRdEtForum */
                $oMbqRdEtForum = MbqMain::$oClk->newObj('MbqRdEtForum');
                $oMbqEtForumTopic->oMbqEtForum = $oMbqRdEtForum->initOMbqEtForum($oMbqEtForumTopic->forumId->oriValue, array('case' => 'byForumId'));
            }

        }

        $oMbqEtForumTopic->topicTitle->setOriValue($thread['title']);

        if (isset($thread['prefix_id'])) {
            $oMbqEtForumTopic->prefixId->setOriValue($thread['prefix_id']);
            $oMbqEtForumTopic->prefixName->setOriValue(TT_get_prefix_name($thread['prefix_id']));
        }

        $oMbqRdEtUser = MbqMain::$oClk->newObj('MbqRdEtUser');
        // last post infos
        if (isset($thread['last_post_user_id']) && $thread['last_post_user_id'] != 0) {

            $oMbqEtForumTopic->lastReplyAuthorId->setSafeOriValueFromArray($thread, 'last_post_user_id');
            $oMbqEtForumTopic->oLastReplyMbqEtUser = $oMbqRdEtUser->initOMbqEtUser($oMbqEtForumTopic->lastReplyAuthorId->oriValue, array('case' => 'byUserId'));

        } else {

            if (isset($thread['last_post_username'])) {
                $oLastReplyMbqEtUser = $oMbqRdEtUser->initOMbqEtUser($thread['last_post_username'], array('case' => 'byLoginName'));
                if ($oLastReplyMbqEtUser instanceof MbqEtUser) {
                    $oMbqEtForumTopic->oLastReplyMbqEtUser = $oLastReplyMbqEtUser;
                    $oMbqEtForumTopic->lastReplyAuthorId->setOriValue($oLastReplyMbqEtUser->userId->oriValue);
                }
            }
        }

        $oMbqEtForumTopic->canSubscribe->setOriValue(MbqMain::isActiveMember());

        $threadWatchModel = $bridge->getThreadWatchRepo();
        // follow thread info
        $subscriptionStatus = $threadWatchModel->getUserThreadWatchByThreadId($visitor->user_id, $thread['thread_id']);
        if (!$subscriptionStatus) {
            $oMbqEtForumTopic->isSubscribed->setOriValue(false);
        }else{
            $oMbqEtForumTopic->isSubscribed->setOriValue(true);

            if (isset($subscriptionStatus['email_subscribe']) && $subscriptionStatus['email_subscribe'] == '1') {
                $oMbqEtForumTopic->subscriptionEmail->setOriValue(true);
            } else {
                $oMbqEtForumTopic->subscriptionEmail->setOriValue(false);
            }
        }

        if (isset($thread['discussion_open'])) {
            $oMbqEtForumTopic->isClosed->setOriValue($thread['discussion_open'] == 0);
        }

        if (isset($thread['message'])) {
            $oMbqEtForumTopic->shortContent->setOriValue($bridge->renderPostPreview($thread['message'], $oMbqEtForumTopic->topicAuthorId->oriValue, 200));
        }
        else
        {
            $firstPost = $postModel->findPostById($thread['first_post_id']);
            $oMbqEtForumTopic->shortContent->setOriValue($bridge->renderPostPreview($firstPost['message'], $thread['user_id'], 200));
        }
        $lastPost = $postModel->findPostById($thread['last_post_id']);
        $oMbqEtForumTopic->lastPostShortContent->setOriValue($bridge->renderPostPreview($lastPost['message'], $thread['last_post_user_id'], 200));

        $oMbqEtForumTopic->authorIconUrl->setOriValue($authUser->iconUrl);
        $oMbqEtForumTopic->lastReplyTime->setSafeOriValueFromArray($thread, 'last_post_date');
        $oMbqEtForumTopic->postTime->setSafeOriValueFromArray($thread, 'post_date');
        if (isset($thread['reply_count'])) {
            $oMbqEtForumTopic->replyNumber->setOriValue($thread['reply_count']);
            $oMbqEtForumTopic->totalPostNum->setOriValue($thread['reply_count'] + 1);
        }else{
            $oMbqEtForumTopic->replyNumber->setOriValue(0);
            $oMbqEtForumTopic->totalPostNum->setOriValue(0);
        }
        $oMbqEtForumTopic->viewNumber->setSafeOriValueFromArray($thread, 'view_count');
        $oMbqEtForumTopic->newPost->setOriValue($thread->isUnread());
        $oMbqEtForumTopic->likeCount->setSafeOriValueFromArray($thread, 'first_post_likes');
        $oMbqEtForumTopic->participatedIn->setOriValue($thread->getUserPostCount()); // dev
        $oMbqEtForumTopic->canDelete->setOriValue($thread->canDelete('soft'));
        $oMbqEtForumTopic->canClose->setOriValue($thread->canLockUnlock());
        $oMbqEtForumTopic->canApprove->setOriValue($thread->canApproveUnapprove());
        $oMbqEtForumTopic->canRename->setOriValue($thread->canEdit());
        $oMbqEtForumTopic->canStick->setOriValue($thread->canStickUnstick());
        $oMbqEtForumTopic->canMove->setOriValue($thread->canMove());

        $oMbqEtForumTopic->isMoved->setOriValue($isMoved);
        $oMbqEtForumTopic->isMerged->setOriValue($isMerged);
        $oMbqEtForumTopic->canMerge->setOriValue($canMerge && $thread->canMerge());
        $oMbqEtForumTopic->isApproved->setOriValue($thread['discussion_state'] != 'moderated');
        $oMbqEtForumTopic->isDeleted->setOriValue($thread->discussion_state == 'deleted');
        $oMbqEtForumTopic->isSticky->setSafeOriValueFromArray($thread, 'sticky');
        $oMbqEtForumTopic->canBan->setOriValue(false);

        if ($visitor->hasAdminPermission('ban')) {
            if ($visitor->canCleanSpam()) {
                $oMbqEtForumTopic->canBan->setOriValue(true);
            }
        }

        $oMbqEtForumTopic->isBan->setSafeOriValueFromArray($thread, 'is_banned');

        if (isset($thread['reply_count'])) {
            $firstUnreadPosition = $thread['reply_count'] + 1;
            if ($visitor['user_id']) {

                if ($thread->canView()) {
                    $readDate = $thread->getVisitorReadDate();
                    $oMbqEtForumTopic->readTimestamp->setOriValue($readDate);

                    $firstUnreadPost = $postModel->findNextPostsInThread($thread, $readDate)->fetchOne();
                    if ($firstUnreadPost) {
                        $firstUnreadPosition = $firstUnreadPost->getValue('position');
                    }
                }
            }
            $oMbqEtForumTopic->firstUnreadPosition->setOriValue($firstUnreadPosition);
        }
        $oMbqEtForumTopic->canReply->setOriValue($thread->canReply() && MbqMain::isActiveMember());

        $oMbqEtForumTopic->mbqBind = $thread;

        return $oMbqEtForumTopic;
    }

    protected function _initOMbqEtForumTopicByTopicId($var, $mbqOpt, Bridge $bridge)
    {
        $visitor = $bridge::visitor();

        $threadType = '';
        if (is_string($var) && preg_match('/^tpann_\d+$/', $var)) {
            $threadType = 'ann';
            $prefix_id = preg_split('/_/', $var);
            $threadId = (int)$prefix_id[1];
        }
        if (is_array($var) && isset($var['thread_type']) && $var['thread_type']) {
            $threadType = $var['thread_type'];
        }
        if (is_array($var) && isset($var['thread_id']) && $var['thread_id'] && is_numeric($var['thread_id'])) {
            $threadId = $var['thread_id'];
        }
        if (!isset($threadId)) {
            $threadId = $var;
        }

        // Announcement thread ,get announcement
        if ($threadType == 'ann') {
            $notices = array();

            if ($bridge->options()->enableNotices) {
                $user = $bridge::visitor()->toArray();
                $noticeTokens = array(
                    '{name}' => $user['username'] !== '' ? $user['username'] : TT_GetPhraseString('guest'),
                    '{user_id}' => $user['user_id'],
                );

                $noticeModel = $bridge->getNoticeRepo();
                $dismissedNoticeIds = $noticeModel->getDismissedNoticesForUser($visitor);

                /** @var \XF\Entity\Notice $notice */
                $notice = $noticeModel->getNoticeById($threadId);
                if ($notice && ($notice instanceof  \XF\Entity\Notice)) {
                    $noticeId = $notice->notice_id;

                    if (!in_array($noticeId, $dismissedNoticeIds)) {
                        $matchesUser = $bridge->app()->criteria('XF:User', $notice['user_criteria'])->isMatched($visitor);
                        if ($matchesUser) {
                            $notices[$noticeId] = array(
                                'thread_id' => 'tpann_' . $noticeId,
                                'thread_type' => 'ann',
                                'user_id' => 1, // dev
                                'title' => $notice['title'],
                                'message' => str_replace(array_keys($noticeTokens), $noticeTokens, $notice['message']),
                                'wrap' => isset($notice['wrap']) ? $notice['wrap'] :'',
                                'dismissible' => ($notice['dismissible'] && $visitor->user_id)
                            );
                        }
                    }
                }

                if (isset($notices[$threadId])) {
                    if($notices[$threadId]['dismissible'] && $notice->notice_type === 'floating' ){
                        $noticeModel->dismissNotice($notice, \XF::visitor());
                        $bridge->app()->session()->remove('dismissedNotices'); // force recache
                    }
                    $objMbqEtForumTopic = $this->initOMbqEtForumTopic($notices[$threadId], array('case' => 'byRow', 'oMbqEtUser' => true));
                    return $objMbqEtForumTopic;
                }
            }

            return false;
        }// ann end

        $threadRepo = $bridge->getThreadRepo();
        $thread = $threadRepo->findThreadById($threadId);

        if (!$thread || !$thread->canView()) {

            return false;
        }

        $objMbqEtForumTopic = $this->initOMbqEtForumTopic($thread, array('case' => 'byRow', 'oMbqEtForum' => true, 'oMbqEtUser' => true));

        return $objMbqEtForumTopic;
    }


    public function getUrl($oMbqEtForumTopic)
    {
        return XenForoLink::buildPublicLink('full:threads', array('thread_id' => $oMbqEtForumTopic->topicId->oriValue, 'title' => $oMbqEtForumTopic->mbqBind['title']));
    }
}