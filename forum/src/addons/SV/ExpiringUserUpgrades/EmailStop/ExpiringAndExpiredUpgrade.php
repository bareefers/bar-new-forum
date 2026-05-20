<?php

namespace SV\ExpiringUserUpgrades\EmailStop;

use XF\EmailStop\AbstractHandler;

/**
 * Class ExpiringAndExpiredUpgrade
 *
 * @package SV\ExpiringUserUpgrades
 */
class ExpiringAndExpiredUpgrade extends AbstractHandler
{
    /**
     * @param \XF\Entity\User $user
     * @param int             $contentId
     * @return null
     */
    public function getStopOneText(\XF\Entity\User $user, $contentId): ?string
    {
        return null;
    }

    public function getStopAllText(\XF\Entity\User $user): \XF\Phrase
    {
        return \XF::phrase('expiringUserUpgrades_stop_notifications_email_for_all_expiring_and_expired_upgrades');
    }

    public function stopOne(\XF\Entity\User $user, $contentId): void
    {
    }

    public function stopAll(\XF\Entity\User $user): void
    {
        /** @var \SV\ExpiringUserUpgrades\XF\Entity\UserOption $option */
        $option = $user->Option;
        $option->sv_exup_email_on_expiring_expired_upgrade = false;
        $option->saveIfChanged();
    }
}