<?php

use Tapatalk\Bridge;

defined('MBQ_IN_IT') or exit;

MbqMain::$oClk->includeClass('MbqBaseWrEtForumPost');

/**
 * forum post write class
 */
Class MbqWrEtForumPost extends MbqBaseWrEtForumPost
{

    public function __construct()
    {
    }

    /**
     * add/reply forum post
     *
     * @param MbqEtForumPost $oMbqEtForumPost
     * @return mixed
     */
    public function addMbqEtForumPost($oMbqEtForumPost)
    {

        $topic_id = $oMbqEtForumPost->topicId->oriValue;
        $message = $oMbqEtForumPost->postContent->oriValue;
        $groupId = '';
        if ($oMbqEtForumPost->groupId->hasSetOriValue()) {
            $groupId = $oMbqEtForumPost->groupId->oriValue;
        }
        $subject = $oMbqEtForumPost->postTitle->oriValue;
        $attachmentIdsArray = $oMbqEtForumPost->attachmentIdArray->oriValue;

        $bridge = Bridge::getInstance();
        $threadRepo = $bridge->getThreadRepo();

        $thread = null;
        $oMbqEtForumTopic = $oMbqEtForumPost->oMbqEtForumTopic;
        if ($oMbqEtForumTopic && isset($oMbqEtForumTopic->mbqBind)) {
            $thread = $oMbqEtForumTopic->mbqBind;
        }
        if (!$thread || !($thread instanceof \XF\Entity\Thread)) {
            /** @var \XF\Entity\Thread $thread */
            $thread = $threadRepo->findThreadById($topic_id);
        }

        if (!$thread->canReply($error)) {
            return $bridge->responseError($error);
        }

        $attachmentHash = '';
        if ($groupId) {
            $attachmentHash = $groupId;
        } else {

            if ($attachmentIdsArray && is_array($attachmentIdsArray)) {
                $attachmentRepo = $bridge->getAttachmentRepo();
                $attachments = $attachmentRepo->getAttachmentsByIds($attachmentIdsArray);
                if ($attachments) {
                    $attachments = $attachments->toArray();
                }else{
                    $attachments = [];
                }
                $attachmentHashArray = [];
                /** @var \XF\Entity\Attachment $attachment */
                foreach ($attachments as $attachment) {
                    if ($attachment->canView() && $attachment->temp_hash) {
                        $attachmentHashArray[] = $attachment->temp_hash;
                    }
                }
                if ($attachmentHashArray) {
                    $attachmentHash = implode(',', $attachmentHashArray);
                }
            }
        }
        $message = $bridge->newPostContent($message);

        $byoLinkPattern = "/\\[url=https:\\/\\/siteowners.tapatalk.com\\/byo\\/displayAndDownloadByoApp\\?rid=\\d+\\].*\\[\\/url\\]/mi";
        $byoSpamFreeBody = preg_replace($byoLinkPattern, '', $message);

        $replier = $this->_setupThreadReply($thread, $byoSpamFreeBody, $attachmentHash);
        $replier->checkForSpam();
        $replier->setMessage($message);

        if (!$replier->validate($errors)) {
            return $bridge->responseError($errors);
        }
        if ($error = $bridge->XFAssertNotFlooding('post')) {
            return $bridge->responseError($error);
        }

        $post = $replier->save();

        $this->_finalizeThreadReply($replier);

        $threadRepo->markThreadReadByVisitor($thread);

        $oTapatalkPush = new \TapatalkPush();
        $oTapatalkPush->processPush('AddReply', $post, $thread);

        $oMbqEtForumPost->state->setOriValue($thread->canView() ? 0 : 1);
        $oMbqEtForumPost->postId->setOriValue($post['post_id']);

        return $oMbqEtForumPost;
    }

    /**
     * @param \XF\Entity\Thread $thread
     * @param $message
     * @param string $attachmentHashs
     * @return \XF\Service\Thread\Replier
     */
    protected function _setupThreadReply(\XF\Entity\Thread $thread, $message, $attachmentHashs)
    {
        $bridge = Bridge::getInstance();
//        $message = $bridge->plugin('XF:Editor')->fromInput('message');

        /** @var \XF\Service\Thread\Replier $replier */
        $replier = $bridge->getThreadReplierService($thread);

        $replier->setMessage($message);

        if ($thread->Forum->canUploadAndManageAttachments()) {
            $replier->setAttachmentHash($attachmentHashs);
        }

        return $replier;
    }

    /**
     * @param \XF\Service\Thread\Replier $replier
     * @param array $setOptions
     */
    protected function _finalizeThreadReply(\XF\Service\Thread\Replier $replier, $setOptions = [])
    {
        $bridge = Bridge::getInstance();
        $visitor = $bridge::visitor();

        $replier->sendNotifications();
        $thread = $replier->getThread();

        if ($thread->canWatch()) {
            if (isset($setOptions['watch_thread'])) {
                $watch = $setOptions['watch_thread'];
                if ($watch) {
                    $threadWatchRepo = $bridge->getThreadWatchRepo();
                    $state = (isset($setOptions['watch_thread_email']) && $setOptions['watch_thread_email']) ? 'watch_email' : 'watch_no_email';
                    $threadWatchRepo->setWatchState($thread, $visitor, $state);
                }
            } else {
                // use user preferences
                $bridge->getThreadWatchRepo()->autoWatchThread($thread, $visitor, false);
            }
        }

        if ($thread->canLockUnlock() && isset($setOptions['discussion_open'])) {
            $thread->discussion_open = (bool)$setOptions['discussion_open'];
        }
        if ($thread->canStickUnstick() && isset($setOptions['sticky'])) {
            $thread->sticky = (bool)$setOptions['sticky'];
        }

        $thread->saveIfChanged($null, false);

        if ($visitor->user_id) {
            $readDate = $thread->getVisitorReadDate();
            if ($readDate && $readDate >= $thread->getPreviousValue('last_post_date')) {
                $post = $replier->getPost();
                $bridge->getThreadRepo()->markThreadReadByVisitor($thread, $post->post_date);
            }

            $thread->draft_reply->delete();
        }
    }

    /**
     * modify forum post
     *
     * @param MbqEtForumPost $oMbqEtForumPost
     * @param $mbqOpt
     * @return MbqEtForumPost
     */
    public function mdfMbqEtForumPost($oMbqEtForumPost, $mbqOpt)
    {
        $bridge = Bridge::getInstance();

        $data = array(
            'post_id' => $oMbqEtForumPost->postId->oriValue,
            'post_title' => $oMbqEtForumPost->postTitle->oriValue,
            'post_content' => $oMbqEtForumPost->postContent->oriValue,
            'attachment_id_array' => $oMbqEtForumPost->attachmentIdArray->oriValue,
            'group_id' => $oMbqEtForumPost->groupId->oriValue,
            'reason' => $mbqOpt['in']->reason,
        );

        $post = $oMbqEtForumPost->mbqBind;
        if (!$post || !($post instanceof \XF\Entity\Post)) {
            /** @var \XF\Entity\Post $post */
            $post = $bridge->getPostRepo()->findPostById($data['post_id']);
        }

        if (!$post->canEdit($error)) {
            if (!$error) {
                return $bridge->noPermissionToString();
            }
            return $bridge->errorToString($error);
        }

        $input = [];
        if ($data['post_title'] != '') {
            $input['title'] = $data['post_title'];
        }
        $message = $data['post_content'];
        if (isset($input['attachment_id_array']) && is_array($input['attachment_id_array'])) {
            $attachmentIdsArray = $input['attachment_id_array'];
            $attachmentRepo = $bridge->getAttachmentRepo();
            $attachments = $attachmentRepo->getAttachmentsByIds($attachmentIdsArray);
            if ($attachments) {
                $attachments = $attachments->toArray();
            }else{
                $attachments = [];
            }
            $attachmentHashArray = [];
            /** @var \XF\Entity\Attachment $attachment */
            foreach ($attachments as $attachment) {
                if ($attachment->canView() && $attachment->temp_hash) {
                    $attachmentHashArray[] = $attachment->temp_hash;
                }
            }
            if ($attachmentHashArray) {
                $input['attachment_hash'] = implode(',', $attachmentHashArray);
            }
        }
        $editor = $this->_setupPostEdit($post, $message, $input);
        $editor->checkForSpam();
        $thread = $post->Thread;

        if (!$editor->validate($errors)) {
            return $bridge->errorToString($errors);
        }

        $threadChanges = [];
        if ($post->isFirstPost() && $thread->canEdit()) {
            $threadEditor = $this->_setupFirstPostThreadEdit($thread, $input, $threadChanges);
            if (!$threadEditor->validate($errors)) {
                return $bridge->errorToString($errors);
            }
        } else {
            $threadEditor = null;
            $threadChanges = [];
        }

        $editor->save();

        if ($threadEditor)
        {
            $threadEditor->save();
        }

        $this->_finalizePostEdit($editor, $threadEditor);

        return $oMbqEtForumPost;
    }

    /**
     * @param \XF\Entity\Post $post
     * @param $message
     * @param array $options
     *
     * @return \XF\Service\Post\Editor
     */
    protected function _setupPostEdit(\XF\Entity\Post $post, $message, $options)
    {
        $bridge = Bridge::getInstance();
        // $message = $bridge->plugin('XF:Editor')->fromInput('message');

        /** @var \XF\Service\Post\Editor $editor */
        $editor = $bridge->getPostEditorService($post);

        if ($post->canEditSilently()) {
            $silentEdit = isset($options['silent']) ? $options['silent'] : false;
            if ($silentEdit) {
                $editor->logEdit(false);
                if (isset($options['clear_edit']) && $options['clear_edit']) {
                    $post->last_edit_date = 0;
                }
            }
        }
        $editor->setMessage($message);

        $forum = $post->Thread->Forum;
        if ($forum->canUploadAndManageAttachments()) {
            $editor->setAttachmentHash(isset($options['attachment_hash']) ? $options['attachment_hash'] : '');
        }

        if (isset($options['author_alert']) && $options['author_alert'] && $post->canSendModeratorActionAlert()) {
            $editor->setSendAlert(true, $options['author_alert_reason']);
        }

        return $editor;
    }

    protected function _finalizePostEdit(\XF\Service\Post\Editor $editor, \XF\Service\Thread\Editor $threadEditor = null)
    {

    }

    /**
     * @param \XF\Entity\Thread $thread
     * @param $options
     * @param array $threadChanges Returns a list of whether certain important thread fields are changed
     *
     * @return \XF\Service\Thread\Editor
     */
    protected function _setupFirstPostThreadEdit(\XF\Entity\Thread $thread, $options, &$threadChanges)
    {
        $bridge = Bridge::getInstance();
        /** @var \XF\Service\Thread\Editor $threadEditor */
        $threadEditor = $bridge->getThreadEditorService($thread);

        $prefixId = isset($options['prefix_id']) ? $options['prefix_id'] : '';
        if ($prefixId != $thread->prefix_id && !$thread->Forum->isPrefixUsable($prefixId))
        {
            $prefixId = 0; // not usable, just blank it out
        }
        $threadEditor->setPrefix($prefixId);

        if (isset($options['title']) && $options['title'] != '') {
            $threadEditor->setTitle(isset($options['title']) ? $options['title'] : '');
        }

        $customFields = isset($options['custom_fields']) ? $options['custom_fields'] : []; // array
        $threadEditor->setCustomFields($customFields);

        $threadChanges = [
            'title' => $thread->isChanged(['title', 'prefix_id']),
            'customFields' => $thread->isChanged('custom_fields')
        ];

        return $threadEditor;
    }

    /**
     * m_delete_post
     *
     * @param $oMbqEtForumPost
     * @param $mode
     * @param $reason
     * @return bool|mixed|string
     */
    public function mDeletePost($oMbqEtForumPost, $mode, $reason)
    {
        $bridge = Bridge::getInstance();
        $bridge->request()->set('reason', $reason);
        $reason = $bridge->request()->filter('reason', 'str');

        if (!is_a($oMbqEtForumPost, 'MbqEtForumPost')) {
            return 'Not find post';
        }

        /** @var MbqEtForumPost $oMbqEtForumPost */
        $postId = $oMbqEtForumPost->postId->oriValue;
        $post = $oMbqEtForumPost->mbqBind;
        if (!$post || !($post instanceof \XF\Entity\Post)) {
            $post = $bridge->getPostRepo()->findPostById($postId);
        }
        if (!$post) {
            return 'not find post';
        }

        $options = array(
            'deleteType' => ($mode == 2 ? 'hard' : 'soft'),
            'reason' => $reason,
        );
        $type = $options['deleteType'];

        if (!$post->canDelete($type, $error))
        {
            return $bridge->errorToString($error);
        }

        /** @var \XF\Entity\Thread $thread */
        // $thread = $post->Thread;

        /** @var \XF\Service\Post\Deleter $deleter */
        $deleter = $bridge->service('XF:Post\Deleter', $post);

        $deleter->delete($type, $reason);

        $bridge->plugin('XF:InlineMod')->clearIdFromCookie('post', $post->post_id);

        if ($deleter->wasThreadDeleted())
        {
            $bridge->plugin('XF:InlineMod')->clearIdFromCookie('thread', $post->thread_id);
        }

        return true;
    }

    /**
     * m_undelete_post
     *
     * @param $oMbqEtForumPost
     * @return bool|mixed|string
     */
    public function mUndeletePost($oMbqEtForumPost)
    {
        $bridge = Bridge::getInstance();

        if (!is_a($oMbqEtForumPost, 'MbqEtForumPost')) {
            return 'Not find post';
        }

        /** @var MbqEtForumPost $oMbqEtForumPost */
        $postId = $oMbqEtForumPost->postId->oriValue;
        $post = $oMbqEtForumPost->mbqBind;
        if (!$post || !($post instanceof \XF\Entity\Post)) {
            $post = $bridge->getPostRepo()->findPostById($postId);
        }
        if (!$post) {
            return 'not find post';
        }

        if (!$post->canUndelete($error))
        {
            return $bridge->noPermissionToString($error);
        }

        if ($post->message_state == 'deleted')
        {
            $post->message_state = 'visible';
            $post->save();
        }

        return true;
    }

    /**
     * m_move_post
     *
     * @param array $oMbqEtForumPosts
     * @param MbqEtForum $oMbqEtForum
     * @param MbqEtForumTopic $oMbqEtForumTopic
     * @param string $topicTitle
     * @return bool|mixed|string
     */
    public function mMovePost($oMbqEtForumPosts, $oMbqEtForum, $oMbqEtForumTopic, $topicTitle)
    {
        $bridge = Bridge::getInstance();
        $request = $bridge->request();

        $request->set('type', 'post');
        $request->set('action', 'move');
        $request->set('confirmed', '1');
        $nodeModel = $bridge->getNodeRepo();
        $threadRepo = $bridge->getThreadRepo();
        //
        $type = $request->filter('type', 'str');
        $handler = $this->_getInlineModHandler($type);
        if (!$handler)
        {
            return $bridge->noPermissionToString();
        }

        $action = $request->filter('action', 'str');
        $actionHandler = $handler->getAction($action);
        if (!$actionHandler)
        {
            return $bridge->noPermissionToString();
        }

        $postIds = [];
        foreach ($oMbqEtForumPosts as $oMbqEtForumPost) {
            $postIds[] = $oMbqEtForumPost->postId->oriValue;
        }

        $ids = $postIds;
        $ids = array_unique($ids);
        if (!$ids) {
            return 'Not find posts';
        }
        $request->set('ids', $ids);

        $entities = $handler->getEntities($ids);

        if (!$entities->count())
        {
            return 'Not find posts';
        }

        if (isset($oMbqEtForum)) {

            $viewableNodes = $nodeModel->getNodeList()->filterViewable();
            if (!isset($viewableNodes[$oMbqEtForum->forumId->oriValue])) {
                return TT_GetPhraseString('requested_forum_not_found');
            }

            $request->set('thread_type', 'new');
            $request->set('title', $topicTitle);
            $request->set('node_id', $oMbqEtForum->forumId->oriValue);

        } else if (isset($oMbqEtForumTopic)) {
            $request->set('thread_type', 'existing');

            $threadId = $oMbqEtForumTopic->topicId->oriValue;
            $thread = $oMbqEtForumTopic->mbqBind;
            if (!$thread || !($thread instanceof \XF\Entity\Thread)) {
                $thread = $threadRepo->findThreadById($threadId);
            }
            if (!$thread) {
                return 'not find thread';
            }
            $threadUrl = $bridge->app()->router('public')->buildLink('threads', $thread);

            $request->set('existing_url', $threadUrl);
        }

        $options = $actionHandler->getFormOptions($entities, $request);


        if (!$actionHandler->canApply($entities, $options, $error))
        {
            return $bridge->noPermissionToString($error);
        }

        // either we're confirmed or we don't have a form to render
        $actionHandler->apply($entities, $options);
        $newUrl = $actionHandler->getReturnUrl();

        if ($newUrl) {
            return true;
        }

        return false;
    }

    /**
     * m_merge_post
     *
     * @param array $objsMbqEtForumPost
     * @param MbqEtForumPost $oMbqEtForumPost
     * @return bool|string
     */
    public function mMergePost($objsMbqEtForumPost, $oMbqEtForumPost)
    {

        $bridge = Bridge::getInstance();
        $request = $bridge->request();

        $request->set('type', 'post');
        $request->set('action', 'merge');
        $request->set('confirmed', '1');
        //
        $type = $request->filter('type', 'str');
        $handler = $this->_getInlineModHandler($type);
        if (!$handler)
        {
            return $bridge->noPermissionToString();
        }

        $action = $request->filter('action', 'str');
        $actionHandler = $handler->getAction($action);
        if (!$actionHandler)
        {
            return $bridge->noPermissionToString();
        }

        $postIds = [];
        foreach ($objsMbqEtForumPost as $oMbqEtForumPost) {
            $postIds[] = $oMbqEtForumPost->postId->oriValue;
        }
        $targetPostId = $oMbqEtForumPost->postId->oriValue;
        $request->set('target_post_id', $targetPostId);

        $ids = $postIds;
        $ids = array_unique($ids);
        if (!$ids) {
            return 'Not find posts';
        }
        $ids[] = $targetPostId;
        $ids = array_unique($ids);
        $request->set('ids', $ids);

        $entities = $handler->getEntities($ids);

        if (!$entities->count())
        {
            return 'Not find posts';
        }

        $options = $actionHandler->getFormOptions($entities, $request);


        if (!$actionHandler->canApply($entities, $options, $error))
        {
            return $bridge->noPermissionToString($error);
        }

        // either we're confirmed or we don't have a form to render
        $actionHandler->apply($entities, $options);
        $newUrl = $actionHandler->getReturnUrl();

        if ($newUrl) {
            return true;
        }

        return false;
    }

    /**
     * @param string $type
     *
     * @return null|\XF\InlineMod\AbstractHandler
     */
    protected function _getInlineModHandler($type)
    {
        $bridge = Bridge::getInstance();
        /** @var \XF\ControllerPlugin\InlineMod $pluginInlineMod */
        $pluginInlineMod = $bridge->plugin('XF:InlineMod');

        return $pluginInlineMod->getInlineModHandler($type);
    }

    /**
     * @param MbqEtForumPost $oMbqEtForumPost
     * @param string $reason
     * @return bool
     */
    public function reportPost($oMbqEtForumPost, $reason)
    {
        $bridge = Bridge::getInstance();

        /** @var \XF\Entity\Post $post */
        $post = $oMbqEtForumPost->mbqBind;
        if (!$post || ($post instanceof \XF\Entity\Post)) {
            $post = $bridge->getPostRepo()->findPostById($oMbqEtForumPost->postId->oriValue);
        }
        if (!$post) {
            return false;
        }
        if (!$post->canReport($error)) {
            return $bridge->errorToString($error);
        }
        if (!$reason) {
            return $bridge::XFPhrase('please_enter_reason_for_reporting_this_message')->render();
        }

        $creator = $this->_setupReportCreate('post', $post, $reason);
        if (!$creator->validate($errors)) {
            return $bridge->errorToString($errors);
        }
        if ($error = $bridge->XFAssertNotFlooding('report')) {
            return $bridge->errorToString($error);
        }

        $creator->save();
        $creator->sendNotifications();

        return true;
    }

    /**
     * @param $contentType
     * @param $content
     * @param $message
     * @return \XF\Service\Report\Creator
     */
    protected function _setupReportCreate($contentType, $content, $message)
    {
        $bridge = Bridge::getInstance();
        /** @var \XF\Service\Report\Creator $creator */
        $creator = $bridge->getReportCreatorService($contentType, $content);
        $creator->setMessage($message);

        return $creator;
    }

    /**
     * m_approve_post
     *
     * @param MbqEtForumPost $oMbqEtForumPost
     * @param $mode
     * @return bool|mixed|string
     */
    public function mApprovePost($oMbqEtForumPost, $mode)
    {
        $bridge = Bridge::getInstance();
        $request = $bridge->request();

        $request->set('type', 'post');
        if ($mode == 1) {
            $request->set('action', 'approve');
        }else{
            $request->set('action', 'unapprove');
        }
        $request->set('confirmed', '0');
        //
        $type = $request->filter('type', 'str');
        $handler = $this->_getInlineModHandler($type);
        if (!$handler) {
            return $bridge->noPermissionToString();
        }
        $action = $request->filter('action', 'str');
        $actionHandler = $handler->getAction($action);
        if (!$actionHandler) {
            return $bridge->noPermissionToString();
        }

        $postId = $oMbqEtForumPost->postId->oriValue;
        $postIds = array_unique(array_map('intval', explode(',', $postId)));

        $ids = $postIds;
        $ids = array_unique($ids);
        $entities = $handler->getEntities($ids);
        if (!$entities->count()) {
            return 'Not find posts';
        }
        $request->set('ids', $ids);

        $options = [];
        if (!$actionHandler->canApply($entities, $options, $error)) {
            return $bridge->noPermissionToString($error);
        }

        // either we're confirmed or we don't have a form to render
        $actionHandler->apply($entities, $options);

        return true;
    }

    /**
     * m_close_report
     */
    public function mCloseReport($oMbqEtForumPost)
    {
        $bridge = Bridge::getInstance();
        $reportModel = $bridge->getReportRepo();
        $visitor = $bridge::visitor();
        $postId = $oMbqEtForumPost->postId->oriValue;

        $report = $reportModel->getReportById($postId);
        if (!$report || $report->content_type != 'post') {
            return TT_GetPhraseString('requested_report_not_found');
        }
        if (!$report->canView()) {
            return $bridge->noPermissionToString();
        }
        /** @var \XF\Service\Report\Commenter $commenter */
        $commenter = $bridge->service('XF:Report\Commenter', $report);
        $newState = 'resolved';
        $commenter->setReportState($newState, $visitor);

        if (!$commenter->validate($errors))
        {
            return $bridge->errorToString($errors);
        }
        $comment = $commenter->save();

        $commenter->sendNotifications();
        $report = $commenter->getReport();
        $report->draft_comment->delete();
        $bridge->session()->reportLastRead = \XF::$time;

        return true;
    }

    /**
     * @param MbqEtForumPost $oMbqEtForumPost
     * @return bool
     */
    public function likePost($oMbqEtForumPost)
    {
        $bridge = Bridge::getInstance();
        $visitor = $bridge::visitor();

        $postId = $oMbqEtForumPost->postId->oriValue;
        $post = $oMbqEtForumPost->mbqBind;

        if (!$post || !($post instanceof \XF\Entity\Post)) {
            $postRepo = $bridge->getPostRepo();
            /** @var \XF\Entity\Post $post */
            $post = $postRepo->findPostById($postId);
        }
        if (method_exists($post, 'isReactedTo')) {
            $likeStatus = $post->isReactedTo();
        }else{
            $likeStatus = $post->isLiked();
        }
        if ($likeStatus) {
            return true; // It's a mobile app - let's do the sensible thing and stay silent here.
        }
        $contentType = $post->getEntityContentType();
        $contentId = $post->getEntityId();
        if (class_exists('\XF\ControllerPlugin\Reaction')) {
            $reactionRepo = $bridge->getReactionRepo();
            try {
                /** @var \XF\Entity\ReactionContent $reaction */
                $reaction = $reactionRepo->insertReaction(1, 'post', $contentId, $visitor, true, false);
                return true;
            }catch (\Exception $e) {
                MbqError::alert('', $e->getMessage(), '', MBQ_ERR_APP);
                return false;
            }
        }else {
            $likeRepo = $bridge->getLikedContentRepo();

            try {
                $like = $likeRepo->toggleLike($contentType, $contentId, $visitor); // toggleLike
            } catch (Exception $e) {
                return false;
            }
            return true;
        }
    }

    /**
     * @param MbqEtForumPost $oMbqEtForumPost
     * @return bool
     */
    public function unlikePost($oMbqEtForumPost)
    {
        $bridge = Bridge::getInstance();
        $visitor = $bridge::visitor();

        $postId = $oMbqEtForumPost->postId->oriValue;
        $post = $oMbqEtForumPost->mbqBind;
        if (!$post || !($post instanceof \XF\Entity\Post)) {
            $postRepo = $bridge->getPostRepo();
            /** @var \XF\Entity\Post $post */
            $post = $postRepo->findPostById($postId);
        }

        if (method_exists($post, 'isReactedTo')) {
            $likeStatus = $post->isReactedTo();
        }else{
            $likeStatus = $post->isLiked();
        }
        if (!$likeStatus) {
            return true; // It's a mobile app - let's do the sensible thing and stay silent here.
        }

        if (class_exists('\XF\ControllerPlugin\Reaction')) {
            $reactionRepo = $bridge->getReactionRepo();
            try {

                $contentId = $post->getEntityId();
                $existingReaction = $reactionRepo->getReactionByContentAndReactionUser('post', $contentId, $visitor->user_id);
                if ($existingReaction && $existingReaction->reaction_id)
                {
                    $existingReaction->setOption('is_like_only', false);
                    $existingReaction->delete();
                    return true;
                }else{
                    return false;
                }
            }catch (\Exception $e) {
                MbqError::alert('', $e->getMessage(), '', MBQ_ERR_APP);
                return false;
            }
        }else {
            $likeRepo = $bridge->getLikedContentRepo();
            $likeRepo->toggleLike($post->getEntityContentType(), $post->getEntityId(), $visitor);
            // $like = $likeRepo->getLikeByContentAndLiker($post->getEntityContentType(), $post->getEntityId(), $visitor->user_id);
            // $like->delete();
        }

        return true;
    }
}