<?php

if(!defined('MBQ_PROTOCOL'))
{
    define('MBQ_PROTOCOL','xmlrpc');
}
define('IN_MOBIQUO', true);
define('TT_ROOT', getcwd() . DIRECTORY_SEPARATOR);

require_once('mobiquoCommon.php');
require_once(MBQ_PATH . '/logger.php');
TT_InitAccessLog();
MbqMain::init(); // frame init
MbqMain::input(); // handle input data
require_once(MBQ_PATH.'IncludeBeforeMbqAppEnv.php');
MbqMain::initAppEnv(); // application environment init
@ ob_start();
TT_InitErrorLog();
MbqMain::action(); // main program handle
MbqMain::beforeOutput(); // do something before output
MbqMain::output(); // handle output data
