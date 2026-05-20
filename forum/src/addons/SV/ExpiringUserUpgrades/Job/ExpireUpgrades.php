<?php
namespace SV\ExpiringUserUpgrades\Job;

use XF\Entity\UserUpgradeActive;
use XF\Job\AbstractRebuildJob;
use function count;

class ExpireUpgrades extends AbstractRebuildJob
{
    protected $defaultData = [
        'user_upgrade_id' => null,
        'excluded_payment_provider_ids' => null,
        'timestamp' => null,
    ];

    protected function getNextIds($start, $batch)
    {
        $db = $this->app->db();

        $userUpgradeId = (int)($this->data['user_upgrade_id'] ?? 0);
        if ($userUpgradeId <= 0)
        {
            return [];
        }
        $timestamp = (int)($this->data['timestamp'] ?? 0);
        if ($timestamp <= 0)
        {
            return [];
        }

        $excludedPaymentProviderIds = (array)($this->data['excluded_payment_provider_ids'] ?? []);
        $excludedPaymentProviderIdsSql = count($excludedPaymentProviderIds) !== 0
            ? ' AND (xpr.payment_profile_id is null or xpr.payment_profile_id not in ('.$db->quote($excludedPaymentProviderIds).'))'
            : '';

        return $db->fetchAllColumn($db->limit('
            SELECT activeUpgrade.user_upgrade_record_id
            FROM xf_user_upgrade_active AS activeUpgrade
            JOIN xf_user_upgrade xuu ON activeUpgrade.user_upgrade_id = xuu.user_upgrade_id
            JOIN xf_user xu ON activeUpgrade.user_id = xu.user_id
            LEFT JOIN xf_purchase_request xpr ON (activeUpgrade.purchase_request_key = xpr.request_key)
            WHERE activeUpgrade.user_upgrade_record_id > ? 
              AND activeUpgrade.user_upgrade_id = ?
              AND activeUpgrade.notified_date = 0
              AND activeUpgrade.end_date > ?
              AND activeUpgrade.end_date <= ?
              '.$excludedPaymentProviderIdsSql.'
            ORDER BY activeUpgrade.user_upgrade_record_id
        ', $batch), [$start, $userUpgradeId, \XF::$time, $timestamp]);
    }

    protected function rebuildById($id)
    {
        /** @var UserUpgradeActive $upgradeAboutToExpire */
        $upgradeAboutToExpire = $this->app->find('XF:UserUpgradeActive', $id);
        if ($upgradeAboutToExpire === null)
        {
            return;
        }

        /** @var \SV\ExpiringUserUpgrades\XF\Repository\UserUpgrade $userUpgradeRepo */
        $userUpgradeRepo = \XF::repository('XF:UserUpgrade');
        $userUpgradeRepo->alertExpiringUserUpgrade($upgradeAboutToExpire);

    }

    protected function getStatusType(): \XF\Phrase
    {
        return \XF::phrase('exup_user_upgrade_expiry_notify');
    }
}