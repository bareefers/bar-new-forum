<?php

namespace SV\ExpiringUserUpgrades\Option;

use XF\Option\AbstractOption;

/**
 * Class NotifyBy
 *
 * @package SV\ExpiringUserUpgrades
 */
class NotifyBy extends AbstractOption
{
    public static function verifyOption(array &$values, \XF\Entity\Option $option): bool
    {
        if (!empty($values['conversation']['active']))
        {
            if (!isset($values['conversation']['sender_username']))
            {
                $values['conversation']['sender_username'] = '';
            }

            $values['conversation']['sender_username'] = trim($values['conversation']['sender_username']);

            /** @var \XF\Finder\User $userFinder */
            $userFinder = \XF::app()->finder('XF:User');
            /** @var \XF\Entity\User $sender */
            $sender = $userFinder->where('username', '=', $values['conversation']['sender_username'])
                                 ->where('user_state', '=', 'valid')
                                 ->fetchOne();

            if (!$sender)
            {
                $option->error(\XF::phrase('exup_conversation_sender_must_be_valid_user'), $option->option_id);

                return false;
            }
        }

        return true;
    }
}