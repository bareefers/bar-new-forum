<?php

use Tapatalk\Bridge;
use XF\Legacy\DataWriter as XenForo_DataWriter;

error_reporting(E_ALL & ~E_NOTICE);
define('IN_MOBIQUO', 1);
if(isset($_GET['checkAccess']))
{
    echo "yes";
    exit;
}
define('SCRIPT_ROOT', (!isset($_SERVER['SCRIPT_FILENAME']) || empty($_SERVER['SCRIPT_FILENAME'])) ? '../' : dirname(dirname($_SERVER['SCRIPT_FILENAME'])).'/');
if (DIRECTORY_SEPARATOR == '/')
    define('FORUM_ROOT', 'http://'.$_SERVER['HTTP_HOST'].dirname(dirname($_SERVER['SCRIPT_NAME'])));
else
    define('FORUM_ROOT', 'http://'.$_SERVER['HTTP_HOST'].str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))));
require_once './mbqFrame/3rdLib/classTTConnection.php';

$xf_root_path = dirname(dirname(__FILE__));
if (!file_exists($xf_root_path . '/src/XF.php')) {
    die('src/XF.php not find');
}
require($xf_root_path . '/src/XF.php');

XF::start($xf_root_path);   // $xf_root_path need add xf code path !!!!!! (last not /)
$app = XF::setupApp('XF\Pub\App');
$app->setup();
$app->start();

$bridge = Bridge::setInstance($app, $app->request());
$bridge->setAction('');
$bridge->setUserParams('useragent', isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : "");
$bridge->initBridgeWithPush();

// Make sure deprecated warnings go back off due to XF override
$errorReporting = ini_get('error_reporting') &~ 8096;
@error_reporting($errorReporting);
@ini_set('error_reporting', $errorReporting);
// Hide errors from normal display - will be cleanly output via shutdown function.
// (No need to turn off errors when not debugging like in normal Tapatalk plugins - all are passed through cleanly via XMLRPC result_text.)
ini_set('display_errors', 0);
//
// Revert XenForo's error handler also
restore_error_handler();

$output = 'Tapatalk Push Notification Status Monitor<br><br>';
$output .= 'Push notification test: <b>';
$options = $bridge->options();

if(isset($options->tp_push_key) && !empty($options->tp_push_key))
{
    $push_key = $options->tp_push_key;
    $return_status = do_post_request(array('test' => 1, 'key' => $push_key), true);
    if ($return_status === '1')
        $output .= 'Success</b>';
    else
        $output .= 'Failed</b><br />'.$return_status;
}
else
{
    $output .= 'Failed</b><br /> Please set Tapatalk API Key at forum option/setting<br />';
}
//    $ip =  do_post_request(array('ip' => 1), true);

$table_exist = false;
try{
    /** @var \Tapatalk\XF\Legacy\DataWriter $tapatalk_user_writer */
    $tapatalk_user_writer = XenForo_DataWriter::create('\Tapatalk\XF\Legacy\DataWriter');
    $tapatalk_user_model = $tapatalk_user_writer->getTapatalkUserModel();
    $table_exist = true;
}catch(\Exception $e)
{
    $table_exist = false;
}

$output .="<br>Current forum url: ".FORUM_ROOT."<br>";
//    $output .="Current server IP: ".$ip."<br>";
$output .="Tapatalk user table existence:".($table_exist ? "Yes" : "No")."<br>";

if(isset($options->push_slug))
{
    if (!is_array($options->push_slug)) {
        $push_slug = unserialize($options->push_slug);
    }else{
        $push_slug = $options->push_slug;
    }
    if(!empty($push_slug) && is_array($push_slug))
        $output .= 'Push Slug Status : ' . ((isset($push_slug[5]) && $push_slug[5] == 1) ? 'Stick' : 'Free') . '<br />';
    if(isset($_GET['slug']))
        $output .= 'Push Slug Value: ' . var_export($push_slug, true) . "<br /><br />";
}
$output .="<br>
<a href=\"http://tapatalk.com/api.php\" target=\"_blank\">Tapatalk API for Universal Forum Access</a> | <a href=\"http://tapatalk.com/build.php\" target=\"_blank\">Build Your Own</a><br>
For more details, please visit <a href=\"http://tapatalk.com\" target=\"_blank\">http://tapatalk.com</a>";
echo $output;


function do_post_request($data, $pushTest = false)
{
    $push_url = 'http://push.tapatalk.com/push.php';
    $connection = new classTTConnection();
    $res = $connection->getContentFromSever($push_url, $data, 'post');
    return $res;
}
