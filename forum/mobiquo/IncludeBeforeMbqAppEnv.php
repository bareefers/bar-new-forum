<?php
use Tapatalk\Bridge;
use XF\Phrase as XenForoPhrase;

defined('MBQ_IN_IT') or exit;
/**
 * This file is not needed by default!
 * Run this first before call MbqMain::initAppEnv() when you need!

 */
/* Please write any codes you need in the following area before call MbqMain::initAppEnv()! */
$startTime = microtime(true);

$mobiquo_root_path = dirname(__FILE__) . '/';
$xf_root_path = dirname(dirname(__FILE__));

define('MOBIQUO_DIR', $mobiquo_root_path);

define('SCRIPT_ROOT', $xf_root_path . '/'); // last  have /
if (DIRECTORY_SEPARATOR == '/')
{
    define('FORUM_ROOT', 'http://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['SCRIPT_NAME']).'/');
}
else
{
    define('FORUM_ROOT', 'http://'.$_SERVER['HTTP_HOST'].str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])).'/');
}

//fix for eu_cookie plugin
$GLOBALS['eucookie_set'] = 1;
//end of fix for eu_cookie plugin

$phpVersion = phpversion();
if (version_compare($phpVersion, '5.4.0', '<'))
{
    die("PHP 5.4.0 or newer is required. $phpVersion does not meet this requirement. Please ask your host to upgrade PHP.");
}

require($xf_root_path . '/src/XF.php');

XF::start($xf_root_path);   // $xf_root_path need add xf code path !!!!!! (last not /)
$app = XF::setupApp('XF\Pub\App');
$app->setup();
$app->start();

MbqMain::regShutDown();  // handle error
if (class_exists('MbqErrorHandle')) {
    set_error_handler(['MbqErrorHandle', 'handlePhpError']);
    set_exception_handler(['MbqErrorHandle', 'handleException']);
}

try
{
    $bridge = Bridge::setInstance($app, $app->request());
    $bridge->setAction(MbqMain::getCurrentCmd());
    $bridge->setUserParams('useragent', isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : "");
    $bridge->initBasePlugin();
}
catch (\Exception $e)
{
    // head send is_login
    $visitor = \XF::visitor();
    if ($visitor->user_id) {
        header('Mobiquo_is_login: true');
    } else {
        header('Mobiquo_is_login: false');
    }
    $reason = [];
    if ($e instanceof \XF\Mvc\Reply\Exception && $bridge->getAction() == "get_config" && $e->getReply()->getResponseCode() == 503) {
        //let code run and return get_config payload for closed board
    }
    else
    {
        if ($e instanceof \XF\Mvc\Reply\Exception) {
            $xfReplay = $e->getReply();
            if ($xfReplay instanceof \XF\Mvc\Reply\Redirect) {
                if ($xfReplay->getUrl()) {
                    /** @var \XF\Mvc\Router $publicRouter */
                    $publicRouter = $app->container('router.public');
                    $fullIndex = $publicRouter->buildLink('full:index');
                    $reason = [
                        'reason' => MBQ_ERR_REDIRECT_WEB_BROWSER,
                        'result_url' => $fullIndex,
                    ];
                    if (strpos($xfReplay->getUrl(), 'misc/accept-privacy-policy')) {
                        $reason['result_url'] = $publicRouter->buildLink('full:login'); // full:misc/accept-privacy-policy
                        MbqError::alert('', 'Please go to the website of the forum to read and accept Privacy Policy before continuing', $reason);
                    }elseif (strpos($xfReplay->getUrl(), 'misc/accept-terms')) {
                        $reason['result_url'] = $publicRouter->buildLink('full:login'); // full:misc/accept-terms
                        MbqError::alert('', 'Please go to the website of the forum to read and accept terms and rules before continuing',$reason);
                    }
                }
                if ($xfReplay->getMessage()) {
                    $errorMsg = $xfReplay->getMessage();
                    if ($errorMsg instanceof \XF\Phrase) {
                        $errorMsg = $errorMsg->render();
                    }elseif ($errorMsg instanceof \XF\PreEscaped) {
                        $errorMsg = $errorMsg->value;
                    }
                    MbqError::alert('', $errorMsg, $reason?:[]);
                }
            }elseif ($xfReplay instanceof \XF\Mvc\Reply\Error) {
                $errors = $xfReplay->getErrors();
                if (is_array($errors) && !empty($lastError = end($errors))) {
                    $lastErrorMsg = $lastError;
                    if ($lastError instanceof \XF\Phrase) {
                        $lastErrorMsg = $lastError->render();
                    }
                    MbqError::alert('', $lastErrorMsg);
                }
            }elseif ($xfReplay instanceof \XF\Mvc\Reply\View) {
                $viewParams = $xfReplay->getParams();
                if (isset($viewParams['error']) && ($viewError = $viewParams['error'])) {
                    if ($viewError instanceof \XF\Phrase) {
                        if ($viewError->getName() == 'login_required') {
                            $reason = [
                                'reason' => MBQ_ERR_LOGIN_REQUIRED,
                            ];
                        }
                        $errorMsg = $viewError->render();
                    }elseif ($viewError instanceof \XF\PreEscaped) {
                        $errorMsg = $viewError->value;
                    }else{
                        $errorMsg = $e->getMessage();
                    }
                    MbqError::alert('', $errorMsg, $reason?:[]);
                }
            }
            if ($e->getMessage()) {
                MbqError::alert('', $e->getMessage(), $reason?:[]);
            }
        }
        MbqError::alert('', 'Unknow error', $reason?:[]);
    }
}

$visitor = \XF::visitor();
$user_id = $visitor->user_id;
date_default_timezone_set($visitor->timezone);

if($user_id != 0)
{
    $oMbqRdEtUser = MbqMain::$oClk->newObj('MbqRdEtUser');
    $oMbqRdEtUser->initOCurMbqEtUser($user_id);
}

function tp_get_forum_icon($id, $type = 'forum', $lock = false, $new = false)
{
    if (!in_array($type, array('link', 'category', 'forum')))
        $type = 'forum';

    $icon_name = $type;
    if ($type != 'link')
    {
        if ($lock) $icon_name .= '_lock';
        if ($new) $icon_name .= '_new';
    }

    $icon_map = array(
        'category_lock_new' => array('category_lock', 'category_new', 'lock_new', 'category', 'lock', 'new'),
        'category_lock'     => array('category', 'lock'),
        'category_new'      => array('category', 'new'),
        'lock_new'          => array('lock', 'new'),
        'forum_lock_new'    => array('forum_lock', 'forum_new', 'lock_new', 'forum', 'lock', 'new'),
        'forum_lock'        => array('forum', 'lock'),
        'forum_new'         => array('forum', 'new'),
        'category'          => array(),
        'forum'             => array(),
        'lock'              => array(),
        'new'               => array(),
        'link'              => array(),
    );

    $final = !isset($icon_map[$icon_name]) || empty($icon_map[$icon_name]);

    if ($url = tp_get_forum_icon_by_name($id, $icon_name, $final))
        return $url;

    foreach ($icon_map[$icon_name] as $sub_name)
    {
        $final = !isset($icon_map[$sub_name]) || empty($icon_map[$sub_name]);
        if ($url = tp_get_forum_icon_by_name($id, $sub_name, $final))
            return $url;
    }

    return '';
}

function tp_get_forum_icon_by_name($id, $name, $final)
{
    global $boarddir, $boardurl;

    $tapatalk_forum_icon_dir = './forum_icons/';
    $tapatalk_forum_icon_url = FORUM_ROOT.'mobiquo/forum_icons/';

    $filename_array = array(
    $name.'_'.$id.'.png',
    $name.'_'.$id.'.jpg',
    $id.'.png', $id.'.jpg',
    $name.'.png',
    $name.'.jpg',
    );

    foreach ($filename_array as $filename)
    {
        if (file_exists($tapatalk_forum_icon_dir.$filename))
        {
            return $tapatalk_forum_icon_url.$filename;
        }
    }

    if ($final) {
        if (file_exists($tapatalk_forum_icon_dir.'default.png'))
            return $tapatalk_forum_icon_url.'default.png';
        else if (file_exists($tapatalk_forum_icon_dir.'default.jpg'))
            return $tapatalk_forum_icon_url.'default.jpg';
    }

    return '';
}
/**
 *
 * Simulate XenForo_Helper_Criteria but as cannot initilize as Xenforo, we simly match nodes rule.
 */
function TT_pageMatchesCriteria($criteria, $node)
{
    $breadCrumbs = $node->getBreadcrumbs();
    if (!$criteria = TT_unserializeCriteria($criteria))
    {
        return true;
    }

    foreach ($criteria AS $criterion)
    {
        $data = $criterion['data'];

        switch ($criterion['rule'])
        {
            // browsing within one of the specified nodes
            case 'nodes':
                {

                    if (!isset($data['node_ids']) || empty($data['node_ids']))
                    {
                            return false; // no node ids specified
                        }
                    if(is_array($breadCrumbs) && !empty($breadCrumbs) && is_array($data['node_ids']))
                    {
                        foreach ($breadCrumbs as $parent_node)
                        {
                            if(in_array($parent_node['node_id'], $data['node_ids']))
                                return true;
                        }
                        return false;
                    }
                }
                break;
        }

    }
    return true;
}

function TT_unserializeCriteria($criteria)
{
    if (!is_array($criteria))
    {
        $criteria = @unserialize($criteria);
        if (!is_array($criteria))
        {
            return array();
        }
    }

    return $criteria;
}

function TT_cutstr($string, $length)
{
    if(strlen($string) <= $length) {
        return $string;
    }

    $string = str_replace(array('&amp;', '&quot;', '&lt;', '&gt;'), array('&', '"', '<', '>'), $string);

    $strcut = '';

    $n = $tn = $noc = 0;
    while($n < strlen($string)) {

        $t = ord($string[$n]);
        if($t == 9 || $t == 10 || (32 <= $t && $t <= 126)) {
            $tn = 1; $n++; $noc++;
        } elseif(194 <= $t && $t <= 223) {
            $tn = 2; $n += 2; $noc += 2;
        } elseif(224 <= $t && $t <= 239) {
            $tn = 3; $n += 3; $noc += 2;
        } elseif(240 <= $t && $t <= 247) {
            $tn = 4; $n += 4; $noc += 2;
        } elseif(248 <= $t && $t <= 251) {
            $tn = 5; $n += 5; $noc += 2;
        } elseif($t == 252 || $t == 253) {
            $tn = 6; $n += 6; $noc += 2;
        } else {
            $n++;
        }

        if($noc >= $length) {
            break;
        }

    }
    if($noc > $length) {
        $n -= $tn;
    }

    $strcut = TT_wholeWordTrim($string, $n, 0, "");

    return $strcut;
}

function TT_wholeWordTrim($string, $maxLength, $offset = 0, $elipses = '...')
{
    //TODO: this may need a handler for language independence and some form of error correction for bbcode

    if ($offset)
    {
        $string = preg_replace('/^\S*\s+/s', '', utf8_substr($string, $offset));
    }

    $strLength = utf8_strlen($string);

    if ($maxLength > 0 && $strLength > $maxLength)
    {
        $string = utf8_substr($string, 0, $maxLength);
        $string = strrev(preg_replace('/^\S*\s+/s', '', strrev($string))) . $elipses;
    }

    if ($offset)
    {
        $string = $elipses . $string;
    }

    return $string;
}
function TT_get_avatar($user, $size = 'm')
{
    $bridge = Bridge::getInstance();

    $avatar = $user->getAvatarUrl($size, null, true);

    if (!$avatar) {

        if(isset($user['custom_fields'])) {
            $customFields = @unserialize($user['custom_fields']);

            if(isset($customFields['tapatalk_avatar_url'])) {
                return $customFields['tapatalk_avatar_url'];
            }
        }
    }
    if ($avatar) $avatar = $bridge->_request->convertToAbsoluteUri($avatar);

    return $avatar;
}


function TT_get_prefix_name($id)
{
    static $prefixModel;

    if (empty($prefixModel))
    {
        $bridge = Bridge::getInstance();
        $prefixModel = $bridge->getThreadPrefixRepo();
    }

    $prefix = '';
    if (!empty($id))
    {
        $prefix = $prefixModel->getPrefixTitlePhraseName($id);
    }

    return $prefix;
}

function TT_addNameValue($name, $value, &$list){
    $list[] = array(
        'name'  => $name,
        'value' => $value
    );
}
function TT_get_usertype_by_item($userid)
{
    $bridge = Bridge::getInstance();
    $userModel = $bridge->getUserRepo();
    $member = $userModel->findUserById($userid);
    $state = $member['user_state'];
    if( $member['is_banned'] == 1)
    {
        return 'banned';
    }
    else if($state == 'email_confirm' || $state == 'email_confirm_edit' || $state == 'Email invalid (bounced)')
    {
        return 'inactive';
    }
    else if($state == 'moderated')
    {
        return 'unapproved';
    }
    else if($member['is_admin'] == 1)
    {
        return 'admin';
    }
    else if($member['is_moderator'] == 1)
    {
        return 'mod';
    }
    return 'normal';
}
function TT_forum_exclude($nodeId, $allNodes, $nodeModel)
{
    if(in_array($nodeId, $allNodes))
    {
        $childNodes = $nodeModel->getChildNodesForNodeIds(array($nodeId));

        foreach($allNodes as $index => $node)
            if($node == $nodeId)
                unset($allNodes[$index]);

        foreach($childNodes as $_nodeid => $_node)
            $allNodes = TT_forum_exclude($_nodeid, $allNodes, $nodeModel);
    }

    return $allNodes;
}

function TT_forum_include($nodeId, $allNodes, $nodeModel, $selectedNodes)
{
    if(in_array($nodeId, $allNodes))
    {

        $childNodes = $nodeModel->getChildNodesForNodeIds(array($nodeId));

        if(in_array($nodeId, $allNodes) && !in_array($nodeId, $selectedNodes))
        {

            $selectedNodes[] = $nodeId;
        }

        foreach($childNodes as $_nodeid => $_node)
        {

            $selectedNodes = TT_forum_include($_nodeid, $allNodes, $nodeModel, $selectedNodes);
        }
    }

    return $selectedNodes;
}

function TT_GetPhraseString($string, $params = array())
{
    $XenForoPhrase = \XF::phrase($string, $params);
    return $XenForoPhrase->render();
}
function TT_GetXenforoPhraseString(XenForoPhrase $XenForoPhrase, $params = array())
{
    return $XenForoPhrase->render();
}

function TT_get_board_url()
{
    $bridge = Bridge::getInstance();
    return $bridge::getBoardUrl();
}
