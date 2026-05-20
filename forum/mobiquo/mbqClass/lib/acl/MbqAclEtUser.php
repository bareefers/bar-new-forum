<?php

use Tapatalk\Bridge;

defined('MBQ_IN_IT') or exit;

MbqMain::$oClk->includeClass('MbqBaseAclEtUser');

/**
 * user acl class
 */
Class MbqAclEtUser extends MbqBaseAclEtUser
{

    public function __construct()
    {
    }

    /**
     * judge can get online users
     *
     * @return  Boolean
     */
    public function canAclGetOnlineUsers()
    {
        return true;
    }

    /**
     * judge can get online users
     *
     * @return  Boolean
     */
    public function canAclGetIgnoredUsers()
    {
        return true;
    }

    /**
     * judge can m_ban_user
     * here,this function is just the same to m_mark_as_spam
     * @param  Object $oMbqEtUser
     * @param  Integer $mode
     * @return  Boolean
     */
    public function canAclMBanUser($oMbqEtUser, $mode)
    {
        $bridge = Bridge::getInstance();
        $visitor = $bridge::visitor();

        if (!$visitor->hasAdminPermission('ban')) {
            return TT_GetPhraseString('security_error_occurred');
        }
        return true;
    }

    /**
     * judge can m_mark_as_spam
     *
     * @return  Boolean
     */
    public function canAclMMarkAsSpam($oMbqEtUser)
    {
        $bridge = Bridge::getInstance();
        $visitor = $bridge::visitor();

        if (!$visitor->canCleanSpam()) {
            return TT_GetPhraseString('security_error_occurred');
        }

        return true;
    }

    /**
     * judge can m_ban_user
     *
     * @return  Boolean
     */
    public function canAclMUnbanUser($oMbqEtUser)
    {
        $bridge = Bridge::getInstance();
        $visitor = $bridge::visitor();

        if (!$visitor->hasPermission('general', 'banUser')) {
            return TT_GetPhraseString('security_error_occurred');
        }
        return true;
    }

    /**
     * judge can update_password
     *
     * @return Boolean
     */
    public function canAclUpdatePassword()
    {
        return MbqMain::hasLogin();
    }

    /**
     * judge can update_email
     *
     * @return Boolean
     */
    public function canAclUpdateEmail()
    {
        $bridge = Bridge::getInstance();
        $visitor = $bridge::visitor();
        $auth = $visitor->Auth->getAuthenticationHandler();
        if (!$auth) {
            return false;
        }
//
//        if(isset($visitor['is_admin']) && $visitor['is_admin'])
//        {
//            return false;
//        }
        return MbqMain::hasLogin();
    }

    /**
     * judge can upload avatar
     *
     * @return Boolean
     */
    public function canAclUploadAvatar()
    {
        return MbqMain::hasLogin();
    }

    /**
     * judge can searc_user
     *
     * @return Boolean
     */
    public function canAclSearchUser()
    {
        $bridge = Bridge::getInstance();
        $visitor = $bridge::visitor();

        return $visitor->canSearch();
    }

    /**
     * judge can get_recommended_user
     *
     * @return Boolean
     */
    public function canAclGetRecommendedUser()
    {
        return MbqMain::hasLogin();
    }

    /**
     * judge can ignore_user
     *
     * @return Boolean
     */
    public function canAclIgnoreUser($oMbqEtUser, $mode)
    {
        return MbqMain::hasLogin();
    }
}