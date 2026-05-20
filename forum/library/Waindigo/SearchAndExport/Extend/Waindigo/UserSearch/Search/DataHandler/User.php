<?php

/**
 *
 * @see Waindigo_UserSearch_Search_DataHandler_User
 */
class Waindigo_SearchAndExport_Extend_Waindigo_UserSearch_Search_DataHandler_User extends XFCP_Waindigo_SearchAndExport_Extend_Waindigo_UserSearch_Search_DataHandler_User
{

    protected static $_exportSearchConstraints = null;

    public function setExportSearchContstraints(array $constraints)
    {
        self::$_exportSearchConstraints = $constraints;
    } /* END setExportSearchContstraints */

    /**
     *
     * @see Waindigo_UserSearch_Search_DataHandler_User::getDataForResults()
     */
    public function getDataForResults(array $ids, array $viewingUser, array $resultsGrouped)
    {
        $users = parent::getDataForResults($ids, $viewingUser, $resultsGrouped);

        if (self::$_exportSearchConstraints) {
            $constraints = self::$_exportSearchConstraints;
            if (!empty($constraints['active_user_upgrade_id'])) {
                $activeUserUpgradeId = $constraints['active_user_upgrade_id'];
                foreach ($users as &$user) {
                    $user = array_merge($user,
                        array(
                            'upgrade_title' => '',
                            'upgrade_start_date' => '',
                            'upgrade_end_date' => ''
                        ));
                }
                unset($user);

                /* @var $userUpgradeModel XenForo_Model_UserUpgrade */
                $userUpgradeModel = XenForo_Model::create('XenForo_Model_UserUpgrade');

                $userUpgrade = $userUpgradeModel->getUserUpgradeById($activeUserUpgradeId);

                if ($userUpgrade) {
                    $conditions = array(
                        'user_upgrade_id' => $activeUserUpgradeId,
                        'active' => true
                    );

                    $upgradeRecords = $userUpgradeModel->getUserUpgradeRecords($conditions);

                    foreach ($upgradeRecords as $upgradeRecord) {
                        $users[$upgradeRecord['user_id']] = array_merge($users[$upgradeRecord['user_id']],
                            array(
                                'upgrade_title' => $userUpgrade['title'],
                                'upgrade_start_date' => $upgradeRecord['start_date'],
                                'upgrade_end_date' => $upgradeRecord['end_date']
                            ));
                    }
                }
            }
        }



        return $users;
    } /* END getDataForResults */
}