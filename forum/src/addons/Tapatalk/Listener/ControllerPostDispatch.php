<?php

namespace Tapatalk\Listener;

use XF\Entity\ConversationMaster;
use XF\Mvc\Controller;
use XF\Mvc\ParameterBag;
use XF\Repository\Conversation;
use XF\Repository\Post;
use XF\Repository\Thread;
use XF\Repository\ThreadWatch;

if (!defined('SCRIPT_ROOT')) {
    define('SCRIPT_ROOT', empty($_SERVER['SCRIPT_FILENAME']) ? '../../../' : dirname($_SERVER['SCRIPT_FILENAME']) . '/');
}

class ControllerPostDispatch
{
    /**
     * @param Controller $controller
     * @param string $action
     * @param ParameterBag $paramsBag
     * @param $reply
     * @return bool|void
     */
    public static function postDispatchListener($controller, $action, $paramsBag, $reply)
    {
        /** @var ParameterBag $paramsBag */
        $params = $paramsBag->params();
        $app = \XF::app();
        $visitor = \XF::visitor();
        $thread = $post = '';

        switch ($action) {
            case 'AddReply':
                if ($params) {
                    // reply post
                    $thread_id = isset($params['thread_id']) ? $params['thread_id'] : '';
                    if (isset($thread_id) && $thread_id) {
                        /** @var Thread $threadRepo */
                        $threadRepo = $app->repository('XF:Thread');
                        /** @var \XF\Entity\Thread $thread */
                        $thread = $threadRepo->finder('XF:Thread')->whereId($thread_id)->fetchOne();
                        if ($thread) {
                            $postCache = $thread->getLastPostCache();
                            $post_id = $postCache['post_id'];
                        }
                    }

                    // reply con message_html
                    $conversation_id = isset($params['conversation_id']) ? $params['conversation_id'] : '';
                    $msg = $controller->request()->get('message_html');
                    if ($conversation_id && $msg) {
                        $action = 'InsertReply';
                        self::doPushConv($action);
                    }

                }
                break;
            case 'AddThread':
            case 'PostThread':
                if ($params) {
                    $action = 'AddThread';

                    $nodeId = isset($params['node_id']) ? $params['node_id'] : '';
                    $title = $controller->request()->get('title');
                    if ($nodeId && $title) {
                        /** @var \Tapatalk\XF\Repository\Forum $forumRepo */
                        $forumRepo = $app->repository('XF:Forum');
                        /** @var \XF\Entity\Forum $forum */
                        $forum = $forumRepo->findForumById($nodeId);
                        $datas = $forum->getNodeListExtras();
                        if (isset($datas['last_post_id']) && $datas['last_post_id']) {
                            /** @var \XF\Entity\Post $post */
                            $post = $app->finder('XF:Post')->whereId($datas['last_post_id'])->fetchOne();
                            if ($post) {
                                if ($post->user_id != $visitor->user_id) {
                                    unset($post);
                                } else {
                                    $thread = $post->Thread;
                                }
                            }
                        }
                    }
                }
                break;
            case 'Like':
                if ($params) {
                        $post_id = isset($params['post_id']) ? $params['post_id'] : '';
                        $thread_id = isset($params['thread_id']) ? $params['thread_id'] : '';
                        if (isset($post_id) && $post_id && !$thread_id) {
                            /** @var \XF\Entity\Post $post */
                            $post = $app->finder('XF:Post')->whereId($post_id)->fetchOne();
                            if ($post) {
                                $thread = $post->Thread;
                            }
                        }
                }
                break;
            case 'Delete':
                if ($params) {
                    $ids = array();
                    $reason = $controller->request()->exists('reason');
                    $type = $controller->request()->exists('hard_delete');
                    if (isset($params['post_id']) && $params['post_id'] && $type) {
                        $action = 'delpost';
                        $ids[] = $params['post_id'];
                        self::doPushDelete($action, $ids);
                        return;
                    } else if (isset($params['thread_id']) && $params['thread_id']) {
                        $action = 'deltopic';
                        $ids[] = $params['thread_id'];
                        self::doPushDelete($action, $ids);
                        return;
                    } else if (isset($params['posts'])) {
                        foreach ($params['posts'] as $key => $postId) {
                            $ids[] = $postId;
                        }
                        $action = 'delpost';
                        self::doPushDelete($action, $ids);
                        return;
                    } else if (isset($params['threads'])) {
                        foreach ($params['threads'] as $key => $threadId) {
                            $ids[] = $threadId;
                        }
                        $action = 'deltopic';
                        self::doPushDelete($action, $ids);
                        return;
                    }
                }
                break;
            case 'Watch':
                if ($params) {
                    $thread_id = isset($params['thread_id']) ? $params['thread_id'] : '';
                    $nodeId = isset($params['node_id']) ? $params['node_id'] : '';
                    $unWatch = $controller->request()->get('stop');
                    $notify = $controller->request()->get('notify');
                    $threadSubscribe = $controller->request()->exists('email_subscribe');

                    if ($unWatch || (!$notify && !$threadSubscribe)) {
                        break;
                    }

                    if ($thread_id && $threadSubscribe) {

                        if ($action == 'Watch') {
                            /** @var \Tapatalk\XF\Repository\ThreadWatch $threadWatchRepo */
                            $threadWatchRepo = $app->repository('XF:ThreadWatch');

                            $threadIds = $thread_id;
                            if (!is_array($thread_id)) {
                                $threadIds = explode(',', $thread_id);
                                if (!$threadIds) $threadIds = [];
                            }
                            $watch = $threadWatchRepo->getUserThreadWatchByThreadIds($visitor->user_id, $threadIds);
                            if (empty($watch)) {
                                break;
                            }
                        }
                        /** @var Thread $threadRepo */
                        $threadRepo = $app->repository('XF:Thread');
                        $thread = $threadRepo->finder('XF:Thread')->whereId($thread_id)->fetchOne();
                        if ($thread) {
                            /** @var Post $postRepo */
                            $postRepo = $app->repository('XF:Post');
                            $post = $postRepo->finder('XF:Post')->whereId($thread['last_post_id'])->fetchOne();
                        }
                    }
                }
                break;
            // xenforo action
            case 'InviteInsert':
            case 'Invite':
            case 'invite':
                $recipients = $controller->request()->get('recipients');
                if (isset($params['conversation_id']) && $params['conversation_id'] && $recipients) {
                    $invited_user_ids = [];
                    if ($recipients) {
                        $temp = $recipients;
                        $temp_arr = explode(',', $temp);
                        /** @var \Tapatalk\XF\Repository\User $userRepo */
                        $userRepo = $app->repository('XF:User');
                        $users = $userRepo->getUsersByNames($temp_arr);
                        if ($users) {
                            $users = $users->toArray();
                        }
                        if ($users) {
                            $invited_user_ids = array_keys($users);
                        }
                    }
                    $invited_user_ids = implode(',', $invited_user_ids);
                    if ($invited_user_ids) {
                        self::doPushConvInvite($action, $invited_user_ids, $params['conversation_id']);
                    }
                }
                break;

            // tapatalk action
            case 'invite_participant':
                if (isset($_REQUEST['tapatalk_invited_user_ids']) && !empty($_REQUEST['tapatalk_invited_user_ids'])) {
                    $invited_user_ids = $_REQUEST['tapatalk_invited_user_ids'];
                    self::doPushConvInvite($action, $invited_user_ids);
                }
                break;

            // xenforo action
            case 'Insert':
            case 'InsertReply':
            case 'Add':
            case 'add':
                // tapatalk action
            case 'new_conversation':
            case 'reply_conversation':
                if ($action == 'Add' || $action == 'add') {
                    $recipients = $controller->request()->get('recipients');
                    $title = $controller->request()->get('title');
                    if (!$recipients || !$title) {
                        break;
                    }
                    $action = 'InviteInsert';
                }

                self::doPushConv($action);
                return;

            case 'React':
                // compatible plugin
                if ($params && isset($params['reaction_id']) && isset($params['content_id']) && isset($params['content_type'])) {
                    if ($params['content_type'] == 'post' && $params['reaction_id'] == '2') {
                        // 2 is like
                        if ($postId = (int)$params['content_id']) {
                            /** @var \XF\Entity\Post $post */
                            $post = $app->finder('XF:Post')->whereId($postId)->fetchOne();
                            if ($post) {
                                if ($post->user_id == $visitor->user_id) {
                                    unset($post);
                                } else {
                                    $thread = $post->Thread;
                                    $action = 'Like';
                                }
                            }
                        }
                    }
                }
                break;
        }
        if (isset($thread_id) && $thread_id && !$thread) {
            /** @var Thread $threadRepo */
            $threadRepo = $app->repository('XF:Thread');
            $thread = $threadRepo->finder('XF:Thread')->whereId($thread_id)->fetchOne();
        }
        if (isset($post_id) && $post_id && !$post) {
            /** @var Post $postRepo */
            $postRepo = $app->repository('XF:Post');
            $post = $postRepo->finder('XF:Post')->whereId($post_id)->fetchOne();
        }
        if (isset($thread) && !empty($thread) && isset($post) && !empty($post)) {
            $options = $app->options();
            if (isset($options->tp_push_key) && !empty($options->tp_push_key)) {
                $boardUrl = self::getBoardUrl();
                if (!self::includeTapatalkPushClass()) {
                    return false;
                }
                $TapatalkPush = new \TapatalkPush($options->tp_push_key, $boardUrl);
                $TapatalkPush->processPush($action, $post, $thread);
            }
        }
    }

    public static function doPushDelete($action, $ids)
    {
        $app = \XF::app();
        $options = $app->options();

        if (isset($options->tp_push_key) && !empty($options->tp_push_key)) {
            $boardUrl = self::getBoardUrl();
            if (!self::includeTapatalkPushClass()) {
                return false;
            }
            $TapatalkPush = new \TapatalkPush($options->tp_push_key, $boardUrl);
            $TapatalkPush->doPushDelete($action, $ids);
        }
    }

    protected static function getBoardUrl()
    {
        $app = \XF::app();
        $options = $app->options();
        $homePageUrl = $app->container('homePageUrl');
        if ($homePageUrl) {
            return $homePageUrl;
        }
        return $options->boardUrl;
    }

    protected static function includeTapatalkPushClass()
    {
        $app = \XF::app();
        $options = $app->options();

        if (!class_exists('TapatalkPush')) {
            $tapatalk_dir_name = $options->tp_directory;
            if (empty($tapatalk_dir_name)) {
                $tapatalk_dir_name = 'mobiquo';
            }
            $tapatalk_dir_name = \XF::getRootDirectory() . '/' . $tapatalk_dir_name;
            $tapatalkPushFile = $tapatalk_dir_name . '/push/TapatalkPush.php';
            if (file_exists($tapatalkPushFile)) {
                include_once($tapatalkPushFile);
                return true;
            }
        }else{
            return true;
        }

        return false;
    }


    public static function doPushConv($action)
    {
        if (!isset($GLOBALS['tapatalk_conversation_id']) || !$GLOBALS['tapatalk_conversation_id']) {
            return;
        }

        $app = \XF::app();
        $options = $app->options();
        $visitor = \XF::visitor();

        /** @var Conversation $conversationModel */
        $conversationModel =  $app->repository('XF:Conversation');

        if (!isset($options->tp_push_key) || empty($options->tp_push_key)) {
            return false;
        }
        if (!self::includeTapatalkPushClass()) {
            return false;
        }

        /** @var ConversationMaster $conver_msg */
        $conver_msg = $conversationModel->finder('XF:ConversationMaster')->whereId($GLOBALS['tapatalk_conversation_id'])->fetchOne();
        if (!$conver_msg) {
            return false;
        }
        $participated_members = $conver_msg->getRelationFinder('Recipients')->fetch();
        if ($participated_members) {
            $participated_members = $participated_members->toArray();
        }else{
            $participated_members = [];
        }
        $conver_msg = $conver_msg->toArray();
        $conver_msg['action'] = $action;

        $recepients = array();
        $current_user = $visitor->toArray();
        $conver_msg['conv_sender_id'] = isset($current_user['user_id']) && !empty($current_user['user_id']) ? $current_user['user_id'] : $conver_msg['last_message_user_id'];
        $conver_msg['conv_sender_name'] = isset($current_user['username']) && !empty($current_user['username']) ? $current_user['username'] : $conver_msg['last_message_username'];
        foreach ($participated_members as $member) {
            $membersId = $member['user_id'];
            if ($membersId == $conver_msg['conv_sender_id']) continue;
            if ($member['recipient_state'] != 'active') continue;
            $recepients[] = $membersId;
        }
        $conver_msg['recepients'] = $recepients;
        $ttp_data['key'] = $options->tp_push_key;

        unset($GLOBALS['tapatalk_conversation_id']);
        $boardUrl = self::getBoardUrl();
        $TapatalkPush = new \TapatalkPush($options->tp_push_key, $boardUrl);
        $TapatalkPush->doPushConv($conver_msg, $participated_members);
    }

    public static function doPushConvInvite($action, $invited_user_ids, $conversationId = null)
    {
        if (!$conversationId) {
            if (!isset($GLOBALS['tapatalk_conversation_id_invite']) || !$GLOBALS['tapatalk_conversation_id_invite']) {
                return;
            }
            $conversationId = $GLOBALS['tapatalk_conversation_id_invite'];
        }

        $app = \XF::app();
        $options = $app->options();
        $visitor = \XF::visitor();

        /** @var Conversation $conversationModel */
        $conversationModel =  $app->repository('XF:Conversation');

        if (!isset($options->tp_push_key) || empty($options->tp_push_key)) {
            return false;
        }
        if (!self::includeTapatalkPushClass()) {
            return false;
        }

        /** @var ConversationMaster $conver_msg */
        $conver_msg = $conversationModel->finder('XF:ConversationMaster')->whereId($conversationId)->fetchOne();
        if (!$conver_msg) {
            return false;
        }
        $participated_members = $conver_msg->getRelationFinder('Recipients')->fetch();
        if ($participated_members) {
            $participated_members = $participated_members->toArray();
        }else{
            $participated_members = [];
        }
        $conver_msg = $conver_msg->toArray();

        $conver_msg['action'] = $action;
        $recipients = array();

        $current_user = $visitor->toArray();
        $conver_msg['conv_sender_id'] = isset($current_user['user_id']) && !empty($current_user['user_id']) ? $current_user['user_id'] : $conver_msg['user_id'];
        $conver_msg['conv_sender_name'] = isset($current_user['username']) && !empty($current_user['username']) ? $current_user['username'] : $conver_msg['username'];

        foreach ($participated_members as $member) {
            $recipientUserId = $member['user_id'];
            if ($conver_msg['conv_sender_id'] == $recipientUserId) {
                continue;
            }
            if ($member['recipient_state'] != 'active') continue;
            $recipients[] = $recipientUserId;

        }
        $conver_msg['recipients'] = $recipients;
        $conver_msg['recepients'] = $recipients;
        $ttp_data['key'] = $options->tp_push_key;

        unset($GLOBALS['tapatalk_conversation_id_invite']);
        $boardUrl = self::getBoardUrl();
        $TapatalkPush = new \TapatalkPush($options->tp_push_key, $boardUrl);
        $TapatalkPush->doPushConvInvite($conver_msg, $invited_user_ids);
    }
}
