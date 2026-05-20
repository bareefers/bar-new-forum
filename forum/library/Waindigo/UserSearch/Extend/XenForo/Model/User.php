<?php

/**
 *
 * @see XenForo_Model_User
 */
class Waindigo_UserSearch_Extend_XenForo_Model_User extends XFCP_Waindigo_UserSearch_Extend_XenForo_Model_User
{

    public function prepareUserFetchOptions(array $fetchOptions)
    {
        $userFetchOptions = parent::prepareUserFetchOptions($fetchOptions);

        $selectFields = $userFetchOptions['selectFields'];
        $joinTables = $userFetchOptions['joinTables'];

        if (isset($fetchOptions['activeUserUpgradeId'])) {
            $fetchOptions['activeUserUpgradeId'] = intval($fetchOptions['activeUserUpgradeId']);
            $selectFields .= ',
				user_upgrade_active.user_upgrade_record_id,
                user_upgrade_active.extra AS user_upgrade_extra,
                user_upgrade_active.start_date AS user_upgrade_start_date,
                user_upgrade_active.end_date AS user_upgrade_end_date';
            $joinTables .= '
				LEFT JOIN xf_user_upgrade_active AS user_upgrade_active
					ON (user_upgrade_active.user_id = user.user_id
						AND user_upgrade_active.user_upgrade_id = ' . $fetchOptions['activeUserUpgradeId'] . ')';
        }

        return array(
            'selectFields' => $selectFields,
            'joinTables' => $joinTables
        );
    } /* END prepareUserFetchOptions */

    public function canUserSearch(&$errorPhraseKey = '', array $viewingUser = null)
    {
        $this->standardizeViewingUserReference($viewingUser);

        if (!XenForo_Permission::hasPermission($viewingUser['permissions'], 'general', 'userSearch')) {
            return false;
        }

        return true;
    } /* END canUserSearch */
}