<?php

/**
 * Model for search export profiles.
 */
class Waindigo_SearchAndExport_Model_SearchExportProfile extends XenForo_Model
{

    /**
     * Gets search export profiles that match the specified criteria.
     *
     * @param array $conditions List of conditions.
     * @param array $fetchOptions
     *
     * @return array [search export profile id] => info.
     */
    public function getSearchExportProfiles(array $conditions = array(), array $fetchOptions = array())
    {
        $whereClause = $this->prepareSearchExportProfileConditions($conditions, $fetchOptions);

        $sqlClauses = $this->prepareSearchExportProfileFetchOptions($fetchOptions);
        $limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

        return $this->fetchAllKeyed($this->limitQueryResults('
                SELECT search_export_profile.*
                    ' . $sqlClauses['selectFields'] . '
                FROM xf_search_export_profile_waindigo AS search_export_profile
                ' . $sqlClauses['joinTables'] . '
                WHERE ' . $whereClause . '
                ' . $sqlClauses['orderClause'] . '
            ', $limitOptions['limit'], $limitOptions['offset']
        ), 'search_export_profile_id');
    } /* END getSearchExportProfiles */

    /**
     * Gets the search export profile that matches the specified criteria.
     *
     * @param array $conditions List of conditions.
     * @param array $fetchOptions Options that affect what is fetched.
     *
     * @return array|false
     */
    public function getSearchExportProfile(array $conditions = array(), array $fetchOptions = array())
    {
        $searchExportProfiles = $this->getSearchExportProfiles($conditions, $fetchOptions);

        return reset($searchExportProfiles);
    } /* END getSearchExportProfile */

    /**
     * Gets a search export profile by ID.
     *
     * @param integer $searchExportProfileId
     * @param array $fetchOptions Options that affect what is fetched.
     *
     * @return array|false
     */
    public function getSearchExportProfileById($searchExportProfileId, array $fetchOptions = array())
    {
        $conditions = array('search_export_profile_id' => $searchExportProfileId);

        return $this->getSearchExportProfile($conditions, $fetchOptions);
    } /* END getSearchExportProfileById */

    /**
     * Gets the total number of a search export profile that match the specified criteria.
     *
     * @param array $conditions List of conditions.
     *
     * @return integer
     */
    public function countSearchExportProfiles(array $conditions = array())
    {
        $fetchOptions = array();

        $whereClause = $this->prepareSearchExportProfileConditions($conditions, $fetchOptions);
        $joinOptions = $this->prepareSearchExportProfileFetchOptions($fetchOptions);

        $limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

        return $this->_getDb()->fetchOne('
            SELECT COUNT(*)
            FROM xf_search_export_profile_waindigo AS search_export_profile
            ' . $joinOptions['joinTables'] . '
            WHERE ' . $whereClause . '
        ');
    } /* END countSearchExportProfiles */

    /**
     * Gets all search export profiles titles.
     *
     * @return array [search export profile id] => title.
     */
    public static function getSearchExportProfileTitles()
    {
        $searchExportProfiles = XenForo_Model::create(__CLASS__)->getSearchExportProfiles();
        $titles = array();
        foreach ($searchExportProfiles as $searchExportProfileId => $searchExportProfile) {
            $titles[$searchExportProfileId] = $searchExportProfile['title'];
        }
        return $titles;
    } /* END getSearchExportProfileTitles */

    /**
     * Gets the default search export profile record.
     *
     * @return array
     */
    public function getDefaultSearchExportProfile()
    {
        return array(
            'search_export_profile_id' => '', /* END 'search_export_profile_id' */
            'columns' => '', /* END 'columns' */
        );
    } /* END getDefaultSearchExportProfile */

    public function prepareSearchExportProfile(array $searchExportProfile)
    {
        if ($searchExportProfile['columns']) {
            $searchExportProfile['columns'] = unserialize($searchExportProfile['columns']);
            $searchExportProfile['columns'] = array_values($searchExportProfile['columns']);
        } else {
            $searchExportProfile['columns'] = array();
        }

        return $searchExportProfile;
    }

    public function prepareSearchExportProfiles(array $searchExportProfiles)
    {
        foreach ($searchExportProfiles as &$searchExportProfile) {
            $searchExportProfile = $this->prepareSearchExportProfile($searchExportProfile);
        }

        return $searchExportProfiles;
    } /* END prepareSearchExportProfiles */ /* END prepareSearchExportProfiles */ /* END prepareSearchExportProfiles */

    /**
     * Prepares a set of conditions to select search export profiles against.
     *
     * @param array $conditions List of conditions.
     * @param array $fetchOptions The fetch options that have been provided. May be edited if criteria requires.
     *
     * @return string Criteria as SQL for where clause
     */
    public function prepareSearchExportProfileConditions(array $conditions, array &$fetchOptions)
    {
        $db = $this->_getDb();
        $sqlConditions = array();

        if (isset($conditions['search_export_profile_ids']) && !empty($conditions['search_export_profile_ids'])) {
            $sqlConditions[] = 'search_export_profile.search_export_profile_id IN (' . $db->quote($conditions['search_export_profile_ids']) . ')';
        } else if (isset($conditions['search_export_profile_id'])) {
            $sqlConditions[] = 'search_export_profile.search_export_profile_id = ' . $db->quote($conditions['search_export_profile_id']);
        }

        if (isset($conditions['content_types']) && !empty($conditions['content_types'])) {
            $sqlConditions[] = 'search_export_profile.content_type IN (' . $db->quote($conditions['content_types']) . ')';
        } else if (isset($conditions['content_type'])) {
            $sqlConditions[] = 'search_export_profile.content_type = ' . $db->quote($conditions['content_type']);
        }

        return $this->getConditionsForClause($sqlConditions);
    } /* END prepareSearchExportProfileConditions */ /* END prepareSearchExportProfileConditions */

    /**
     * Checks the 'join' key of the incoming array for the presence of the FETCH_x bitfields in this class
     * and returns SQL snippets to join the specified tables if required.
     *
     * @param array $fetchOptions containing a 'join' integer key built from this class's FETCH_x bitfields.
     *
     * @return string containing selectFields, joinTables, orderClause keys.
     *          Example: selectFields = ', user.*, foo.title'; joinTables = ' INNER JOIN foo ON (foo.id = other.id) '; orderClause = 'ORDER BY x.y'
     */
    public function prepareSearchExportProfileFetchOptions(array &$fetchOptions)
    {
        $selectFields = '';
        $joinTables = '';
        $orderBy = '';

        return array(
            'selectFields' => $selectFields,
            'joinTables'   => $joinTables,
            'orderClause'  => ($orderBy ? "ORDER BY $orderBy" : '')
        );
    } /* END prepareSearchExportProfileFetchOptions */
}