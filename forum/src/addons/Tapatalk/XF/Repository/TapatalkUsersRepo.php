<?php
namespace Tapatalk\XF\Repository;

use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;


class TapatalkUsersRepo extends Repository
{

    /**
     * @return Finder
     */
    public function findAllUsers() {
        return $this->finder('Tapatalk:TapatalkUsers')->order('userid');
    }

    /**
     * @param $userId
     * @return null|\XF\Mvc\Entity\Entity
     */
    public function getTapatalkUserById($userId)
    {
        return $this->finder('Tapatalk:TapatalkUsers')->whereId($userId)->fetchOne();
    }

    /**
     * Get all the rows of our table.
     *
     */
    public function getAllTapatalkUser()
    {
        return $this->findAllUsers()->fetch();
    }

    /**
     * @param $userIds
     * @return array|\XF\Mvc\Entity\ArrayCollection
     */
    public function getTapatalkUsersInArray($userIds)
    {
        if (!is_array($userIds) || empty($userIds)) {
            return array();
        }
        $search_users = array_unique(array_map('intval', $userIds));

        return $this->finder('Tapatalk:TapatalkUsers')->whereIds($search_users)->fetch();
    }

    /**
     * @param $userIds
     * @return array|\XF\Mvc\Entity\ArrayCollection
     */
    public function getAllPmOpenTapatalkUsersInArray($userIds)
    {
        if (!is_array($userIds) || !$userIds) {
            return array();
        }
        $search_users = array_unique(array_map('intval', $userIds));

        return $this->finder('Tapatalk:TapatalkUsers')
            ->whereIds($search_users)
            ->where('pm', '=', '1')
            ->fetch();
    }

    /**
     * @param $userId
     * @param $action
     * @return array|\XF\Mvc\Entity\ArrayCollection
     */
    public function getPushTypeOpenTapatalkUsers($userId, $action)
    {
        $supportActionList = array(
            'sub' => 'subscribe',
            'quote' => 'quote',
            'like' => 'liked',
            'tag' => 'tag',
        );
        $userId = (int)$userId;
        if (!$userId) {
            return array();
        }
        if (!isset($supportActionList[$action]) || empty($supportActionList[$action]))
            return array();

        return $this->finder('Tapatalk:TapatalkUsers')
            ->whereId($userId)
            ->where($supportActionList[$action], '=', '1')
            ->fetch();
    }

    public function getDisplayNameByTableKey($key)
    {
        $display_key_map = array(
            'conv' => 'Conversation push',
            'pm' => 'PM push',
            'subscribe' => 'Subscription topic push',
            'liked' => 'Likes push',
            'quote' => 'Quotes push',
            'newtopic' => 'Subscription forum push',
            'tag' => 'Mention push',
            'announcement' => 'Announcement push',
        );
        return isset($display_key_map[$key]) ? $display_key_map[$key] : '';
    }

    public function getStarndardNameByTableKey($key)
    {
        $starndard_key_map = array(
            'conv' => 'conv',
            'pm' => 'conv',
            'subscribe' => 'sub',
            'liked' => 'like',
            'quote' => 'quote',
            'newtopic' => 'newtopic',
            'tag' => 'tag',
//            'announcement'      => 'ann',
        );
        return isset($starndard_key_map[$key]) ? $starndard_key_map[$key] : '';
    }

}