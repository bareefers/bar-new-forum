<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\ExpiringUserUpgrades\XF\Entity;

use XF\Mvc\Entity\Structure;
use function array_unique;
use function sort;

/**
 * Extends \XF\Entity\UserUpgrade
 *
 * @property int $exup_days
 * @property array<int>|null $exup_excluded_payment_provider_ids
 */
class UserUpgrade extends XFCP_UserUpgrade
{
    /**
     * @return bool
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function canPurchase()
    {
        $visitor = \XF::visitor();

        if ($this->Active[$visitor->user_id])
        {
            return $this->canExtend();
        }

        return parent::canPurchase();
    }

    public function getNonExtendReason(): ?\XF\Phrase
    {
        if (!$this->canExtend($error))
        {
            return $error ?: \XF::phrase('error');
        }

        return null;
    }

    /**
     * @param \XF\Phrase|string|null $error
     * @return bool
     */
    public function canExtend(&$error = null): bool
    {
        if (empty(\XF::options()->exup_extend_upgrade_button))
        {
            return false;
        }

        $visitor = \XF::visitor();
        $activeUpgrade = $this->Active[$visitor->user_id];

        if (!$this->can_purchase)
        {
            $error = \XF::phrase("exup_upgrade_can_not_be_purchased");

            return false;
        }


        if ($this->recurring)
        {
            $error = \XF::phrase("exup_upgrade_is_recurring");

            return false;
        }

        $extra = $activeUpgrade->extra;

        if (isset($extra['recurring']) && $this->recurring !== (bool)$extra['recurring'])
        {
            $error = \XF::phrase("exup_upgrade_recurring_status_has_changed");

            return false;
        }

        if (isset($extra['extra_group_ids']))
        {
            if (!is_array($extra['extra_group_ids']))
            {
                $extra['extra_group_ids'] = array_unique(array_map('\intval', explode(',', $extra['extra_group_ids'])));
                sort($extra['extra_group_ids']);
            }

            $extraGroupsIds = array_unique(array_map('\intval', $this->extra_group_ids));
            sort($extraGroupsIds);

            if ($extraGroupsIds !== $extra['extra_group_ids'])
            {
                $error = \XF::phrase("exup_upgrade_usergroups_added_has_changed");

                return false;
            }
        }

        if ($this->length_unit === '')
        {
            $error = \XF::phrase("exup_upgrade_has_no_length_units");

            return false;
        }

        if ($this->length_amount === 0)
        {
            $error = \XF::phrase("exup_upgrade_has_no_length_amount");

            return false;
        }

        if (empty($activeUpgrade->end_date))
        {
            $error = \XF::phrase("exup_upgrade_has_no_end_date");

            return false;
        }

        return true;
    }

    protected function _preSave()
    {
        parent::_preSave();

        if (empty($this->exup_excluded_payment_provider_ids))
        {
            $this->exup_excluded_payment_provider_ids = null;
        }
    }

    /**
     * @param Structure $structure
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->columns['exup_days'] = ['type' => self::UINT, 'default' => 7, 'max' => 30];
        $structure->columns['exup_excluded_payment_provider_ids'] = ['type' => self::JSON_ARRAY, 'default' => null, 'nullable' => true];

        return $structure;
    }
}