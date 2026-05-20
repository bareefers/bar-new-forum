<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\ExpiringUserUpgrades\XF\Admin\Controller;

/**
 * Class User
 *
 * @package SV\ExpiringUserUpgrades
 */
class User extends XFCP_User
{
    /**
     * @param \XF\Entity\User $user
     * @return \XF\Mvc\FormAction
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function userSaveProcess(\XF\Entity\User $user)
    {
        $formAction = parent::userSaveProcess($user);

        $input = $this->filter([
            'option' => [
                'sv_exup_email_on_expiring_expired_upgrade' => 'bool',
                'sv_exup_email_on_upgrade_purchase'         => 'bool',
                'sv_exup_email_on_upgrade_reversal'         => 'bool'
            ]
        ]);

        $userOptions = $user->getRelationOrDefault('Option');
        $formAction->setupEntityInput($userOptions, $input['option']);

        return $formAction;
    }
}