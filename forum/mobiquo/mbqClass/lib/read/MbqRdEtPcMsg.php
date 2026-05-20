<?php
use Tapatalk\Bridge;

defined('MBQ_IN_IT') or exit;

MbqMain::$oClk->includeClass('MbqBaseRdEtPcMsg');

/**
 * private conversation message read class
 */
Class MbqRdEtPcMsg extends MbqBaseRdEtPcMsg
{

    public function __construct()
    {
    }

    public function makeProperty(&$oMbqEtPcMsg, $pName, $mbqOpt = array())
    {
        switch ($pName) {
            default:
                MbqError::alert('', __METHOD__ . ',line:' . __LINE__ . '.' . MBQ_ERR_INFO_UNKNOWN_PNAME . ':' . $pName . '.');
                break;
        }
    }

    /**
     * get private conversation message objs
     *
     * @param  Mixed $var
     * @param  Array $mbqOpt
     * $mbqOpt['case'] = 'byPc' means get data by private conversation obj.$var is the private conversation obj
     * $mbqOpt['case'] = 'byMsgIds' means get data by conversation message ids.$var is the ids.
     * @return  Mixed
     */
    public function getObjsMbqEtPcMsg($var, $mbqOpt)
    {
        if ($mbqOpt['case'] == 'byPc') {

            $bridge = Bridge::getInstance();
            $oMbqEtPc = $var;
            $oMbqDataPage = $mbqOpt['oMbqDataPage'];

            $conversationMsgModel = $bridge->getConversationMessageRepo();
            $conversationId = $oMbqEtPc->convId->oriValue;
            $conversation = $oMbqEtPc->mbqBind;

            if (!$conversation || !($conversation instanceof \XF\Entity\ConversationMaster)) {
                if (!$conversationId) {
                    return false;
                }
                $conversationModel = $bridge->getConversationRepo();
                $conversation = $conversationModel->getConversationById($conversationId);
            }
            if (!$conversation) {
                return false;
            }

            $messages = $conversationMsgModel->findMessagesForConversationView($conversation)
                ->limitByPage($oMbqDataPage->curPage, $oMbqDataPage->numPerPage)->fetch();

            if ($messages && $messages->count() > 0) {
                /** @var \XF\Entity\ConversationMessage $message */
                foreach ($messages as $message) {
                    $etPcMsg = $this->initOMbqEtPcMsg($message, array('case' => 'byRow'));
                    if ($etPcMsg) $oMbqDataPage->datas[] = $etPcMsg;
                }
            }

            $oMbqDataPage->totalNum = $conversation['reply_count'] + 1;

            return $oMbqDataPage;
        }

        MbqError::alert('', __METHOD__ . ',line:' . __LINE__ . '.' . MBQ_ERR_INFO_UNKNOWN_CASE);
    }

    /**
     * @param $var
     * @param $mbqOpt
     * @return bool|MbqEtPcMsg|null
     */
    public function initOMbqEtPcMsg($var, $mbqOpt)
    {
        $bridge = Bridge::getInstance();
        $return = null;

        switch ($mbqOpt['case']) {
            case 'byRow':
                $return = $this->_initOMbqEtPcMsgByRow($var, $mbqOpt, $bridge);
                break;

            case 'byPcMsgId':
                $return = $this->_initOMbqEtPcMsgByPcMsgId($var, $mbqOpt, $bridge);
                break;
        }

        return $return;
    }

    protected function _initOMbqEtPcMsgByPcMsgId($var, $mbqOpt, Bridge $bridge)
    {
        $oMbqEtPc = $var;
        if ($oMbqEtPc != null && $oMbqEtPc->mbqBind) {
            /** @var MbqRdEtPc $oMbqRdEtPc */
            $oMbqRdEtPc = MbqMain::$oClk->newObj('MbqRdEtPc');
            $oMbqEtPc = $oMbqRdEtPc->initOMbqEtPc($oMbqEtPc->convId->oriValue, array('case' => 'byConvId'));
            /** @var \XF\Entity\ConversationMaster $conversation */
            $conversation = $oMbqEtPc->mbqBind;
        }
        $msgId = $mbqOpt['pcMsgId'];
        $conversationMsgRepo = $bridge->getConversationMessageRepo();
        /** @var \XF\Entity\ConversationMessage $message */
        $message = $conversationMsgRepo->getMessageById($msgId);
        if (!$message || !($message instanceof \XF\Entity\ConversationMessage)){
            return false;
        }

        return $this->initOMbqEtPcMsg($message, array('case' => 'byRow'));
    }

    /**
     * @param $var
     * @param $mbqOpt
     * @param Bridge $bridge
     * @return MbqEtPcMsg
     */
    protected function _initOMbqEtPcMsgByRow($var, $mbqOpt, Bridge $bridge)
    {
        /** @var MbqEtPcMsg $oMbqEtPcMsg */
        $oMbqEtPcMsg = MbqMain::$oClk->newObj('MbqEtPcMsg');
        $message = $var;
        $oMbqEtPcMsg->mbqBind = $message;

        if (!$message || !($message instanceof \XF\Entity\ConversationMessage)) {
            return false;
        }

        if (!$message->canView()) {
            return false;
        }

        /** @var \XF\Entity\ConversationMaster $conversation */
        $conversation = $message->Conversation;
        /** @var \XF\Entity\User $msgUser */
        $msgUser = $message->User;
        if (!$conversation || !$msgUser) {

            return null;
        }

        if ($message) {
            $message = $message->toArray();
        }

        $attachmentRepo = $bridge->getAttachmentRepo();

        $defaultOptions = array(
            'states' => array(
                'viewAttachments' => false,
                'returnHtml' => true
            )
        );
        if (isset($message['attach_count']) && ($message['attach_count'] > 0)) {

            $attachments = $attachmentRepo->findAttachmentsByContent('conversation_message', $message['message_id'])->fetch();
            if ($attachments) {
                $attachments = $attachments->toArray();
            }else{
                $attachments = [];
            }
            $defaultOptions['states']['attachments'] = $attachments;

            if (stripos($message['message'], '[/attach]') !== false)
            {
                if (preg_match_all('#\[attach(=[^\]]*)?\](?P<id>\d+)\[/attach\]#i', $message['message'], $matches))
                {
                    foreach ($matches['id'] AS $attachId)
                    {
                        $attachment = isset($attachments[$attachId]) ? $attachments[$attachId] :  null;
                        if($attachment != null)
                        {
                            /** @var MbqRdEtAtt $oMbqRdAtt */
                            $oMbqRdAtt = MbqMain::$oClk->newObj('MbqRdEtAtt');
                            $oMbqEtAtt = $oMbqRdAtt->initOMbqEtAtt($attachment, array('case' => 'byRow'));
                            $oMbqEtPcMsg->objsMbqEtAtt[] = $oMbqEtAtt;
                            unset($attachments[$attachId]);
                        }
                    }
                }
            }

            foreach ($attachments as $attachment){
                /** @var MbqRdEtAtt $oMbqRdAtt */
                $oMbqRdAtt = MbqMain::$oClk->newObj('MbqRdEtAtt');

                $oMbqEtAtt = $oMbqRdAtt->initOMbqEtAtt($attachment, array('case' => 'byRow'));
                if ($oMbqEtAtt) $oMbqEtPcMsg->objsNotInContentMbqEtAtt[] = $oMbqEtAtt;
            }
        }

        $oMbqEtPcMsg->msgId->setOriValue($message['message_id']);
        $oMbqEtPcMsg->convId->setOriValue($message['conversation_id']);
        $oMbqEtPcMsg->msgTitle->setOriValue('');

        $content = $bridge->cleanPost($message['message'], $defaultOptions);
        $oMbqEtPcMsg->msgContent->setOriValue($content);
        $oMbqEtPcMsg->msgContent->setAppDisplayValue($content);
        $oMbqEtPcMsg->msgContent->setTmlDisplayValue($content);
        $oMbqEtPcMsg->msgContent->setTmlDisplayValueNoHtml($content);
        $oMbqEtPcMsg->msgAuthorId->setOriValue($message['user_id']);
        $oMbqEtPcMsg->postTime->setOriValue($message['message_date']);


        /** @var MbqRdEtUser $oMbqRdEtUser */
        $oMbqRdEtUser = MbqMain::$oClk->newObj('MbqRdEtUser');
        $oMbqEtPcMsg->oAuthorMbqEtUser = $oMbqRdEtUser->initOMbqEtUser($oMbqEtPcMsg->msgAuthorId->oriValue, array('case' => 'byUserId'));

        return $oMbqEtPcMsg;
    }

    function getQuoteConversation($oMbqEtPcMsg)
    {
        $bridge = Bridge::getInstance();

        $defaultMessage = '';

        if (is_a($oMbqEtPcMsg, 'MbqEtPcMsg')) {
            /** @var MbqEtPcMsg $oMbqEtPcMsg */
            $defaultMessage = $oMbqEtPcMsg->msgContent->oriValue;

            /** @var \XF\Entity\ConversationMessage $message */
            $message = $oMbqEtPcMsg->mbqBind;
            if ($message && $message->canView()) {
                $defaultMessage = $message->getQuoteWrapper(
                    $bridge->stringFormatter()->getBbCodeForQuote($message->message, 'conversation_message')
                );

            }
        }

        return $defaultMessage;
    }

    function getUrl($oMbqEtPcMsg)
    {
        return \XF\Legacy\Link::buildPublicLink('full:conversations/message',
            array(
                'conversation_id' => $oMbqEtPcMsg->convId->oriValue,
                'title' => $oMbqEtPcMsg->msgTitle->oriValue,
            ),
            array(
                'message_id' => $oMbqEtPcMsg->msgId->oriValue,
            ));
    }
}