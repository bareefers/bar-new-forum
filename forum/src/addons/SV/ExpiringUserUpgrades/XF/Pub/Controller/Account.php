<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\ExpiringUserUpgrades\XF\Pub\Controller;

use XF\Entity\User;
use XF\Mvc\FormAction;

/**
 * Class Account
 *
 * @package SV\ExpiringUserUpgrades\
 */
class Account extends XFCP_Account
{
    protected function savePrivacyProcess(User $visitor)
    {
        $form = parent::savePrivacyProcess($visitor);

        $this->svUserUpgradeSaveProcess($visitor, $form);

        return $form;
    }

    protected function accountDetailsSaveProcess(User $visitor)
    {
        $form = parent::accountDetailsSaveProcess($visitor);

        $this->svUserUpgradeSaveProcess($visitor, $form);

        return $form;
    }

    protected function preferencesSaveProcess(User $visitor)
    {
        $form = parent::preferencesSaveProcess($visitor);

        $this->svUserUpgradeSaveProcess($visitor, $form);

        return $form;
    }

    /**
     * @param User|\SV\ExpiringUserUpgrades\XF\Entity\User $visitor
     * @param FormAction                                   $formAction
     */
    protected function svUserUpgradeSaveProcess(User $visitor, FormAction $formAction)
    {
        if ($visitor->canChangeSVExUpEmailPreferences())
        {
            $input = $this->filter([
                'option' => [
                    'sv_exup_email_on_expiring_expired_upgrade' => 'bool',
                    'sv_exup_email_on_upgrade_purchase'         => 'bool',
                    'sv_exup_email_on_upgrade_reversal'         => 'bool'
                ]
            ]);

            $userOptions = $visitor->getRelationOrDefault('Option');
            $formAction->setupEntityInput($userOptions, $input['option']);
        }
    }
}