<?php
use Tapatalk\Bridge;

defined('MBQ_IN_IT') or exit;

MbqMain::$oClk->includeClass('MbqBaseRdCommon');

Class MbqRdCommon extends MbqBaseRdCommon {

    public $bridge;

    public function __construct()
    {
        $this->bridge = Bridge::getInstance();
    }

    public function getApiKey()
    {
        $bridge = Bridge::getInstance();
        $tp_push_key = $bridge->options()->tp_push_key;
        if ($tp_push_key) {
            return $tp_push_key;
        }
        $optionRepo = $bridge->getOptionRepo();
        if ($optionKey = $optionRepo->getOptionById('tp_push_key')) {
            if ($optionKey->getOptionValue()) {
                return $optionKey->getOptionValue();
            }
        }

        return null;
    }
    public function getForumUrl()
    {
        return TT_get_board_url();
    }
    public function getCheckSpam()
    {
        return false;
    }

    public function get_id_by_url($url)
    {
        $bridge  = $this->bridge;
        $url = str_ireplace("index.php?", "", $url);
        $request = $bridge->_request;

        $routePath = $request->getRoutePathFromUrl($url);

        /** @var \XF\Mvc\RouteMatch $routeMatch */
        $routeMatch = $bridge->app()->router(null)->routeToController($routePath);
        $routeClassName = $routeMatch->getController();
        /** @var \XF\Mvc\ParameterBag $params */
        $params = $routeMatch->getParameterBag();

        switch($routeClassName){
            case "XF:Thread":
                if($theId = $params->get('thread_id')) {
                    /** @var MbqRdEtForumTopic $oMbqRdEtForumTopic */
                    $oMbqRdEtForumTopic = MbqMain::$oClk->newObj('MbqRdEtForumTopic');
                    return $oMbqRdEtForumTopic->initOMbqEtForumTopic($theId, array('case'=>'byTopicId'));
                }
                break;

            case "XF:Forum":
                if($theId = $params->get('node_id')) {
                    /** @var MbqRdEtForum $oMbqRdEtForum */
                    $oMbqRdEtForum = MbqMain::$oClk->newObj('MbqRdEtForum');
                    return $oMbqRdEtForum->initOMbqEtForum($theId, array('case'=>'byForumId'));
                }
                break;

            case "XF:Post":
                if($theId = $params->get('post_id')) {
                    /** @var MbqRdEtForumPost $oMbqRdEtForumPost */
                    $oMbqRdEtForumPost = MbqMain::$oClk->newObj('MbqRdEtForumPost');
                    return $oMbqRdEtForumPost->initOMbqEtForumPost($theId, array('case'=>'byPostId'));
                }
                break;

            case "XF:Conversation":
                if($theId = $params->get('conversation_id')) {
                    /** @var MbqRdEtPc $oMbqRdEtPc */
                    $oMbqRdEtPc = MbqMain::$oClk->newObj('MbqRdEtPc');
                    return $oMbqRdEtPc->initOMbqEtPc($theId, array('case'=>'byConvId'));
                }
                break;
        }

        return TT_GetPhraseString('dark_unknown_url');
    }

    public function getPushSlug()
    {
        $options = $this->bridge->options();
        $slug = $options->push_slug;
        if(isset($slug) && !empty($slug))
        {
            if (is_array($slug)) {
                return $slug;
            }
            return @unserialize($slug);
        }
        return null;
    }
    public function getSmartbannerInfo()
    {
        $options = $this->bridge->options();
        $tapatalkBannerControl = $options->tapatalk_banner_control;
        if(isset($tapatalkBannerControl))
        {
            if (is_array($tapatalkBannerControl)) {
                return $tapatalkBannerControl;
            }
            return unserialize($tapatalkBannerControl);
        }
        return null;
    }
    public function getTapatalkForumId()
    {
        $options = $this->bridge->options();
        if(isset($options->tapatalk_forum_id) && !empty($options->tapatalk_forum_id))
        {
            return $options->tapatalk_forum_id;
        }
        return null;
    }
}