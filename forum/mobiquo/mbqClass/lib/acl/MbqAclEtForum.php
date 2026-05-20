<?php
use Tapatalk\Bridge;

defined('MBQ_IN_IT') or exit;

MbqMain::$oClk->includeClass('MbqBaseAclEtForum');

/**
 * forum acl class
 */
Class MbqAclEtForum extends MbqBaseAclEtForum
{

    public function __construct()
    {
    }

    /**
     * judge can get subscribed forum
     *
     * @return  Boolean
     */
    public function canAclGetSubscribedForum()
    {
        return MbqMain::isActiveMember();
    }

    /**
     * judge can subscribe forum
     *
     * @param  MbqEtForum $oMbqEtForum
     * @return  Boolean
     */
    public function canAclSubscribeForum($oMbqEtForum, $receiveEmail)
    {
        /** @var \XF\Entity\Forum $form */
        $form = $oMbqEtForum->mbqBind;
        if (!$form || !($form instanceof \XF\Entity\Forum)) {
            return TT_GetPhraseString('do_not_have_permission');
        }

        if (!$form->canWatch($error) || !MbqMain::isActiveMember()) {
            return TT_GetPhraseString('do_not_have_permission');
        }

        return true;
    }

    /**
     * judge can unsubscribe forum
     *
     * @param  Object $oMbqEtForum
     * @return  Boolean
     */
    public function canAclUnsubscribeForum($oMbqEtForum)
    {
        return $this->canAclSubscribeForum($oMbqEtForum, '');
    }

    public function canAclMarkAllAsRead($oMbqEtForum)
    {
        return MbqMain::isActiveMember();
    }
}