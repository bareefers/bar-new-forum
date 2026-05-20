<?php

namespace SV\ExpiringUserUpgrades\EmailStop;

use XF\EmailStop\AbstractHandler;

/**
 * Class ReversedUpgrade
 *
 * @package SV\ExpiringUserUpgrades
 */
class ReversedUpgrade extends AbstractHandler
{
    /**
     * @param \XF\Entity\User $user
     * @param  int            $contentId
     * @return null
     */
    public function getStopOneText(\XF\Entity\User $user, $contentId)
    {
        return null;
    }

    public function getStopAllText(\XF\Entity\User $user): \XF\Phrase
    {
        return \XF::phrase('expiringUserUpgrades_stop_notifications_email_for_all_reversed_user_upgrade_payments');
    }

    /**
     * @param \XF\Entity\User $user
     * @param int             $contentId
     */
    public function stopOne(\XF\Entity\User $user, $contentId): void
    {
    }

    /**
     * @param \XF\Entity\User $user
     */
    public function stopAll(\XF\Entity\User $user): void
    {
        /** @var \SV\ExpiringUserUpgrades\XF\Entity\UserOption $option */
        $option = $user->Option;
        $option->sv_exup_email_on_upgrade_reversal = false;
        $option->saveIfChanged();
    }
}