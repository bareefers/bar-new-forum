<?php

use Tapatalk\Bridge;

defined('MBQ_IN_IT') or exit;

MbqMain::$oClk->includeClass('MbqBaseWrEtPc');

/**
 * private conversation write class
 */
Class MbqWrEtPc extends MbqBaseWrEtPc
{

    public function __construct()
    {
    }

    /**
     * add private conversation
     *
     * @param MbqEtPc $oMbqEtPc
     * @return MbqEtPc|mixed|string
     */
    public function addMbqEtPc($oMbqEtPc)
    {
        $participants = $oMbqEtPc->userNames->oriValue;
        if (!is_array($participants)) {
            $participants = array($participants);
        }

        $bridge = Bridge::getInstance();

        $input = array(
            'recipients' => $participants,
            'title' => $oMbqEtPc->convTitle->oriValue,
            'message' => $oMbqEtPc->convContent->oriValue,
            'attachment_id_array' => $oMbqEtPc->attachmentIdArray->oriValue,
            'group_id' => $oMbqEtPc->groupId->oriValue,
        );

        $bridge->_request->set('recipients', implode(',', $input['recipients']));
        $bridge->_request->set('title', $input['title']);

        if ($input['group_id']) {
            $input['attachment_hash'] = $input['group_id'];
        }else {
            $attachmentIdsArray = $input['attachment_id_array'];
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
                        $attachmentHashArray[] = $attachment->temp_hash;
                    }
                }
                if ($attachmentHashArray) {
                    $input['attachment_hash'] = implode(',', $attachmentHashArray);
                }
            }
        }

        $input['message'] = $bridge::XFCleanString($input['message']);

        $creator = $this->_setupConversationCreate($input, $input['message']);
        if (!$creator->validate($errors)) {
            return $bridge->responseError($errors);
        }
        $error = $bridge->XFAssertNotFlooding('conversation');
        if ($error) {
            $bridge->responseError($error);
        }

        /** @var \XF\Entity\ConversationMaster $conversation */
        $conversation = $creator->save();
        $this->_finalizeConversationCreate($creator);
        /** @var \XF\Finder\ConversationMessage $firstFinderMesage */
        $firstFinderMesage = $conversation->getRelationFinder('FirstMessage');

        if ($conversation && ($conversation instanceof \XF\Entity\ConversationMaster)) {
            /** @var MbqRdEtPc $oMbqRdEtPc */
            $oMbqRdEtPc = MbqMain::$oClk->newObj('MbqRdEtPc');
            $oMbqEtPc = $oMbqRdEtPc->initOMbqEtPc($conversation['conversation_id'], array('case' => 'byConvId'));

            return $oMbqEtPc;
        }

        return null; // error
    }

    /**
     * @param array $input
     * @param $message
     * @return \XF\Service\Conversation\Creator
     */
    protected function _setupConversationCreate($input, $message)
    {
        $bridge = Bridge::getInstance();
        $visitor = $bridge::visitor();

        $recipients = $input['recipients'];
        $title = $input['title'];
//        $message = $this->plugin('XF:Editor')->fromInput('message');

        $conversationLocked = isset($input['conversation_locked']) ? $input['conversation_locked'] : false; // 'bool'

        $options = [];
        $options['open_invite'] = isset($input['open_invite']) ? $input['open_invite'] : false; // 'bool'
        $options['conversation_open'] = !$conversationLocked;

        /** @var \XF\Service\Conversation\Creator $creator */
        $creator = $bridge->getConversationCreatorService($visitor);
        $creator->setOptions($options);
        $creator->setRecipients($recipients);
        $creator->setContent($title, $message);

        $conversation = $creator->getConversation();

        if ($conversation->canUploadAndManageAttachments()) {
            $creator->setAttachmentHash(isset($input['attachment_hash']) ? $input['attachment_hash'] : '');
        }

        return $creator;
    }

    protected function _finalizeConversationCreate(\XF\Service\Conversation\Creator $creator)
    {
        \XF\Draft::createFromKey('conversation')->delete();
    }

    public function inviteParticipant($oMbqEtPcInviteParticipant)
    {
        $bridge = Bridge::getInstance();

        $recipients = $recipientUserIds = [];
        foreach ($oMbqEtPcInviteParticipant->objsMbqEtUser as $objMbqEtUser) {
            if (isset($objMbqEtUser->mbqBind) && $objMbqEtUser->mbqBind && ($objMbqEtUser->mbqBind instanceof \XF\Entity\User)) {
                $recipients[] = $objMbqEtUser->mbqBind;
                $recipientUserIds[] = $objMbqEtUser->mbqBind->user_id;
            }
        }

        if (!$recipients) {
            return false;
        }

        $conversation = $oMbqEtPcInviteParticipant->oMbqEtPc->mbqBind;
        $conversationId = $oMbqEtPcInviteParticipant->oMbqEtPc->convId->oriValue;
        if ($conversation && ($conversation instanceof \XF\Entity\ConversationMaster)) {
            /** @var \XF\Entity\ConversationMaster $conversationMaster */
            $conversationMaster = $conversation;
        } else {
            $conversationRepo = $bridge->getConversationRepo();
            $conversationMaster = $conversationRepo->getConversationById($conversationId);
        }

        if (!$conversationMaster) {
            return false;
        }

        $visitor = Bridge::visitor();

        /** @var \XF\Service\Conversation\Inviter $inviter */
        $inviter = $bridge->getConversationInviterService($conversation, $visitor);
        $inviter->setRecipients($recipients);

        if (!$inviter->validate($errors)) {
            return false;
        }

        if ($recipientUserIds) {
            $_REQUEST['tapatalk_invited_user_ids'] = implode(',', $recipientUserIds);
            $GLOBALS['tapatalk_conversation_id_invite'] = $conversationMaster->conversation_id;
        }

        $inviter->save();

        return true;
    }

    /**
     * delete conversation
     *
     * @param MbqEtPc $oMbqEtPc
     * @param $mode
     * @return bool
     */
    public function deleteConversation($oMbqEtPc, $mode)
    {
        if ($mode == 1 || $mode == 2) {
            // Recipient TYPE: deleted_ignored , deleted
            $recipientState = (isset($mode) && $mode == 2) ? 'deleted_ignored' : 'deleted';

            try {
                $bridge = Bridge::getInstance();

                $conversationId = $oMbqEtPc->convId->oriValue;

                $conversationRepo = $bridge->getConversationRepo();
                $userConv = $conversationRepo->assertViewableUserConversation($conversationId);

                if (!$userConv || !($userConv instanceof \XF\Entity\ConversationUser)) {
                    return false;
                }

                $recipient = $userConv->Recipient;
                if ($recipient) {

                    $recipient->recipient_state = $recipientState;
                    $recipient->save();
                }

            } catch (Exception $e) {
                MbqError::alert('', "Can not delete conversation!", '', MBQ_ERR_APP);
            }

        } else {
            MbqError::alert('', "Need valid mode id!", '', MBQ_ERR_APP);
        }
    }

    /**
     * mark private conversation read
     *
     * @param $oMbqEtPc
     */
    public function markPcRead($oMbqEtPc)
    {
        $bridge = Bridge::getInstance();
        $conversationModel = $bridge->getConversationRepo();
        $userConv = $conversationModel->assertViewableUserConversation($oMbqEtPc->convId->oriValue);
        if ($userConv) {
            $conversationModel->markUserConversationRead($userConv);
        }
    }

    /**
     * mark private conversationunread
     *
     * @param MbqEtPc $oMbqEtPc
     * @return bool
     */
    public function markPcUnread($oMbqEtPc)
    {
        $bridge = Bridge::getInstance();

        $conversationId = $oMbqEtPc->convId->oriValue;
        if (!$conversationId) {
            return false;
        }
        $conversationRepo = $bridge->getConversationRepo();
        /** @var \XF\Entity\ConversationUser $userConv */
        $userConv = $conversationRepo->assertViewableUserConversation($conversationId);

        $wasUnread = $userConv->is_unread;
        if ($wasUnread){
            return true;
        }
        $userConv->is_unread = true;

        if ($userConv->Recipient) {

            $userConv->Recipient->last_read_date = $userConv->is_unread ? 0 : \XF::$time;
            $userConv->Recipient->save();
        }

        $userConv->save();
        //$message = \XF::phrase('conversation_marked_as_unread');
    }

    /**
     * mark all private conversations read
     */
    public function markAllPcRead()
    {


    }
}