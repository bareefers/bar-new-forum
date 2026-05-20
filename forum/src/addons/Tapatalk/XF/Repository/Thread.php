<?php

namespace Tapatalk\XF\Repository;

class Thread extends XFCP_Thread
{
    /**
     * @param $threadId
     * @return \XF\Entity\Thread
     */
    public function findThreadById($threadId)
    {
        /** @var \XF\Entity\Thread $thread */
        $thread = $this->finder('XF:Thread')->whereId($threadId)->fetchOne();
        return $thread;
    }

    /**
     * @param \XF\Entity\Thread $thread
     * @return bool
     */
    public function isRedirect(\XF\Entity\Thread $thread)
    {
        return ($thread->discussion_type == 'redirect');
    }

    /**
     * @param \XF\Entity\Forum $forum
     * @return \XF\Mvc\Entity\Finder
     */
    public function findStickyThreadsForForumView(\XF\Entity\Forum $forum)
    {
        $threadList = $this->findThreadsForForumView($forum)
            ->where('sticky', '=', 1)
            ->order('last_post_date', 'DESC');

        return $threadList;
    }

    /**
     * @return \XF\Finder\Thread
     */
    public function getThreadFinderByUserWatched()
    {
        $threadFinder = $this->findThreadsForWatchedList();

        // skip ignore user
        $threadFinder = $threadFinder->skipIgnored();
        //$threads = $threadFinder->limitByPage($start, $limit)->fetch()->filterViewable();

        return $threadFinder;
    }

    /**
     * @return \XF\Mvc\Entity\Finder
     */
    public function getDeletedThreadFinder()
    {
        /** @var \XF\Mvc\Entity\Finder $finder */
        $finder = $this->finder('XF:Thread')->where('discussion_state', '=', 'deleted');

        return $finder;
    }

    /**
     * @param $userId
     * @return \XF\Finder\Thread
     */
    public function getThreadsByUserParticipated($userId)
    {
        /** @var \XF\Finder\Thread $finder */
        $finder = $this->findThreadsWithPostsByUser($userId);
        return $finder;
    }

    /**
     * @param array $threadIds
     * @return \XF\Mvc\Entity\ArrayCollection
     */
    public function getThreadsByIds($threadIds)
    {
        if (!is_array($threadIds)) {
            $threadIds = [$threadIds];
        }
        $threads = $this->finder('XF:Thread')->whereIds($threadIds)->fetch();
        return $threads;
    }

    /**
     * @param \XF\Entity\User $user
     * @return \XF\Finder\Thread
     */
    public function getThreadsByUserUnread($user)
    {
        $finder = $this->getThreadsByUserParticipated($user->user_id);
        /** @var \XF\Finder\Thread $finder */
        $finder = $finder->unreadOnly($user->user_id);
        return $finder;
    }

    /**
     * @return \XF\Finder\Thread
     */
    public function findThreadsWithLatestPostsAll()
    {
        return $this->finder('XF:Thread')
            ->with(['Forum', 'User'])
            ->where('discussion_state', 'visible')
            ->where('discussion_type', '<>', 'redirect')
            ->order('last_post_date', 'DESC')
            ->indexHint('FORCE', 'last_post_date');
    }

    /**
     * @param null $userId
     * @return $this|\XF\Finder\Thread
     */
    public function findThreadsWithUnreadPostsAll($userId = null)
    {
        $threadFinder = $this->findThreadsWithLatestPostsAll();

        $userId = $userId ?: \XF::visitor()->user_id;

        if (!$userId)
        {
            return $threadFinder;
        }

        return $threadFinder->unreadOnly($userId);
    }

}