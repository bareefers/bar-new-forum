<?php

namespace Tapatalk\XF\Repository;


class User extends XFCP_User
{
    /**
     * @param $userId
     * @return \XF\Entity\User
     */
    public function findUserById($userId)
    {
        /** @var \XF\Entity\User $user */
        $user = $this->finder('XF:User')->whereId($userId)->fetchOne();
        return $user;
    }

    /**
     * @param $userId
     * @return \XF\Entity\User
     */
    public function getUserById($userId){
        return $this->findUserById($userId);
    }

    /**
     * mbq output format get user last activity info
     *
     * @param \XF\Entity\User $user
     * @return bool|string
     */
    public function outputLastActivityToString($user)
    {
        if (!($user instanceof \XF\Entity\User)) {
            return false;
        }

        $output = '';
        $visitorActivity = $user->Activity;
        if ($visitorActivity) {

            $output .= \XF::escapeString($visitorActivity->description);
            if ($visitorActivity->item_title)
            {
                $title = \XF::escapeString($visitorActivity->item_title);
                $url = \XF::escapeString($visitorActivity->item_url);
                $output .= " ({$title})";
                //$output .= " url:{$url} ";
            }

            // format time
            $dateTime = $user->last_activity;
            if (!($dateTime instanceof \DateTime))
            {
                $ts = intval($dateTime);
                $dateTime = new \DateTime();
                $dateTime->setTimestamp($ts);
                $dateTime->setTimezone(new \DateTimeZone($user->timezone));
            }

            $output.= ' ' . $dateTime->format('Y-m-d H:i:s');// $dateTime->format(\DateTime::ISO8601);
        }

        return $output;
    }

    /**
     * @param array $userIds
     * @return \XF\Mvc\Entity\ArrayCollection
     */
    public function getUsersByIds($userIds)
    {
        /** @var \XF\Mvc\Entity\ArrayCollection $userList */
        $userList = $this->finder('XF:User')->whereIds($userIds)->fetch();
        return $userList;
    }

    /**
     * @param $userId
     * @return \XF\Mvc\Entity\ArrayCollection
     */
    public function getTfasByUserId($userId)
    {
        $UserTfa = $this->finder('XF:UserTfa')->where('user_id', '=', $userId)->fetch();
        return $UserTfa;
    }

    /**
     * @param string $email
     * @param string $username
     * @return \XF\Entity\User|null
     */
    public function validUserExistUsernameOrEmail($email, $username)
    {
        /** @var \XF\Entity\User $checkUser */
        $checkUser = $this->finder('XF:User')
            ->whereOr([
                0 => ['username' => $username],
                1 => ['email', $email]
            ])
            ->fetchOne();
        return $checkUser;
    }


}