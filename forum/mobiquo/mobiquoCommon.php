<?php
use Tapatalk\Bridge;

define('MBQ_IN_IT', true);  /* is in mobiquo flag */
define('MBQ_REG_SHUTDOWN', true);  /* register shutdown function flag */
require_once('MbqConfig.php');
require_once('MbqErrorHandle.php');

$mbqDebug = false;
if (isset($_SERVER['HTTP_X_PHPDEBUG'])) {
    if (isset($_SERVER['HTTP_X_PHPDEBUGCODE'])) {
        $code = trim($_SERVER['HTTP_X_PHPDEBUGCODE']);
        if (!class_exists('classTTConnection')) {
            require_once(MBQ_3RD_LIB_PATH . 'classTTConnection.php');
        }
        $connection = new classTTConnection();
        $response = $connection->actionVerification($code, 'PHPDEBUG');
        if ($response) {
            $mbqDebug = $_SERVER['HTTP_X_PHPDEBUG'];
        }
    } else if (file_exists(MBQ_PATH . 'debug.on')) {
        $mbqDebug = $_SERVER['HTTP_X_PHPDEBUG'];
    }
}


define('MBQ_DEBUG', $mbqDebug);  /* is in debug mode flag */
if (MBQ_DEBUG) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting($mbqDebug);
} else {    // Turn off all error reporting
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
}

/**
 * frame main program
 */
Abstract Class MbqMain extends MbqBaseMain
{
    public static function init()
    {
        parent::init();
        self::$oMbqCm->changeWorkDir('../');  /* change work dir to parent dir.Important!!! */
        self::regShutDown();
    }

    public static function getCurrentCmd()
    {
        global $tapatalk_cmd;
        if (isset($_GET['method_name']) && $_GET['method_name']) {     //for more flexibility
            self::$cmd = $_GET['method_name'];
        } else if (isset($_POST['method_name']) && $_POST['method_name']) {    //for upload_attach and other post method
            self::$cmd = $_POST['method_name'];
            foreach ($_POST as $k => $v) {
                self::$input[$k] = $v;
            }
        }
        if (!self::$cmd && isset($_SERVER['PATH_INFO'])) {
            $splitArray = preg_split('[&?]', $_SERVER['PATH_INFO']);
            $pathInfoCmd = $splitArray[0];
            $pathInfoCmd = substr($pathInfoCmd, 1);
            self::$cmd = $pathInfoCmd;
        }
        if (!self::$cmd && isset($tapatalk_cmd)) //for avatar.php
        {
            self::$cmd = $tapatalk_cmd;
        }
        return self::$cmd;
    }

    /**
     * action
     */
    public static function action()
    {
        parent::action();

        if (self::hasLogin()) {
            header('Mobiquo_is_login: true');
        } else {
            header('Mobiquo_is_login: false');
        }

        self::$oMbqConfig->calCfg();    /* you should do some modify within this function in multiple different type applications! */

        if (!self::$oMbqConfig->pluginIsOpen() && self::$cmd != 'get_config') {
            MbqError::alert('', self::$oMbqConfig->getPluginClosedMessage());
        }

        self::$cmd = self::getCurrentCmd();

        if (!self::$cmd) {
            if (empty($_POST) && empty($_GET)) {
                //
            } else {
                MbqError::alert('', "Need not empty cmd!");
            }
        }else {

            self::$cmd = (string)self::$cmd;

            if (!preg_match('/[A-Za-z0-9_]{1,128}/', self::$cmd)) {
                MbqError::alert('', "Need valid cmd!");
            }

            $arr = explode('_', self::$cmd);
            foreach ($arr as &$v) {
                $v = ucfirst(strtolower($v));
            }

            $actionClassName = 'MbqAct' . implode('', $arr);
            if (!self::$oClk->hasReg($actionClassName)) {
                //MbqError::alert('', "Not support action for ".self::$cmd."!", '', MBQ_ERR_NOT_SUPPORT);
                MbqError::alert('', "Sorry!This feature is not available in this forum.Method name:" . self::$cmd, '', MBQ_ERR_NOT_SUPPORT);
            }

            self::$oAct = self::$oClk->newObj($actionClassName);
            self::$oAct->actionImplement(self::$oAct->getInput());
        }
    }

    /**
     * do something before output
     */
    public static function beforeOutPut()
    {
        parent::beforeOutput();
        $bridge = Bridge::getInstance();
        $bridge->shutdown();
    }

}
