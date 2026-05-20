<?php

namespace Tapatalk\XF\Repository;

class Notice extends XFCP_Notice
{
    /**
     * @return \XF\Mvc\Entity\ArrayCollection
     */
    public function getAllNoticeLists()
    {
        $notices = $this->findNoticesForList()->fetch();
        return $notices;
    }

    /**
     * @param $noticeId
     * @return \XF\Entity\Notice
     */
    public function getNoticeById($noticeId)
    {
        /** @var \XF\Entity\Notice $notice */
        $notice = $this->finder('XF:Notice')->whereId($noticeId)->fetchOne();
        return $notice;
    }

    /**
     * @param $noticeIds
     * @return \XF\Mvc\Entity\ArrayCollection
     */
    public function getNoticeByIds($noticeIds)
    {
        if (!is_array($noticeIds)) {
            $noticeIds = [$noticeIds];
        }

        return $this->finder('XF:Notice')->whereIds($noticeIds)->fetch();
    }


}