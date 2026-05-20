<?php

use Tapatalk\Bridge;
use XF\Legacy\DataWriter as XenForo_DataWriter;

define('MBQ_PUSH_BLOCK_TIME', 60);    /* push block time(minutes) */
if (!class_exists('TapatalkBasePush')) {
    require_once(dirname(__FILE__) . '/../mbqFrame/basePush/TapatalkBasePush.php');
}
require_once dirname(__FILE__) . '/../helper.php';

/**
 * push class
 */
Class TapatalkPush extends TapatalkBasePush
{
    protected $tp_push_key;
    protected $boardUrl;

    //init
    public function __construct($tp_push_key = null, $boardUrl = null)
    {
        $app = \XF::app();
        if (!$tp_push_key) {
            $this->tp_push_key = $app->options()->tp_push_key;
        }else{
            $this->tp_push_key = $tp_push_key;
        }
        if (!$boardUrl) {
            $this->boardUrl = self::getBoardUrl();
        }else {
            $this->boardUrl = $boardUrl;
        }

        parent::__construct($this);
    }

    public static function getBoardUrl()
    {
        $app = \XF::app();
        $options = $app->options();
        $homePageUrl = $app->container('homePageUrl');
        if ($homePageUrl) {
            return $homePageUrl;
        }
        return $options->boardUrl;
    }

    function get_push_slug()
    {
        $app = \XF::app();
        $options = $app->options();
        $slug = $options->push_slug;
        if (isset($slug)) {
            return $slug;
        }
        return null;
    }

    function set_push_slug($slug = null)
    {
        $app = \XF::app();
        /** @var \XF\Repository\Option $optionRepo */
        $optionRepo = $app->repository('XF:Option');
        $optionRepo->updateOptions(array('push_slug' => $slug));
        return true;
    }

    public function doAfterAppLogin($userId = '')
    {
        if (!$userId) {
            return false;
        }
        $app = \XF::app();

        /** @var \XF\Repository\Ip $ipRepo */
        $ipRepo = $app->repository('XF:Ip');

        $ipRepo->logIp($userId, $app->request()->getIp(), 'user', $userId, 'login');

        /** @var \Tapatalk\XF\Legacy\DataWriter $tapatalk_user_writer */
        $tapatalk_user_writer = XenForo_DataWriter::create('\Tapatalk\XF\Legacy\DataWriter');
        $tapatalk_user_model = $tapatalk_user_writer->getTapatalkUserModel();
        $existing_record = $tapatalk_user_model->getTapatalkUserById($userId);
        if (empty($existing_record)) {
            $tapatalk_user_writer->set('userid', $userId);
            $tapatalk_user_writer->preSave();
            $tapatalk_user_writer->save();
        } else {
            $tapatalk_user_writer->setExistingData($existing_record);
            $tapatalk_user_writer->save();
        }
    }

    public function processPush($action, $post, $thread)
    {
        try {
            if (!$post['post_id'] || !$thread['thread_id'] || (!function_exists('curl_init') && !ini_get('allow_url_fopen'))) {
                return false;
            }

            $push_datas = self::analysisPushAction($action, $post, $thread);

            foreach ($push_datas as $push_data) {
                self::do_push_request($push_data);
            }
        }
        catch (Exception $ex) {
        }
        return true;
    }

    public function doPushLike($data)
    {

    }

    public function doPushDelete($action, $ids)
    {
        $boardUrl = self::tt_get_board_url();
        $push_data = array(
            'url' => $boardUrl,
            'userid' => '',
            'type' => $action,
            'id' => implode(',', $ids),
            'from_app' => self::getIsFromApp(),
            'dateline' => time(),
        );
        if (!empty($this->tp_push_key)) {
            $push_data['key'] = $this->tp_push_key;
        }
        self::do_push_request($push_data);
    }

    protected static function cleanStringWithPush($string)
    {
        $str = strip_tags($string);
        $str = trim($str);
        return html_entity_decode($str, ENT_QUOTES, 'UTF-8');
    }

    public function doPushConv($data, $values)
    {
        $app = \XF::app();
        $visitor = \XF::visitor();
        $options = $app->options();
        $bridge = Bridge::getInstance();

        $canDoPush = false;
        if (isset($data['recepients']) && !empty($data['recepients']) && $data['title'] && (function_exists('curl_init') || ini_get('allow_url_fopen'))) {
            $canDoPush = true;
        }
        if (!$canDoPush) {
            return false;

        } else {

            if (isset($options->tapatalk_push_notifications) && $options->tapatalk_push_notifications == 1) {
                /** @var Tapatalk\XF\Repository\ConversationMessage $convMsgRepo */
                $convMsgRepo = $bridge->getConversationMessageRepo();

                $message = $convMsgRepo->getConversationMessageById($data['last_message_id']);
                if ($message) {
                    $message = $message->toArray();
                    $myOptions = array(
                        'states' => array(
                            'returnHtml' => true,
                        ),
                    );
                    $content = self::cleanPost($message['message'], $myOptions);
                }
            }

            /** @var Tapatalk\XF\Repository\TapatalkUsersRepo $tapatalkUser_model */
            $tapatalkUser_model = $bridge->getTapatalkUsersRepo();
            $spcTpUsers = $tapatalkUser_model->getAllPmOpenTapatalkUsersInArray($data['recepients']);
            if ($spcTpUsers) {
                $spcTpUsers = $spcTpUsers->toArray();
            }
            $title = self::cleanStringWithPush($data['title']);
            $author = self::cleanStringWithPush($data['conv_sender_name']);
            $boardurl = self::tt_get_board_url();
            if (empty($spcTpUsers)) {
                return;
            }
            $tpu_ids = '';
            foreach ($spcTpUsers as $tpu_id => $tapatalk_user) {
                $tpu_ids .= $tpu_id . ',';
            }
            $tpu_ids = substr($tpu_ids, 0, strlen($tpu_ids) - 1);

            $inviteFlag = 0;
            // add a flag to indicate it's an invite
            if (isset($data['action']) && $data['action'] == 'InviteInsert' ||
                isset($data['action']) && $data['action'] == 'invite_participant') {
                $inviteFlag = 1;
            }

            $ttp_data = array(
                'url' => $boardurl,
                'userid' => $tpu_ids,
                'type' => 'conv',
                'id' => $data['conversation_id'],
                'subid' => $data['reply_count'] + 1,
                'mid' => $data['last_message_id'],
                'title' => $title,
                'author' => $author,
                'authorid' => $data['conv_sender_id'],
                'author_postcount' => $visitor['message_count'],
                'author_ua' => self::getClienUserAgent(),
                'author_type' => self::get_usertype_by_item('', $visitor['display_style_group_id'], $visitor['is_banned'], $visitor['user_state']),
                'from_app' => self::getIsFromApp(),
                'dateline' => time(),
                'invite' => $inviteFlag,
            );
            if (isset($content) && !empty($content)) {
                $ttp_data['content'] = $content;
            }

            if (isset($options->tp_push_key) && !empty($options->tp_push_key)) {
                $ttp_data['key'] = $options->tp_push_key;
            }

            self::do_push_request($ttp_data);
        }
    }

    public function doPushConvInvite($data, $invited_user_ids)
    {
        $app = \XF::app();
        $visitor = \XF::visitor();
        $options = $app->options();
        $bridge = Bridge::getInstance();

        $canDoPush = false;
        if (!empty($invited_user_ids) && $data['title'] && (function_exists('curl_init') || ini_get('allow_url_fopen'))) {
            $canDoPush = true;
        }
        if (!$canDoPush) {
            return false;

        } else {

            if (isset($options->tapatalk_push_notifications) && $options->tapatalk_push_notifications == 1) {
                /** @var Tapatalk\XF\Repository\ConversationMessage $convMsgRepo */
                $convMsgRepo = $bridge->getConversationMessageRepo();
                $message = $convMsgRepo->getConversationMessageById($data['last_message_id']);
                if ($message) {
                    $message = $message->toArray();
                    $myOptions = array(
                        'states' => array(
                            'returnHtml' => true,
                        ),
                    );
                    $content = self::cleanPost($message['message'], $myOptions);
                }
            }

            $title = self::cleanStringWithPush($data['title']);
            $author = self::cleanStringWithPush($data['conv_sender_name']);
            $boardurl = self::tt_get_board_url();

            $ttp_data = array(
                'url' => $boardurl,
                'userid' => $invited_user_ids,
                'type' => 'conv',
                'id' => $data['conversation_id'],
                'subid' => $data['reply_count'] + 1,
                'mid' => $data['last_message_id'],
                'title' => $title,
                'author' => $author,
                'authorid' => $data['conv_sender_id'],
                'author_postcount' => $visitor['message_count'],
                'author_ua' => self::getClienUserAgent(),
                'author_type' => self::get_usertype_by_item('', $visitor['display_style_group_id'], $visitor['is_banned'], $visitor['user_state']),
                'from_app' => self::getIsFromApp(),
                'dateline' => time(),
                'invite' => 1,
            );
            if (isset($content) && !empty($content)) {
                $ttp_data['content'] = $content;
            }

            if (isset($options->tp_push_key) && !empty($options->tp_push_key)) {
                $ttp_data['key'] = $options->tp_push_key;
            }

            self::do_push_request($ttp_data);
        }
    }

    public function doPushNewTopic($data)
    {

    }

    public function doPushReply($data, $excludeUsers = array())
    {
    }

    public function doPushQuote($data, $quotedUsers)
    {

    }

    protected function doInternalPushThank($p)
    {
    }

    protected function doInternalPushReply($p)
    {
        //this is addressed directly in MbqWrEtForumPost to get the real quote code
    }

    protected function doInternalPushReplyConversation($p)
    {
    }

    protected function doInternalPushNewTopic($p)
    {
        $oMbqEtForumTopic = $p['oMbqEtForumTopic'];
        $bridge = Bridge::getInstance();

        /** @var \XF\Entity\Thread $thread */
        $thread = $oMbqEtForumTopic->mbqBind;
        if (!$thread || !($thread instanceof \XF\Entity\Thread)) {
            /** @var Tapatalk\XF\Repository\Thread $threadRepo */
            $threadRepo = $bridge->getThreadRepo();
            $thread = $threadRepo->findThreadById($oMbqEtForumTopic->topicId->oriValue);
        }
        if (!$thread || !($thread instanceof \XF\Entity\Thread)) {
            return false;
        }
        $post = $thread->getLastPostCache();
        /** @var Tapatalk\XF\Repository\Post $postRepo */
        $postRepo = $bridge->getPostRepo();
        $post = $postRepo->findPostById($post['post_id']);
        if ($post) self::processPush('AddThread', $post, $thread);
    }

    protected function doInternalPushNewConversation($p)
    {
    }

    protected function doInternalPushNewMessage($p)
    {
    }

    protected function doInternalPushLike($p)
    {
        $oMbqEtForumPost = $p['oMbqEtForumPost'];
        $post = $oMbqEtForumPost->mbqBind;
        $thread = $oMbqEtForumPost->oMbqEtForumTopic->mbqBind;

        self::processPush('Like', $post, $thread);
    }

    protected function doInternalPushNewSubscription($p)
    {
        $oMbqEtForumTopic = $p['oMbqEtForumTopic'];

        $bridge = Bridge::getInstance();
        /** @var \XF\Entity\Thread $thread */
        $thread = $oMbqEtForumTopic->mbqBind;
        if (!$thread || !($thread instanceof \XF\Entity\Thread)) {
            /** @var Tapatalk\XF\Repository\Thread $threadRepo */
            $threadRepo = $bridge->getThreadRepo();
            $thread = $threadRepo->findThreadById($oMbqEtForumTopic->topicId->oriValue);
        }
        $post = $thread->getLastPostCache();
        /** @var Tapatalk\XF\Repository\Post $postRepo */
        $postRepo = $bridge->getPostRepo();
        $post = $postRepo->findPostById($post['post_id']);
        if ($post) self::processPush('Watch', $post, $oMbqEtForumTopic->mbqBind);
    }

    protected function doInternalPushDeleteTopic($p)
    {
        self::doPushDelete('deltopic', array($p['oMbqEtForumTopic']->topicId->oriValue));
    }

    protected function doInternalPushDeletePost($p)
    {
        self::doPushDelete('delpost', array($p['oMbqEtForumPost']->postId->oriValue));
    }

    protected static function analysisPushAction($action, $post, $thread)
    {
        $pushData = array();

        $app = \XF::app();
        $bridge = Bridge::getInstance();
        $visitor = \XF::visitor();
        $options = $app->options();
        /** @var \XF\Repository\Forum $forumModel */
        $forumModel = $app->repository('XF:Forum');
        /** @var Tapatalk\XF\Repository\TapatalkUsersRepo $tapatalkUser_model */
        $tapatalkUser_model = $bridge->getTapatalkUsersRepo();

        $ttp_data = array('id' => $thread['thread_id']);
        if (isset($post)) {
            $ttp_data['subid'] = $post['post_id'];
        }
        $ttp_data['subfid'] = $thread['node_id'];
        $ttp_data['title'] = self::tt_push_clean($thread['title']);
        $ttp_data['author_ua'] = self::getClienUserAgent();
        $ttp_data['author_type'] = self::get_usertype_by_item('', $visitor['display_style_group_id'], $visitor['is_banned'], $visitor['user_state']);
        $ttp_data['from_app'] = self::getIsFromApp();
        $ttp_data['dateline'] = time();

        if ($action == 'Like' || $action == 'Watch') {
            $ttp_data['author'] = self::tt_push_clean($visitor['username']);
            $ttp_data['authorid'] = $visitor['user_id'];
            $ttp_data['author_postcount'] = $visitor['message_count'];
        } else {
            $ttp_data['author'] = self::tt_push_clean($post['username']);
            $ttp_data['authorid'] = $post['user_id'];
            $ttp_data['author_postcount'] = $visitor['message_count'] + 1;
        }

        $forum = $forumModel->finder('XF:Forum')->whereId($thread['node_id'])->fetchOne();
        $ttp_data['sub_forum_name'] = self::tt_push_clean($forum['title']);

        $ttp_data['url'] = self::tt_get_board_url();
        if (isset($options->tp_push_key) && !empty($options->tp_push_key)) {
            $ttp_data['key'] = $options->tp_push_key;
        }

        if ($action == 'DeleteTopic') {
            $ttp_data['contenttype'] = 'topic';
            $ttp_data['type'] = 'delete';
            $ttp_data['userid'] = '';
            $pushData[] = $ttp_data;

        } else if ($action == 'DeletePost') {

            $ttp_data['contenttype'] = 'post';
            $ttp_data['type'] = 'delete';
            $ttp_data['userid'] = '';
            $pushData[] = $ttp_data;

        } else {

            if (isset($options->tapatalk_push_notifications) && $options->tapatalk_push_notifications == 1) {
                $myOptions = array(
                    'states' => array(
                        'returnHtml' => true,
                    ),
                );

                $content = self::cleanPost($post['message'], $myOptions);
                $ttp_data['content'] = $content;
            }
            $data = self::findParticipants($action, $post, $thread);
            $allUserList = [];
            foreach ($data as $pushAction => $users) {
                $ttp_data['type'] = $pushAction;
                if (empty($users)) {
                    continue;
                }
                unset($users[$visitor['user_id']]);
                if (!$users) {
                    continue;
                }
                $user_ids = $tapatalkUser_model->getTapatalkUsersInArray(array_keys($users));
                if (!$user_ids || !$user_ids->count()) {
                    continue;
                }
                $user_ids = $user_ids->toArray();
                if (empty($user_ids)) {
                    continue;
                }
                $push_user_ids = array_keys($user_ids);
                $push_user_ids = array_diff($push_user_ids, $allUserList);
                $allUserList+= array_keys($user_ids);
                $ttp_data['userid'] = implode(',', $push_user_ids);
                $pushData[] = $ttp_data;
            }

            if (empty($pushData)) {
                if ($action == 'Like') {
                    $ttp_data['type'] = 'like';
                    $ttp_data['userid'] = '';
                    $pushData[] = $ttp_data;
                } else {
                    if ($action == 'AddThread') {
                        $ttp_data['type'] = 'newtopic';
                    } else {
                        $ttp_data['type'] = 'sub';
                    }
                    $ttp_data['userid'] = '';
                    $pushData[] = $ttp_data;

                }
            }
        }

        return $pushData;
    }

    protected static function tt_push_clean($str)
    {
        $str = strip_tags($str);
        $str = trim($str);
        return html_entity_decode($str, ENT_QUOTES, 'UTF-8');
    }

    protected static function get_usertype_by_item($userid = '', $groupid = '', $is_banned = false, $state = '')
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
                /** @var \XF\Repository\User $userModel */
                $userModel = $bridge->getUserRepo();
                $user = $userModel->finder('XF:User')->whereId($userid)->fetchOne();
                if (!$user) {
                    return ' ';
                }
                $user = $user->toArray();
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

    protected static function tt_get_board_url()
    {
        return self::getBoardUrl();
    }

    protected static function cleanPost($post, $extraStates = array())
    {
        $app = \XF::app();
        $options = $app->options();

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

        $post = preg_replace('/\[(CODE|PHP|HTML)\](.*?)\[\/\1\]/si','[CODE]$2[/CODE]',$post);

        $post = self::processListTag($post);
        try {
            $new_post = $post;
            if ($options->autoEmbedMedia['embedType'] == 2){
                $linkBbCode = $options->autoEmbedMedia['linkBbCode'];
                $linkBbCodeRegular = preg_quote($linkBbCode, '/');
                $linkBbCodeRegular = '/(\[MEDIA=.*?\].*?\[\/MEDIA\])\s*?' . str_replace('\{\$url\}', '(\S*?)', $linkBbCodeRegular) . '/';
                $new_post = preg_replace($linkBbCodeRegular, '$1', $post);
            }
            $post = $new_post;
        } catch (Exception $e) {}

        // $post = $app->bbCode()->render($post, 'html', 'post', ['ttType' => 'push']);

        $formatter = $app->stringFormatter();
        $post = $formatter->censorText($post);
        $post = $formatter->stripBbCode($post);

        $post = trim($post);
        $custom_replacement = $options->tapatalk_custom_replacement;
        if(!empty($custom_replacement))
        {
            $replace_arr = explode("\n", $custom_replacement);
            foreach ($replace_arr as $replace)
            {
                preg_match('/^\s*(\'|")((\#|\/|\!).+\3[ismexuADUX]*?)\1\s*,\s*(\'|")(.*?)\4\s*$/', $replace,$matches);
                if(count($matches) == 6)
                {
                    $temp_post = $post;
                    $post = @preg_replace($matches[2], $matches[5], $post);
                    if(empty($post))
                    {
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
                        'TapatalkPush::matchCount',
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

    /**
     * @param $action
     * @param \XF\Entity\Post $post
     * @param \XF\Entity\Thread $thread
     * @return array
     */
    protected static function findParticipants($action, $post, $thread)
    {
        $app = \XF::app();
        $visitor = \XF::visitor();
        $bridge = Bridge::getInstance();
        //push mentioned > quote > sub
        $participants = array();
        $participants['tag'] = array();
        $participants['quote'] = array();
        $participants['sub'] = array();
        $participants['like'] = array();
        $participants['newtopic'] = array();
        $participants['newsub'] = array();
        if ($action == 'AddReply' || $action == 'AddThread') {
            //handle tag
            $mentions = $app->stringFormatter()->getMentionFormatter();
            $string = $mentions->getMentionsStructuredText($post['message']);
            $mentionedUsers = $mentions->getMentionedUsers();
            if (!empty($mentionedUsers)) {
                foreach ($mentionedUsers as $taggedUser) {
                    if (isset($post['user_id']) && $post['user_id'] == $taggedUser['user_id']) {
                        continue;
                    }
                    if (self::can_view_post($post, $taggedUser)) {
                        $participants['tag'][$taggedUser['user_id']] = $taggedUser;
                    }
                }
            }

            //handle quote
            if (preg_match_all('/\[quote=("|\'|)([^,]+),\s*post:\s*(\d+?).*\\1\]/siU', $post['message'], $quotes)) {
                $quotedPosts = $app->finder('XF:Post')->whereIds(array_unique($quotes[3]))->fetch();
                if ($quotedPosts) {
                    $quotedPosts = $quotedPosts->toArray();
                }else{
                    $quotedPosts = [];
                }
                foreach ($quotedPosts AS $quotedPostId => $quotedPost) {
                    if (!isset($quotedUsers[$quotedPost['user_id']]) && isset($quotedPost['user_id']) &&
                        $quotedPost['user_id'] && $quotedPost['user_id'] != $post['user_id']) {
                        if (!isset($participants['tag'][$quotedPost['user_id']])) {

                            $user = $app->finder('XF:User')->whereId($quotedPost['user_id'])->fetchOne();
                            if (self::can_view_post($post, $user)) {
                                $participants['quote'][$user['user_id']] = $user;
                            }
                        }
                    }
                }
            }
        }

        if ($action == 'AddReply') {
            //handle sub
            /** @var Tapatalk\XF\Repository\ThreadWatch $threadWatchModel */
            $threadWatchModel = $bridge->getThreadWatchRepo();
            $users = $threadWatchModel->getUsersWatchingThreadId($thread['thread_id']);
            if (!empty($users)) {
                $users = $users->toArray();
                /** @var \XF\Entity\User $user */
                foreach ($users as $user) {
                    if ($user['user_id'] == $post['user_id']) {
                        continue;
                    }
                    if (!$user->hasNodePermission($thread['node_id'], 'view')) {
                        continue;
                    }
                    if (!isset($participants['tag'][$user['user_id']]) && !isset($participants['quote'][$user['user_id']])) {
                        $participants['sub'][$user['user_id']] = $user;
                    }
                }
            }
        }

        //handle like
        if ($action == 'Like') {
            /** @var Tapatalk\XF\Repository\User $userModel */
            $userModel = $bridge->getUserRepo();
            $user = $userModel->findUserById($post['user_id']);
            if ($user) {
                $participants['like'][$user['user_id']] = $user;
            }
        }

        //handle new topic
        if ($action == 'AddThread') {
            /** @var Tapatalk\XF\Repository\ForumWatch $forumWatchModel */
            $forumWatchModel = $bridge->getForumWatchRepo();
            $users = $forumWatchModel->getUsersWatchingForumId($thread['node_id']);
            if (!empty($users) && $users) {
                $users = $users->toArray();
                /** @var \XF\Entity\User $user */
                foreach ($users as $user) {
                    if ($user['user_id'] == $post['user_id']) {
                        continue;
                    }
                    if (!$user->hasNodePermission($thread['node_id'], 'view')) {
                        continue;
                    }
                    $participants['newtopic'][$user['user_id']] = $user;
                }
            }
        }

        //handle subscrib topic
        if ($action == 'Watch') {
            /** @var Tapatalk\XF\Repository\User $userModel */
            $userModel = $bridge->getUserRepo();
            $user = $userModel->findUserById($thread['user_id']);
            if ($user) {
                $user = $user->toArray();
            }
            if ($user && $user['user_id'] != $visitor->user_id) {
                $participants['newsub'][$user['user_id']] = $user;
            }
        }
        return $participants;
    }

    protected static function can_view_post($post, $user)
    {
        $app = \XF::app();
        $bridge = Bridge::getInstance();

        if (!($user instanceof \XF\Entity\User)) {
            if (!is_numeric($user) && isset($user['user_id'])) {
                $user = $user['user_id'];
            }
            /** @var Tapatalk\XF\Repository\User $userRepo */
            $userRepo = $bridge->getUserRepo();
            $user =$userRepo->findUserById($user);
        }
        if (!$user) {
            return false;
        } else {
            if (!($post instanceof \XF\Entity\Post)) {
                if (!is_numeric($post) && isset($post['post_id'])) {
                    $post = $post['post_id'];
                }
                /** @var Tapatalk\XF\Repository\Post $postRepo */
                $postRepo = $bridge->getPostRepo();
                $post = $postRepo->findPostById($post);
            }
            if (!$post) {
                return false;
            } else {
                $thread = $post->Thread;
                if (!$thread) return false;
                $nodeId = $thread->node_id;
                if (!$user->hasNodePermission($nodeId, 'view')) {
                    return false;
                }

                if ($post->message_state == 'moderated') {
                    if (!$user->hasNodePermission($nodeId, 'viewModerated')) {
                        return false;
                    }
                } else if ($post->message_state == 'deleted') {
                    if (!$user->hasNodePermission($nodeId, 'viewDeleted')) {
                        return false;
                    }
                }

                return true;
            }
        }

        return false;
    }
}

