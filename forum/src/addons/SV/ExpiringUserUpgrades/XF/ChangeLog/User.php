<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\ExpiringUserUpgrades\XF\ChangeLog;

/**
 * Extends \XF\ChangeLog\User
 */
class User extends XFCP_User
{
    protected function getLabelMap()
    {
        $map = parent::getLabelMap();
        $map['sv_exup_email_on_expiring_expired_upgrade'] = 'expiringUserUpgrades_receive_expiring_and_expired_user_upgrades_mailings_option';
        $map['sv_exup_email_on_upgrade_purchase'] = 'expiringUserUpgrades_receive_user_upgrade_purchase_mailings_option';
        $map['sv_exup_email_on_upgrade_reversal'] = 'expiringUserUpgrades_receive_user_upgrade_reversal_mailings_option';

        return $map;
    }

    protected function getFormatterMap()
    {
        $map = parent::getFormatterMap();
        $map['sv_exup_email_on_expiring_expired_upgrade'] = 'formatYesNo';
        $map['sv_exup_email_on_upgrade_purchase'] = 'formatYesNo';
        $map['sv_exup_email_on_upgrade_reversal'] = 'formatYesNo';

        return $map;
    }
}