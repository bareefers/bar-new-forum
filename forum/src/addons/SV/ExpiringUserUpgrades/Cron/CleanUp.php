<?php

namespace SV\ExpiringUserUpgrades\Cron;

/**
 * Class CleanUp
 *
 * @package SV\ExpiringUserUpgrades
 */
class CleanUp
{
    /**
     * Alert about to expire user upgrades.
     */
    public static function runAlertExpiringUserUpgrades()
    {
        /** @var \SV\ExpiringUserUpgrades\XF\Repository\UserUpgrade $userUpgradeRepo */
        $userUpgradeRepo = \XF::repository('XF:UserUpgrade');
        $userUpgradeRepo->alertExpiringUserUpgrades();
    }
}