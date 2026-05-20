<?php

/**
 * Handles searching of user.
 *
 * @see XenForo_Search_DataHandler_Abstract
 *
 * @package XenForo_Search
 */
class Waindigo_UserSearch_Search_DataHandler_User extends XenForo_Search_DataHandler_Abstract
{

    /**
     *
     * @var XenForo_Model_User
     */
    protected $_userModel = null;

    /**
     * Inserts into (or replaces a record) in the index.
     *
     * @see XenForo_Search_DataHandler_Abstract::_insertIntoIndex()
     */
    protected function _insertIntoIndex(XenForo_Search_Indexer $indexer, array $data, array $parentData = null)
    {
        $metadata = array();

        $userGroupIds = array();
        $userGroupIds[$data['user_group_id']] = true;
        foreach (explode(',', $data['secondary_group_ids']) as $secondaryGroupId) {
            $userGroupIds[$secondaryGroupId] = true;
        }

        // $metadata['user_group_ids'] = implode(',',
        // array_keys($userGroupIds));
        foreach ($userGroupIds as $userGroupId => $bool) {
            $metadata['user_group_' . $userGroupId] = '';
        }

        if (!empty($data['gender'])) {
            $metadata['gender'] = $data['gender'];
        } else {
            $metadata['gender'] = 'unspecified';
        }

        $metadata['location'] = $data['location'];

        $indexer->insertIntoIndex('user', $data['user_id'], $data['username'], $data['about'], $data['register_date'],
            $data['user_id'], 0, $metadata);
    } /* END _insertIntoIndex */

    /**
     * Updates a record in the index.
     *
     * @see XenForo_Search_DataHandler_Abstract::_updateIndex()
     */
    protected function _updateIndex(XenForo_Search_Indexer $indexer, array $data, array $fieldUpdates)
    {
        $indexer->updateIndex('user', $data['user_id'], $fieldUpdates);
    } /* END _updateIndex */

    /**
     * Deletes one or more records from the index.
     *
     * @see XenForo_Search_DataHandler_Abstract::_deleteFromIndex()
     */
    protected function _deleteFromIndex(XenForo_Search_Indexer $indexer, array $dataList)
    {
        $userIds = array();
        foreach ($dataList as $data) {
            $userIds[] = $data['user_id'];
        }

        $indexer->deleteFromIndex('user', $userIds);
    } /* END _deleteFromIndex */

    /**
     * Rebuilds the index for a batch.
     *
     * @see XenForo_Search_DataHandler_Abstract::rebuildIndex()
     */
    public function rebuildIndex(XenForo_Search_Indexer $indexer, $lastId, $batchSize)
    {
        $userModel = $this->_getUserModel();

        $userIds = $userModel->getUserIdsInRange($lastId, $batchSize);
        if (!$userIds) {
            return false;
        }

        $this->quickIndex($indexer, $userIds);

        return max($userIds);
    } /* END rebuildIndex */

    /**
     * Rebuilds the index for the specified content.
     *
     * @see XenForo_Search_DataHandler_Abstract::quickIndex()
     */
    public function quickIndex(XenForo_Search_Indexer $indexer, array $contentIds)
    {
        $users = $this->_getUserModel()->getUsersByIds($contentIds);

        $userIds = array();
        foreach ($users as $user) {
            $userIds[] = $user['user_id'];
        }

        $users = $this->_getUserModel()->getUsersByIds(array_unique($userIds),
            array(
                'join' => XenForo_Model_User::FETCH_USER_PROFILE
            ));

        foreach ($users as $user) {
            $user = (isset($users[$user['user_id']]) ? $users[$user['user_id']] : null);
            if (!$user || $user['user_state'] != 'valid') {
                continue;
            }

            $this->insertIntoIndex($indexer, $user);
        }

        return true;
    } /* END quickIndex */

    /**
     * Gets the type-specific data for a collection of results of this content
     * type.
     *
     * @see XenForo_Search_DataHandler_Abstract::getDataForResults()
     */
    public function getDataForResults(array $ids, array $viewingUser, array $resultsGrouped)
    {
        $userModel = $this->_getUserModel();

        $fetchOptions = array(
            'join' => XenForo_Model_User::FETCH_USER_FULL
        );

        $users = $userModel->getUsersByIds($ids, $fetchOptions);

        $removedUserGroupIds = XenForo_Application::get('options')->waindigo_userSearch_userGroupsRemovedFromSearch;

        /* @var $userModel XenForo_Model_User */
        $userModel = XenForo_Model::create('XenForo_Model_User');
        foreach ($users as $userId => $user) {
            if ($userModel->isMemberOfUserGroup($user, $removedUserGroupIds)) {
                unset($users[$userId]);
            }
        }

        return $users;
    } /* END getDataForResults */

    /**
     * Determines if this result is viewable.
     *
     * @see XenForo_Search_DataHandler_Abstract::canViewResult()
     */
    public function canViewResult(array $result, array $viewingUser)
    {
        if (!$this->_getUserModel()->canUserSearch()) {
            return false;
        }

        return true;
    } /* END canViewResult */

    /**
     * Prepares a result for display.
     *
     * @see XenForo_Search_DataHandler_Abstract::prepareResult()
     */
    public function prepareResult(array $result, array $viewingUser)
    {
        $result = $this->_getUserModel()->prepareUser($result);
        return $result;
    } /* END prepareResult */

    /**
     * Gets the date of the result (from the result's content).
     *
     * @see XenForo_Search_DataHandler_Abstract::getResultDate()
     */
    public function getResultDate(array $result)
    {
        return $result['register_date'];
    } /* END getResultDate */

    /**
     * Renders a result to HTML.
     *
     * @see XenForo_Search_DataHandler_Abstract::renderResult()
     */
    public function renderResult(XenForo_View $view, array $result, array $search)
    {
        return $view->createTemplateObject('search_result_user',
            array(
                'user' => $result,
                'search' => $search
            ));
    } /* END renderResult */

    /**
     * Gets the content types searched in a type-specific search.
     *
     * @see XenForo_Search_DataHandler_Abstract::getSearchContentTypes()
     */
    public function getSearchContentTypes()
    {
        return array(
            'user'
        );
    } /* END getSearchContentTypes */

    /**
     * Get type-specific constraints from input.
     *
     * @param XenForo_Input $input
     *
     * @return array
     */
    public function getTypeConstraintsFromInput(XenForo_Input $input)
    {
        $constraints = array();

        $xenOptions = XenForo_Application::get('options');

        $userId = $input->filterSingle('user_id', XenForo_Input::UINT);
        if ($userId) {
            $constraints['user'] = $userId;

            // undo things that don't make sense with this
            $constraints['titles_only'] = false;
        }

        if ($xenOptions->waindigo_userSearch_genderSearch) {
            $genders = $input->filterSingle('genders', XenForo_Input::STRING,
                array(
                    'array' => true
                ));
            if ($genders && reset($genders)) {
                $genders = array_unique($genders);
                $constraints['gender'] = implode(' ', $genders);
                if (!$constraints['gender']) {
                    unset($constraints['gender']);
                }
            }
        }

        if ($xenOptions->waindigo_userSearch_userGroupSearch) {
            $userGroups = $input->filterSingle('user_groups', XenForo_Input::UINT,
                array(
                    'array' => true
                ));
            if ($userGroups && reset($userGroups)) {
                $userGroups = array_unique($userGroups);
                $constraints['user_group'] = implode(' ', $userGroups);
                if (!$constraints['user_group']) {
                    unset($constraints['user_group']);
                }
            }
        }

        if ($xenOptions->waindigo_userSearch_emailSearch) {
            $email = $input->filterSingle('email', XenForo_Input::STRING);
            if ($email) {
                $constraints['email'] = $email;
            }
        }

        $location = $input->filterSingle('location', XenForo_Input::STRING);
        if ($location) {
            $constraints['location'] = $location;
        }

        if ($xenOptions->waindigo_userSearch_userUpgradeSearch) {
            $activeUserUpgradeId = $input->filterSingle('active_user_upgrade_id', XenForo_Input::STRING);
            if ($activeUserUpgradeId) {
                $constraints['active_user_upgrade_id'] = $activeUserUpgradeId;
            }
        }

        return $constraints;
    } /* END getTypeConstraintsFromInput */

    /**
     * Process a type-specific constraint.
     *
     * @see XenForo_Search_DataHandler_Abstract::processConstraint()
     */
    public function processConstraint(XenForo_Search_SourceHandler_Abstract $sourceHandler, $constraint, $constraintInfo,
        array $constraints)
    {
        $xenOptions = XenForo_Application::get('options');

        switch ($constraint) {
            case 'user':
                $userId = intval($constraintInfo);
                if ($userId > 0) {
                    return array(
                        'metadata' => array(
                            'user',
                            $userId
                        )
                    );
                }
            case 'gender':
                if ($constraintInfo) {
                    return array(
                        'metadata' => array(
                            'gender',
                            preg_split('/\s+/', strval($constraintInfo))
                        )
                    );
                }
            case 'user_group':
                if ($constraintInfo) {
                    $constraintInfo = preg_split('/\D+/', strval($constraintInfo));
                    foreach ($constraintInfo as $key => &$constraintInfoValue) {
                        if (in_array($constraintInfoValue,
                            $xenOptions->waindigo_userSearch_userGroupsRestrictedFromSearch) || in_array(
                            $constraintInfoValue, $xenOptions->waindigo_userSearch_userGroupsRemovedFromSearch)) {
                            unset($constraintInfo[$key]);
                            continue;
                        }
                        $constraintInfoValue .= '_';
                    }
                    if (empty($constraintInfo)) {
                        return false;
                    }
                    return array(
                        'metadata' => array(
                            'user_group',
                            $constraintInfo
                        )
                    );
                }
            case 'email':
                if ($constraintInfo) {
                    return array(
                        'query' => array(
                            'user',
                            'email',
                            'LIKE',
                            '%' . $constraintInfo . '%'
                        )
                    );
                }
            case 'location':
                if ($constraintInfo) {
                    return array(
                        'metadata' => array(
                            'location',
                            $constraintInfo
                        )
                    );
                }
            case 'active_user_upgrade_id':
                if ($constraintInfo) {
                    $constraintInfo = strval($constraintInfo);
                    if ($xenOptions->waindigo_userSearch_userUpgsRestrictedFromSearch &&
                         in_array($constraintInfo, $xenOptions->waindigo_userSearch_userUpgsRestrictedFromSearch)) {
                        return false;
                    }
                    return array(
                        'query' => array(
                            'user_upgrade_active',
                            'user_upgrade_id',
                            '=',
                            $constraintInfo
                        )
                    );
                }
        }

        return false;
    } /* END processConstraint */

    /**
     * Gets the search form controller response for this type.
     *
     * @see XenForo_Search_DataHandler_Abstract::getSearchFormControllerResponse()
     */
    public function getSearchFormControllerResponse(XenForo_ControllerPublic_Abstract $controller, XenForo_Input $input,
        array $viewParams)
    {
        $userModel = $this->_getUserModel();

        if (!$userModel->canUserSearch()) {
            return $controller->responseNoPermission();
        }

        $controller->getRouteMatch()->setSections('members');

        $params = $input->filterSingle('c', XenForo_Input::ARRAY_SIMPLE);

        $viewParams['search']['user'] = array();
        if (!empty($params['user'])) {
            $user = $userModel->getUserById($params['user'],
                array(
                    'join' => XenForo_Model_User::FETCH_USER_FULL,
                    'permissionCombinationId' => XenForo_Visitor::getPermissionCombinationId()
                ));

            if ($user) {
                $viewParams['search']['user'] = $this->_getUserModel()->getUserById($params['user']);
            }
        }

        if (!empty($params['gender'])) {
            $viewParams['search']['gender'] = array_fill_keys(explode(' ', $params['gender']), true);
        } else {
            $viewParams['search']['gender'] = array();
        }

        if (!empty($params['email'])) {
            $viewParams['search']['email'] = $params['email'];
        }

        if (!empty($params['location'])) {
            $viewParams['search']['location'] = $params['location'];
        }

        if (!empty($params['active_user_upgrade_id'])) {
            $viewParams['search']['active_user_upgrade_id'] = $params['active_user_upgrade_id'];
        }

        if (!empty($params['user_group'])) {
            $viewParams['search']['user_group'] = array_fill_keys(explode(' ', $params['user_group']), true);
        } else {
            $viewParams['search']['user_group'] = array();
        }

        /* @var $userGroupModel XenForo_Model_UserGroup */
        $userGroupModel = XenForo_Model::create('XenForo_Model_UserGroup');

        $viewParams['userGroups'] = $userGroupModel->getAllUserGroupTitles();

        /* @var $userUpgradeModel XenForo_Model_UserUpgrade */
        $userUpgradeModel = XenForo_Model::create('XenForo_Model_UserUpgrade');

        $viewParams['userUpgrades'] = $userUpgradeModel->getAllUserUpgrades();

        return $controller->responseView('Waindigo_UserSearch_ViewPublic_Search_Form_User', 'search_form_user',
            $viewParams);
    } /* END getSearchFormControllerResponse */

    /**
     * Gets the search order for a type-specific search.
     *
     * @see XenForo_Search_DataHandler_Abstract::getOrderClause()
     */
    public function getOrderClause($order)
    {
        return false;
    } /* END getOrderClause */

    /**
     * Gets the necessary join structure information for this type.
     *
     * @see XenForo_Search_DataHandler_Abstract::getJoinStructures()
     */
    public function getJoinStructures(array $tables)
    {
        $structures = array();
        if (isset($tables['user_upgrade_active'])) {
            $structures['user_upgrade_active'] = array(
                'table' => 'xf_user_upgrade_active',
                'key' => 'user_id',
                'relationship' => array(
                    'search_index',
                    'content_id'
                )
            );
        }

        if (isset($tables['user'])) {
            $structures['user'] = array(
                'table' => 'xf_user',
                'key' => 'user_id',
                'relationship' => array(
                    'search_index',
                    'content_id'
                )
            );
        }

        return $structures;
    } /* END getJoinStructures */

    /**
     * Gets the content type that will be used when grouping for this type.
     *
     * @see XenForo_Search_DataHandler_Abstract::getGroupByType()
     */
    public function getGroupByType()
    {
        return 'user';
    } /* END getGroupByType */

    /**
     *
     * @return XenForo_Model_User
     */
    protected function _getUserModel()
    {
        if (!$this->_userModel) {
            $this->_userModel = XenForo_Model::create('XenForo_Model_User');
        }

        return $this->_userModel;
    } /* END _getUserModel */
}