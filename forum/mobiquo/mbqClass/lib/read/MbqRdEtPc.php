<?php
use Tapatalk\Bridge;

defined('MBQ_IN_IT') or exit;

MbqMain::$oClk->includeClass('MbqBaseRdEtPc');

/**
 * private conversation read class
 */
Class MbqRdEtPc extends MbqBaseRdEtPc {

    public function __construct() {
    }

    public function makeProperty(&$oMbqEtPc, $pName, $mbqOpt = array()) {
        switch ($pName) {
            default:
            MbqError::alert('', __METHOD__ . ',line:' . __LINE__ . '.' . MBQ_ERR_INFO_UNKNOWN_PNAME . ':' . $pName . '.');
            break;
        }
    }

    /**
     * get unread private conversations number
     *
     * @return  Integer
     */
    public function getUnreadPcNum()
    {
        $bridge = Bridge::getInstance();
        $visitor = $bridge::visitor();

        $inbox_unread_count = $visitor['conversations_unread'] ? $visitor['conversations_unread'] : 0;
        return $inbox_unread_count;
    }

    /**
     * get unread private conversations number
     *
     * @return  Integer
     */
    public function getSubcribedUnreadPcNum()
    {
        $bridge = Bridge::getInstance();
        $visitor = $bridge::visitor();
        $threadWatchModel = $bridge->getThreadWatchRepo();

        $newThreads = $threadWatchModel->getUserThreadWatchByUser($visitor['user_id']);

        $sub_threads_num = $newThreads->count();
        return $sub_threads_num;
    }

    /**
     * get private conversation objs
     *
     * $mbqOpt['case'] = 'all' means get my all data.
     * $mbqOpt['case'] = 'byConvIds' means get data by conversation ids.$var is the ids.
     * $mbqOpt['case'] = 'byObjsStdPc' means get data by objsStdPc.$var is the objsStdPc.
     * @return  Mixed
     */
    public function getObjsMbqEtPc($var, $mbqOpt)
    {
        if ($mbqOpt['case'] == 'all') {
            $bridge = Bridge::getInstance();
            $visitor = $bridge::visitor();

            $oMbqDataPage = $mbqOpt['oMbqDataPage'];
            $limit = $oMbqDataPage->numPerPage;
            $page = $oMbqDataPage->curPage;

            $conversationModel = $bridge->getConversationRepo();
            $conversationsFinder = $conversationModel->findUserConversations($visitor, false);
            $totalConversations = $conversationsFinder->total();

            $unreadConversations = $visitor['conversations_unread'] ? $visitor['conversations_unread'] : 0;
            $conversationList = $conversationsFinder->limitByPage($page, $limit)->fetch();

            /** @var \XF\Entity\ConversationUser $conversation */
            foreach($conversationList as $conversation)
            {
                if ($conversation->Master) {
                    $etPc = $this->initOMbqEtPc($conversation->Master, array('case' => 'byRow'));
                    if ($etPc) $oMbqDataPage->datas[] = $etPc;
                }
            }

            $oMbqDataPage->totalNum = $totalConversations;
            $oMbqDataPage->totalUnreadNum = $unreadConversations;

            return $oMbqDataPage;
        }
        MbqError::alert('', __METHOD__ . ',line:' . __LINE__ . '.' . MBQ_ERR_INFO_UNKNOWN_CASE);
    }

    /**
     * @param $var
     * @param $mbqOpt
     * @return bool|MbqEtPc|mixed|null
     */
    public function initOMbqEtPc($var, $mbqOpt)
    {
        $bridge = Bridge::getInstance();
        $return = null;
        switch ($mbqOpt['case']){
            case 'byRow':
                $return = $this->_initOMbqEtPcByRow($var, $mbqOpt, $bridge);
                break;
            case 'byConvId':
                $return = $this->_initOMbqEtPcByConvId($var, $mbqOpt, $bridge);
                break;
        }

        return $return;
    }

    protected function _initOMbqEtPcByConvId($var, $mbqOpt, Bridge $bridge)
    {
        $conversationId = $var;
        if (!$conversationId) {
            return false;
        }

        $conversationModel = $bridge->getConversationRepo();
        $visitor = $bridge::visitor();
        /** @var \XF\Entity\ConversationMaster $conversation */
        $conversation = $conversationModel->getConversationById($conversationId);

        if (!$conversation) {
            return false;
        }
        if (!$conversation->canView()) {
            return false; //'noPermission';
        }
        if($conversation && $conversation->canView())
        {
            return $this->initOMbqEtPc($conversation, array('case'=>'byRow'));
        }

        return false;
    }

    /**
     * @param $var
     * @param $mbqOpt
     * @param Bridge $bridge
     * @return MbqEtPc
     *
     */
    protected function _initOMbqEtPcByRow($var, $mbqOpt, Bridge $bridge)
    {
        $conversation = $var;
        if (!$conversation || !($conversation instanceof \XF\Entity\ConversationMaster)) {
            return false;
        }
        $conversationMsgRepo = $bridge->getConversationMessageRepo();
        $conversationRepo = $bridge->getConversationRepo();

        /** @var MbqEtPc $oMbqEtPc */
        $oMbqEtPc = MbqMain::$oClk->newObj('MbqEtPc');

        /** @var \XF\Entity\ConversationMaster $conversation */
        $oMbqEtPc->mbqBind = $conversation;
        $userConv = $conversationRepo->assertViewableUserConversation($conversation->conversation_id);

        $oMbqEtPc->convId->setOriValue($conversation['conversation_id']);
        $oMbqEtPc->convTitle->setOriValue($conversation['title']);

        /** @var \XF\Entity\ConversationMessage $message */
        $message = $conversationMsgRepo->findLatestMessage($conversation)->fetchOne();// ['last_message_id']

        $oMbqEtPc->convContent->setOriValue($bridge->renderPostPreview($message['message'], $conversation['last_message_user_id'],200));

        $oMbqEtPc->totalMessageNum->setOriValue($conversation['reply_count'] +1);
        $oMbqEtPc->participantCount->setOriValue($conversation['recipient_count']);
        $oMbqEtPc->startUserId->setOriValue($conversation['user_id']);
        $oMbqEtPc->startConvTime->setOriValue($conversation['start_date']);
        $oMbqEtPc->lastUserId->setOriValue($conversation['last_message_user_id']);
        $oMbqEtPc->lastConvTime->setOriValue($conversation['last_message_date']);

        $oMbqEtPc->newPost->setOriValue(($userConv && $userConv->is_unread) ? $userConv->is_unread : 0);

        $oMbqEtPc->firstMsgId->setOriValue($conversation['first_message_id']);
        $oMbqEtPc->deleteMode->setOriValue(MbqBaseFdt::getFdt('MbqFdtPc.MbqEtPc.deleteMode.range.soft-delete'));

        $oMbqEtPc->canInvite->setOriValue($conversation->canInvite());
        $oMbqEtPc->canEdit->setOriValue($conversation->canEdit());
        $oMbqEtPc->canClose->setOriValue($conversation->canEdit());
        $oMbqEtPc->isClosed->setOriValue(!isset($conversation['conversation_open']) || empty($conversation['conversation_open']));
        $oMbqEtPc->canUpload->setOriValue($conversation->canUploadAndManageAttachments());

        $recipients = $conversation->getRelationFinder('Recipients')->fetch();
        if ($recipients) {
            $recipients = $recipients->toArray();
        }else{
            $recipients = [];
        }

        $userIds = array();
        foreach($recipients as $recipient)
        {
            $userIds[] = $recipient->user_id;
        }

        /** @var MbqRdEtUser $oMbqRdEtUser */
        $oMbqRdEtUser = MbqMain::$oClk->newObj('MbqRdEtUser');
        $objRecipientMbqEtUser = $oMbqRdEtUser->getObjsMbqEtUser($userIds, array('case' => 'byUserIds'));

        if ($objRecipientMbqEtUser) {
            foreach ($objRecipientMbqEtUser as $oRecipientMbqEtUser) {
                if ($oRecipientMbqEtUser != null) {
                    $oMbqEtPc->objsRecipientMbqEtUser[$oRecipientMbqEtUser->userId->oriValue] = $oRecipientMbqEtUser;
                }
            }
        }
        return $oMbqEtPc;
    }

    function canUpload()
    {
        $viewingUser = Bridge::visitor();
        return $viewingUser->canUploadAndManageAttachments('conversation');
    }

    function getUrl($oMbqEtPc)
    {
        return \XF\Legacy\Link::buildPublicLink('full:conversations',
			array(
				'conversation_id' => $oMbqEtPc->convId->oriValue,
				'title' => $oMbqEtPc->convTitle->oriValue,
			));
    }
}