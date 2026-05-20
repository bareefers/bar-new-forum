<?php
namespace Tapatalk\XF\Entity;

class UserAlert extends XFCP_UserAlert
{
    protected function _postSave()
    {
        parent::_postSave();
        //Tapatalk add
        $this->Tapatalk_hook();
    }

    protected function Tapatalk_hook($newData = array())
    {
        return;
        $type = $this->content_type;
        $id = $this->content_id;
        $action = strtolower($this->action);

        if ($type != 'post') {
            return;
        }
        // ignore repeat?
        $ignoreActions = ['like', 'mention', 'tag'];
        if (in_array($action, $ignoreActions)) {
            return;
        }

        $app =\XF::app();
        $options = $app->options();

        if (!isset($options->tp_push_key) || !$options->tp_push_key) {
            return;
        }

        /** @var \XF\Entity\Post $post */
        $post = $app->finder('XF:Post')->whereId($id)->fetchOne();
        if (!$post) {
            return;
        }
        $thread = $post->Thread;
        if (!$thread) {
            return;
        }
        if ($action == 'insert'){
            if ($thread->first_post_id == $id) {
                $action = 'newtopic';
            }else{
                $action = 'sub';
            }
        }
        $allow_action = array(
            'watch_reply' => 'sub',
            'quote'       => 'quote',
            'tag'         => 'tag',
            'tagged'      => 'tag',
            'like'        => 'like',
            'sub'         => 'sub',
            'newtopic'    => 'newtopic'
        );
        if(!isset($allow_action[$action]) || empty($allow_action[$action])){
            return;
        }

        $boardUrl = self::getBoardUrl();
        if (!self::includeTapatalkPushClass()) {
            return false;
        }
        $TapatalkPush = new \TapatalkPush($options->tp_push_key, $boardUrl);
        @$TapatalkPush->processPush($action, $post, $thread);
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

}