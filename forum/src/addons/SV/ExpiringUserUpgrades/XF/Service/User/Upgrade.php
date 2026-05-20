<?php

namespace SV\ExpiringUserUpgrades\XF\Service\User;

use SV\ExpiringUserUpgrades\XF\Repository\UserUpgrade;

/**
 * Extends \XF\Service\User\Upgrade
 */
class Upgrade extends XFCP_Upgrade
{
    protected function finalSetup()
    {
        $active = $this->activeUpgrade;
        $upgrade = $this->userUpgrade;
        $extra = $active->extra;
        if ($active->user_upgrade_record_id && $this->endDate === null && !isset($extra['length_unit']))
        {
            $extra['cost_amount'] = $upgrade->cost_amount;
            $extra['cost_currency'] = $upgrade->cost_currency;
            $extra['length_amount'] = $upgrade->length_amount;
            $extra['length_unit'] = $upgrade->length_unit;
            $extra = array_merge($extra, $this->extraData);
            $active->extra = $extra;
        }
        parent::finalSetup();
    }

    /**
     * @return bool|\XF\Entity\UserUpgradeActive
     * @throws \Exception
     */
    public function upgrade()
    {
        $options = $this->app->options();
        $notifyUponPurchase = $options->exup_notify_purchase;
        $notifyUponRecurringExtend = $options->exup_notify_recurring_extend;

        $isExistingUpgrade = $this->activeUpgrade && $this->activeUpgrade->exists();
        $db = $this->db();
        $db->beginTransaction();

        /** @var \SV\ExpiringUserUpgrades\XF\Entity\UserUpgradeActive $active */
        $active = parent::upgrade();

        if ($active instanceof \XF\Entity\UserUpgradeActive)
        {
            $active->notified_date = 0;
            $upgrade = $active->Upgrade;
            $extra = $active->extra;
            $extra['extra_group_ids'] = $upgrade->extra_group_ids;
            $extra['recurring'] = $upgrade->recurring;
            $extra['cost_amount'] = $upgrade->cost_amount;
            $extra['cost_currency'] = $upgrade->cost_currency;
            $extra['length_amount'] = $upgrade->length_amount;
            $extra['length_unit'] = $upgrade->length_unit;
            $active->extra = $extra;
            $active->save();
        }

        $db->commit();

        if ($active instanceof \XF\Entity\UserUpgradeActive && $notifyUponPurchase)
        {
            $upgrade = $active->Upgrade;
            /*
            if ($isNewUpgrade)
            {
                if ($upgrade->recurring)
                {
                    $reason = 'extending_subscription';
                }
                else
                {
                    $reason = 'extending_upgrade';
                }
            }
            else
            {*/
            if ($upgrade->recurring)
            {
                if (!$notifyUponRecurringExtend && $isExistingUpgrade)
                {
                    return $active;
                }
                $reason = 'purchased_subscription';
            }
            else
            {
                $reason = 'purchased_upgrade';
            }


            \XF::asVisitor($this->user, function () use ($reason) {
                /** @var UserUpgrade $userUpgradeRepo */
                $userUpgradeRepo = \XF::repository('XF:UserUpgrade');
                $userUpgradeRepo->svNotifyUser($this->activeUpgrade, $this->userUpgrade, $reason);
            });
        }

        return $active;
    }
}