<?php

use Tapatalk\Bridge;

defined('MBQ_IN_IT') or exit;

MbqMain::$oClk->includeClass('MbqBaseActGetContact');

Class MbqActGetContact extends MbqBaseActGetContact
{

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * action implement
     *
     * @param $in
     */
    public function actionImplement($in)
    {
        $email = '';//get user email for $in->userId
        if (isset($in->userId) && $in->userId) {
            $userId = (int)$in->userId;
            $bridge = Bridge::getInstance();
            $user = $bridge->getUserRepo()->findUserById($userId);
            $email = $user->email;
        }

        $this->data = array(
            'result' => true,
            'email' => $email
        );
    }

}
