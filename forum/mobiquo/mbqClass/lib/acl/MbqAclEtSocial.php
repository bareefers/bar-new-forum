<?php

defined('MBQ_IN_IT') or exit;

MbqMain::$oClk->includeClass('MbqBaseAclEtSocial');

/**
 * forum acl class
 */
Class MbqAclEtSocial extends MbqBaseAclEtSocial {
    
    public function __construct() {
    }
    
    /**
     * judge getalert
     *
     * @return  Boolean
     */
    public function canAclGetAlert() {
       return MbqMain::hasLogin();
    }
}
