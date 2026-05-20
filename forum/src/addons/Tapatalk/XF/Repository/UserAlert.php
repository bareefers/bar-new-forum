<?php

namespace Tapatalk\XF\Repository;


class UserAlert extends XFCP_UserAlert
{

    /**
     * @param $alertId
     * @return \XF\Entity\UserAlert
     */
    public function getUserAlertById($alertId)
    {
        /** @var \XF\Entity\UserAlert $userAlert */
        $userAlert = $this->finder('XF:UserAlert')->whereId($alertId)->fetchOne();
        return $userAlert;
    }


}