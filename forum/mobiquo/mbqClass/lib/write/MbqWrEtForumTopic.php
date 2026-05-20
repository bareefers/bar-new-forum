<?php
use Tapatalk\Bridge;

defined('MBQ_IN_IT') or exit;

MbqMain::$oClk->includeClass('MbqBaseWrEtForumTopic');

/**
 * forum topic write class
 */
Class MbqWrEtForumTopic extends MbqBaseWrEtForumTopic
{

    public function __construct()
    {
    }

    /**
     * add forum topic view num
     */
    public function addForumTopicViewNum($oMbqEtForumTopic)
    {

    }

    /**
     * @param \XF\Entity\Forum $forum
     * @param $title
     * @param $message
     * @param array $option
     * @return \XF\Service\Thread\Creator
     */
    protected function _setupThreadCreate(\XF\Entity\Forum $forum, $title, $message, $option = [])
    {
        $bridge = Bridge::getInstance();

        // $title = $this->filter('title', 'str');
        // $message = $this->plugin('XF:Editor')->fromInput('message');

        /** @var \XF\Service\Thread\Creator $creator */
        $creator = $bridge->getThreadCreatorService($forum);

        $creator->setContent($title, $message);

        $prefixId = isset($option['prefix_id']) ? $option['prefix_id'] : '';
        if ($prefixId && $forum->isPrefixUsable($prefixId))
        {
            $creator->setPrefix($prefixId);
        }

        if ($forum->canEditTags())
        {
            $creator->setTags(isset($option['tags']) ? $option['tags'] : ''); // tags string
        }

        if ($forum->canUploadAndManageAttachments())
        {
            $creator->setAttachmentHash(isset($option['attachment_hash']) ? $option['attachment_hash'] : ''); // hashs string
        }

        $setOptions = [];
        if (isset($option['discussion_open'])) $setOptions['discussion_open'] = $option['discussion_open'];
        if (isset($option['sticky'])) $setOptions['sticky'] = $option['sticky'];

        if ($setOptions)
        {
            $thread = $creator->getThread();

            if (isset($setOptions['discussion_open']) && $thread->canLockUnlock())
            {
                $creator->setDiscussionOpen((bool)$setOptions['discussion_open']);
            }
            if (isset($setOptions['sticky']) && $thread->canStickUnstick())
            {
                $creator->setSticky((bool)$setOptions['sticky']);
            }
        }

        $customFields = [];
        if (isset($option['custom_fields']) && is_array($option['custom_fields'])) {
            $customFields = $option['custom_fields'];
        }
        $creator->setCustomFields($customFields);

        // $pollCreator

        return $creator;
    }

    protected function _finalizeThreadCreate(\XF\Service\Thread\Creator $creator)
    {
        $bridge = Bridge::getInstance();
        $creator->sendNotifications();

        $forum = $creator->getForum();
        $thread = $creator->getThread();
        $visitor = $bridge::visitor();

        $setOptions['watch_thread'] = 1; // default watch your self topic
        $setOptions['watch_thread_email'] = 1; // default watch your self topic and notification email
        if ($thread->canWatch())
        {
            if (isset($setOptions['watch_thread']))
            {
                $watch = $setOptions['watch_thread'];
                if ($watch)
                {
                    $threadWatchRepo = $bridge->getThreadWatchRepo();

                    $state = $setOptions['watch_thread_email'] ? 'watch_email' : 'watch_no_email';
                    $threadWatchRepo->setWatchState($thread, $visitor, $state);
                }
            }
            else
            {
                // use user preferences
                $bridge->getThreadWatchRepo()->autoWatchThread($thread, $visitor, true);
            }
        }

        $bridge->getThreadRepo()->markThreadReadByVisitor($thread, $thread->post_date);

        $forum->draft_thread->delete();
    }

    /**
     * add forum topic
     *
     * @param MbqEtForumTopic $oMbqEtForumTopic
     * @return mixed
     */
    public function addMbqEtForumTopic($oMbqEtForumTopic)
    {
        $bridge = Bridge::getInstance();

        /** @var MbqEtForum $oMbqEtForum */
        $oMbqEtForum = $oMbqEtForumTopic->oMbqEtForum;
        /** @var \XF\Entity\Forum $forum */
        $forum = $oMbqEtForum->mbqBind;
        if (!$forum || !($forum instanceof \XF\Entity\Forum)) {
            if ($oMbqEtForum) {
                $forumId = $oMbqEtForum->forumId->oriValue;
                $forum = $bridge->getForumRepo()->findForumById($forumId);
            }
            if (!$forum || !($forum instanceof \XF\Entity\Forum)) {
                return 'not find forum';
            }
        }

        if (!$forum->canCreateThread($error)) {
            return $bridge->noPermissionToString($error);
        }

        $prefixId = $oMbqEtForumTopic->prefixId->oriValue;
        if (!$forum->isPrefixUsable($prefixId)) {
            $oMbqEtForumTopic->prefixId->setOriValue(0); // not usable, just blank it out
        }
        $option = [];
        $option['prefix_id'] = $prefixId;
        $title = $oMbqEtForumTopic->topicTitle->oriValue;
        $message = $oMbqEtForumTopic->topicContent->oriValue;

        $groupId = '';
        if ($oMbqEtForumTopic->groupId->hasSetOriValue()) {
            $groupId = $oMbqEtForumTopic->groupId->oriValue;
        }

        $attachmentHash = '';
        if ($groupId) {
            $option['attachment_hash'] = $groupId;
        } else {

            if ($oMbqEtForumTopic->attachmentIdArray->hasSetOriValue()) {
                $attachmentIdsArray = $oMbqEtForumTopic->attachmentIdArray->oriValue;
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
                if ($attachmentHash) {
                    $option['attachment_hash'] = $attachmentHash;
                }
            }
        }
        $message = $bridge->newPostContent($message);
        
        $byoLinkPattern = "/\\[url=https:\\/\\/siteowners.tapatalk.com\\/byo\\/displayAndDownloadByoApp\\?rid=\\d+\\].*\\[\\/url\\]/mi";
        $byoSpamFreeBody = preg_replace($byoLinkPattern, '', $message);

        $creator = $this->_setupThreadCreate($forum, $title, $byoSpamFreeBody, $option);
        $creator->checkForSpam();
        $creator->setContent($title, $message);

        if (!$creator->validate($errors)) {
            return $bridge->errorToString($errors);
        }
        if ($error = $bridge->XFAssertNotFlooding('post')){
            return $bridge->errorToString($errors);
        }

        /** @var \XF\Entity\Thread $thread */
        $thread = $creator->save();
        $this->_finalizeThreadCreate($creator);

        $oMbqEtForumTopic->state->setOriValue($thread->canView() ? 0 : 1);
        $oMbqEtForumTopic->topicId->setOriValue($thread['thread_id']);

        return $oMbqEtForumTopic;
    }

    /**
     * mark forum topic read
     *
     * @param MbqEtForumTopic $oMbqEtForumTopic
     * @return bool
     */
    public function markForumTopicRead($oMbqEtForumTopic)
    {
        $topicId = $oMbqEtForumTopic->topicId->oriValue;
        if (preg_match('/^tpann_\d+$/', $topicId)) {
            return true;
        }

        $thread = $oMbqEtForumTopic->mbqBind;
        // $forum = $oMbqEtForumTopic->oMbqEtForum->mbqBind;

        if (!$thread || !($thread instanceof \XF\Entity\Thread)) {
            return false;
        }
        $bridge = Bridge::getInstance();
        $threadModel = $bridge->getThreadRepo();

        $result = $threadModel->markThreadReadByVisitor($thread, \XF::$time);

        return $result;
    }

    /**
     * @param MbqEtForumTopic $oMbqEtForumTopic
     * @param $receiveEmail
     * @return bool
     */
    public function subscribeTopic($oMbqEtForumTopic, $receiveEmail)
    {
        $bridge = Bridge::getInstance();
        $visitor = $bridge::visitor();

        /** @var \XF\Entity\Thread $thread */
        $thread = $this->_setupSubscribeAndUnTopic($oMbqEtForumTopic, $visitor);
        if (!$thread || !($thread instanceof \XF\Entity\Thread)) {
            return $thread;
        }

        $mode = 'watch_no_email';
        if ($receiveEmail) {
            $mode = 'watch_email';
        }
        $bridge->getThreadWatchRepo()->setWatchState($thread, $visitor, $mode);

        return true;
    }

    /**
     * @param MbqEtForumTopic $oMbqEtForumTopic
     * @return bool
     */
    public function unsubscribeTopic($oMbqEtForumTopic)
    {
        $topicId = $oMbqEtForumTopic->topicId->oriValue;
        $bridge = Bridge::getInstance();
        $visitor = $bridge::visitor();
        $threadWatchRepo = $bridge->getThreadWatchRepo();

        if ($topicId != 'ALL') {
            /** @var \XF\Entity\Thread $thread */
            $thread = $this->_setupSubscribeAndUnTopic($oMbqEtForumTopic, $visitor);
            if (!$thread || !($thread instanceof \XF\Entity\Thread)) {
                return $thread;
            }

            $threadWatchRepo->setWatchState($thread, $visitor, '');
        } else {

            $state = ''; // delete
            if ($threadWatchRepo->isValidWatchState($state)) {
                $threadWatchRepo->setWatchStateForAll($visitor, $state);
            }
        }
        return true;
    }

    /**
     * @param MbqEtForumTopic $oMbqEtForumTopic
     * @param $user
     * @return bool|string|\XF\Entity\Thread
     */
    protected function _setupSubscribeAndUnTopic($oMbqEtForumTopic, $user)
    {
        if (!$user) {
            return false;
        }
        $bridge = Bridge::getInstance();
        $topicId = $oMbqEtForumTopic->topicId->oriValue;

        /** @var \XF\Entity\Thread $thread */
        $thread = $oMbqEtForumTopic->mbqBind;
        $threadRepo = $bridge->getThreadRepo();

        if (!$thread || !($thread instanceof \XF\Entity\Thread)) {
            $thread = $threadRepo->findThreadById($topicId);
        }
        if (!$thread || !($thread instanceof \XF\Entity\Thread)) {
            return false;
        }
        if (!$thread->canWatch($error)) {
            if ($error) {
                return $bridge->errorToString($error);
            }
            return $bridge->noPermissionToString();
        }

        return $thread;
    }

    /**
     * reset forum topic subscription
     */
    public function resetForumTopicSubscription($oMbqEtForumTopic)
    {

    }

    /**
     * m_stick_topic
     *
     * @param MbqEtForumTopic $oMbqEtForumTopic
     * @param $mode
     * @return bool|mixed|string
     */
    public function mStickTopic($oMbqEtForumTopic, $mode)
    {
        $bridge = Bridge::getInstance();

        if (!is_a($oMbqEtForumTopic, 'MbqEtForumTopic')) {
            return 'Not find topic';
        }
        $threadId = $oMbqEtForumTopic->topicId->oriValue;
        $thread = $oMbqEtForumTopic->mbqBind;
        if (!$thread || !($thread instanceof \XF\Entity\Thread)) {
            $thread = $bridge->getThreadRepo()->findThreadById($threadId);
        }
        if (!$thread) {
            return 'Not find topic';
        }
        if (!$thread->canStickUnstick($error)) {
            return $bridge->errorToString($error);
        }

        /** @var \XF\Service\Thread\Editor $editor */
        $editor = $bridge->service('XF:Thread\Editor', $thread);

        if ($mode == 1) {
            $editor->setSticky(true);
        } else {
            $editor->setSticky(false);
        }

        if (!$editor->validate($errors)) {
            return $bridge->errorToString($errors);
        }

        $editor->save();
        return true;
    }

    /**
     * m_close_topic
     *
     * $mode : "1" = reopen a topic. "2" = close a topic.
     *
     * @param $oMbqEtForumTopic
     * @param int $mode
     * @return bool|string
     */
    public function mCloseTopic($oMbqEtForumTopic, $mode)
    {
        $bridge = Bridge::getInstance();

        if (!is_a($oMbqEtForumTopic, 'MbqEtForumTopic')) {
            return 'Not find topic';
        }
        $threadId = $oMbqEtForumTopic->topicId->oriValue;
        $thread = $oMbqEtForumTopic->mbqBind;
        if (!$thread || !($thread instanceof \XF\Entity\Thread)) {
            $thread = $bridge->getThreadRepo()->findThreadById($threadId);
        }
        if (!$thread) {
            return 'Not find topic';
        }
        if (!$thread->canLockUnlock($error)) {
            return $bridge->errorToString($error);
        }

        /** @var \XF\Service\Thread\Editor $editor */
        $editor = $bridge->service('XF:Thread\Editor', $thread);

        if ($mode == 2) {
            $editor->setDiscussionOpen(false);
        } else {
            $editor->setDiscussionOpen(true);
        }

        if (!$editor->validate($errors)) {
            return $bridge->errorToString($errors);
        }
        $editor->save();

        return true;
    }

    /**
     * m_delete_topic
     *
     * @param $oMbqEtForumTopic
     * @param $mode
     * @param $reason
     * @return bool|mixed|string
     */
    public function mDeleteTopic($oMbqEtForumTopic, $mode, $reason)
    {
        $bridge = Bridge::getInstance();
        $bridge->request()->set('reason', $reason);

        if (!is_a($oMbqEtForumTopic, 'MbqEtForumTopic')) {
            return 'Not find topic';
        }
        $threadId = $oMbqEtForumTopic->topicId->oriValue;
        $thread = $oMbqEtForumTopic->mbqBind;
        if (!$thread || !($thread instanceof \XF\Entity\Thread)) {
            $thread = $bridge->getThreadRepo()->findThreadById($threadId);
        }
        if (!$thread) {
            return 'Not find topic';
        }
        $reason = $bridge->request()->filter('reason', 'str');
        $options = array(
            'deleteType' => ($mode == 2 ? 'hard' : 'soft'),
            'reason' => $reason,
        );
        $type = $options['deleteType'];

        if (!$thread->canDelete($type, $error)) {
            return $bridge->errorToString($error);
        }

        /** @var \XF\Service\Thread\Deleter $deleter */
        $deleter = $bridge->service('XF:Thread\Deleter', $thread);
        $deleter->delete($type, $reason);

        /** @var \XF\ControllerPlugin\InlineMod $pluginInlineMod */
        $pluginInlineMod = $bridge->plugin('XF:InlineMod');
        $pluginInlineMod->clearIdFromCookie('thread', $thread->thread_id);

        return true;
    }

    /**
     * m_undelete_topic
     *
     * @param $oMbqEtForumTopic
     * @return bool|mixed|string
     */
    public function mUndeleteTopic($oMbqEtForumTopic)
    {
        $bridge = Bridge::getInstance();

        if (!is_a($oMbqEtForumTopic, 'MbqEtForumTopic')) {
            return 'Not find topic';
        }
        $threadId = $oMbqEtForumTopic->topicId->oriValue;
        $thread = $oMbqEtForumTopic->mbqBind;
        if (!$thread || !($thread instanceof \XF\Entity\Thread)) {
            $thread = $bridge->getThreadRepo()->findThreadById($threadId);
        }
        if (!$thread) {
            return 'Not find topic';
        }

        if (!$thread->canUndelete($error))
        {
            return $bridge->noPermissionToString($error);
        }

        if ($thread->discussion_state == 'deleted') {
            $thread->discussion_state = 'visible';
            $thread->save();
        }

        return true;
    }

    /**
     * @param \XF\Entity\Thread $thread
     *
     * @return \XF\Service\Thread\Editor
     */
    protected function _setupThreadEdit(\XF\Entity\Thread $thread)
    {
        $bridge = Bridge::getInstance();
        $request = $bridge->request();

        /** @var \XF\Service\Thread\Editor $editor */
        $editor = $bridge->service('XF:Thread\Editor', $thread);

        $prefixId = $request->filter('prefix_id', 'uint');
        if ($prefixId != $thread->prefix_id && !$thread->Forum->isPrefixUsable($prefixId))
        {
            $prefixId = 0; // not usable, just blank it out
        }
        $editor->setPrefix($prefixId);
        $editor->setTitle($request->filter('title', 'str'));

        $customFields = $request->filter('custom_fields', 'array');
        $editor->setCustomFields($customFields);

        return $editor;
    }

    /**
     * m_rename_topic
     *
     * @param MbqEtForumTopic $oMbqEtForumTopic
     * @param $title
     * @return bool|mixed|string
     */
    public function mRenameTopic($oMbqEtForumTopic, $title)
    {
        $bridge = Bridge::getInstance();
        $request = $bridge->request();

        $threadId = $oMbqEtForumTopic->topicId->oriValue;
        $thread = $oMbqEtForumTopic->mbqBind;
        if (!$thread || !($thread instanceof \XF\Entity\Thread)) {
            $thread = $bridge->getThreadRepo()->findThreadById($threadId);
        }
        if (!$thread) {
            return 'not find thread';
        }
        if (!$thread->canEdit($error)) {
            return $bridge->noPermissionToString($error);
        }
        $request->set('title', $title);
        if ($oMbqEtForumTopic->prefixId->hasSetOriValue() && !empty($oMbqEtForumTopic->prefixId->oriValue)) {
            $prefixId = $oMbqEtForumTopic->prefixId->oriValue;
            $request->set('prefix_id', $prefixId);
        }

        $editor = $this->_setupThreadEdit($thread);
        if (!$editor->validate($errors)) {
            return $bridge->errorToString($errors);
        }

        $editor->save();
        return true;
    }

    /**
     * m_move_topic
     *
     * @param MbqEtForumTopic $oMbqEtForumTopic
     * @param MbqEtForum $oMbqEtForum
     * @param $redirect
     * @return bool|mixed|string
     */
    public function mMoveTopic($oMbqEtForumTopic, $oMbqEtForum, $redirect)
    {
        $bridge = Bridge::getInstance();

        if (!is_a($oMbqEtForumTopic, 'MbqEtForumTopic') || !is_a($oMbqEtForum, 'MbqEtForum')) {
            return 'not find topic or forum';
        }

        $thread = $oMbqEtForumTopic->mbqBind;
        $forum = $oMbqEtForum->mbqBind;
        $threadId = $oMbqEtForumTopic->topicId->oriValue;
        $forumId = $oMbqEtForum->forumId->oriValue;
        if (!$thread || !($thread instanceof \XF\Entity\Thread)) {
            $thread = $bridge->getThreadRepo()->findThreadById($threadId);
        }
        if (!$thread) {
            return 'Not find thread';
        }

        if (!$thread->canView($error)) {
            return $bridge->noPermissionToString($error);
        }
        if (!$thread->canMove($error)) {
            return $bridge->noPermissionToString($error);
        }

        if (!$forum || !($forum instanceof \XF\Entity\Forum)) {
            $forum = $bridge->getForumRepo()->getForumById($forumId);
        }
        if (!$forum) {
            return 'Not find forum';
        }
        $targetForum = $forum;

        if (!$targetForum || !$targetForum->canView())
        {
            return $bridge->noPermissionToString(\XF::phrase('requested_forum_not_found'));
        }

        if ($redirect && $redirect != 'false' && $redirect != '0') {
            $bridge->request()->set('redirect_type', 'permanent');
        }else{
            $bridge->request()->set('redirect_type', 'none');
        }

        $this->_setupThreadMove($thread, $targetForum)->move($targetForum);

        return true;
    }

    /**
     * @param \XF\Entity\Thread $thread
     * @param \XF\Entity\Forum $forum
     *
     * @return \XF\Service\Thread\Mover
     */
    protected function _setupThreadMove(\XF\Entity\Thread $thread, \XF\Entity\Forum $forum)
    {
        $bridge = Bridge::getInstance();
        $request = $bridge->request();
        $options = $request->filter([
            'notify_watchers' => 'bool',
            'starter_alert' => 'bool',
            'starter_alert_reason' => 'str',
            'prefix_id' => 'uint'
        ]);

        $redirectType = $request->filter('redirect_type', 'str');
        if ($redirectType == 'permanent')
        {
            $options['redirect'] = true;
            $options['redirect_length'] = 0;
        }
        else if ($redirectType == 'temporary')
        {
            $options['redirect'] = true;
            $options['redirect_length'] = $request->filter('redirect_length', 'timeoffset');
        }
        else
        {
            $options['redirect'] = false;
            $options['redirect_length'] = 0;
        }

        /** @var \XF\Service\Thread\Mover $mover */
        $mover = $bridge->service('XF:Thread\Mover', $thread);

        if ($options['starter_alert'])
        {
            $mover->setSendAlert(true, $options['starter_alert_reason']);
        }

        if ($options['notify_watchers'])
        {
            $mover->setNotifyWatchers();
        }

        if ($options['redirect'])
        {
            $mover->setRedirect(true, $options['redirect_length']);
        }

        if ($options['prefix_id'] !== null)
        {
            $mover->setPrefix($options['prefix_id']);
        }

        return $mover;
    }

    /**
     * m_merge_topic
     */
    /**
     * @param MbqEtForumTopic $oMbqEtForumTopicFrom
     * @param MbqEtForumTopic $oMbqEtForumTopicTo
     * @param $redirect
     * @return bool|mixed|string
     */
    public function mMergeTopic($oMbqEtForumTopicFrom, $oMbqEtForumTopicTo, $redirect)
    {

        $bridge = Bridge::getInstance();
        $request = $bridge->request();

        $request->set('type', 'thread');
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

        $topicId = $oMbqEtForumTopicFrom->topicId->oriValue;
        $threadId = array_unique(array_map('intval', explode(',', $topicId)));

        $targetThreadId = $oMbqEtForumTopicTo->topicId->oriValue;
        $ids = $threadId;
        $ids[] = $targetThreadId;
        $ids = array_unique($ids);

        $entities = $handler->getEntities($ids);
        if (!$entities->count())
        {
            return 'Not find posts';
        }
        $request->set('ids', $ids);
        $request->set('target_thread_id', $targetThreadId);

        if ($redirect && $redirect != 'false' && $redirect != '0') {
            $request->set('redirect_type', 'permanent');
        }else{
            $request->set('redirect_type', 'none');
        }

        $options = $actionHandler->getFormOptions($entities, $request);
        if (!$actionHandler->canApply($entities, $options, $error))
        {
            return $bridge->noPermissionToString($error);
        }

        // either we're confirmed or we don't have a form to render
        $actionHandler->apply($entities, $options);

        return true;
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
     * m_approve_topic
     *
     * @param MbqEtForumTopic $oMbqEtForumTopic
     * @param $mode
     * @return bool|mixed|string
     */
    public function mApproveTopic($oMbqEtForumTopic, $mode)
    {
        $bridge = Bridge::getInstance();
        $request = $bridge->request();

        $request->set('type', 'thread');
        if ($mode == 1) {
            $request->set('action', 'approve');
        }else{
            $request->set('action', 'unapprove');
        }
        $request->set('confirmed', '0');
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

        $threadId = $oMbqEtForumTopic->topicId->oriValue;
        $threadIds = array_unique(array_map('intval', explode(',', $threadId)));

        $ids = $threadIds;
        $ids = array_unique($ids);
        $entities = $handler->getEntities($ids);
        if (!$entities->count()) {
            return 'Not find posts';
        }
        $request->set('ids', $ids);

        $options = [];
        if (!$actionHandler->canApply($entities, $options, $error))
        {
            return $bridge->noPermissionToString($error);
        }

        // either we're confirmed or we don't have a form to render
        $actionHandler->apply($entities, $options);

        return true;
    }
}
