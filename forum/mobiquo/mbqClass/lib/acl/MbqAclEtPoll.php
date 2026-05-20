<?php

defined('MBQ_IN_IT') or exit;

MbqMain::$oClk->includeClass('MbqBaseAclEtPoll');

/**
 * poll acl class
 */
Class MbqAclEtPoll extends MbqBaseAclEtPoll {
    
    public function __construct() {
    }
    
    /**
     * judge getvoteslist
     *
     * @return  Boolean
     */
    public function canAclGetVotesList() {
      
    }

    /**
     * judge vote
     *
     * @return  Boolean
     */
    public function canAclVote($oMbqEtPoll) {
      
    }

    /**
     * judge editpoll
     *
     * @return  Boolean
     */
    public function canAclEditPoll($oMbqEtPoll) {
      
    }
}
