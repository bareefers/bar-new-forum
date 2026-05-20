<?php

namespace Tapatalk\XF\Repository;


class UserTfaTrusted extends XFCP_UserTfaTrusted
{

    /**
     * @param $userId
     * @return \XF\Entity\UserTfaTrusted
     */
    public function getTfaTrustedByUserId($userId)
    {
        /** @var \XF\Entity\UserTfaTrusted $UserTfaTrusted */
        $UserTfaTrusted = $this->finder('XF:UserTfaTrusted')->where('user_id', '=', $userId)->fetchOne();
        return $UserTfaTrusted;
    }


}