<?php

use Tapatalk\Bridge;

defined('MBQ_IN_IT') or exit;

MbqMain::$oClk->includeClass('MbqBaseWrEtForum');

/**
 * forum write class
 */
Class MbqWrEtForum extends MbqBaseWrEtForum
{

    public function __construct()
    {
    }

    /**
     * subscribe forum
     *
     * @param MbqEtForum $oMbqEtForum
     * @param $receiveEmail
     * @return bool
     */
    public function subscribeForum($oMbqEtForum, $receiveEmail)
    {
        $bridge = Bridge::getInstance();
        $visitor = $bridge::visitor();
        $forumRepo = $bridge->getForumRepo();

        if (!$visitor->user_id) {
            return $bridge->noPermissionToString();
        }
        $forumId = $oMbqEtForum->forumId->oriValue;
        $sendAlert = true;
        $sendEmail = $receiveEmail;
        $unwatch = false;

        $forum = $oMbqEtForum->mbqBind;
        if (!$forum || !($forum instanceof \XF\Entity\Forum)) {
            $forum = $forumRepo->findForumById($forumId);
        }

        if (!$forum) {
            return false;
        }
        if (!$forum->canWatch($error)) {
            return $bridge->errorToString($error);
        }

        if ($unwatch) {
            $notifyType = 'delete';
        } else {
            $notifyType = 'thread'; //we only notify new thread
            if ($notifyType != 'thread' && $notifyType != 'message') {
                $notifyType = '';
            }

            if ($forum->allowed_watch_notifications == 'none') {
                $notifyType = '';
            } else if ($forum->allowed_watch_notifications == 'thread' && $notifyType == 'message') {
                $notifyType = 'thread';
            }
        }

        /** @var \XF\Repository\ForumWatch $watchRepo */
        $watchRepo = $bridge->getForumWatchRepo();
        $watchRepo->setWatchState($forum, $visitor, $notifyType, $sendAlert, $sendEmail);

        return true;
    }

    /**
     * unsubscribe forum
     *
     * @param MbqEtForum $oMbqEtForum
     * @return bool
     */
    public function unsubscribeForum($oMbqEtForum)
    {
        $bridge = Bridge::getInstance();
        $visitor = $bridge::visitor();
        $forumRepo = $bridge->getForumRepo();
        $forumWatchRepo = $bridge->getForumWatchRepo();

        if (!$visitor->user_id) {
            return $bridge->noPermissionToString();
        }
        $forumId = $oMbqEtForum->forumId->oriValue;
        $sendAlert = true;
        $sendEmail = false;
        $unwatch = true;

        if (strtolower($forumId) == 'all') {
            $forumWatchRepo->setWatchStateForAll($visitor, 'delete');
            return true;
        }

        $forum = $oMbqEtForum->mbqBind;
        if (!$forum || !($forum instanceof \XF\Entity\Forum)) {
            $forum = $forumRepo->findForumById($forumId);
        }

        if (!$forum) {
            return false;
        }
        if (!$forum->canWatch($error)) {
            return $bridge->errorToString($error);
        }

        if ($unwatch) {
            $notifyType = 'delete';
        } else {
            $notifyType = 'thread'; //we only notify new thread
            if ($notifyType != 'thread' && $notifyType != 'message') {
                $notifyType = '';
            }

            if ($forum->allowed_watch_notifications == 'none') {
                $notifyType = '';
            } else if ($forum->allowed_watch_notifications == 'thread' && $notifyType == 'message') {
                $notifyType = 'thread';
            }
        }

        $forumWatchRepo->setWatchState($forum, $visitor, $notifyType, $sendAlert, $sendEmail);

        return true;
    }

    /**
     * @param MbqEtForum|null $oMbqEtForum
     * @return bool
     */
    public function markForumRead($oMbqEtForum)
    {
        $bridge = Bridge::getInstance();
        $visitor = $bridge::visitor();

        if (!$visitor->user_id) {
            return false;
        }

        $markDate = \XF::$time;

        $forumRepo = $bridge->getForumRepo();

        if ($oMbqEtForum && ($oMbqEtForum instanceof MbqEtForum)) {
            /** @var \XF\Entity\Forum $forum */
            $forum = $oMbqEtForum->mbqBind;
            if (!$forum || !($forum instanceof \XF\Entity\Forum)) {
                $forum = $bridge->getForumRepo()->findForumById($oMbqEtForum->forumId->oriValue);
            }

        } else {
            $forum = null;
        }

        if ($forum) {
            $forumRepo->markForumTreeReadByVisitor($forum, $markDate);
        } else {
            $forumRepo->markForumTreeReadByVisitor(null, $markDate);
        }

        return true;
    }

}