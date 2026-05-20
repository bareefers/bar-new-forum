<?php
use Tapatalk\Bridge;

defined('MBQ_IN_IT') or exit;

MbqMain::$oClk->includeClass('MbqBaseAclEtPcMsg');

/**
 * private conversation message acl class
 */
Class MbqAclEtPcMsg extends MbqBaseAclEtPcMsg
{

    public function __construct()
    {
    }

    /**
     * judge can reply_conversation
     *
     * @return  Boolean
     */
    public function canAclReplyConversation($oMbqEtPcMsg, $oMbqEtPc)
    {
        /** @var \XF\Entity\ConversationMaster $conversation */
        $conversation = $oMbqEtPc->mbqBind;
        if (!$conversation || !($conversation instanceof \XF\Entity\ConversationMaster)) {
            return TT_GetPhraseString('requested_conversation_not_found');
        }
        $bridge = Bridge::getInstance();

        if (!$conversation->canReply()) {
            return $bridge->noPermissionToString();
        }
        return MbqMain::hasLogin();
    }

    /**
     * judge can get_quote_conversation
     *
     * @return  Boolean
     */
    public function canAclGetQuoteConversation($oMbqEtPcMsg, $oMbqEtPc)
    {
        return MbqMain::hasLogin();
    }
}