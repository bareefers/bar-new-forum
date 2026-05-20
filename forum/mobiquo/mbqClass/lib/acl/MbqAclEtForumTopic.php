<?php
use Tapatalk\Bridge;

defined('MBQ_IN_IT') or exit;

MbqMain::$oClk->includeClass('MbqBaseAclEtForumTopic');

/**
 * forum topic acl class
 */
Class MbqAclEtForumTopic extends MbqBaseAclEtForumTopic
{

    public function __construct()
    {
    }

    /**
     * judge can get topic from the forum
     *
     * @param  Object $oMbqEtForum
     * @return  Boolean
     */
    public function canAclGetTopic($oMbqEtForum)
    {
        $check3rd = true;
        try {
            $bridge = Bridge::getInstance();
            $compatibleOtherPlugin = $bridge::getCompatibleOtherPlugin();
            $check3rd = $compatibleOtherPlugin::canAclGetTopic($oMbqEtForum);
            if ($check3rd instanceof \XF\Phrase) {
                return $check3rd->render();
            }elseif (!is_bool($check3rd)) {
                $check3rd = true;
            }
        }catch (\Exception $e){}
        return (MbqMain::isNotBanned() && $check3rd);
    }

    /**
     * judge can get topics by ids
     *
     * @return  Boolean
     */
    public function canAclGetTopicByIds()
    {
        return MbqMain::isNotBanned();
    }

    /**
     * judge can get thread
     *
     * @param  Object $oMbqEtForumTopic
     * @return  Boolean
     */
    public function canAclGetThread($oMbqEtForumTopic)
    {
        $check3rd = true;
        try {
            $bridge = Bridge::getInstance();
            $compatibleOtherPlugin = $bridge::getCompatibleOtherPlugin();
            $check3rd = $compatibleOtherPlugin::canAclGetThread($oMbqEtForumTopic);
            if ($check3rd instanceof \XF\Phrase) {
                return $check3rd->render();
            }elseif (!is_bool($check3rd)) {
                $check3rd = true;
            }
        }catch (\Exception $e){}
        return MbqMain::isNotBanned() && $check3rd;
    }

    /**
     * judge can new topic
     *
     * @param  Object $oMbqEtForum
     * @return  Boolean
     */
    public function canAclNewTopic($oMbqEtForum)
    {
        return $oMbqEtForum->canPost->oriValue && MbqMain::isActiveMember();
    }

    /**
     * judge can get subscribed topic
     *
     * @return  Boolean
     */
    public function canAclGetSubscribedTopic()
    {
        return MbqMain::isActiveMember();
    }

    /**
     * judge can mark all my unread topics as read
     *
     * @return  Boolean
     */
    public function canAclMarkAllAsRead()
    {
        return MbqMain::isActiveMember();
    }

    /**
     * judge can mark all my unread topics as read
     *
     * @return  Boolean
     */
    public function canAclMarkTopicRead($oMbqEtForumTopic)
    {
        return MbqMain::isActiveMember();
    }

    /**
     * judge can get_unread_topic
     *
     * @return  Boolean
     */
    public function canAclGetUnreadTopic()
    {
        return MbqMain::isActiveMember();
    }

    /**
     * judge can get_participated_topic
     *
     * @return  Boolean
     */
    public function canAclGetParticipatedTopic()
    {
        return MbqMain::isActiveMember();
    }

    /**
     * judge can get_latest_topic
     *
     * @return  Boolean
     */
    public function canAclGetLatestTopic()
    {
        return MbqMain::canViewBoard();
    }

    /**
     * judge can search_topic
     *
     * @return  Boolean
     */
    public function canAclSearchTopic()
    {
        $bridge = Bridge::getInstance();
        $visitor = $bridge::visitor();
        return $visitor->canSearch();
    }

    /**
     * judge can subscribe_topic
     *
     * @param  Object $oMbqEtForumTopic
     * @return  Boolean
     */
    public function canAclSubscribeTopic($oMbqEtForumTopic, $receiveEmail)
    {
        return $oMbqEtForumTopic->canSubscribe->oriValue && MbqMain::isActiveMember();
    }

    /**
     * judge can unsubscribe_topic
     *
     * @param  Object $oMbqEtForumTopic
     * @return  Boolean
     */
    public function canAclUnsubscribeTopic($oMbqEtForumTopic)
    {
        return $oMbqEtForumTopic->canSubscribe->oriValue && MbqMain::isActiveMember();
    }

    /**
     * judge can get_user_topic
     *
     * @return  Boolean
     */
    public function canAclGetUserTopic()
    {
        return MbqMain::isNotBanned();
    }

    /**
     * judge can m_stick_topic
     *
     * @param  Object $oMbqEtForumTopic
     * @param  Integer $mode
     * @return  Boolean
     */
    public function canAclMStickTopic($oMbqEtForumTopic, $mode)
    {
        return MbqMain::isActiveMember();
    }

    /**
     * judge can m_close_topic
     *
     * @param  Object $oMbqEtForumTopic
     * @param  Integer $mode
     * @return  Boolean
     */
    public function canAclMCloseTopic($oMbqEtForumTopic, $mode)
    {
        return $oMbqEtForumTopic->canClose->oriValue;
    }

    /**
     * judge can m_delete_topic
     *
     * @param  Object $oMbqEtForumTopic
     * @param  Integer $mode
     * @return  Boolean
     */
    public function canAclMDeleteTopic($oMbqEtForumTopic, $mode)
    {
        return $oMbqEtForumTopic->canDelete->oriValue;
    }

    /**
     * judge can m_undelete_topic
     *
     * @param  Object $oMbqEtForumTopic
     * @return  Boolean
     */
    public function canAclMUndeleteTopic($oMbqEtForumTopic)
    {
        return $oMbqEtForumTopic->canDelete->oriValue;
    }

    /**
     * judge can m_move_topic
     *
     * @param  Object $oMbqEtForumTopic
     * @param  Object $oMbqEtForum
     * @return  Boolean
     */
    public function canAclMMoveTopic($oMbqEtForumTopic, $oMbqEtForum)
    {
        return $oMbqEtForumTopic->canMove->oriValue;
    }

    /**
     * judge can m_rename_topic
     *
     * @param  Object $oMbqEtForumTopic
     * @return  Boolean
     */
    public function canAclMRenameTopic($oMbqEtForumTopic)
    {
        $bridge = Bridge::getInstance();
        $threadId = $oMbqEtForumTopic->topicId->oriValue;
        /** @var \XF\Entity\Thread $thread */
        $thread = $oMbqEtForumTopic->mbqBind;
        if (!($thread instanceof \XF\Entity\Thread)) {
            $thread = $bridge->getThreadRepo()->findThreadById($threadId);
        }
        if (!$thread->canEdit()) {
            return false;
        }

        return true;
    }

    /**
     * judge can m_approve_topic
     *
     * @param  Object $oMbqEtForumTopic
     * @param  Integer $mode
     * @return  Boolean
     */
    public function canAclMApproveTopic($oMbqEtForumTopic, $mode)
    {
        return MbqMain::isActiveMember() && MbqMain::hasLogin();
    }

    /**
     * judge can m_merge_topic
     *
     * @param  Object $oMbqEtForumTopic
     * @param  Object $oMbqEtForumTopic
     * @return  Boolean
     */
    public function canAclMMergeTopic($oMbqEtForumTopicFrom, $oMbqEtForumTopicTo)
    {
        return MbqMain::isActiveMember() && MbqMain::hasLogin();
    }

    /**
     * judge can m_get_moderate_topic
     *
     * @return  Boolean
     */
    public function canAclMGetModerateTopic()
    {
        return MbqMain::isActiveMember() && MbqMain::hasLogin();
    }

    /**
     * judge can m_get_delete_topic
     *
     * @return  Boolean
     */
    public function canAclMGetDeleteTopic()
    {
        return MbqMain::isActiveMember() && MbqMain::hasLogin();
    }
}