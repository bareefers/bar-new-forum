<?php
use Tapatalk\Bridge;
use XF\Phrase as XenForoPhrase;
use XF\Legacy\Link as XenForoLink;

defined('MBQ_IN_IT') or exit;

MbqMain::$oClk->includeClass('MbqBaseRdEtForum');

// dev
//if (XenForo_Template_Helper_Core::styleProperty('forumIconReadPath'))
//{
//    $icon_read =  XenForo_Link::convertUriToAbsoluteUri(XenForo_Template_Helper_Core::styleProperty('forumIconReadPath'), true);
//    $icon_unread = XenForo_Link::convertUriToAbsoluteUri(XenForo_Template_Helper_Core::styleProperty('forumIconUnreadPath'), true);
//    $icon_link = XenForo_Link::convertUriToAbsoluteUri(XenForo_Template_Helper_Core::styleProperty('linkIconPath'), true);
//}
//else
//{
//    $tapatalk_dir_name = XenForo_Application::get('options')->tp_directory;
//    if (empty($tapatalk_dir_name)) $tapatalk_dir_name = 'mobiquo';
//    $icon_read =   FORUM_ROOT.$tapatalk_dir_name.'/forum_icons/forum-read.png';
//    $icon_unread = FORUM_ROOT.$tapatalk_dir_name.'/forum_icons/forum-unread.png';
//    $icon_link =   FORUM_ROOT.$tapatalk_dir_name.'/forum_icons/link.png';
//}


/**
 * forum read class
 */
Class MbqRdEtForum extends MbqBaseRdEtForum
{

    public static $bridge;

    public function __construct()
    {
        self::$bridge = Bridge::getInstance();
    }

    public function makeProperty(&$oMbqEtForum, $pName, $mbqOpt = array())
    {
        switch ($pName) {
            default:
                MbqError::alert('', __METHOD__ . ',line:' . __LINE__ . '.' . MBQ_ERR_INFO_UNKNOWN_PNAME . ':' . $pName . '.');
                break;
        }
    }

    /**
     * @param $return_description
     * @param $root_forum_id
     * @return array
     */
    public function getForumTree($return_description, $root_forum_id)
    {

        $nodeRepo = self::$bridge->getNodeRepo();

        $nodes = $nodeRepo->getNodeListAndForum();
        if ($nodes) {
            $nodes = $nodes->toArray();
            $nodesArray = $nodes;
        }else{
            $nodesArray = $nodes = [];
        }

        /** @var \XF\Entity\Node $node */
        foreach ($nodes as $id => $node) {
            if (($node['parent_node_id'] != 0 && !isset($nodes[$node['parent_node_id']])) || !$node['display_in_list'])
                unset($nodesArray[$id]);

            if (!isset($nodesArray['hasNew'])) {
                $hasNew = $this->getNodeHasNewByChilds($nodes, $id);

                if (isset($nodesArray[$id]) && is_object($nodesArray[$id])) {
                    $nodesArray[$id] = $nodesArray[$id]->toArray();
                    $nodesArray[$id]['xfNodeObject'] = $node;
                }  // change obj to array
                $nodesArray[$id]['hasNew'] = $hasNew;
            }

            if (isset($nodesArray[$id]) && is_object($nodesArray[$id])) {
                $nodesArray[$id] = $nodesArray[$id]->toArray();
                $nodesArray[$id]['xfNodeObject'] = $node;
            }  // change obj to array
        }

        $done = array();

        if (isset($root_forum_id) && !empty($root_forum_id)) {
            $result = $this->treeBuild($root_forum_id, $nodesArray, $xml_nodes, $done);
        } else {
            $result = $this->treeBuild(0, $nodesArray, $xml_nodes, $done);
        }

        return $result;
    }

    protected function getNodeHasNewByChilds($nodes, $id)
    {
        $currentNode = $nodes[$id];
        if (isset($currentNode['hasNew']) && $currentNode['hasNew']) {
            return true;
        }
        if (is_object($currentNode) && $currentNode['node_type_id'] == 'Forum' && $currentNode->Forum) {
            /** @var \XF\Entity\Forum $forum */
            $forum = $currentNode->Forum;
            if ($forum->isUnread()) {
                return true;
            }
        }
        foreach ($nodes as $childId => $childnode) {
            if ($childnode['parent_node_id'] == $id) {
                if (is_object($childnode) && $childnode['node_type_id'] == 'Forum' && $childnode->Forum) {
                    /** @var \XF\Entity\Forum $forum */
                    $forum = $childnode->Forum;
                    if ($forum->isUnread()) {
                        return true;
                    }else{
                        return $this->getNodeHasNewByChilds($nodes, $childId);
                    }
                }else{
                    return $this->getNodeHasNewByChilds($nodes, $childId);
                }
            }
        }
        return false;
    }

    protected function treeBuild($parent_id, &$nodes, &$xml_nodes, &$done)
    {
        $newNodes = array();
        foreach ($nodes as $id => &$node) {
            // not interested in page nodes or nodes from addons etc.
            if (!isset($node['node_type_id']) || ($node['node_type_id'] != 'Forum' && $node['node_type_id'] != 'Category' && $node['node_type_id'] != 'LinkForum'))
                continue;

            if ((string)$node['parent_node_id'] === (string)$parent_id && !array_key_exists((string)$id, $done)) {
                $done[(string)$id] = true;
                $child_nodes = $this->treeBuild($id, $nodes, $xml_nodes, $done);
                $node2 = $this->initOMbqEtForum($node, array('case' => 'byRow'));

                if (empty($child_nodes)) {
                    if ($node['node_type_id'] == 'Category') continue;
                } else
                    $node2->objsSubMbqEtForum = $child_nodes;

                $newNodes[] = $node2;

            }
        }

        return $newNodes;
    }

    protected function treeBuildResources($parent_id, &$nodes, &$xml_nodes, &$done)
    {
        $newNodes = array();
        foreach ($nodes as $id => &$node) {
            // not interested in page nodes or nodes from addons etc.
            if ($node['parent_category_id'] === $parent_id && !array_key_exists($id, $done)) {
                $done[$id] = true;
                $child_nodes = $this->treeBuildResources($id, $nodes, $xml_nodes, $done);
                $node2 = $this->initOMbqEtForum($node, array('case' => 'byResourceRow'));
                $node2->objsSubMbqEtForum = $child_nodes;
                $newNodes[] = $node2;
            }
        }

        return $newNodes;
    }

    function stillHasChildren($id, &$nodes)
    {
        foreach ($nodes as $node_id => $node) {
            if ($node['parent_node_id'] === $id /*&& $node_id !== $id && $id !== 0*/) return true;
        }

        return false;
    }

    /**
     * get forum objs
     *
     * @param  Mixed $var
     * @param  Array $mbqOpt
     * $mbqOpt['case'] = 'byForumIds' means get data by forum ids.$var is the ids.
     * $mbqOpt['case'] = 'subscribed' means get subscribed data.$var is the user id.
     * @return  Array
     */
    public function getObjsMbqEtForum($var, $mbqOpt)
    {
        switch ($mbqOpt['case']) {
            case 'byForumIds':

                $forumIds = $var;
                if (!is_array($forumIds)) {
                    $forumIds = array($forumIds);
                }
                $objsMbqEtForum = array();
                foreach ($forumIds as $forumId) {
                    if (mobiquo_hide_forum($forumId)) {
                        continue;
                    }
                    $mbqForum = $this->initOMbqEtForum($forumId, array('case' => 'byForumId'));
                    if ($mbqForum) $objsMbqEtForum[] =$mbqForum;
                }
                return $objsMbqEtForum;
                break;

            case 'subscribed':

                $bridge = self::$bridge;
                $visitor = $bridge->getVisitor();
                $forum_list = array();

                $forumModel = $bridge->getForumRepo();
                $forumDetails = $forumModel->getForumsByUserSubscribed($visitor['user_id']);

                foreach ($forumDetails as $id => $node) {
                    // filtering hideForums
                    $options = self::$bridge->options();
                    $hideForums = $options->hideForums;
                    if (in_array($node['node_id'], $hideForums)) {
                        continue;
                    }
                    $mbqForum = $this->initOMbqEtForum($node, array('case' => 'byRow'));
                    if ($mbqForum) $forum_list[]= $mbqForum;
                }

                return $forum_list;
                break;
        }

        MbqError::alert('', __METHOD__ . ',line:' . __LINE__ . '.' . MBQ_ERR_INFO_UNKNOWN_CASE);
    }


    /**
     * @param $var
     * @param $mbqOpt
     * @return MbqEtForum|null
     */
    public function initOMbqEtForum($var, $mbqOpt)
    {
        $bridge = self::$bridge;
        $case = $mbqOpt['case'];
        $return = null;
        switch ($case) {
            case 'byForumId':
                $return = $this->initOMbqEtForumByForumId($var, $mbqOpt, $bridge);
                break;

            case 'byRow':
                $return = $this->initOMbqEtForumByRow($var, $mbqOpt, $bridge);
                break;

            case 'byResourceRow':
                $return = $this->initOMbqEtForumByResourceRow($var, $mbqOpt, $bridge);
                break;
        }

        return $return;
    }

    protected function initOMbqEtForumByForumId($forumId, $mbqOpt, Bridge $bridge)
    {

            $forumRepo = $bridge->getForumRepo();
            $forum = $forumRepo->assertForumValidAndViewable($forumId);
            if (!$forum) {
                return null;
            }
            $objsMbqEtForum = $this->initOMbqEtForum($forum, array('case' => 'byRow'));

            return $objsMbqEtForum;
    }

    /**
     * @param $node
     * @param $mbqOpt
     * @param Bridge $bridge
     * @return MbqEtForum
     */
    protected function initOMbqEtForumByRow($node, $mbqOpt, Bridge $bridge)
    {
        global $icon_read, $icon_unread, $icon_link;

        $inputOriginNode = $node;
        $forum_id = $node['node_id'];
        if (MbqMain::$Cache->Exists('MbqEtForum', $forum_id)) {

            return MbqMain::$Cache->Get('MbqEtForum', $forum_id);
        }
        if (!$forum_id || !$node)
            return null;

        $bridge = self::$bridge;
        $visitor = $bridge::visitor();

        // initType : forum or node

        if ($node instanceof \XF\Entity\Forum) {
            $getForum = $node;
            if ($node->Node) $node = $node->Node;
        }
        $url = '';
        if($node['node_type_id'] == 'LinkForum'){
            $linkForumModel = $bridge->getForumRepo();
            $link = $linkForumModel->getLinkForumByNodeId($forum_id);
            $url = $link['link_url'];
        }

        switch ($node['node_type_id']) {
            case 'Category' : $nodeType = 'category'; break;
            case 'LinkForum': $nodeType = 'link'; break;
            default : $nodeType = 'forum';
        }

        if (!is_object($node) && $nodeType == 'forum') {
            /** @var \XF\Entity\Forum $getForum */
            $getForum = $bridge->getForumRepo()->findForumById($forum_id);
            if ($getForum) {
                $node = $getForum->Node;
            }
        }

        $icon = '';
//        $icon = tp_get_forum_icon($forum_id, $nodeType, false, ($node['hasNew'] || !$visitor['user_id']) );
//        if (empty($icon)) {
//            if($node['node_type_id'] == 'LinkForum') {
//                $icon = $icon_link;
//            } else {
//                $icon = ($node['hasNew'] || !$visitor['user_id']) ? $icon_unread : $icon_read;
//            }
//        }

        $subscriptionEmail = false;

        if($nodeType == 'forum') {

            $subscriptionStatus = $bridge->getForumWatchRepo()->getUserForumWatchByForumId($visitor['user_id'], $forum_id);
            if($subscriptionStatus) {
                $is_subscribed = true;
                if($subscriptionStatus['send_email'] == 1) {
                    $subscriptionEmail = true;
                }
            } else {
                $is_subscribed = false;
            }

            $can_subscribe = false;
            if (isset($getForum) && $getForum) {
                $can_subscribe = ($getForum->canWatch() && MbqMain::isActiveMember());
            }

            // readonlyForums , admin can setting tapatalk ( Disable New Topic )
            $options = $bridge->options();
            $readonlyForums = $options->readonlyForums;

            $processed_roForums = [];
            if(is_array($readonlyForums)) {
                $processed_roForums = $readonlyForums;
            }
            $processed_roForums = array_unique($processed_roForums);

            $can_post = false;
            if (isset($getForum) && $getForum) {
                $can_post = $getForum->canCreateThread() && !in_array($forum_id, $processed_roForums);
            }

        } else {
            // is a node no forum
            $is_subscribed = false;
            $can_subscribe = false;
            $can_post = false;
        }

        $canUpload = false;
        if (isset($getForum) && $getForum) {
            $canUpload = $getForum->canUploadAndManageAttachments();
        }

        $hasNew = 0;
        if (is_array($inputOriginNode) && isset($inputOriginNode['hasNew']) && $inputOriginNode['hasNew']) {
            $hasNew = $inputOriginNode['hasNew'];
        }elseif (isset($node['hasNew']) && !empty($node['hasNew'])) {
            $hasNew = $node['hasNew'];
        }

        /** @var MbqEtForum $oMbqEtForum */
        $oMbqEtForum = MbqMain::$oClk->newObj('MbqEtForum');
        $oMbqEtForum->forumId->setOriValue($forum_id);
        $oMbqEtForum->forumName->setOriValue($node['title']);
        $oMbqEtForum->parentId->setOriValue($node['parent_node_id']);
        $oMbqEtForum->description->setOriValue($node['description']);
        $oMbqEtForum->logoUrl->setOriValue($icon);
        $oMbqEtForum->newPost->setOriValue($hasNew);
        $oMbqEtForum->unreadTopicNum->setOriValue(isset($node['hasNew']) && !empty($node['hasNew']) ? $node['hasNew'] : 0);
        $isProtected = false;
        try {
            $compatibleOtherPlugin = $bridge::getCompatibleOtherPlugin();
            $isProtectedOtherPlugin = $compatibleOtherPlugin::forumIsProtected($forum_id);
            if (is_bool($isProtectedOtherPlugin)) $isProtected = $isProtectedOtherPlugin;
        }catch (\Exception $e){}
        $oMbqEtForum->isProtected->setOriValue($isProtected);
        $oMbqEtForum->isSubscribed->setOriValue($is_subscribed);
        if($is_subscribed)
        {
            $oMbqEtForum->subscriptionEmail->setOriValue($subscriptionEmail);
        }
        $oMbqEtForum->canSubscribe->setOriValue($can_subscribe);
        $oMbqEtForum->url->setOriValue($url);
        $oMbqEtForum->subOnly->setOriValue($node['node_type_id'] == 'Category');
        $oMbqEtForum->canPost->setOriValue($can_post);
        $oMbqEtForum->canUpload->setOriValue($canUpload);

        $prefixes_list = [];
        if (isset($getForum) && $getForum) {
            $prefixGroups = $getForum->getPrefixesGrouped();
            if (!empty($prefixGroups)) {
                foreach ($prefixGroups as $prefixGroup) {
                    foreach ($prefixGroup as $prefix) {
                        $prefixItem = array(
                            'id' => $prefix['prefix_id'],
                            'name' => TT_get_prefix_name($prefix['prefix_id']),
                        );
                        $prefixes_list[] = $prefixItem;
                    }
                }
            }
        }
        $oMbqEtForum->prefixes->setOriValue($prefixes_list);

        $require_prefix = isset($node['require_prefix']) ? $node['require_prefix'] : 0;
        if (!$require_prefix && isset($getForum) && $getForum) {
            $require_prefix = isset($getForum['require_prefix']) ? $getForum['require_prefix'] : 0;
        }
        $oMbqEtForum->requirePrefix->setOriValue($require_prefix);

        $oMbqEtForum->mbqBind = ( isset($getForum) && $getForum) ? $getForum : $node;;
        MbqMain::$Cache->Set('MbqEtForum',$forum_id, $oMbqEtForum);

        return $oMbqEtForum;
    }

    // dev resource Category ?
    protected function initOMbqEtForumByResourceRow($node, $mbqOpt, Bridge $bridge)
    {
        global $icon_read, $icon_unread, $icon_link;

        $forum_id = $bridge->xenResourcePrefix . $node['node_id'];  // dev

        if (MbqMain::$Cache->Exists('MbqEtForum', $forum_id)) {

            return MbqMain::$Cache->Get('MbqEtForum', $forum_id);
        }

        $is_subscribed = $bridge->getForumWatchRepo()->getUserForumWatchByForumId('', $forum_id); // dev $bridge->getXenResourceCategoryWatchModel()->getUserCategoryWatchByCategoryId($visitor['user_id'], $node['node_id']);
        $can_subscribe = $is_subscribed ? false : true; // dev
        $can_post = false;

        $oMbqEtForum = MbqMain::$oClk->newObj('MbqEtForum');
        $oMbqEtForum->forumId->setOriValue($forum_id);
        $oMbqEtForum->forumName->setOriValue($node['title']);
        $oMbqEtForum->parentId->setOriValue($bridge->xenResourcePrefix . $node['parent_node_id']); // dev
        $oMbqEtForum->description->setOriValue($node['description']);
        //$oMbqEtForum->logoUrl->setOriValue($icon);
        $oMbqEtForum->newPost->setOriValue(false);
        $oMbqEtForum->unreadTopicNum->setOriValue(0);
        $isProtected = false;
        try {
            $compatibleOtherPlugin = $bridge::getCompatibleOtherPlugin();
            $isProtectedOtherPlugin = $compatibleOtherPlugin::forumIsProtected($forum_id);
            if (is_bool($isProtectedOtherPlugin)) $isProtected = $isProtectedOtherPlugin;
        }catch (\Exception $e){}
        $oMbqEtForum->isProtected->setOriValue($isProtected);
        $oMbqEtForum->isSubscribed->setOriValue($is_subscribed);
        $oMbqEtForum->canSubscribe->setOriValue($can_subscribe);
        //$oMbqEtForum->url->setOriValue($url);
        $oMbqEtForum->subOnly->setOriValue(false);
        $oMbqEtForum->canPost->setOriValue(false);
        $oMbqEtForum->canUpload->setOriValue(false);

        $prefixes_list = array();

//        $prefixModel = $bridge->getXenResourcePrefixModel();
//        $prefixGroups = $prefixModel->getUsablePrefixesInCategories($node['node_id']);
//        if (!empty($prefixGroups)) {
//            foreach ($prefixGroups as $prefixGroup) {
//                foreach ($prefixGroup['prefixes'] as $prefix) {
//                    $prefixItem = array(
//                        'id' => $prefix['prefix_id'],
//                        'name' => TT_get_prefix_name($prefix['prefix_id']),
//                    );
//                    $prefixes_list[] = $prefixItem;
//                }
//            }
//        }
        $oMbqEtForum->prefixes->setOriValue($prefixes_list);
        $oMbqEtForum->requirePrefix->setOriValue(isset($node['require_prefix']) && $node['require_prefix']);

        $oMbqEtForum->mbqBind = $node;
        MbqMain::$Cache->Set('MbqEtForum', $forum_id, $oMbqEtForum);
        return $oMbqEtForum;
    }

    /**
     * login forum
     *
     * @return string
     */
    public function loginForum($oMbqEtForum, $password)
    {
        $bridge = self::$bridge;
        $forum_id = $oMbqEtForum->forumId->oriValue;
        try {
            $compatibleOtherPlugin = $bridge::getCompatibleOtherPlugin();
            $loginOtherPlugin = $compatibleOtherPlugin::loginForum($forum_id, $password);
            if ($loginOtherPlugin !== null) {
                if ($loginOtherPlugin == false) {
                    return 'Password is wrong';
                }
                return $loginOtherPlugin;
            }
        }catch (\Exception $e){}
        return TT_GetPhraseString('dark_passworded_forums_not_supported');
    }

    public function getUrl($oMbqEtForum)
    {
        return XenForoLink::buildPublicLink('full:forums', $oMbqEtForum->mbqBind);
    }
}