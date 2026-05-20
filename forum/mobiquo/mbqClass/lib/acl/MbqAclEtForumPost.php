<?php
use Tapatalk\Bridge;

defined('MBQ_IN_IT') or exit;

MbqMain::$oClk->includeClass('MbqBaseAclEtForumPost');

/**
 * forum post acl class
 */
Class MbqAclEtForumPost extends MbqBaseAclEtForumPost
{

    public function __construct()
    {
    }

    /**
     * judge can reply post
     *
     * @param  Object $oMbqEtForumPost
     * @return  Boolean
     */
    public function canAclReplyPost($oMbqEtForumTopic)
    {
        return $oMbqEtForumTopic->canReply->oriValue && MbqMain::isActiveMember();
    }

    /**
     * judge can get quote post
     *
     * @param  Object $oMbqEtForumPost
     * @return  Boolean
     */
    public function canAclGetQuotePost($oMbqEtForumPost)
    {
        return $oMbqEtForumPost->oMbqEtForumTopic->canReply->oriValue;
    }

    /**
     * judge can search_post
     *
     * @return  Boolean
     */
    public function canAclSearchPost()
    {
        $bridge = Bridge::getInstance();
        $visitor = $bridge::visitor();
        return $visitor->canSearch();
    }

    /**
     * judge can get_raw_post
     *
     * @param  Object $oMbqEtForumPost
     * @return  Boolean
     */
    public function canAclGetRawPost($oMbqEtForumPost)
    {
        return $oMbqEtForumPost->canEdit->oriValue;
    }

    /**
     * judge can save_raw_post
     *
     * @param  Object $oMbqEtForumPost
     * @return  Boolean
     */
    public function canAclSaveRawPost($oMbqEtForumPost)
    {
        return $oMbqEtForumPost->canEdit->oriValue;
    }

    /**
     * judge can get_user_reply_post
     *
     * @return  Boolean
     */
    public function canAclGetUserReplyPost()
    {
        return true;
    }

    /**
     * judge can report_post
     *
     * @param  Object $oMbqEtForumPost
     * @return  Boolean
     */
    public function canAclReportPost($oMbqEtForumPost)
    {
        return $oMbqEtForumPost->canReport->oriValue;
    }

    /**
     * judge can thank_post
     *
     * @param  Object $oMbqEtForumPost
     * @return  Boolean
     */
    public function canAclThankPost($oMbqEtForumPost)
    {
        return $oMbqEtForumPost->canThank->oriValue;
    }

    /**
     * judge can m_delete_post
     *
     * @param  Object $oMbqEtForumPost
     * @param  Integer $mode
     * @return  Boolean
     */
    public function canAclMDeletePost($oMbqEtForumPost, $mode)
    {
        return $oMbqEtForumPost->canDelete->oriValue;
    }

    /**
     * judge can m_undelete_post
     *
     * @param  Object $oMbqEtForumPost
     * @return  Boolean
     */
    public function canAclMUndeletePost($oMbqEtForumPost)
    {
        return $oMbqEtForumPost->canDelete->oriValue;
    }

    /**
     * judge can m_move_post
     *
     * @param  Object $oMbqEtForumPost
     * @param  Mixed $oMbqEtForum
     * @param  Mixed $oMbqEtForumTopic
     * @return  Boolean
     */
    public function canAclMMovePost($oMbqEtForumPosts, $oMbqEtForum, $oMbqEtForumTopic)
    {
        return $oMbqEtForumPosts[0]->canMove->oriValue;
    }


    /**
     * judge can m_approve_post
     *
     * @param  Object $oMbqEtForumPost
     * @param  Integer $mode
     * @return  Boolean
     */
    public function canAclMApprovePost($oMbqEtForumPost, $mode)
    {
        return $oMbqEtForumPost->canApprove->oriValue;
    }

    /**
     * judge can m_merge_post
     *
     * @param  Object $oMbqEtForumPost
     * @return  Boolean
     */
    public function canAclMMergePost($oMbqEtForumPost)
    {
        return $oMbqEtForumPost->canMerge->oriValue;
    }

    /**
     * judge can m_get_moderate_topic
     *
     * @return  Boolean
     */
    public function canAclMGetModeratePost()
    {
        return MbqMain::isActiveMember();
    }

    /**
     * judge can m_get_delete_topic
     *
     * @return  Boolean
     */
    public function canAclMGetDeletePost()
    {
        return MbqMain::isActiveMember();
    }

    /**
     * judge can m_close_report
     */
    public function canAclMCloseReport($oMbqEtForumPost)
    {
        return MbqMain::isActiveMember();
    }

    /**
     * judge can can like
     *
     * @param MbqEtForumPost $oMbqEtForumPost
     * @return bool
     */
    public function canAclLikePost($oMbqEtForumPost)
    {
        $bridge = Bridge::getInstance();
        $post = $oMbqEtForumPost->mbqBind;
        $postId = $oMbqEtForumPost->postId->oriValue;

        if (!$post || !($post instanceof \XF\Entity\Post)) {
            $postRepo = $bridge->getPostRepo();
            /** @var \XF\Entity\Post $post */
            $post = $postRepo->findPostById($postId);
        }

        if (method_exists($post, 'canReact')) {
            $rst = $post->canReact($error);
        }else{
            $rst = $post->canLike($error);
        }
        if (!$rst) {
            return $bridge->errorToString($error);
        }
        return true;
    }

    /**
     * @param MbqEtForumPost $oMbqEtForumPost
     * @return bool
     */
    public function canAclUnlikePost($oMbqEtForumPost)
    {
        $bridge = Bridge::getInstance();
        /** @var \XF\Entity\Post $post */
        $post = $oMbqEtForumPost->mbqBind;
        $postId = $oMbqEtForumPost->postId->oriValue;
        if (!$post || !($post instanceof \XF\Entity\Post)) {
            /** @var \XF\Entity\Post $post */
            $post = $bridge->getPostRepo()->findPostById($postId);
        }
        if (!$post) {
            return false;
        }
        if (method_exists($post, 'canReact')) {
            $rst = $post->canReact($error);
        }else{
            $rst = $post->canLike($error);
        }
        if (!$rst) {
            return $bridge->errorToString($error);
        }

        return true;
    }
}