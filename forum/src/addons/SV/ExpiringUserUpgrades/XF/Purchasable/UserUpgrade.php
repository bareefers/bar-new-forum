<?php

namespace SV\ExpiringUserUpgrades\XF\Purchasable;

use SV\ExpiringUserUpgrades\Globals;
use XF\Payment\CallbackState;

/**
 * Extends \XF\Purchasable\UserUpgrade
 */
class UserUpgrade extends XFCP_UserUpgrade
{
    /**
     * @param CallbackState $state
     * @return mixed|void
     */
    public function reversePurchase(CallbackState $state)
    {
        Globals::$forceDowngradeAlert = true;
        Globals::$downgradeReason = 'payment_reversal_upgrade';
        try
        {
            parent::reversePurchase($state);
        }
        finally
        {
            Globals::$forceDowngradeAlert = false;
            Globals::$downgradeReason = null;
        }
    }
}