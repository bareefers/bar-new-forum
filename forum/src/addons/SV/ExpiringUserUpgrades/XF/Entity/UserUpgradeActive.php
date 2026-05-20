<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\ExpiringUserUpgrades\XF\Entity;

use XF\Mvc\Entity\Structure;

/**
 * Extends \XF\Entity\UserUpgradeActive
 *
 * @property int notified_date
 */
class UserUpgradeActive extends XFCP_UserUpgradeActive
{
    /**
     * @param Structure $structure
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->columns['notified_date'] = ['type' => self::UINT, 'default' => 0];

        return $structure;
    }
}