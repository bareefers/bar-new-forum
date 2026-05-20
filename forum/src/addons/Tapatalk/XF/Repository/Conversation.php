<?php

namespace Tapatalk\XF\Repository;


class Conversation extends XFCP_Conversation
{
    /**
     * @param $conversationId
     * @return \XF\Entity\ConversationMaster
     */
    public function getConversationById($conversationId)
    {
        /** @var \XF\Entity\ConversationMaster $conversation */
        $conversation = $this->finder('XF:ConversationMaster')->whereId($conversationId)->fetchOne();
        return $conversation;
    }

    /**
     * @param \XF\Entity\ConversationMaster $conversation
     * @return \XF\Mvc\Entity\ArrayCollection
     */
    public function getConversationMessageListByConversationMaster($conversation)
    {
        /** @var \XF\Finder\ConversationMessage $finder */
        $finder = $this->finder('XF:ConversationMessage');
        $finder = $finder->inConversation($conversation)
            ->order('message_date')
            ->forFullView();

        /** @var \XF\Mvc\Entity\ArrayCollection $CMMsg */
        $CMMsg = $finder->fetch();
        return $CMMsg;
    }

    /**
     * @param $conversationId
     * @return null|\XF\Entity\ConversationUser
     */
    public function assertViewableUserConversation($conversationId)
    {
        $visitor = \XF::visitor();
        $extraWith = ['Master.DraftReplies|' . \XF::visitor()->user_id];

        /** @var \XF\Finder\ConversationUser $finder */
        $finder = $this->finder('XF:ConversationUser');
        $finder->forUser($visitor, false);
        $finder->where('conversation_id', $conversationId);
        $finder->with($extraWith);

        /** @var \XF\Entity\ConversationUser $conversation */
        $conversation = $finder->fetchOne();
        if (!$conversation)
        {
            return null;
        }

        return $conversation;
    }


}