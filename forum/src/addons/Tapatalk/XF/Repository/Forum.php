<?php

namespace Tapatalk\XF\Repository;


class Forum extends XFCP_Forum
{
    /**
     * @param $nodeId
     * @return null|\XF\Entity\Forum
     */
    public function findForumById($nodeId)
    {
        /** @var \XF\Entity\Forum $forum */
        $forum = $this->finder('XF:Forum')->whereId($nodeId)->fetchOne();
        return $forum;
    }

    /**
     * @param $forumId
     * @return null|\XF\Entity\Forum
     */
    public function getForumById($forumId)
    {
        return $this->findForumById($forumId);
    }

    /**
     * @param $nodeId
     * @return \XF\Entity\LinkForum
     */
    public function getLinkForumByNodeId($nodeId)
    {
        /** @var \XF\Entity\LinkForum $forum */
        $forum = $this->em->find('XF:LinkForum', $nodeId);
        return $forum;
    }

    /**
     * @param $nodeId
     * @return bool|null|\XF\Entity\Forum
     */
    public function assertForumValidAndViewable($nodeId)
    {
        $nodeId = str_replace('r', '', $nodeId);
        $nodeId = (int)$nodeId;

        $hideForums = \XF::app()->options()->hideForums;
        if ($hideForums && is_array($hideForums) && in_array($nodeId, $hideForums)) {
           return false;
        }

        if (!$nodeId)
            return false;

        $forum = $this->findForumById($nodeId);
        if (!$forum || !$forum->canView()) {
            return false;
        }

        return $forum;
    }

    /**
     * @param $userId
     * @return null|\XF\Mvc\Entity\ArrayCollection
     */
    public function getForumsByUserWatch($userId)
    {
        $userId = (int)$userId;
        if (!$userId)
            return null;
        /** @var ForumWatch $ForumWatchRepo */
        $ForumWatchRepo = \XF::app()->repository('XF:ForumWatch');
        $forumIds = $ForumWatchRepo->getUserForumWatchByUser($userId);
        if (!$forumIds) {
            return null;
        }

        return $this->getForumsByIds($forumIds);
    }

    /**
     * @param array $forumIds
     * @return null|\XF\Mvc\Entity\ArrayCollection
     */
    public function getForumsByIds(array $forumIds)
    {
        if (!$forumIds || !is_array($forumIds))
            return null;

        return $this->finder('XF:Forum')->whereIds($forumIds)->fetch();
    }

    /**
     * @param $userId
     * @return array|\XF\Mvc\Entity\Entity[]
     */
    public function getForumsByUserSubscribed($userId)
    {
        $rst = [];

        $watchForums = $this->getForumsByUserWatch($userId);
        if ($watchForums) {
            $watchForums = $watchForums->toArray();
            $rst = $watchForums + $rst;
        }
        // dev add read forums ?

        return $rst;
    }




}