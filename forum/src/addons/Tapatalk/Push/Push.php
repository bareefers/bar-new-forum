<?php

use Tapatalk\Bridge;

if (!defined('SCRIPT_ROOT')) {
    define('SCRIPT_ROOT', empty($_SERVER['SCRIPT_FILENAME']) ? '../../../../' : dirname(dirname($_SERVER['SCRIPT_FILENAME'])) . '/');
}

class Tapatalk_Push_Push
{
    public function __construct()
    {
    }

    public static function tt_push_clean($str)
    {
        $str = strip_tags($str);
        $str = trim($str);
        return html_entity_decode($str, ENT_QUOTES, 'UTF-8');
    }

    public static function tapatalk_push_reply($action, $post, $thread)
    {
        if (!$post['post_id'] || !$thread['thread_id'] || (!function_exists('curl_init') && !ini_get('allow_url_fopen'))) {
            return false;
        }

        $push_datas = self::analysisPushAction($action, $post, $thread);

        foreach ($push_datas as $push_data) {
            self::do_push_request($push_data);
        }

    }

    public static function tapatalk_push_conv($conver_msg)
    {
        if (isset($conver_msg['recepients']) && !empty($conver_msg['recepients']) && $conver_msg['title'] && (function_exists('curl_init') || ini_get('allow_url_fopen'))) {
            $visitor = XenForo_Visitor::getInstance();
            $options = XenForo_Application::get('options');
            if (isset($options->tapatalk_push_notifications) && $options->tapatalk_push_notifications == 1) {
                $convModel = XenForo_Model::create('XenForo_Model_Conversation');
                $message = $convModel->getConversationMessageById($conver_msg['last_message_id']);
                $myOptions = array(
                    'states' => array(
                        'returnHtml' => true,
                    ),
                );
                $content = self::cleanPost($message['message'], $myOptions);
            }

            $tapatalkUser_model = XenForo_Model::create('Tapatalk_Model_TapatalkUser');
            $spcTpUsers = $tapatalkUser_model->getAllPmOpenTapatalkUsersInArray($conver_msg['recepients']);
            $title = Tapatalk_Push_Push::tt_push_clean($conver_msg['title']);
            $author = Tapatalk_Push_Push::tt_push_clean($conver_msg['conv_sender_name']);
            $boardurl = self::tt_get_board_url();
            if (empty($spcTpUsers)) {
                return;
            }
            $tpu_ids = '';
            foreach ($spcTpUsers as $tpu_id => $tapatalk_user) {
                $tpu_ids .= $tpu_id . ',';
            }
            $tpu_ids = substr($tpu_ids, 0, strlen($tpu_ids) - 1);
            $ttp_data = array(
                'url' => $boardurl,
                'userid' => $tpu_ids,
                'type' => 'conv',
                'id' => $conver_msg['conversation_id'],
                'subid' => $conver_msg['reply_count'] + 1,
                'mid' => $conver_msg['last_message_id'],
                'title' => $title,
                'author' => $author,
                'authorid' => $conver_msg['conv_sender_id'],
                'author_postcount' => $visitor['message_count'],
                'dateline' => time(),
            );
            if (isset($content) && !empty($content)) {
                $ttp_data['content'] = $content;
            }

            $options = XenForo_Application::get('options');
            if (isset($options->tp_push_key) && !empty($options->tp_push_key)) {
                $ttp_data['key'] = $options->tp_push_key;
            }

            $return_status = self::do_push_request($ttp_data);
        }
    }

    public static function can_view_post($post_id, $user_id)
    {
        $userModel = XenForo_Model::create('XenForo_Model_User');
        $user = $userModel->getUserById($user_id);
        if ($user) {
            $user = $userModel->prepareUser($user);

            $postModel = XenForo_Model::create('XenForo_Model_Post');
            $post = $postModel->getPostById($post_id);
            if ($post) {
                $thread_id = $post['thread_id'];
                if ($thread_id) {
                    $thread = XenForo_Model::create('XenForo_Model_Thread')->getThreadById($thread_id);
                    if ($thread) {
                        $forum_id = $thread['node_id'];
                        if ($forum_id) {
                            $forumModel = XenForo_Model::create('XenForo_Model_Forum');
                            $forum = $forumModel->getForumById($forum_id, array(
                                'permissionCombinationId' => $user['permission_combination_id']
                            ));
                            if ($forum) {
                                $permissions = XenForo_Permission::unserializePermissions($forum['node_permission_cache']);
                                if ($postModel->canViewPost($post, $thread, $forum, $null, $permissions, $user)) {
                                    return true;
                                }
                            }
                        }
                    }
                }
            }
        }

        return false;
    }

    public static function push_slug($push_v, $method = 'NEW')
    {
        if (empty($push_v))
            $push_v = serialize(array());
        $push_v_data = unserialize($push_v);
        $current_time = time();
        if (!is_array($push_v_data))
            return serialize(array(2 => 0, 3 => 'Invalid v data', 5 => 0));
        if ($method != 'CHECK' && $method != 'UPDATE' && $method != 'NEW')
            return serialize(array(2 => 0, 3 => 'Invalid method', 5 => 0));

        if ($method != 'NEW' && !empty($push_v_data)) {
            $push_v_data[8] = $method == 'UPDATE';
            if ($push_v_data[5] == 1) {
                if ($push_v_data[6] + $push_v_data[7] > $current_time)
                    return $push_v;
                else
                    $method = 'NEW';
            }
        }

        if ($method == 'NEW' || empty($push_v_data)) {
            $push_v_data = array();     //Slug
            $push_v_data[0] = 3;        //        $push_v_data['max_times'] = 3;                //max push failed attempt times in period
            $push_v_data[1] = 300;      //        $push_v_data['max_times_in_period'] = 300;     //the limitation period
            $push_v_data[2] = 1;        //        $push_v_data['result'] = 1;                   //indicate if the output is valid of not
            $push_v_data[3] = '';       //        $push_v_data['result_text'] = '';             //invalid reason
            $push_v_data[4] = array();  //        $push_v_data['stick_time_queue'] = array();   //failed attempt timestamps
            $push_v_data[5] = 0;        //        $push_v_data['stick'] = 0;                    //indicate if push attempt is allowed
            $push_v_data[6] = 0;        //        $push_v_data['stick_timestamp'] = 0;          //when did push be sticked
            $push_v_data[7] = 600;      //        $push_v_data['stick_time'] = 600;             //how long will it be sticked
            $push_v_data[8] = 1;        //        $push_v_data['save'] = 1;                     //indicate if you need to save the slug into db
            return serialize($push_v_data);
        }

        if ($method == 'UPDATE') {
            $push_v_data[4][] = $current_time;
        }
        $sizeof_queue = count($push_v_data[4]);

        $period_queue = $sizeof_queue > 1 ? ($push_v_data[4][$sizeof_queue - 1] - $push_v_data[4][0]) : 0;

        $times_overflow = $sizeof_queue > $push_v_data[0];
        $period_overflow = $period_queue > $push_v_data[1];

        if ($period_overflow) {
            if (!array_shift($push_v_data[4]))
                $push_v_data[4] = array();
        }

        if ($times_overflow && !$period_overflow) {
            $push_v_data[5] = 1;
            $push_v_data[6] = $current_time;
        }

        return serialize($push_v_data);
    }

    public static function do_push_request($data, $pushTest = false)
    {
        $options = XenForo_Application::get('options');
        if (isset($options->tp_push_key) && !empty($options->tp_push_key))
            $ttp_data['key'] = $options->tp_push_key;
        if (isset($options->tp_push_key) && !empty($options->tp_push_key)) {
            $boardurl = XenForo_Application::get('options')->boardUrl;
            $boardurl = urlencode($boardurl);
            if (!class_exists('TapatalkPush')) {
                $tapatalk_dir_name = XenForo_Application::get('options')->tp_directory;
                if (empty($tapatalk_dir_name)) $tapatalk_dir_name = 'mobiquo';
                $tapatalk_dir_name = XenForo_Application::getInstance()->getRootDir() . '/' . $tapatalk_dir_name;
                include_once($tapatalk_dir_name . '/push/TapatalkPush.php');
            }
            $TapatalkPush = new \TapatalkPush($options->tp_push_key, $boardurl);
            $TapatalkPush->do_push_request($data);
        }
        /*
        $push_url = 'http://push.tapatalk.com/push.php';

        $optionModel = XenForo_Model::create('XenForo_Model_Option');
        $visitor = XenForo_Visitor::getInstance();

        $forum_root = dirname(dirname(dirname(dirname(__FILE__))));
        $option = XenForo_Application::get('options');
        $tapatalk_dir_name = $option->tp_directory;
        if (!class_exists('classTTConnection')){
            include_once($forum_root.'/'.$tapatalk_dir_name.'/lib/classTTConnection.php');
        }

        if($pushTest){
            $connection = new classTTConnection();
            $error = $connection->errors;
            return $connection->getContentFromSever($push_url, $data, 'post', false);
        }

        //Initial this key in modSettings

        //Get push_slug from db
        $push_slug = $option->push_slug;
        $push_slug = isset($push_slug) && !empty($push_slug) ? $push_slug : 0;

        $slug = $push_slug;
        $slug = self::push_slug($slug, 'CHECK');
        $check_res = unserialize($slug);

        //If it is valide(result = true) and it is not sticked, we try to send push
        if($check_res[2] && !$check_res[5])
        {
            //Slug is initialed or just be cleared
            if($check_res[8])
            {
                $optionModel->updateOptions(array('push_slug' => $slug));
            }

            //Send push
            $connection = new classTTConnection();
            $push_resp = $connection->getContentFromSever($push_url, $data, 'post', false);

            if(trim($push_resp) === 'Invalid push notification key') $push_resp = 1;
            if(!is_numeric($push_resp))
            {
                //Sending push failed, try to update push_slug to db
                $slug = self::push_slug($slug, 'UPDATE');
                $update_res = unserialize($slug);
                if($update_res[2] && $update_res[8])
                {
                    $optionModel->updateOptions(array('push_slug' => $slug));
                }
            }
        }
        */
        return true;
    }

    protected static function cleanPost($post, $extraStates = array())
    {
        if (!isset($extraStates['states']['returnHtml']))
            $extraStates['states']['returnHtml'] = false;

        if ($extraStates['states']['returnHtml']) {
            $post = str_replace("&", '&amp;', $post);
            $post = str_replace("<", '&lt;', $post);
            $post = str_replace(">", '&gt;', $post);
            $post = str_replace("\r", '', $post);
            $post = str_replace("\n", '<br />', $post);
        }

        if (!$extraStates)
            $extraStates = array('states' => array());

        // replace code like content with quote
        //      $post = preg_replace('/\[(CODE|PHP|HTML)\](.*?)\[\/\1\]/si','[CODE]$2[/CODE]',$post);

        $post = self::processListTag($post);
        $bbCodeFormatter = new Tapatalk_BbCode_Formatter_Tapatalk((boolean)$extraStates['states']['returnHtml']);
        if (version_compare(XenForo_Application::$version, '1.2.0') >= 0) {
            $bbCodeParser = XenForo_BbCode_Parser::create($bbCodeFormatter);
        } else {
            $bbCodeParser = new XenForo_BbCode_Parser($bbCodeFormatter);
        }
        $post = $bbCodeParser->render($post, $extraStates['states']);
        $post = trim($post);


        $options = XenForo_Application::get('options');
        $custom_replacement = $options->tapatalk_custom_replacement;
        if (!empty($custom_replacement)) {
            $replace_arr = explode("\n", $custom_replacement);
            foreach ($replace_arr as $replace) {
                preg_match('/^\s*(\'|")((\#|\/|\!).+\3[ismexuADUX]*?)\1\s*,\s*(\'|")(.*?)\4\s*$/', $replace, $matches);
                if (count($matches) == 6) {
                    $temp_post = $post;
                    $post = @preg_replace($matches[2], $matches[5], $post);
                    if (empty($post)) {
                        $post = $temp_post;
                    }
                }
            }
        }
        return $post;
    }

    protected static function processListTag($message)
    {
        $contents = preg_split('#(\[LIST=[^\]]*?\]|\[/?LIST\])#siU', $message, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);

        $result = '';
        $status = 'out';
        foreach ($contents as $content) {
            if ($status == 'out') {
                if ($content == '[LIST]') {
                    $status = 'inlist';
                } elseif (strpos($content, '[LIST=') !== false) {
                    $status = 'inorder';
                } else {
                    $result .= $content;
                }
            } elseif ($status == 'inlist') {
                if ($content == '[/LIST]') {
                    $status = 'out';
                } else {
                    $result .= str_replace('[*]', '  * ', ltrim($content));
                }
            } elseif ($status == 'inorder') {
                if ($content == '[/LIST]') {
                    $status = 'out';
                } else {
                    $index = 1;
                    $result .= preg_replace_callback('/\[\*\]/s',
                        'Tapatalk_Push_Push::matchCount',
                        ltrim($content));
                }
            }
        }
        return $result;
    }

    protected static function matchCount($matches)
    {
        static $index = 1;
        return '  ' . $index++ . '. ';
    }

    protected static function analysisPushAction($action, $post, $thread)
    {
        $bridge = Bridge::getInstance();
        $visitor = $bridge::visitor();
        $forumModel = $bridge->getForumRepo();
        $options = $bridge->options();

        $pushData = array();

        $ttp_data = array(
            'id' => $thread['thread_id'],
            'subid' => $post['post_id'],
            'subfid' => $thread['node_id'],
            'title' => self::tt_push_clean($thread['title']),
            'author_ua' => self::getClienUserAgent(),
            'author_type' => self::get_usertype_by_item('', $visitor['display_style_group_id'], $visitor['is_banned'], $visitor['user_state']),
            'from_app' => self::getIsFromApp(),
            'dateline' => time(),
        );

        if ($action == 'Like' || $action == 'Watch') {
            $ttp_data['author'] = self::tt_push_clean($visitor['username']);
            $ttp_data['authorid'] = $visitor['user_id'];
            $ttp_data['author_postcount'] = $visitor['message_count'];
        } else {
            $ttp_data['author'] = self::tt_push_clean($post['username']);
            $ttp_data['authorid'] = $post['user_id'];
            $ttp_data['author_postcount'] = $visitor['message_count'] + 1;
        }

        $forum = $forumModel->getForumById($thread['node_id']);
        $ttp_data['sub_forum_name'] = self::tt_push_clean($forum['title']);

        if (isset($options->tapatalk_push_notifications) && $options->tapatalk_push_notifications == 1) {
            $myOptions = array(
                'states' => array(
                    'returnHtml' => true,
                ),
            );
            $content = self::cleanPost($post['message'], $myOptions);
            $ttp_data['content'] = $content;
        }

        $ttp_data['url'] = self::tt_get_board_url();
        if (isset($options->tp_push_key) && !empty($options->tp_push_key)) {
            $ttp_data['key'] = $options->tp_push_key;
        }

        $data = self::findParticipants($action, $post, $thread);

        $tapatalkUser_model = XenForo_Model::create('Tapatalk_Model_TapatalkUser');

        foreach ($data as $pushAction => $users) {
            $ttp_data['type'] = $pushAction;
            $user_ids = array();
            $extrauser_ids = array();
            foreach ($users as $user) {
                if ($user['user_id'] == $visitor['user_id']) {
                    $extrauser_ids[] = $user['user_id'];
                } else {
                    if (self::can_view_post($post['post_id'], $user['user_id']) === false) {
                        $extrauser_ids[] = $user['user_id'];
                    } else {
                        $tapatalk_user = $tapatalkUser_model->getTapatalkUserById($user['user_id']);
                        if (!empty($tapatalk_user)) {
                            $user_ids[] = $user['user_id'];
                        } else {
                            $extrauser_ids[] = $user['user_id'];
                        }
                    }
                }

            }
            if (empty($user_ids) && empty($extrauser_ids)) {
                continue;
            }
            $ttp_data['userid'] = implode(',', $user_ids);
            $pushData[] = $ttp_data;
        }

        if (empty($pushData)) {
            $ttp_data['type'] = 'sub';
            $ttp_data['userid'] = '';
            $pushData[] = $ttp_data;
        }

        return $pushData;
    }

    protected static function findParticipants($action, $post, $thread)
    {
        $participants = array();
        $participants['tag'] = array();
        $participants['quote'] = array();
        $participants['sub'] = array();
        $participants['like'] = array();
        $participants['newtopic'] = array();
        $participants['newsub'] = array();
        if ($action == 'AddReply' || $action == 'AddThread') {
            //handle tag
            if (file_exists(SCRIPT_ROOT . 'library/XenForo/Model/UserTagging.php')) {
                $taggingModel = XenForo_Model::create('XenForo_Model_UserTagging');
                $taggedUsers = $taggingModel->getTaggedUsersInMessage(
                    $post['message'], $newMessage, 'text'
                );
                if (!empty($taggedUsers)) {
                    foreach ($taggedUsers as $taggedUser) {
                        $participants['tag'][$taggedUser['user_id']] = $taggedUser;
                    }
                }
            }

            //handle quote
            if (preg_match_all('/\[quote=("|\'|)([^,]+),\s*post:\s*(\d+?).*\\1\]/siU', $post['message'], $quotes)) {
                $postModel = XenForo_Model::create('XenForo_Model_Post');
                if (version_compare(XenForo_Application::$version, '1.2.0') >= 0) {
                    $fetchOptions = array(
                        'join' => XenForo_Model_Post::FETCH_USER_OPTIONS
                            | XenForo_Model_Post::FETCH_USER_PROFILE
                            | XenForo_Model_Post::FETCH_THREAD
                            | XenForo_Model_Post::FETCH_FORUM
                            | XenForo_Model_Post::FETCH_NODE_PERMS
                    );
                } else {
                    $fetchOptions = array(
                        'join' => XenForo_Model_Post::FETCH_USER_OPTIONS
                            | XenForo_Model_Post::FETCH_USER_PROFILE
                            | XenForo_Model_Post::FETCH_THREAD
                            | XenForo_Model_Post::FETCH_FORUM
                    );
                }
                $quotedPosts = $postModel->getPostsByIds(array_unique($quotes[3]), $fetchOptions);
                $userModel = XenForo_Model::create('XenForo_Model_User');

                foreach ($quotedPosts AS $quotedPostId => $quotedPost) {
                    if (!isset($quotedUsers[$quotedPost['user_id']]) && $quotedPost['user_id'] && $quotedPost['user_id'] != $post['user_id']) {
                        $user = $userModel->getUserById($quotedPost['user_id']);
                        if (!isset($participants['tag'][$user['user_id']])) {
                            $participants['quote'][$user['user_id']] = $user;
                        }
                    }
                }
            }

            //handle sub
            $threadWatchModel = XenForo_Model::create('XenForo_Model_ThreadWatch');
            $users = $threadWatchModel->getUsersWatchingThread($thread['thread_id'], $thread['node_id']);
            if (!empty($users)) {
                foreach ($users as $user) {
                    if (!isset($participants['tag'][$user['user_id']]) && !isset($participants['quote'][$user['user_id']])) {
                        $participants['sub'][$user['user_id']] = $user;
                    }
                }
            }


        }

        //handle like
        if ($action == 'Like') {
            $userModel = XenForo_Model::create('XenForo_Model_User');
            $user = $userModel->getUserById($post['user_id']);
            $participants['like'][$user['user_id']] = $user;
        }

        //handle new topic
        if ($action == 'AddThread') {
            if (file_exists(SCRIPT_ROOT . 'library/XenForo/Model/ForumWatch.php')) {
                $forumWatchModel = XenForo_Model::create('XenForo_Model_ForumWatch');
                $users = $forumWatchModel->getUsersWatchingForum($thread['node_id'], $thread['thread_id']);
                if (!empty($users)) {
                    foreach ($users as $user) {
                        $participants['newtopic'][$user['user_id']] = $user;
                    }
                }
            }
        }

        //handle subscrib topic
        if ($action == 'Watch') {
            $userModel = XenForo_Model::create('XenForo_Model_User');
            $user = $userModel->getUserById($thread['user_id']);
            $participants['newsub'][$user['user_id']] = $user;
        }
        return $participants;
    }


    public static function getClienUserAgent()
    {
        $useragent = $_SERVER['HTTP_USER_AGENT'];
        return $useragent;
    }

    public static function getIsFromApp()
    {
        return defined('IN_MOBIQUO') ? 1 : 0;
    }

    public static function get_usertype_by_item($userid = '', $groupid = '', $is_banned = false, $state = '')
    {
        $bridge = Bridge::getInstance();
        if ($is_banned)
            return 'banned';
        if ($state == 'email_confirm' || $state == 'email_confirm_edit' || $state == 'Email invalid (bounced)')
            return 'inactive';
        if ($state == 'moderated')
            return 'unapproved';
        if (empty($groupid)) {
            if (!empty($userid)) {
                $userModel = $bridge->getUserRepo();
                $user = $userModel->getUserById($userid);
                if ($user['is_banned'])
                    return 'banned';
                $groupid = $user['display_style_group_id'];
            } else
                return ' ';
        }

        if ($groupid == 3)
            return 'admin';
        else if ($groupid == 4)
            return 'mod';
        else if ($groupid == 2)
            return 'normal';
        else if ($groupid == 1)
            return ' ';
    }

    public static function tt_get_board_url()
    {
        $bridge = Bridge::getInstance();
        $app = $bridge->app();
        $request = $app->request();
        $boardUrl = $request->getFullBasePath();

        if (!$boardUrl){
            $boardUrl = $app->container('homePageUrl');
        }

        return $boardUrl;
    }
}
