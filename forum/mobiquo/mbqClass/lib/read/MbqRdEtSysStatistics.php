<?php

use Tapatalk\Bridge;

defined('MBQ_IN_IT') or exit;

MbqMain::$oClk->includeClass('MbqBaseRdEtSysStatistics');

/**
 * system statistics read class
 */
Class MbqRdEtSysStatistics extends MbqBaseRdEtSysStatistics
{

    public function __construct()
    {
    }

    public function makeProperty(&$oMbqEtSysStatistics, $pName, $mbqOpt = array())
    {
        switch ($pName) {
            default:
                MbqError::alert('', __METHOD__ . ',line:' . __LINE__ . '.' . MBQ_ERR_INFO_UNKNOWN_PNAME . ':' . $pName . '.');
                break;
        }
    }

    public function initOMbqEtSysStatistics()
    {
        $bridge = Bridge::getInstance();

        $visitor = $bridge::visitor();

        if (!$visitor->canViewMemberList())
        {
            return $bridge->noPermissionToString();
        }

        $activityRepo = $bridge->getSessionActivityRepo();

        $onlineUserCounts = $activityRepo->getOnlineCounts();
        $onlineAllTotal = $onlineUserCounts['total'];
        $memberOnlineTotal = $onlineUserCounts['members'];
        $guestsOnlineTotal = $onlineUserCounts['guests'];


        $statistics = $bridge->getCountersRepo()->getForumStatisticsCacheData();

        $activeMemberTotal = $bridge->getUserRepo()->findValidUsers()->total();

        /** @var MbqEtSysStatistics $oMbqEtSysStatistics */
        $oMbqEtSysStatistics = MbqMain::$oClk->newObj('MbqEtSysStatistics');

        $oMbqEtSysStatistics->forumTotalThreads->setOriValue($statistics['threads']);
        $oMbqEtSysStatistics->forumTotalPosts->setOriValue($statistics['messages']);
        $oMbqEtSysStatistics->forumTotalMembers->setOriValue($statistics['users']);
        $oMbqEtSysStatistics->forumActiveMembers->setOriValue($activeMemberTotal);
        $oMbqEtSysStatistics->forumTotalOnline->setOriValue($guestsOnlineTotal + $memberOnlineTotal);
        $oMbqEtSysStatistics->forumGuestOnline->setOriValue($guestsOnlineTotal);

        return $oMbqEtSysStatistics;
    }
}