<?php
use Tapatalk\Bridge;
use XF\Legacy\Link as XenForoLink;

defined('MBQ_IN_IT') or exit;

MbqMain::$oClk->includeClass('MbqBaseRdEtAtt');

/**
 * attachment read class
 */
Class MbqRdEtAtt extends MbqBaseRdEtAtt {

    public function __construct() {
    }

    public function makeProperty(&$oMbqEtAtt, $pName, $mbqOpt = array()) {
        switch ($pName) {
            default:
            MbqError::alert('', __METHOD__ . ',line:' . __LINE__ . '.' . MBQ_ERR_INFO_UNKNOWN_PNAME . ':' . $pName . '.');
            break;
        }
    }

    public function initOMbqEtAtt($var = null, $mbqOpt = array())
    {
        $return = '';
        $bridge = Bridge::getInstance();

        switch ($mbqOpt['case']) {

            case 'byAttId':
                $return = $this->_initOMbqEtAttByAttId($var, $mbqOpt, $bridge);
                break;

            case 'byRow':
                $return = $this->_initOMbqEtAttByRow($var, $mbqOpt, $bridge);
                break;
        }

        return $return;
    }

    protected function _initOMbqEtAttByAttId($var = null, $mbqOpt = array(), Bridge $bridge)
    {
        $attachmentModel = $bridge->getAttachmentRepo();
        $attachment = $attachmentModel->getAttachmentById($var);
        if($attachment)
        {
            $oMbqAttr = $this->initOMbqEtAtt($attachment, array('case' => 'byRow'));
            return $oMbqAttr;
        }
        return null;
    }

    protected function _initOMbqEtAttByRow($var = null, $mbqOpt = array(), Bridge $bridge)
    {
        /** @var \XF\Entity\Attachment $attachment */
        $attachment = $var;

        $type = isset($attachment['extension']) ? $attachment['extension'] : pathinfo($attachment['filename'], PATHINFO_EXTENSION);;

        switch ($type) {
            case 'gif':
            case 'jpg':
            case 'png':
                $type = MbqBaseFdt::getFdt('MbqFdtAtt.MbqEtAtt.contentType.range.image');;
                break;
            case 'pdf':
                $type =  MbqBaseFdt::getFdt('MbqFdtAtt.MbqEtAtt.contentType.range.pdf');
                break;
        }

        $canViewUrl = $attachment->canView();
        $thumbnail = '';
        if($attachment->getThumbnailUrl())
        {
            $thumbnail = $bridge->XFConvertToAbsoluteUri($attachment->getThumbnailUrl(), true);
        }

        /** @var MbqEtAtt $oMbqEtAtt */
        $oMbqEtAtt = MbqMain::$oClk->newObj('MbqEtAtt');

        $oMbqEtAtt->attId->setOriValue($attachment['attachment_id']);
        $oMbqEtAtt->filtersSize->setOriValue($attachment['file_size']);
        $oMbqEtAtt->uploadFileName->setOriValue($attachment['filename']);
        $oMbqEtAtt->contentType->setOriValue($type);

        $oMbqEtAtt->url->setOriValue($bridge->XFConvertToAbsoluteUri(XenForoLink::buildPublicLink('attachments', $attachment), true));
        $oMbqEtAtt->thumbnailUrl->setOriValue($thumbnail);
//        $oMbqEtAtt->canViewThumbnailUrl->setOriValue($oMbqEtAtt->url->oriValue == $oMbqEtAtt->thumbnailUrl->oriValue ? $canViewUrl : $oMbqEtAtt->thumbnailUrl->oriValue != '');
        $oMbqEtAtt->canViewUrl->setOriValue($canViewUrl);
        $oMbqEtAtt->canViewThumbnailUrl->setOriValue($canViewUrl);
        $oMbqEtAtt->mbqBind = $attachment;
        return $oMbqEtAtt;
    }

}