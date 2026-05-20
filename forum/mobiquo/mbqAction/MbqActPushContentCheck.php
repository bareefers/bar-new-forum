<?php

defined('MBQ_IN_IT') or exit;

MbqMain::$oClk->includeClass('MbqBaseActPushContentCheck');

Class MbqActPushContentCheck extends MbqBaseActPushContentCheck {
    
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