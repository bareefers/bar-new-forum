<?php

namespace Tapatalk\XF\Repository;

class ConversationMessage extends XFCP_ConversationMessage
{
    /**
     * @param $messageId
     * @return \XF\Entity\ConversationMessage
     */
    public function getMessageById($messageId)
    {
        /** @var \XF\Entity\ConversationMessage $message */
        $message = $this->finder('XF:ConversationMessage')->whereId($messageId)->fetchOne();
        return $message;
    }

    /**
     * @param $messageId
     * @return \XF\Entity\ConversationMessage
     */
    public function getConversationMessageById($messageId)
    {
        return $this->getMessageById($messageId);
    }

}