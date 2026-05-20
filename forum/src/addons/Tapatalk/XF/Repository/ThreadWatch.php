<?php

namespace Tapatalk\XF\Repository;

class ThreadWatch extends XFCP_ThreadWatch
{
    /**
     * @param $userId
     * @param $threadId
     * @return bool|null|\XF\Mvc\Entity\Entity
     */
    public function getUserThreadWatchByThreadId($userId, $threadId)
    {
        $threadId = (int)$threadId;
        if (!$userId) {
            $visitor = \XF::visitor();
            $userId = $visitor->get('user_id');
        }

        return $this->finder('XF:ThreadWatch')->where([
            'user_id' => $userId,
            'thread_id' => $threadId,
        ])->fetchOne();
    }

    /**
     * @param $userId
     * @param $threadIds
     * @return bool|\XF\Mvc\Entity\ArrayCollection
     */
    public function getUserThreadWatchByThreadIds($userId, $threadIds)
    {
        if (!$threadIds)
            return false;

        if (!is_array($threadIds)) {
            $threadIds = explode(',', $threadIds);
        }
        if (!$userId) {
            $visitor = \XF::visitor();
            $userId = $visitor->get('user_id');
        }

        return $this->finder('XF:ThreadWatch')
            ->where([
            'user_id' => $userId
            ])
            ->where('thread_id', $threadIds)
            ->fetch();
    }

    /**
     * @param $userId
     * @return bool|\XF\Mvc\Entity\ArrayCollection
     */
    public function getUserThreadWatchByUser($userId)
    {
        $userId = (int)$userId;

        $find = $this->finder('XF:ThreadWatch')->where([
            'user_id' => $userId
        ])->fetch();

        return $find;
    }

    /**
     * return user list
     *
     * @param $threadId
     * @return bool|\XF\Mvc\Entity\ArrayCollection
     */
    public function getUsersWatchingThreadId($threadId)
    {
        if (!$threadId)
            return false;

        return $this->finder('XF:ThreadWatch')
            ->with('User', true)
            ->where([
                'thread_id' => $threadId
            ])
            ->pluckFrom('User', 'user_id')->fetch();
    }


}