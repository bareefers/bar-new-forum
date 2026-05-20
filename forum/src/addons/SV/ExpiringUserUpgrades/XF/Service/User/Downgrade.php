<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\ExpiringUserUpgrades\XF\Service\User;

use SV\ExpiringUserUpgrades\Globals;
use SV\ExpiringUserUpgrades\XF\Repository\UserUpgrade;

/**
 * Extends \XF\Service\User\Downgrade
 */
class Downgrade extends XFCP_Downgrade
{
    /**
     * @return bool
     * @throws \XF\PrintableException
     * @throws \Exception
     */
    public function downgrade()
    {
        $downgradeReason = Globals::$downgradeReason ?: 'expired_upgrade';
        if ($downgradeReason === 'payment_reversal_upgrade')
        {
            /** @var \SV\ExpiringUserUpgrades\XF\Entity\UserUpgradeActive|null $activeUpgrade */
            $activeUpgrade = $this->activeUpgrade;
            if ($activeUpgrade !== null && $activeUpgrade->end_date > 0)
            {
                /** @var \SV\ExpiringUserUpgrades\XF\Entity\UserUpgrade $upgrade */
                $upgrade = $activeUpgrade->Upgrade;
                $doReversalDowngrade = true;
                $extendedStartDate = $activeUpgrade->start_date;

                if ($upgrade->recurring)
                {
                    if (!isset($activeUpgrade->extra['recurring']) || $activeUpgrade->extra['recurring'])
                    {
                        $doReversalDowngrade = false;
                    }
                }

                if ($doReversalDowngrade)
                {
                    // maybe not hard fail?
                    if (!isset($activeUpgrade->extra['length_amount']) || !isset($activeUpgrade->extra['length_unit']))
                    {
                        \XF::logException(new \LogicException("Extra information missing required data"));

                        return false;
                    }
                    $extendedStartDate = strtotime('-' . $activeUpgrade->extra['length_amount'] . ' ' . $activeUpgrade->extra['length_unit'], $activeUpgrade->end_date);

                    if ($extendedStartDate <= $activeUpgrade->start_date)
                    {
                        $doReversalDowngrade = false;
                    }
                }

                if ($doReversalDowngrade)
                {
                    $activeUpgrade->end_date = $extendedStartDate;
                    $activeUpgrade->notified_date = 0;
                    $activeUpgrade->save();


                    if (isset($activeUpgrade->extra['recurring']) && $activeUpgrade->extra['recurring'])
                    {
                        $downgradeReason = 'reversal_extend_subscription';
                    }
                    else
                    {
                        $downgradeReason = 'reversal_extend_upgrade';
                    }

                    \XF::asVisitor($this->user, function () use ($downgradeReason) {
                        /** @var UserUpgrade $userUpgradeRepo */
                        $userUpgradeRepo = \XF::repository('XF:UserUpgrade');
                        $userUpgradeRepo->svNotifyUser($this->activeUpgrade, $this->userUpgrade, $downgradeReason);
                    });

                    return true;
                }
                else if (!empty($activeUpgrade->extra['recurring']))
                {
                    $downgradeReason = 'payment_reversal_subscription';
                }
            }
        }

        $originalSendAlert = $this->sendAlert;
        $this->sendAlert = false;
        $success = parent::downgrade();
        $this->sendAlert = $originalSendAlert;

        /** @var \SV\ExpiringUserUpgrades\XF\Entity\UserUpgradeActive $activeUpgrade */
        $activeUpgrade = $this->activeUpgrade;
        if ($activeUpgrade && (Globals::$forceDowngradeAlert || $this->app->options()->exup_notify_expired))
        {
            \XF::asVisitor($this->user, function () use ($activeUpgrade, $downgradeReason) {
                if (!empty($activeUpgrade->extra['recurring']) && $downgradeReason === 'expired_upgrade')
                {
                    $downgradeReason = 'expired_subscription';
                }

                /** @var UserUpgrade $userUpgradeRepo */
                $userUpgradeRepo = \XF::repository('XF:UserUpgrade');
                $userUpgradeRepo->svNotifyUser($activeUpgrade, $this->userUpgrade, $downgradeReason);
            });
        }

        return $success;
    }
}