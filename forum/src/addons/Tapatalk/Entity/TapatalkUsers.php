<?php

namespace Tapatalk\Entity;

use XF\Entity\User;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * Class TapatalkUsers
 * @property User $User
 * @package Tapatalk\Entity
 */
class TapatalkUsers extends Entity
{

    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_tapatalk_users';
        $structure->shortName = 'Tapatalk:TapatalkUsers';
        $structure->primaryKey = 'userid';
        $structure->columns = [
            'userid' => ['type' => self::UINT, 'default' => \XF::visitor()->user_id],
            'announcement' => ['type' => self::UINT, 'default' => '1'],
            'pm' => ['type' => self::UINT, 'default' => '1'],
            'subscribe' => ['type' => self::UINT, 'default' => '1'],
            'quote' => ['type' => self::UINT, 'default' => '1'],
            'liked' => ['type' => self::UINT, 'default' => '1'],
            'tag' => ['type' => self::UINT, 'default' => '1'],
            'updated' => ['type' => self::STR, 'default' => gmdate('Y-m-d h:i:s',time()) ]
        ];

        $structure->relations = [
            'User' => [
                'entity' => 'XF:User',
                'type' => self::TO_ONE,
                'conditions' => 'userid',
                'key' => 'user_id',
                'primary' => true
            ]
        ];

        return $structure;
    }

    /**
     * @return null|\XF\Entity\User
     */
    public function getUser()
    {
        if (empty($this->userid)) {
            return null;
        }
        if ($this->User && ($this->User instanceof User)) {
            return $this->User;
        }
        /** @var \XF\Repository\User $userRepo */
        $userRepo = $this->repository('XF:User');
        return $userRepo->getVisitor($this->userid);
    }

}