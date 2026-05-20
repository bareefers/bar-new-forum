<?php

defined('MBQ_IN_IT') or exit;

MbqMain::$oClk->includeClass('MbqBaseActGetConfig');

/**
 * get_config action
 */
Class MbqActGetConfig extends MbqBaseActGetConfig {
    
    public function __construct() {
        parent::__construct();
    }
    
    /**
     * action implement
     */
    public function actionImplement($in) {
        parent::actionImplement($in);
    }
  
}
