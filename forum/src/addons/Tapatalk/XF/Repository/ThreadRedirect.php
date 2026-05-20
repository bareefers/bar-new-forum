<?php

namespace Tapatalk\XF\Repository;


class ThreadRedirect extends XFCP_ThreadRedirect
{
    /**
     * @param $threadId
     * @return \XF\Entity\ThreadRedirect
     */
    public function getThreadRedirectById($threadId)
    {
        /** @var \XF\Entity\ThreadRedirect $ThreadRedirect */
        $ThreadRedirect = $this->finder('XF:ThreadRedirect')->whereId($threadId)->fetchOne();
        return $ThreadRedirect;
    }


}