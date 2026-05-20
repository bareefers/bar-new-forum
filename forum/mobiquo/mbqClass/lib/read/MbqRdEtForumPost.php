<?php

use Tapatalk\Bridge;

defined('MBQ_IN_IT') or exit;

MbqMain::$oClk->includeClass('MbqBaseRdEtForumPost');

/**
 * forum post read class
 */
Class MbqRdEtForumPost extends MbqBaseRdEtForumPost
{

    public function __construct()
    {
    }

    public function makeProperty(&$oMbqEtForumPost, $pName, $mbqOpt = array())
    {
        switch ($pName) {
            default:
                MbqError::alert('', __METHOD__ . ',line:' . __LINE__ . '.' . MBQ_ERR_INFO_UNKNOWN_PNAME . ':' . $pName . '.');
                break;
        }
    }

    /**
     * get forum post objs
     *
     * @param  Mixed $var
     * @param  Array $mbqOpt
     * $mbqOpt['case'] = 'byTopic' means get data by forum topic obj.$var is the forum topic obj.
     * $mbqOpt['case'] = 'byPostIds' means get data by post ids.$var is the ids.
     * $mbqOpt['case'] = 'byReplyUser' means get data by reply user.$var is the MbqEtUser obj.
     * @return  Mixed
     */
    public function getObjsMbqEtForumPost($var, $mbqOpt)
    {
        $return = '';
        $bridge = Bridge::getInstance();

        switch ($mbqOpt['case']) {
            case 'byTopic':
                $return = $this->_getObjsMbqEtForumPostByTopic($var, $mbqOpt, $bridge);
                break;
            case 'byPostIds':
                $return = $this->_getObjsMbqEtForumPostByPostIds($var, $mbqOpt, $bridge);
                break;
            case 'awaitingModeration':
                $return = $this->_getObjsMbqEtForumPostAwaitingModeration($var, $mbqOpt, $bridge);
                break;
            case 'deleted':
                $return = $this->_getObjsMbqEtForumPostDeleted($var, $mbqOpt, $bridge);
                break;
            case 'reported':
                $return = $this->_getObjsMbqEtForumPostReported($var, $mbqOpt, $bridge);
                break;
        }

        return $return;
    }

    /**
     * m_get_report_post
     * 
     * @param $var
     * @param $mbqOpt
     * @param Bridge $bridge
     * @return mixed
     */
    protected function _getObjsMbqEtForumPostReported($var, $mbqOpt, Bridge $bridge)
    {
        $oMbqDataPage = $mbqOpt['oMbqDataPage'];

        $bridge = Bridge::getInstance();
        $bridge->session()->reportLastRead = \XF::$time;
        $reportRepo = $bridge->getReportRepo();
        $openReports = $reportRepo->findReports()->fetch();

        if ($openReports->count() != $bridge->app()->reportCounts['total'])
        {
            $reportRepo->rebuildReportCounts();
        }
        $openPostReports = $reportRepo->getPostReports()->fetch();
        $openPostReports = $reportRepo->filterViewableReports($openPostReports);
        if ($openPostReports) {
            $openPostReports = $openPostReports->toArray();
        }else{
            $openPostReports = [];
        }

//        $closedReportsFinder = $reportRepo->findReports(['resolved', 'rejected'], time() - 86400);
//        $closedReports = $closedReportsFinder->fetch();
//        $closedReports = $reportRepo->filterViewableReports($closedReports);

        $totalNum = count($openPostReports);
        $reports = array_slice($openPostReports, $oMbqDataPage->startNum, $oMbqDataPage->numPerPage);
        foreach ($reports as $rid => $report) {
            if ($report['content_type'] != 'post') {
                $totalNum -= 1;
                continue;
            }
            // case by 'byPostId' may return null, so judge here
            $oMbqEtForumPost = $this->initOMbqEtForumPost($report['content_id'], array('case' => 'byPostId', 'oMbqEtForum' => true));
            if (empty($oMbqEtForumPost)) {
                $totalNum -= 1;
                continue;
            }
            $oMbqDataPage->datas[] = $oMbqEtForumPost;
        }
        $oMbqDataPage->totalNum = $totalNum;

        return $oMbqDataPage;
    }

    /**
     * m_get_delete_post
     *
     * ?
     * @param $var
     * @param $mbqOpt
     * @param Bridge $bridge
     * @return array
     */
    protected function _getObjsMbqEtForumPostDeleted($var, $mbqOpt, Bridge $bridge)
    {
        $oMbqDataPage = $mbqOpt['oMbqDataPage'];

        $totalNum = 0;
        $objsMbqEtForumPosts = array();

        //foreach($rows as $row)
        //{
        //    $objsMbqEtForumPosts[] = $this->initOMbqEtForumPost($row, array('case' => 'byRow', 'oMbqEtUser' => true));
        //}
        if ($mbqOpt['oMbqDataPage']) {
            $oMbqDataPage = $mbqOpt['oMbqDataPage'];
            $oMbqDataPage->totalNum = $totalNum;
            $oMbqDataPage->datas = $objsMbqEtForumPosts;
            return $oMbqDataPage;
        } else {
            return $objsMbqEtForumPosts;
        }
    }

    /**
     * m_get_moderate_post
     *
     * @param $var
     * @param $mbqOpt
     * @param Bridge $bridge
     * @return array
     */
    protected function _getObjsMbqEtForumPostAwaitingModeration($var, $mbqOpt, Bridge $bridge)
    {
        $oMbqDataPage = $mbqOpt['oMbqDataPage'];

        $approvalQueueRepo = $bridge->getApprovalQueueRepo();
        $unapprovedItems = $approvalQueueRepo->findUnapprovedContent()->fetch();
        if ($unapprovedItems->count() != $bridge->app()->unapprovedCounts['total'])
        {
            $approvalQueueRepo->rebuildUnapprovedCounts();
        }

        $postUnapprovedItems = $approvalQueueRepo->getPostUnapprovedContent()->fetch();
        $approvalQueueRepo->addContentToUnapprovedItems($postUnapprovedItems);
        $approvalQueueRepo->cleanUpInvalidRecords($postUnapprovedItems);
        $postUnapprovedItems = $approvalQueueRepo->filterViewableUnapprovedItems($postUnapprovedItems);
        if ($postUnapprovedItems) {
            $postUnapprovedItems = $postUnapprovedItems->toArray();
        }else{
            $postUnapprovedItems = [];
        }

        $total_post_num = count($postUnapprovedItems);
        $objsMbqEtForumPosts = array();
        $datas = array_slice($postUnapprovedItems, $oMbqDataPage->startNum, $oMbqDataPage->numPerPage);
        foreach ($datas as $data) {
            $objsMbqEtForumPosts[] = $this->initOMbqEtForumPost($data['content_id'], array('case' => 'byPostId', 'oMbqEtUser' => true));
        }
        if ($mbqOpt['oMbqDataPage']) {
            $oMbqDataPage = $mbqOpt['oMbqDataPage'];
            $oMbqDataPage->totalNum = $total_post_num;
            $oMbqDataPage->datas = $objsMbqEtForumPosts;
            return $oMbqDataPage;
        } else {
            return $objsMbqEtForumPosts;
        }
    }

    protected function _getObjsMbqEtForumPostByPostIds($var, $mbqOpt, Bridge $bridge)
    {
        $arrPids = explode(',', $var);
        $arrPids = is_array($arrPids) ? $arrPids : array($arrPids);
        $objsMbqEtForumPost = array();
        /** @var MbqRdEtForumPost $oMbqRdEtForumPost */
        $oMbqRdEtForumPost = MbqMain::$oClk->newObj('MbqRdEtForumPost');
        foreach ($arrPids as $pid) {
            $objsMbqEtForumPost[] = $oMbqRdEtForumPost->initOMbqEtForumPost($pid, array('case' => 'byPostId'));
        }

        return $objsMbqEtForumPost;
    }

    protected function _getObjsMbqEtForumPostByTopic($var, $mbqOpt, Bridge $bridge)
    {

        $oMbqEtForumTopic = $var;
        /** @var \XF\Entity\Forum $forum */
        $forum = isset($oMbqEtForumTopic->oMbqEtForum) ? $oMbqEtForumTopic->oMbqEtForum->mbqBind : null;

        /** @var \XF\Entity\Thread $thread */
        $thread = $oMbqEtForumTopic->mbqBind;

        $threadId = $oMbqEtForumTopic->topicId->oriValue;
        if (!$threadId) {
            return [];
        }

        $posts = array();
        $oMbqDataPage = $mbqOpt['oMbqDataPage'];
        $visitor = $bridge::visitor();
        $threadModel = $bridge->getThreadRepo();

        $threadType = '';
        if (is_string($var) && preg_match('/^tpann_\d+$/', $var)) {
            $threadType = 'ann';
            $prefix_id = preg_split('/_/', $var);
            $threadId = (int)$prefix_id[1];
        }
        if (is_string($threadId) && preg_match('/^tpann_\d+$/', $threadId)) {
            $threadType = 'ann';
            $prefix_id = preg_split('/_/', $threadId);
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

        // get announcement
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

                $noticeLists = $noticeModel->getNoticeByIds($threadId);

                /** @var \XF\Entity\Notice $notice */
                foreach ($noticeLists as $notice) {
                    $noticeId = $notice->notice_id;

                    if (in_array($noticeId, $dismissedNoticeIds)) {
                        continue;
                    }

                    $matchesUser = $bridge->app()->criteria('XF:User', $notice['user_criteria'])->isMatched($visitor);
                    if (!$matchesUser) {
                        continue;
                    }

                    $notices[$noticeId] = array(
                        'title' => isset($notice['title']) ? $notice['title'] : '',
                        'message' => str_replace(array_keys($noticeTokens), $noticeTokens, $notice['message']),
                        'wrap' => isset($notice['wrap']) ? $notice['wrap'] : '',
                        'dismissible' => (isset($notice['dismissible']) && $notice['dismissible'] && $visitor->user_id)
                    );
                }

            }

            if (isset($notices[$threadId])) {
                $notice = $notices[$threadId];
                $post = array(
                    'post_id' => 'tpann_' . $threadId,
                    'post_title' => '', //not supported in XenForo
                    'message' => $notice['message'],
                    'post_author_name' => 'admin',
                    'user_type' => '',
                    'is_online' => false,
                    'can_edit' => '',
                    'icon_url' => '',
                    'post_time' => '',
                    'timestamp' => '',
                    'can_like' => false,
                    'is_liked' => false,
                    'like_count' => 0,
                    'can_upload' => false,
                    'allow_smilies' => true, // always true

                    'can_delete' => false,
                    'can_approve' => false,
                    'can_move' => false,
                    'is_approved' => true,
                    'is_deleted' => false,
                    'can_ban' => false,
                    'is_ban' => false,
                    'thread_type' => 'ann',
                    'post_type' => 'ann',
                );
                $posts[] = $post;
            }

        } //
        else {
            $start = $oMbqDataPage->startNum;
            $limit = $oMbqDataPage->numPerPage;
            $startPage = $oMbqDataPage->curPage;
            if ($startPage === null || $startPage == '') {
                $startPage = ($start / $limit ) +1 ;
            }
            $postRepo = $bridge->getPostRepo();

            if (!$thread || !($thread instanceof \XF\Entity\Thread)) {
                $thread = $threadModel->findThreadById($threadId);
            }

            $posts = [];
            if ($thread) {
                $posts = $postRepo->findPostsForThreadView($thread)->onPage($startPage, $limit)->fetch(); // $page, prePage
                if ($posts) {
                    $posts = $posts->toArray();
                }else{
                    $posts = [];
                }
                $threadModel->logThreadView($thread);
            }
        }

        $newMbqOpt = $mbqOpt;
        $newMbqOpt['case'] = 'byRow';
        $newMbqOpt['oMbqEtForum'] = $oMbqEtForumTopic->oMbqEtForum;
        $newMbqOpt['oMbqEtForumTopic'] = $oMbqEtForumTopic;
        $newMbqOpt['oMbqDataPage'] = $oMbqDataPage;
        $newMbqOpt['oMbqEtUser'] = true;
        $objsMbqEtForumPost = array();

        foreach ($posts as $id => &$post) {
            $objsMbqEtForumPost[] = $this->initOMbqEtForumPost($post, $newMbqOpt);
        }

        if (!isset($mbqOpt['oMbqDataPage'])) {

            return $objsMbqEtForumPost;
        }

        $oMbqDataPage = $mbqOpt['oMbqDataPage'];
        $oMbqDataPage->datas = $objsMbqEtForumPost;

        return $oMbqDataPage;
    }


    /**
     * init one forum post by condition
     *
     * @param  Mixed $var
     * @param  Array $mbqOpt
     * $mbqOpt['case'] = 'byObj' means init forum post by obj from viewtopic.php page
     * $mbqOpt['case'] = 'byPostId' means init forum post by post id
     * $mbqOpt['withAuthor'] = true means load post author,default is true
     * $mbqOpt['withAtt'] = true means load post attachments,default is true
     * $mbqOpt['withObjsNotInContentMbqEtAtt'] = true means load the attachement objs not in the content,default is true
     * $mbqOpt['oMbqEtForum'] = true means load oMbqEtForum property of this post,default is true
     * $mbqOpt['oMbqEtForumTopic'] = true means load oMbqEtForumTopic property of this post,default is true
     * $mbqOpt['objsMbqEtThank'] = true means load objsMbqEtThank property of this post,default is true
     * @return  MbqEtForumPost|null
     */
    public function initOMbqEtForumPost($var, $mbqOpt)
    {
        $return = null;
        $bridge = Bridge::getInstance();

        switch ($mbqOpt['case']) {
            case 'byPostId':
                $return = $this->_initOMbqEtForumPostByPostId($var, $mbqOpt, $bridge);
                break;
            case 'byRow':
                $return = $this->_initOMbqEtForumPostByRow($var, $mbqOpt, $bridge);
                break;
        }

        return $return;
    }

    /**
     * @param $var
     * @param $mbqOpt
     * @param Bridge $bridge
     * @return MbqEtForumPost|null
     */
    protected function _initOMbqEtForumPostByPostId($var, $mbqOpt, Bridge $bridge)
    {
        $id = $var;
        $threadType = '';
        if (is_string($var) && preg_match('/^tpann_\d+$/', $var)) {
            $threadType = 'ann';
            $prefix_id = preg_split('/_/', $var);
            $id = (int)$prefix_id[1];
        }
        if (is_array($var) && isset($var['thread_type']) && $var['thread_type']) {
            $threadType = $var['thread_type'];
        }

        $postRepo = $bridge->getPostRepo();

        $post = $postRepo->findPostById($id);

        if ($threadType == 'ann') {
            $positionInTopic = 1;  // dev
        } else {
            $positionInTopic = $post['position'] + 1;
        }

        /** @var MbqRdEtForumTopic $oMbqRdEtForumTopic */
        $oMbqRdEtForumTopic = MbqMain::$oClk->newObj('MbqRdEtForumTopic');

        $oMbqEtForumTopic = $oMbqRdEtForumTopic->initOMbqEtForumTopic($post['thread_id'], array('case' => 'byTopicId'));
        $mbqOpt['oMbqEtForum'] = $oMbqEtForumTopic->oMbqEtForum;
        $mbqOpt['oMbqEtForumTopic'] = $oMbqEtForumTopic;

        $newMbqOpt['case'] = 'byRow';
        $newMbqOpt['oMbqEtForum'] = $oMbqEtForumTopic->oMbqEtForum;
        $newMbqOpt['oMbqEtForumTopic'] = $oMbqEtForumTopic;
        $newMbqOpt['oMbqEtUser'] = true;
        $oMbqEtForumPost = $this->initOMbqEtForumPost($post, $newMbqOpt);
        if ($oMbqEtForumPost) {
            $oMbqEtForumPost->position->setOriValue($positionInTopic);
        }

        return $oMbqEtForumPost;
    }

    /**
     * @param $var
     * @param $mbqOpt
     * @param Bridge $bridge
     * @return MbqEtForumPost
     */
    protected function _initOMbqEtForumPostByRow($var, $mbqOpt, Bridge $bridge)
    {

        $visitor = $bridge::visitor();
        $userModel = $bridge->getUserRepo();

        /** @var \XF\Entity\Post|Array $post */
        $post = $var;
        if (!$post) {
            return null;
        }

        /** @var MbqEtForumPost $oMbqEtForumPost */
        $oMbqEtForumPost = MbqMain::$oClk->newObj('MbqEtForumPost');

        if (isset($mbqOpt['oMbqEtForumTopic']) && $mbqOpt['oMbqEtForumTopic']) {
            /** @var MbqRdEtForumTopic $oMbqRdEtForumTopic */
            $oMbqRdEtForumTopic = MbqMain::$oClk->newObj('MbqRdEtForumTopic');
            /** @var MbqRdEtForum $oMbqRdEtForum */
            $oMbqRdEtForum = MbqMain::$oClk->newObj('MbqRdEtForum');

            $thread = isset($post->Thread) ? $post->Thread : '';
            if ($thread && $thread instanceof \XF\Entity\Thread) {
                $forum = $thread->Forum;
                $oMbqEtForumPost->oMbqEtForumTopic = $oMbqRdEtForumTopic->initOMbqEtForumTopic($thread, array('case' => 'byRow'));
                $oMbqEtForumPost->oMbqEtForum = $oMbqRdEtForum->initOMbqEtForum($forum, array('case' => 'byRow'));
            }else{
                unset($thread);
                $oMbqEtForumPost->oMbqEtForumTopic = $oMbqRdEtForumTopic->initOMbqEtForumTopic($post['thread_id'], array('case' => 'byTopicId', 'oMbqEtForum' => true));
                $oMbqEtForumPost->oMbqEtForum = $oMbqEtForumPost->oMbqEtForumTopic->oMbqEtForum;
            }
        }
        if (isset($mbqOpt['oMbqEtUser']) && $mbqOpt['oMbqEtUser']) {
            /** @var MbqRdEtUser $oMbqRdEtUser */
            $oMbqRdEtUser = MbqMain::$oClk->newObj('MbqRdEtUser');
            $user = isset($post->User) ? $post->User : '';
            if ($user && $user instanceof \XF\Entity\User) {
                $oMbqEtForumPost->oAuthorMbqEtUser = $oMbqRdEtUser->initOMbqEtUser($user,  array('case' => 'user_row'));
            }else{
                $oMbqEtForumPost->oAuthorMbqEtUser = $oMbqRdEtUser->initOMbqEtUser($post['user_id'],  array('case' => 'byUserId'));
            }
        }

        $topicId = $oMbqEtForumPost->oMbqEtForumTopic->topicId->oriValue;
        /** @var \XF\Entity\Thread $thread */
        if (!$thread) $thread = $oMbqEtForumPost->oMbqEtForumTopic->mbqBind;

        $threadType = '';
        if (is_string($topicId) && preg_match('/^tpann_\d+$/', $topicId)) {
            $threadType = 'ann';
        }
        if (is_array($var) && isset($var['thread_type']) && $var['thread_type']) {
            $threadType = $var['thread_type'];
        }

        if ($threadType == 'ann') {
            $oMbqEtForumPost->postId->setOriValue($post['post_id']);
            $postContent = preg_replace('/\[quote="(.*?), post: (.*?), member: (.*?)"\](.*?)/si', '[quote uid=$3 name="$1" post=$2]$4', $post['message']);
            $postContent = preg_replace('/\[quote="(.*?), post: (.*?)"\](.*?)/si', '[quote name="$1" post=$2]$3', $postContent);
            $postContent = $bridge->cleanPost($postContent, array());
            $postContent = str_replace(['&lt','&gt;'],['<','>'],$postContent);
            $oMbqEtForumPost->postContent->setOriValue($postContent);
            $oMbqEtForumPost->postContent->setAppDisplayValue($postContent);
            $oMbqEtForumPost->postContent->setTmlDisplayValue($postContent);
            $oMbqEtForumPost->postContent->setTmlDisplayValueNoHtml($postContent);

            $oMbqEtForumPost->shortContent->setOriValue($bridge->renderPostPreview($post['message'], $oMbqEtForumPost->oMbqEtForumTopic->topicAuthorId->oriValue, 200));
            $oMbqEtForumPost->postAuthorId->setOriValue($oMbqEtForumPost->oMbqEtForumTopic->topicAuthorId->oriValue);
            $oMbqEtForumPost->postAuthorName->setOriValue($post['post_author_name']);

            $oMbqEtForumPost->mbqBind = $post;
            return $oMbqEtForumPost;
        }


        $forumId = $oMbqEtForumPost->oMbqEtForum->forumId->oriValue;

        if (($forumId && !$visitor->hasNodePermission($forumId, 'view')) || ($post && !$post->canView())) {
            return null;
        }

        $oMbqEtForumPost->postId->setOriValue($post['post_id']);
        $oMbqEtForumPost->forumId->setOriValue($forumId);
        $oMbqEtForumPost->topicId->setOriValue($topicId);
        $oMbqEtForumPost->postTitle->setOriValue('');

        // attachments
        $attachmentRepo = $bridge->getAttachmentRepo();
        $attachmentRepo->addAttachmentsToContent([$post->post_id => $post], 'post');

        $postAttachments = '';
        if (isset($post->Attachments) && $post->Attachments) {
            $postAttachments = $post->Attachments;
//            /** @var \XF\Entity\Attachment $attachmentPost */
//            foreach ($post->Attachments as $attachmentPost) {
//                /** @var \XF\Entity\AttachmentData $attachmentData */
//                $attachmentData = $attachmentPost->Data;
//            }
        }

        $defaultOptions = array(
            'states' => array(
                'viewAttachments' => (isset($thread) && $thread && is_object($thread)) ? $thread->canViewAttachments() : false,
                'returnHtml' => isset($mbqOpt['returnHtml'])? $mbqOpt['returnHtml'] : true
            )
        );

        if(isset($postAttachments) && !empty($postAttachments)){
            $defaultOptions['states']['attachments'] = $postAttachments->toArray();
        }

        if (stripos($post['message'], '[/attach]') !== false) {
            $inlineAttachArr = [];
            if (preg_match_all('#\[attach(=[^\]]*)?\](?P<id>\d+)\[/attach\]#i', $post['message'], $matches)) {
                foreach ($matches['id'] AS $attachId) {
                    $inlineAttachArr[] = $attachId;
                }
            }elseif (preg_match_all("#\[ATTACH (.*)alt=\"([0-9]+)\"(.*)\](.*?)\[\/ATTACH\]#i", $post['message'], $matches)) {
                if (isset($matches[2])) {
                    foreach ($matches[2] as $attachId) {
                        $inlineAttachArr[] = $attachId;
                    }
                }
            }
            elseif (preg_match_all('#\[ATTACH (type=[^\]]*)?\](?P<id>\d+)\[/ATTACH\]#i', $post['message'], $matches)) {
                foreach ($matches['id'] AS $attachId) {
                    $inlineAttachArr[] = $attachId;
                }
            }
            if ($inlineAttachArr) {
                foreach ($inlineAttachArr as $attachId) {
                    $attachment = isset($postAttachments[$attachId]) ? $postAttachments[$attachId] : null;
                    if ($attachment != null) {
                        /** @var MbqRdEtAtt $oMbqRdAtt */
                        $oMbqRdAtt = MbqMain::$oClk->newObj('MbqRdEtAtt');
                        $oMbqEtAtt = $oMbqRdAtt->initOMbqEtAtt($attachment, array('case' => 'byRow'));

                        $oMbqEtAtt->postId->setOriValue($oMbqEtForumPost->postId->oriValue);
                        $oMbqEtAtt->forumId->setOriValue($oMbqEtForumPost->forumId->oriValue);
                        $oMbqEtForumPost->objsMbqEtAtt[] = $oMbqEtAtt;

                        unset($postAttachments[$attachId]);
                    }
                }
            }
        }
        if (isset($postAttachments) && count($postAttachments) > 0) {
            foreach ($postAttachments as $attachment) {
                /** @var MbqRdEtAtt $oMbqRdAtt */
                $oMbqRdAtt = MbqMain::$oClk->newObj('MbqRdEtAtt');
                $oMbqEtAtt = $oMbqRdAtt->initOMbqEtAtt($attachment, array('case' => 'byRow'));

                $oMbqEtAtt->postId->setOriValue($oMbqEtForumPost->postId->oriValue);
                $oMbqEtAtt->forumId->setOriValue($oMbqEtForumPost->forumId->oriValue);
                $oMbqEtForumPost->objsNotInContentMbqEtAtt[] = $oMbqEtAtt;
            }
        }

        $postContent = preg_replace('/\[quote="(.*?), post: (.*?), member: (.*?)"\](.*?)/si', '[quote uid=$3 name="$1" post=$2]$4', $post['message']);
        $postContent = preg_replace('/\[quote="(.*?), post: (.*?)"\](.*?)/si', '[quote name="$1" post=$2]$3', $postContent);
        $postContent = $bridge->cleanPost($postContent, $defaultOptions);
        if (MbqCM::checkIfUserIsIgnored($post['user_id'])) {
            $postContent = '[spoiler]' . $postContent . '[/spoiler]';
        }

        $oMbqEtForumPost->postContent->setOriValue($postContent);
        $oMbqEtForumPost->postContent->setAppDisplayValue($postContent);
        $oMbqEtForumPost->postContent->setTmlDisplayValue($postContent);
        $oMbqEtForumPost->postContent->setTmlDisplayValueNoHtml($postContent);

        $oMbqEtForumPost->shortContent->setOriValue($bridge->renderPostPreview($post['message'], $post['user_id'], 200));
        $oMbqEtForumPost->postAuthorId->setOriValue($post['user_id']);
        $oMbqEtForumPost->postAuthorName->oriValue = $post['username'];
        /** @var MbqRdEtUser $oMbqRdEtUser */
        $oMbqRdEtUser = MbqMain::$oClk->newObj('MbqRdEtUser');

        if ($mbqOpt['oMbqEtUser']) {
            if ($oAuthorMbqEtUser = $oMbqRdEtUser->initOMbqEtUser($oMbqEtForumPost->postAuthorId->oriValue, array('case' => 'byUserId'))) {
                if (is_a($oAuthorMbqEtUser, 'MbqEtUser')) {
                    $oMbqEtForumPost->oAuthorMbqEtUser = $oAuthorMbqEtUser;
                    $oMbqEtForumPost->isOnline->setOriValue($oMbqEtForumPost->oAuthorMbqEtUser->isOnline->oriValue);
                }
            }
        }

        $oMbqEtForumPost->postTime->setOriValue($post['post_date']);
        $oMbqEtForumPost->canEdit->setOriValue($post->canEdit());
        if (method_exists($post, 'canReact')) {
            $oMbqEtForumPost->canLike->setOriValue($post->canReact());
        }else{
            $oMbqEtForumPost->canLike->setOriValue($post->canLike());
        }
        if (method_exists($post, 'isReactedTo')) {
            $oMbqEtForumPost->isLiked->setOriValue($post->isReactedTo());
        }else{
            $oMbqEtForumPost->isLiked->setOriValue($post->isLiked());
        }
        $oMbqEtForumPost->likeCount->setOriValue(isset($post['reaction_score']) ? $post['reaction_score'] : $post['likes']);
        $oMbqEtForumPost->allowSmilies->setOriValue(true);
        $oMbqEtForumPost->canReport->setOriValue($post->canReport());
        $oMbqEtForumPost->canDelete->setOriValue($post->canDelete('soft'));
        $oMbqEtForumPost->canApprove->setOriValue($post->canApproveUnapprove());
        $oMbqEtForumPost->canMove->setOriValue($post->canMove());

        $oMbqEtForumPost->isApproved->setOriValue($post->message_state != 'moderated');
        $oMbqEtForumPost->isDeleted->setOriValue($post->message_state == 'deleted');

        $oMbqEtForumPost->canBan->setOriValue(false);
        if($visitor->hasAdminPermission('ban'))
        {
            if($post->canCleanSpam()){
                $oMbqEtForumPost->canBan->setOriValue(true);
            }
        }

        $oMbqEtForumPost->isBan->setOriValue(isset($post['is_banned']) && $post['is_banned']);

        /** @var \XF\Repository\LikedContent $likeRepo */
        $likeRepo = $bridge->getLikedContentRepo();
        $page = 1;
        $perPage = 500; // max
        $contentType = $post->getEntityContentType();
        $contentId = $post->getEntityId();
        $likes = $likeRepo->findContentLikes($contentType, $contentId)
            ->with('Liker')
            ->limitByPage($page, $perPage, 1)
            ->fetch();
        $likes = $likes->slice(0, $perPage);
        if (( isset($post['likes']) && $post['likes'] && isset($post['like_users']) && !empty($post['like_users'])
                || isset($post['reaction_score']) && $post['reaction_score'] && isset($post['reactions']) && !empty($post['reactions'])
            ) && $likes) {
//            $like_users = $post['like_users'];
//            if (!is_array($post['like_users'])) {
//                $like_users = unserialize($post['like_users']);
//            }

            /**
             * @var integer $index
             * @var \XF\Entity\LikedContent $item
             */
            foreach ($likes as $index => $item) {
                /** @var MbqEtLike $oMbqEtLike */
                $oMbqEtLike = MbqMain::$oClk->newObj('MbqEtLike');

                $userId = $item->like_user_id;
                $oMbqEtLike->key->setOriValue($oMbqEtForumPost->postId->oriValue);
                $oMbqEtLike->userId->setOriValue($userId);
                $oMbqEtLike->type->setOriValue('post');

                /** @var MbqEtUser $oLikeEtUser */
                if ($oLikeEtUser = $oMbqRdEtUser->initOMbqEtUser($userId, array('case' => 'byUserId'))) {
                    $oMbqEtLike->oMbqEtUser = $oLikeEtUser;
                }
                //$oMbqEtLike->mbqBind = $user;
                $oMbqEtForumPost->objsMbqEtLike[] = $oMbqEtLike;
            }
        }

        if (isset($post['last_edit_user_id']) && $post['last_edit_user_id']) {
            $editName = "";
            $editUser = $userModel->findUserById($post['last_edit_user_id']);
            if ($editUser) {
                $editName = $editUser['username'];
            }
            $oMbqEtForumPost->editedByUserId->setOriValue($post['last_edit_user_id']);
            $oMbqEtForumPost->editedByUsername->setOriValue($editName);
            $oMbqEtForumPost->editedByTime->setOriValue($post['last_edit_date']);
        }

        $positionInTopic = $post['position'] + 1;
        $oMbqEtForumPost->position->setOriValue($positionInTopic);

        $oMbqEtForumPost->mbqBind = $post;

        return $oMbqEtForumPost;
    }

    /**
     * return raw post content
     *
     * @param MbqEtForumPost $oMbqEtForumPost
     * @return string
     */
    public function getRawPostContent($oMbqEtForumPost)
    {
        return $oMbqEtForumPost->mbqBind['message'];
    }

    /**
     * return raw post content
     *
     * @param MbqEtForumPost $oMbqEtForumPost
     * @return string
     */
    public function getRawPostContentOriginal($oMbqEtForumPost)
    {
        return $oMbqEtForumPost->mbqBind['message'];
    }

    /**
     * return raw post content
     *
     * @param MbqEtForumPost $oMbqEtForumPost
     * @return string
     */
    public function getQuotePostContent($oMbqEtForumPost)
    {
        $bridge = Bridge::getInstance();
        /** @var \XF\Entity\Post $post */
        $post = $oMbqEtForumPost->mbqBind;
        $postId = $oMbqEtForumPost->postId->oriValue;
        $postRepo = $bridge->getPostRepo();

        if (!$post || !($post instanceof \XF\Entity\Post)) {
            $post = $postRepo->findPostById($postId);
        }
        if (!$post) {
            return '';
        }
        $quote = $postRepo->getQuoteTextForPost($post);

        return $quote;
    }

    public function getUrl($oMbqEtForumPost)
    {
        /** @var MbqRdEtForumTopic $oMbqRdEtForumTopic */
        $oMbqRdEtForumTopic = MbqMain::$oClk->newObj('MbqRdEtForumTopic');
        $oMbqEtForumTopic = $oMbqRdEtForumTopic->initOMbqEtForumTopic($oMbqEtForumPost->topicId->oriValue, array('case' => 'byTopicId'));
        return \XF\Legacy\Link::buildPublicLink('full:threads', array('thread_id' => $oMbqEtForumPost->topicId->oriValue, 'title' => $oMbqEtForumTopic->mbqBind['title'])) . '#post-' . $oMbqEtForumPost->postId->oriValue;
    }
}