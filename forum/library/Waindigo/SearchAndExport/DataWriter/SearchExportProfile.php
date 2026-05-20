<?php

/**
 * Data writer for search export profiles.
 */
class Waindigo_SearchAndExport_DataWriter_SearchExportProfile extends XenForo_DataWriter
{

    /**
     * Title of the phrase that will be created when a call to set the
     * existing data fails (when the data doesn't exist).
     *
     * @var string
     */
    protected $_existingDataErrorPhrase = 'waindigo_requested_search_export_profile_not_found_searchandexport';

    /**
     * Gets the fields that are defined for the table.
     * See parent for explanation.
     *
     * @return array
     */
    protected function _getFields()
    {
        return array(
            'xf_search_export_profile_waindigo' => array(
                'search_export_profile_id' => array(
                    'type' => self::TYPE_UINT,
                    'autoIncrement' => true
                ), /* END 'search_export_profile_id' */
                'title' => array(
                    'type' => self::TYPE_STRING,
                    'required' => true
                ), /* END 'title' */
                'content_type' => array(
                    'type' => self::TYPE_STRING,
                    'required' => true
                ), /* END 'content_type' */
                'columns' => array(
                    'type' => self::TYPE_UNKNOWN,
                    'required' => true,
                    'verification' => array(
                        '$this',
                        '_verifyColumns'
                    )
                ), /* END 'columns' */
            ), /* END 'xf_search_export_profile_waindigo' */
        );
    } /* END _getFields */

    /**
     * Gets the actual existing data out of data that was passed in.
     * See parent for explanation.
     *
     * @param mixed
     *
     * @return array false
     */
    protected function _getExistingData($data)
    {
        if (!$searchExportProfileId = $this->_getExistingPrimaryKey($data, 'search_export_profile_id')) {
            return false;
        }

        $searchExportProfile = $this->_getSearchExportProfileModel()->getSearchExportProfileById($searchExportProfileId);
        if (!$searchExportProfile) {
            return false;
        }

        return $this->getTablesDataFromArray($searchExportProfile);
    } /* END _getExistingData */

    /**
     * Gets SQL condition to update the existing record.
     *
     * @return string
     */
    protected function _getUpdateCondition($tableName)
    {
        return 'search_export_profile_id = ' . $this->_db->quote($this->getExisting('search_export_profile_id'));
    } /* END _getUpdateCondition */

    /**
     * Verifies that the columns are valid and formats it correctly.
     * Expected input format: [] with children: [column] => string, [enabled] =>
     * true|false
     *
     * @param array|string $columns Columns array or serialize string; see
     * above for format. Modified by ref.
     *
     * @return boolean
     */
    protected function _verifyColumns(&$columns)
    {
        if (!is_array($columns)) {
            $columns = unserialize($columns);
        }
        foreach ($columns as $columnKey => $column) {
            if (empty($column['column'])) {
                unset($columns[$columnKey]);
                continue;
            }
            if (empty($column['enabled'])) {
                $columns[$columnKey]['enabled'] = 0;
            } else {
                $columns[$columnKey]['enabled'] = 1;
            }
        }
        $columns = array_values($columns);
        $columns = serialize($columns);
        return true;
    } /* END _verifyColumns */ /* END _verifyCriteria */

    /**
     * Get the search export profiles model.
     *
     * @return Waindigo_SearchAndExport_Model_SearchExportProfile
     */
    protected function _getSearchExportProfileModel()
    {
        return $this->getModelFromCache('Waindigo_SearchAndExport_Model_SearchExportProfile');
    } /* END _getSearchExportProfileModel */
}