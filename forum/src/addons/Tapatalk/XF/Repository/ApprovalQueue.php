<?php

namespace Tapatalk\XF\Repository;


class ApprovalQueue extends XFCP_ApprovalQueue
{
    /**
     * @return \XF\Mvc\Entity\Finder
     */
    public function getPostUnapprovedContent()
    {
        return $this->finder('XF:ApprovalQueue')
            ->where('content_type', '=', 'post')
            ->order('content_date');
    }


}