<?php

defined('MBQ_IN_IT') or exit;

MbqMain::$oClk->includeClass('MbqBaseRdEtPoll');

/**
 * poll read class
 */
Class MbqRdEtPoll extends MbqBaseRdEtPoll {
    
    public function __construct() {
    }
    
    public function makeProperty(&$oMbqEtPoll, $pName, $mbqOpt = array()) {
        switch ($pName) {
            default:
            MbqError::alert('', __METHOD__ . ',line:' . __LINE__ . '.' . MBQ_ERR_INFO_UNKNOWN_PNAME . ':' . $pName . '.');
            break;
        }
    }

    public function initOMbqEtPoll($var, $mbqOpt) {
      
    }
}
