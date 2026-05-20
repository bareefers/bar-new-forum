<?php
use Tapatalk\Bridge;

defined('MBQ_IN_IT') or exit;

MbqMain::$oClk->includeClass('MbqBaseWrEtPcMsg');

/**
 * private conversation message write class
 */
Class MbqWrEtPcMsg extends MbqBaseWrEtPcMsg
{

    public function __construct()
    {
    }

    /**
     * add private conversation message
     *
     * @param MbqEtPcMsg $oMbqEtPcMsg
     * @param MbqEtPc $oMbqEtPc
     * @return MbqEtPcMsg|bool|string
     */
    public function addMbqEtPcMsg($oMbqEtPcMsg, $oMbqEtPc)
    {
        $bridge = Bridge::getInstance();

        $conversationId = $oMbqEtPc->convId->oriValue;
        if (!$conversationId) {
            return false;
        }
        $conversationRepo = $bridge->getConversationRepo();
        $userConv = $conversationRepo->assertViewableUserConversation($conversationId);
        if (!$userConv || !($userConv instanceof \XF\Entity\ConversationUser)) {
            return false;
        }
        $conversation = $userConv->Master;
        if (!$conversation->canReply()) {
            return $bridge->noPermissionToString();
        }

        $messageContent = $oMbqEtPcMsg->msgContent->oriValue;
        $option = [];
        $attachmentHash = '';

        if ($oMbqEtPcMsg->groupId->hasSetOriValue()) {
            $option['attachment_hash'] = $oMbqEtPcMsg->groupId->oriValue;
        }else {
            if ($oMbqEtPcMsg->attachmentIdArray->hasSetOriValue()) {
                $attachmentIdsArray = $oMbqEtPcMsg->attachmentIdArray->oriValue;
                if ($attachmentIdsArray && is_array($attachmentIdsArray)) {
                    $attachmentRepo = $bridge->getAttachmentRepo();
                    $attachments = $attachmentRepo->getAttachmentsByIds($attachmentIdsArray);
                    if ($attachments) {
                        $attachments = $attachments->toArray();
                    }else{
                        $attachments = [];
                    }
                    $attachmentHashArray = [];
                    /** @var \XF\Entity\Attachment $attachment */
                    foreach ($attachments as $attachment) {
                        if ($attachment->canView() && $attachment->temp_hash) {
                            $attachmentHashArray[$attachment->temp_hash] = $attachment->temp_hash;
                        }
                    }
                    if ($attachmentHashArray) {
                        $attachmentHash = end($attachmentHash);
                    }
                }
                if ($attachmentHash) {
                    $option['attachment_hash'] = $attachmentHash;
                }
            }
        }

//        /** @var \XF\ControllerPlugin\Editor $e */
//        $e = $bridge->plugin('XF:Editor');$e->fromInput('message');

        $messageContent = $bridge::XFCleanString($messageContent);
        $replier = $this->_setupConversationReply($conversation, $userConv, $messageContent, $option);
        if (!$replier->validate($errors)) {
            return $bridge->errorToString($errors);
        }
        if ($error = $bridge->XFAssertNotFlooding('conversation')) {
            return $bridge->errorToString($error);
        }

        /** @var \XF\Entity\ConversationMessage $message */
        $message = $replier->save();

        $oMbqEtPcMsg->msgId->setOriValue($message['message_id']);

        return $oMbqEtPcMsg;
    }

    /**
     * @param \XF\Entity\ConversationMaster $conversation
     * @param \XF\Entity\ConversationUser $userConv
     * @param $message
     * @param array $option
     *
     * @return \XF\Service\Conversation\Replier
     */
    protected function _setupConversationReply(\XF\Entity\ConversationMaster $conversation, \XF\Entity\ConversationUser $userConv, $message, $option = [])
    {
        $bridge = Bridge::getInstance();
        $visitor = $bridge::visitor();

        /** @var \XF\Service\Conversation\Replier $replier */
        $replier = $bridge->getConversationReplierService($conversation, $visitor);
        $replier->setMessageContent($message);

        if ($conversation->canUploadAndManageAttachments()) {
            $replier->setAttachmentHash(isset($option['attachment_hash']) ? $option['attachment_hash'] : ''); // attachment_hash is string
        }

        return $replier;
    }

}