<?php

namespace Tapatalk\XF\Repository;

class ForumWatch extends XFCP_ForumWatch
{
    /**
     * @param $userId
     * @param $nodeId
     * @return bool|null|\XF\Mvc\Entity\Entity
     */
    public function getUserForumWatchByForumId($userId, $nodeId)
    {
        if (!$nodeId)
            return false;

        if (!$userId) {
            $visitor = \XF::visitor();
            $userId = $visitor->get('user_id');
        }

        return $this->finder('XF:ForumWatch')->where([
            'user_id' => $userId,
            'node_id' => $nodeId,
        ])->fetchOne();
    }

    /**
     * @param $userId
     * @return array
     */
    public function getUserForumWatchByUser($userId)
    {
        $rst = [];
        $userId = (int)$userId;
        if (!$userId)
            return $rst;

        $find = $this->finder('XF:ForumWatch')->where([
            'user_id' => $userId
        ])->fetch();

        if (!$find->count()) {
            return $rst;
        }
        /** @var \XF\Entity\ForumWatch $watch */
        foreach ($find as $watch) {
            $rst[] = $watch->get('node_id');
        }

        return $rst;
    }

    /**
     * return user list
     *
     * @param $nodeId
     * @return bool|\XF\Mvc\Entity\ArrayCollection
     */
    public function getUsersWatchingForumId($nodeId)
    {
        if (!$nodeId)
            return false;

        return $this->finder('XF:ForumWatch')
            ->with('User', true)
            ->where([
            'node_id' => $nodeId
            ])
            ->pluckFrom('User', 'user_id')->fetch();
    }


}