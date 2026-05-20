<?php

use Tapatalk\Bridge;

defined('MBQ_IN_IT') or exit;

MbqMain::$oClk->includeClass('MbqBaseActUserSubscription');

Class MbqActUserSubscription extends MbqBaseActUserSubscription
{

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * action implement
     *
     * @param $in
     */
    public function actionImplement($in)
    {
        $forums = array();
        $topics = array();
        $uid = $in->userId;
        $bridge = Bridge::getInstance();
        $visitor = $bridge::visitor();

        $forumWatchModel = $bridge->getForumWatchRepo();
        $nodeRepo = $bridge->getNodeRepo();

        $forumsWatched = $forumWatchModel->getUserForumWatchByUser($uid);
        if ($forumsWatched) {
            $forumIds = $forumsWatched;
            $nodeDetails = $nodeRepo->getNodesByIds($forumIds); //->filterViewable();
            foreach ($nodeDetails as $id => $node) {
                switch ($node['node_type_id']) {
                    case 'Category' :
                        $nodeType = 'category';
                        break;
                    case 'LinkForum':
                        $nodeType = 'link';
                        break;
                    default :
                        $nodeType = 'forum';
                }

                $forums[] = array(
                    'fid' => $node['node_id'],
                    'name' => $node['title'],
                    'type' => $nodeType,
                );
            }
        }

        //TOPICS
        $threadWatchModel = $bridge->getThreadWatchRepo();
        $threadWatchs = $threadWatchModel->getUserThreadWatchByUser($uid);
        if ($threadWatchs) {
            $threadWatchs = $threadWatchs->toArray();
            $threadIds = [];
            foreach ($threadWatchs as $threadWatch) {
                if (isset($threadWatch['thread_id'])) {
                    $threadIds[] = $threadWatch['thread_id'];
                }
            }
            if ($threadIds) {
                $threadRepo = $bridge->getThreadRepo();
                $threads = $threadRepo->getThreadsByIds($threadIds);
                if ($threads) {
                    $threads = $threads->filterViewable();
                    if ($threads) {
                        $threads = $threads->toArray();
                    }
                }
                foreach ($threads as &$thread) {
                    $topics[] = $thread['thread_id'];
                }
            }
        }

        $this->data = array(
            'result' => true,
            'forums' => $forums,
            'topics' => $topics,
        );
    }

}