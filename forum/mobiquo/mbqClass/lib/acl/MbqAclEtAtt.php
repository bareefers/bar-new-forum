<?php

use Tapatalk\Bridge;

defined('MBQ_IN_IT') or exit;

MbqMain::$oClk->includeClass('MbqBaseAclEtAtt');

/**
 * attachment acl class
 */
Class MbqAclEtAtt extends MbqBaseAclEtAtt
{

    public function __construct()
    {
    }

    /**
     * judge can upload attachment
     *
     * @param Object $oMbqEtForumOrConvPm
     * @param $groupId
     * @param $type
     * @return bool|mixed|string
     */
    public function canAclUploadAttach($oMbqEtForumOrConvPm, $groupId, $type)
    {
        $bridge = Bridge::getInstance();

        $contentType = 'post';
        $contentData = array();
        if (isset($type) && $type == 'pm') {
            $contentType = 'conversation_message';
            if (is_a($oMbqEtForumOrConvPm, 'MbqEtPc')) {
                $contentData['conversation_id'] = $oMbqEtForumOrConvPm->convId->oriValue;
            }
        } else {
            $contentData ['node_id'] = $oMbqEtForumOrConvPm->forumId->oriValue;
        }

        $attachmentModel = $bridge->getAttachmentRepo();

        $attachmentHandler = $attachmentModel->getAttachmentHandler($contentType);
        if (!$attachmentHandler || !$attachmentHandler->canManageAttachments($contentData)) {
            return TT_GetPhraseString('do_not_have_permission');
        }
        return true;
    }

    /**
     * judge can remove attachment
     *
     * @param  Object $oMbqEtAtt
     * @param  Object $oMbqEtForum
     * @return  Boolean
     */
    public function canAclRemoveAttachment($oMbqEtAtt, $oMbqEtForum)
    {
        $bridge = Bridge::getInstance();
        $visitor = Bridge::visitor();

        $attachment = $bridge->getAttachmentRepo()->getAttachmentById($oMbqEtAtt->attId->oriValue);
        if (!$attachment) {
            return TT_GetPhraseString('requested_attachment_not_found');
        }

        if (!$visitor->canUploadAndManageAttachments()) {
            return TT_GetPhraseString('do_not_have_permission');
        }
        return true;
    }
}