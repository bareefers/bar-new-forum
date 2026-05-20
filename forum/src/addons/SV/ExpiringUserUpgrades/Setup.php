<?php

namespace SV\ExpiringUserUpgrades;

use SV\StandardLib\InstallerHelper;
use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Schema\Alter;

/**
 * Add-on installation, upgrade, and uninstall routines.
 */
class Setup extends AbstractSetup
{
    use InstallerHelper;
    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;

    public function installStep1(): void
    {
        $sm = $this->schemaManager();

        foreach ($this->getTables() as $tableName => $callback)
        {
            $sm->createTable($tableName, $callback);
            $sm->alterTable($tableName, $callback);
        }
    }

    public function installStep2(): void
    {
        $sm = $this->schemaManager();

        foreach ($this->getAlterTables() as $tableName => $callback)
        {
            if ($sm->tableExists($tableName))
            {
                $sm->alterTable($tableName, $callback);
            }
        }
    }

    public function upgrade2000000Step1(): void
    {
        $this->installStep1();
    }

    public function upgrade2000000Step2(): void
    {
        $this->installStep2();
    }

    public function upgrade2000000Step3(): void
    {
        $db = $this->db();

        $upgrades = $db->fetchAllKeyed(
            '
                SELECT *
                FROM xf_user_upgrade
            ', 'user_upgrade_id'
        );

        if (!empty($upgrades))
        {
            foreach ($upgrades AS $upgrade)
            {
                $records = \XF::app()->finder('XF:UserUpgradeActive')
                                ->where('user_upgrade_id', '=', $upgrade['user_upgrade_id'])
                                ->fetch();

                // must update records one by one rather than in batches as other add-ons may have added their own info to the extra array of certain records
                foreach ($records AS $record)
                {
                    /** @var \XF\Entity\UserUpgradeActive $record */
                    $extra = $record->extra;

                    $extra['extra_group_ids'] = $upgrade['extra_group_ids'];
                    $extra['recurring'] = $upgrade['recurring'];

                    // VB import does not seem to save these extra values so must do so now
                    if (!isset($extra['cost_amount']))
                    {
                        $extra['cost_amount'] = $upgrade['cost_amount'];
                    }
                    if (!isset($extra['cost_currency']))
                    {
                        $extra['cost_currency'] = $upgrade['cost_currency'];
                    }
                    if (!isset($extra['length_amount']))
                    {
                        $extra['length_amount'] = $upgrade['length_amount'];
                    }
                    if (!isset($extra['length_unit']))
                    {
                        $extra['length_unit'] = $upgrade['length_unit'];
                    }

                    // save the current user groups so we can later know if the upgrade details have changed.
                    // we use this in order to know whether to display the 'Extend Upgrade' button.
                    $record->fastUpdate('extra', $extra);
                }
            }
        }
    }

    public function upgrade2000001Step1(): void
    {
        // migrate alerts
        $this->db()->query('update xf_user_alert set action = ? where action = ? and content_type = ?',
            ['payment_reversal_upgrade', 'payment_reversal', 'exup']);
        $this->db()->query('update xf_user_alert set action = ? where action = ? and content_type = ?',
            ['payment_reversal_extend_upgrade', 'upgrade_payment_reversal', 'exup']);
    }

    public function upgrade2000002Step1(): void
    {
        $this->db()->query(
            'update xf_user_upgrade_active 
          set notified_date = 0 
          where notified_date > 0 && end_date <= (UNIX_TIMESTAMP() + (86400 * 7))'
        );
    }

    public function upgrade2001002Step1(): void
    {
        /** @var \XF\Entity\Option $option */
        $option = \XF::finder('XF:Option')->whereId('exup_autoLeaveConversation')->fetchOne();
        if ($option)
        {
            $option->setOption('verify_validation_callback', false);
            $option->setOption('verify_value', false);

            switch($option->option_value)
            {
                case 'delete':
                    $option->option_value = 'deleted';
                    $option->save();
                    break;
                case 'delete_ignore':
                case 'delete_ignored':
                    $option->option_value = 'deleted_ignored';
                    $option->save();
                    break;
            }
        }
    }

    public function upgrade2030000Step1(): void
    {
        $this->installStep2();
    }

    public function uninstallStep1(): void
    {
        $sm = $this->schemaManager();

        foreach ($this->getTables() as $tableName => $callback)
        {
            $sm->dropTable($tableName);
        }
    }

    public function uninstallStep2(): void
    {
        $sm = $this->schemaManager();

        foreach ($this->getRemoveAlterTables() as $tableName => $callback)
        {
            if ($sm->tableExists($tableName))
            {
                $sm->alterTable($tableName, $callback);
            }
        }
    }

    public function uninstallStep3(): void
    {
        $db = $this->db();
        $db->query("DELETE FROM xf_user_alert WHERE content_type = 'exup'");
    }

    public function postInstall(array &$stateChanges): void
    {
        $this->setupRegistrationDefaults();
    }

    public function postUpgrade($previousVersion, array &$stateChanges): void
    {
        $this->setupRegistrationDefaults();
    }

    public function postRebuild(): void
    {
        $this->setupRegistrationDefaults();
    }

    public function setupRegistrationDefaults(): void
    {
        $this->applyRegistrationDefaults([
            'sv_exup_email_on_expiring_expired_upgrade' => 1,
            'sv_exup_email_on_upgrade_purchase' => 1,
            'sv_exup_email_on_upgrade_reversal' => 1
        ]);
    }

    protected function getTables(): array
    {
        return [

        ];
    }

    protected function getAlterTables(): array
    {
        return [
            'xf_user_upgrade_active' => function (Alter $table) {
                $table->addKey(['user_upgrade_id'], 'user_upgrade_id');
                $this->addOrChangeColumn($table, 'notified_date', 'int')->setDefault(0);
            },
            'xf_user_upgrade' => function (Alter $table) {
                $this->addOrChangeColumn($table, 'exup_days', 'int')->setDefault(7);
                $this->addOrChangeColumn($table, 'exup_excluded_payment_provider_ids', 'MEDIUMBLOB')->nullable()->setDefault(null);
            },
            'xf_user_option' => function (Alter $table) {
                $this->addOrChangeColumn($table, 'sv_exup_email_on_expiring_expired_upgrade', 'tinyint', 3)->setDefault(1);
                $this->addOrChangeColumn($table, 'sv_exup_email_on_upgrade_purchase', 'tinyint', 3)->setDefault(1);
                $this->addOrChangeColumn($table, 'sv_exup_email_on_upgrade_reversal', 'tinyint', 3)->setDefault(1);
            },
        ];
    }

    protected function getRemoveAlterTables(): array
    {
        return [
            'xf_user_upgrade_active' => function (Alter $table) {
                $table->dropColumns(['notified_date']);
                $table->dropIndexes(['user_upgrade_id']);
            },
            'xf_user_upgrade' => function (Alter $table) {
                $table->dropColumns(['exup_days', 'exup_excluded_payment_provider_ids']);
            },
            'xf_user_option' => function (Alter $table) {
                $table->dropColumns([
                    'sv_exup_email_on_expiring_expired_upgrade',
                    'sv_exup_email_on_upgrade_purchase',
                    'sv_exup_email_on_upgrade_reversal'
                ]);
            },
        ];
    }
}