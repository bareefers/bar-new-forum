<?php

namespace Tapatalk\XF\Repository;


class Attachment extends XFCP_Attachment
{
    /**
     * @param $attachmentId
     * @return \XF\Entity\Attachment
     */
    public function getAttachmentById($attachmentId)
    {
        /** @var \XF\Entity\Attachment $attachment */
        $attachment = $this->finder('XF:Attachment')->whereId($attachmentId)->fetchOne();
        return $attachment;
    }

    /**
     * @param array $attachmentIds
     * @return \XF\Mvc\Entity\ArrayCollection
     */
    public function getAttachmentsByIds($attachmentIds)
    {
        /** @var \XF\Mvc\Entity\ArrayCollection $attachments */
        $attachments = $this->finder('XF:Attachment')->whereIds($attachmentIds)->fetch();
        return $attachments;
    }

}