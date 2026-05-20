<?php

namespace SV\ExpiringUserUpgrades\XF\Repository;

use SV\ExpiringUserUpgrades\Globals;
use XF\Entity\UserUpgradeActive;
use XF\Entity\UserUpgrade as UserUpgradeEntity;
use XF\Mvc\Entity\AbstractCollection;
use XF\PrintableException;

/**
 * Extends \XF\Repository\UserUpgrade
 */
class UserUpgrade extends XFCP_UserUpgrade
{
    public function getUpgradeUrl(): string
    {
        $options = \XF::options();
        $exup_upgradeUrl = $options->exup_upgradeUrl;
        $upgradeUrlType = empty($exup_upgradeUrl['type']) ? 'default' : $exup_upgradeUrl['type'];
        if ($upgradeUrlType === 'custom' && isset($exup_upgradeUrl['custom']))
        {
            return $exup_upgradeUrl['custom'];
        }

        return $this->app()->router('public')->buildLink('canonical:account/upgrades');
    }

    public function downgradeExpiredUpgrades()
    {
        Globals::$forceDowngradeAlert = true;
        Globals::$downgradeReason = 'expired_upgrade';
        try
        {
            parent::downgradeExpiredUpgrades();
        }
        finally
        {
            Globals::$forceDowngradeAlert = false;
            Globals::$downgradeReason = null;
        }
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function alertExpiringUserUpgrades(int $limit = 1000): void
    {
        /** @var \SV\ExpiringUserUpgrades\XF\Entity\UserUpgrade[]|AbstractCollection $upgradesToNotify */
        $upgradesToNotify = $this->finder('XF:UserUpgrade')
                                 ->where('exup_days', '>', 0)
                                 ->fetch();
        if ($upgradesToNotify->count() === 0)
        {
            return;
        }

        foreach($upgradesToNotify as $upgradeToNotify)
        {
            $this->app()->jobManager()->enqueueUnique(
                'svExpireUserUpgrade-' . $upgradeToNotify->user_upgrade_id,
                'SV\ExpiringUserUpgrades:ExpireUpgrades',
                [
                    'user_upgrade_id' => $upgradeToNotify->user_upgrade_id,
                    'excluded_payment_provider_ids' => $upgradeToNotify->exup_excluded_payment_provider_ids ?? [],
                    'timestamp' => \XF::$time + $upgradeToNotify->exup_days * 86400,
                ],
                false
            );
        }
    }

    public function alertExpiringUserUpgrade(UserUpgradeActive $upgradeAboutToExpire)
    {
        /** @var UserUpgradeActive|\SV\ExpiringUserUpgrades\XF\Entity\UserUpgradeActive $upgradeAboutToExpire */
        /** @var UserUpgradeEntity|\SV\ExpiringUserUpgrades\XF\Entity\UserUpgrade $upgrade */
        $upgrade = $upgradeAboutToExpire->Upgrade;
        if ($upgrade === null || $upgrade->exup_days <= 0)
        {
            // wat
            return;
        }

        try
        {
            $upgradeAboutToExpire->notified_date = \XF::$time;
            $upgradeAboutToExpire->save();
        }
        catch (\Exception $e)
        {
            \XF::logException($e);
        }

        $notifyAction = $upgrade->recurring
            ? 'expiring_subscription'
            : 'expiring_upgrade';
        try
        {
            $this->svNotifyUser($upgradeAboutToExpire, $upgrade, $notifyAction);
        }
        catch (PrintableException $e)
        {
            \XF::logException($e);
        }
    }

    /**
     * Alerts and/or emails and/or sends conversation user upon expiring/expired/reversed upgrades
     * Should be wrapped in a \XF::asVisitor() call
     *
     * @param UserUpgradeActive $record
     * @param UserUpgradeEntity $userUpgrade
     * @param string            $action
     * @throws PrintableException
     */
    public function svNotifyUser(UserUpgradeActive $record, UserUpgradeEntity $userUpgrade, string $action)
    {
        $user = $record->User;
        /** @var \SV\ExpiringUserUpgrades\XF\Entity\UserOption $userOption */
        $userOption = $user->Option;
        $upgradeAvailable = false;
        $exitingUpgradeId = $userUpgrade->user_upgrade_id;
        [$available, $purchased] = $this->getFilteredUserUpgradesForList();
        switch ($action)
        {
            case 'purchased_upgrade':
            case 'purchased_subscription':
                $upgradeAvailable = true;
                $sendMail = $userOption->sv_exup_email_on_upgrade_purchase;
                break;

            case 'reversal_extend_subscription':
            case 'reversal_extend_upgrade':
            case 'payment_reversal_upgrade':
            case 'payment_reversal_subscription':
                $sendMail = $userOption->sv_exup_email_on_upgrade_reversal;
                break;

            case 'expiring_upgrade':
            case 'expiring_subscription':
                foreach ($purchased AS $upgradeId => $upgrade)
                {
                    /** @var UserUpgradeEntity $upgrade */
                    // this upgrade has somehow been purchased after it has been expired, don't send an expired notice
                    if ($upgradeId === $exitingUpgradeId && $upgrade->can_purchase)
                    {
                        $upgradeAvailable = true;
                    }
                }
                $sendMail = $userOption->sv_exup_email_on_expiring_expired_upgrade;
                break;

            case 'expired_upgrade':
            case 'expired_subscription':
                // determine if this upgrade has been prevented from being applyable due to another purchase
                $exitingUpgradeId = $record->user_upgrade_id;
                foreach ($purchased AS $upgradeId => $upgrade)
                {
                    /** @var UserUpgradeEntity $upgrade */
                    // this upgrade has somehow been purchased after it has been expired, don't send an expired notice
                    if ($upgradeId === $exitingUpgradeId)
                    {
                        return;
                    }

                    foreach ($upgrade->disabled_upgrade_ids AS $disabledId)
                    {
                        if ($disabledId == $exitingUpgradeId)
                        {
                            return;
                        }
                    }
                }
                $sendMail = $userOption->sv_exup_email_on_expiring_expired_upgrade;
                break;

            default:
                return;
        }

        // determine if this upgrade is still available
        if (!$upgradeAvailable)
        {
            foreach ($available as $upgradeId => $upgrade)
            {
                if ($upgradeId == $exitingUpgradeId)
                {
                    $upgradeAvailable = true;
                    break;
                }
            }
        }

        $options = \XF::options();
        $bccActions = explode("\n", \str_replace(",","\n", \strtolower($options->ExUp_BccEmail_Action)));
        $bccActions = array_filter(array_map('\trim', $bccActions));
        $doBcc = in_array(\strtolower($action), $bccActions, true);
        $notifyBy = $options->exup_notify_by;
        $upgradeUrl = $this->getUpgradeUrl();
        /** @var \SV\ExpiringUserUpgrades\XF\Entity\UserUpgrade $upgradeFromRecord */
        $upgradeFromRecord = $record->Upgrade;

        /** @var \NF\GiftUpgrades\XF\Entity\UserUpgradeActive $record */
        $params = [
            'id'            => $record->user_upgrade_record_id,
            'isGift'        => $record->isValidKey('is_gift') && $record->is_gift,
            'username'      => $record->User->username,
            'upgrade_title' => $userUpgrade->title,
            'url'           => $upgradeUrl,
            'upgrade_url'   => $upgradeUrl,
            'upgradeUrl'    => $upgradeUrl,
            'upgradeDenied' => !$upgradeAvailable,
            'boardUrl'      => $options->boardUrl,
            'boardTitle'    => $options->boardTitle,
        ];

        if ($action === 'expiring_upgrade' || $action === 'expiring_subscription')
        {
            if (empty($upgradeFromRecord->exup_days))
            {
                return;
            }

            $numDays = $upgradeFromRecord->exup_days;
            $cutOff = \XF::$time + (86400 * $numDays);
            $numDays = ceil($numDays - (($cutOff - $record->end_date) / 86400));
            if ($numDays < 0)
            {
                $numDays = 0;
            }
            $params['num_days'] = $numDays;
        }

        if (!empty($notifyBy['alert']['active']))
        {
            $alertParams = $params;
            // do not store the following in the alerts
            unset($alertParams['url']);
            unset($alertParams['upgrade_url']);
            unset($alertParams['upgradeUrl']);
            unset($alertParams['boardUrl']);
            unset($alertParams['boardTitle']);

            $alertSenderId = $alertSenderName = null;
            $senderUsername = $notifyBy['alert']['sender_username'] ?? '';
            if (strlen($senderUsername) !== 0)
            {
                /** @var \XF\Entity\User|null $notifyByUser */
                $notifyByUser = $this->finder('XF:User')
                                     ->where('username', '=', $senderUsername)
                                     ->fetchOne();
                if ($notifyByUser !== null)
                {
                    $alertSenderName = $notifyByUser->username;
                    $alertSenderId = $notifyByUser->user_id;
                }
            }

            /** @var \XF\Repository\UserAlert $alertRepo */
            $alertRepo = $this->repository('XF:UserAlert');
            $alertRepo->alert(
                $user,
                $alertSenderId ?? 0,
                $alertSenderName ?? '',
                'exup',
                $user->user_id,
                $action,
                $alertParams
            );
        }

        // has email, is valid, and not banned
        if (!empty($notifyBy['email']['active']) &&
            $user->email && $user->user_state === 'valid' && !$user->is_banned &&
            (!$options->exup_respect_email_privacy || $user->Option->receive_admin_email))
        {
            if ($sendMail)
            {
                $templateName = 'exup_email_' . $action . '_body';
                $mail = $this->app()->mailer()->newMail()
                             ->setToUser($user)
                             ->setTemplate($templateName, $params);
                if ($doBcc)
                {
                    $bccEmail = \trim(strval($options->ExUp_BccEmail));
                    if ($bccEmail)
                    {
                        $mail->getEmailObject()->addBcc($bccEmail);
                    }
                }
                $mail->queue();
            }
        }

        if (!empty($notifyBy['conversation']['active']) && !empty($notifyBy['conversation']['sender_username']))
        {
            /** @var \XF\Finder\User $userFinder */
            $userFinder = $this->finder('XF:User');
            /** @var \XF\Entity\User $notifyByUser */
            $notifyByUser = $userFinder
                ->where('username', '=', $notifyBy['conversation']['sender_username'])
                ->with([
                    'Profile',
                    'Privacy',
                    'PermissionCombination'
                ])->fetchOne();

            if (!$notifyByUser)
            {
                return;
            }

            if ($params['isGift'])
            {
                $params['upgrade_title'] = \XF::phrase('exup_gift_upgrade', ['upgrade_title' => $params['upgrade_title']]);
            }

            $title = (string)\XF::phrase('exup_conversation_' . $action . '_title', $params);
            $body = \XF::phrase('exup_conversation_' . $action . '_message', $params)->render('raw');
            $tokens = [
                '{name}' => $user->username,
                '{id}'   => $user->user_id
            ];
            $body = strtr($body, $tokens);

            \XF::asVisitor($notifyByUser, function () use ($notifyByUser, $user, $title, $body, $options) {
                /** @var \XF\Service\Conversation\Creator $conversationCreator */
                $conversationCreator = $this->app()->service('XF:Conversation\Creator', $notifyByUser);
                $conversationCreator->setIsAutomated();
                $conversationCreator->setRecipientsTrusted([$user]);
                $conversationCreator->setContent($title, $body);
                $conversationCreator->setAutoSendNotifications(false);
                if ($conversationCreator->validate($errors))
                {
                    /** @var \XF\Entity\ConversationMaster $conversation */
                    $conversation = $conversationCreator->save();
                    $recipientState = $options->exup_autoLeaveConversation;
                    $convRecipients = $conversation
                        ->getRelationFinder('Recipients')
                        ->with('ConversationUser')
                        ->fetch();

                    /** @var \XF\Entity\ConversationRecipient $convRecipient */
                    foreach ($convRecipients AS $convRecipient)
                    {
                        if ($convRecipient->user_id !== $notifyByUser->user_id)
                        {
                            continue;
                        }

                        switch ($recipientState)
                        {
                            case '':
                            case 'no_delete':
                                break;
                            case 'deleted':
                            case 'deleted_ignored':
                                $convRecipient->recipient_state = $recipientState;
                                $convRecipient->save();
                                break;
                        }
                    }

                    \XF::runLater(function() use ($conversationCreator) {
                        $conversationCreator->sendNotifications();
                    });
                }
            });
        }
    }
}