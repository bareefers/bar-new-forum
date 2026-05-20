<?php
define('MBQ_PROTOCOL','web');
global $tapatalk_cmd;
$tapatalk_cmd = 'update';
define('IN_MOBIQUO', true);
define('TT_ROOT', getcwd() . DIRECTORY_SEPARATOR);

require_once('mobiquoCommon.php');

MbqMain::init(); // frame init
MbqMain::input(); // handle input data
require_once(MBQ_PATH.'IncludeBeforeMbqAppEnv.php');
MbqMain::initAppEnv(); // application environment init
MbqMain::$oMbqConfig->calCfg();
@ ob_start();
require_once(MBQ_PATH . '/logger.php');
require_once(MBQ_FRAME_PATH . '/MbqBaseStatus.php');

use Tapatalk\Bridge;
class MbqStatus extends MbqBaseStatus
{

    public function GetLoggedUserName()
    {
        if(MbqMain::$oCurMbqEtUser != null)
        {
            return MbqMain::$oCurMbqEtUser->loginName->oriValue;
        }
        return 'anonymous';
    }
    protected function GetMobiquoFileSytemDir()
    {
        return TT_ROOT;
    }
    protected function GetMobiquoDir()
    {
        $bridge = Bridge::getInstance();
        $optionModel =  $bridge->getOptionRepo();
        $tp_directory = $optionModel->getOptionById('tp_directory');
        return $tp_directory['option_value'];
    }
    protected function GetApiKey()
    {
        $bridge = Bridge::getInstance();
        $optionModel =  $bridge->getOptionRepo();
        $tp_push_key = $optionModel->getOptionById('tp_push_key');
        return md5($tp_push_key['option_value']);
    }
    protected function GetForumUrl()
    {
        return TT_get_board_url();
    }


    protected function GetPushSlug()
    {
        if (file_exists(MBQ_PATH . '/push/TapatalkPush.php')) {
            require_once(MBQ_PATH . '/push/TapatalkPush.php');
            $tapatalkPush = new \TapatalkPush();
            return $tapatalkPush->get_push_slug();
        }
        return null;
    }

    protected function ResetPushSlug()
    {
        $bridge = Bridge::getInstance();
        $optionModel =  $bridge->getOptionRepo();
        $optionModel->updateOptions(array('push_slug' => 0));
        return true;
    }

    protected function GetBYOInfo()
    {
        $bridge = Bridge::getInstance();
        $options = $bridge->options();
        $TT_update = isset($options->tapatalk_banner_update) ? $options->tapatalk_banner_update : 0;
        $TT_bannerControlData = isset($options->tapatalk_banner_control) ? unserialize($options->tapatalk_banner_control) : array();

        $TT_bannerControlData['banner_enable'] = (isset($TT_bannerControlData['banner_enable'])) ? $TT_bannerControlData['banner_enable'] : 1;
        $TT_bannerControlData['google_enable'] = (isset($TT_bannerControlData['google_enable'])) ? $TT_bannerControlData['google_enable'] : 1;
        $TT_bannerControlData['facebook_enable'] = (isset($TT_bannerControlData['facebook_enable'])) ? $TT_bannerControlData['facebook_enable'] : 1;
        $TT_bannerControlData['twitter_enable'] = (isset($TT_bannerControlData['twitter_enable'])) ? $TT_bannerControlData['twitter_enable'] : 1;
        $TT_bannerControlData['update'] = $TT_update;
        return $TT_bannerControlData;
    }

    protected function GetOtherPlugins()
    {
        $bridge = Bridge::getInstance();
        $addOnModel = $bridge->getAddOnRepo();
        $addOns = $addOnModel->getAllAddOns();
        $result = array();
        /** @var \XF\Entity\AddOn $addOn */
        foreach ($addOns as $addOn) {
            $result[] = array('name'=>$addOn['title'], 'version'=>$addOn['version_string']);
        }
        return $result;
    }

}

$mbqStatus = new MbqStatus();