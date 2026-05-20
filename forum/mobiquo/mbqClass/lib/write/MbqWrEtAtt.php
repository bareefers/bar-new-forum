/<?php

use Tapatalk\Bridge;
use \XF\Http\Upload as XFUpload;

defined('MBQ_IN_IT') or exit;

MbqMain::$oClk->includeClass('MbqBaseWrEtAtt');

/**
 * attachment write class
 */
Class MbqWrEtAtt extends MbqBaseWrEtAtt
{

    public function __construct()
    {
    }

    /**
     * upload attachment
     */
    /**
     * @param MbqEtPc|MbqEtForum $oMbqEtForumOrConvPm
     * @param $groupId
     * @param $type
     * @return null|String|MbqEtAtt
     */
    public function uploadAttachment($oMbqEtForumOrConvPm, $groupId, $type)
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
            $contentData['node_id'] = $oMbqEtForumOrConvPm->forumId->oriValue;
        }

        if (empty($groupId))
            $hash = md5(uniqid('', true));
        else
            $hash = $groupId;

        $attachmentRepo = $bridge->getAttachmentRepo();
        $handler = $attachmentRepo->getAttachmentHandler($contentType);
        if (!$handler) {
            return $bridge->noPermissionToString();
        }
        if (!$handler->canManageAttachments($contentData, $error)) {
            return $bridge->noPermissionToString($error);
        }

        $manipulator = new \XF\Attachment\Manipulator($handler, $attachmentRepo, $contentData, $hash);
        $request = $bridge->_request;
        $uploadError = null;
        if ($manipulator->canUpload($uploadError)) {
            /** @var \XF\Http\Upload $upload */
            $upload = $request->getFile('attachment', false);  // name: upload or attachment  [ multiple ]
            if (!$upload) $upload = $request->getFile('upload', false);  // name: upload or attachment
            if (!$upload) $upload = $request->getFile('fileupload', false);

            // handle
            if (!$upload) $upload = $request->getFile('attachment', true, true);
            if (!$upload) $upload = $request->getFile('attachment[]', false, true);
            if (!$upload) $upload = $request->getFile('attachment[0]', false, true);

            // handle APP
            if (!$upload) {
                if (isset($_FILES) && $_FILES && isset($_FILES['attachment']) && is_array($_FILES['attachment'])) {
                    $uploadFile = $_FILES['attachment'];
                    if (isset($uploadFile['tmp_name'][0]) && $uploadFile['tmp_name'][0] && file_exists($uploadFile['tmp_name'][0])) {
                        // no support multiple
                        if (isset($uploadFile['name'][0]) && isset($uploadFile['error'][0])) {
                            $tmpFile = [];
                            $tmpFile['tmp_name'] = $uploadFile['tmp_name'][0];
                            $tmpFile['name'] = $uploadFile['name'][0];
                            $tmpFile['error'] = $uploadFile['error'][0];
                            $upload = new XFUpload($tmpFile['tmp_name'], $tmpFile['name'], $tmpFile['error']);
                        }
                    }
                }
            }

            if ($upload) {
                /** @var \XF\Entity\Attachment $attachment */
                $attachment = $manipulator->insertAttachmentFromUpload($upload, $error);
                if (!$attachment) {
                    return $bridge->errorToString($error);
                }
            }else{
                return 'No file';
            }
        } else if ($uploadError) {
            return $bridge->errorToString($uploadError);
        }

        if (!isset($attachment) || !$attachment) {
            return 'upload error';
        }

        $attachmentId = $attachment->attachment_id;

        $oMbqEtAtt = null;

        /** @var MbqRdEtAtt $oMbqRdEtAtt */
        $oMbqRdEtAtt = MbqMain::$oClk->newObj('MbqRdEtAtt');
        if ($attachment instanceof \XF\Entity\Attachment) {
            $oMbqEtAtt = $oMbqRdEtAtt->initOMbqEtAtt($attachment, array('case' => 'byRow'));
        }else{
            $oMbqEtAtt = $oMbqRdEtAtt->initOMbqEtAtt($attachmentId, array('case' => 'byAttId'));
        }
        if ($oMbqEtAtt && is_a($oMbqEtAtt, 'MbqEtAtt')) {
            $oMbqEtAtt->groupId->setOriValue($hash);
        }

        return $oMbqEtAtt;
    }

    /**
     * delete attachment
     *
     * @param MbqEtAtt $oMbqEtAtt
     * @param null $oMbqEtForum
     * @return mixed
     */
    public function deleteAttachment($oMbqEtAtt, $oMbqEtForum = null)
    {
        $bridge = Bridge::getInstance();

        $attachmentRepo = $bridge->getAttachmentRepo();
        /** @var \XF\Entity\Attachment $attachment */
        $attachment = $oMbqEtAtt->mbqBind;
        if (!$attachment || !($attachment instanceof \XF\Entity\Attachment)) {
            $attachment = $attachmentRepo->getAttachmentById($oMbqEtAtt->attId->oriValue);
        }
        if (!$attachment) {
            return false;
        }

        if ($attachment->isDeleted()) {

        } else {

            $type = $attachment->content_type;
            $hash = $attachment->Data->file_hash;
            switch ($type) {
                case 'post':
                    $contextName = 'post_id';
                    break;
                case 'conversation_message':
                    $contextName = 'message_id';
                    break;
                default:
                    $contextName = 'id';
                    break;
            }
            $context = [$contextName => $attachment->content_id];

            $delete = $attachment->attachment_id;

            $handler = $attachmentRepo->getAttachmentHandler($type);
            if (!$handler) {
                return $bridge->noPermissionToString();
            }

            if (!$handler->canManageAttachments($context, $error)) {
                return $bridge->errorToString($error);
            }

            $manipulator = $bridge->XFManipulator($handler, $attachmentRepo, $context, $hash);

            $manipulator->deleteAttachment($delete);
        }

        return $oMbqEtAtt->groupId->oriValue;
    }
}