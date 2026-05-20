<?php

defined('MBQ_IN_IT') or exit;

MbqMain::$oClk->includeClass('MbqBaseActSyncUser');

Class MbqActSyncUser extends MbqBaseActSyncUser {

    public function __construct() {
        parent::__construct();
    }

    /**
     * action implement
     */
    public function actionImplement($in) {
        include_once(MBQ_3RD_LIB_PATH . 'classTTCipherEncrypt.php');

        $userIDs = explode(',', $in->userId);
        $cipher = new TT_Cipher();
      
        $this->data = array(
            'result'  => false,
            'encrypt' => false,
            'users'   => null,
        );
    }

}
