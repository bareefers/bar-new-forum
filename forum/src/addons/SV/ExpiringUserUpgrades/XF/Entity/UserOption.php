<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\ExpiringUserUpgrades\XF\Entity;

use XF\Mvc\Entity\Structure;

/**
 * Class UserOption
 *
 * @package SV\ExpiringUserUpgrades
 *
 * COLUMNS
 * @property bool sv_exup_email_on_expiring_expired_upgrade
 * @property bool sv_exup_email_on_upgrade_purchase
 * @property bool sv_exup_email_on_upgrade_reversal
 */
class UserOption extends XFCP_UserOption
{
    protected function _setupDefaults()
    {
        parent::_setupDefaults();

        $options = \XF::options();

        $defaults = $options->registrationDefaults;
        $this->sv_exup_email_on_expiring_expired_upgrade = !empty($defaults['sv_exup_email_on_expiring_expired_upgrade']);
        $this->sv_exup_email_on_upgrade_purchase = !empty($defaults['sv_exup_email_on_upgrade_purchase']);
        $this->sv_exup_email_on_upgrade_reversal = !empty($defaults['sv_exup_email_on_upgrade_reversal']);
    }

    /**
     * @param Structure $structure
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->columns['sv_exup_email_on_expiring_expired_upgrade'] = ['type' => self::BOOL, 'default' => true];
        $structure->columns['sv_exup_email_on_upgrade_purchase'] = ['type' => self::BOOL, 'default' => true];
        $structure->columns['sv_exup_email_on_upgrade_reversal'] = ['type' => self::BOOL, 'default' => true];

        return $structure;
    }
}