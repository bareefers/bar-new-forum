<?php

/**
 *
 * @see XenForo_Model_Search
 */
class Waindigo_SearchAndExport_Extend_XenForo_Model_Search extends XFCP_Waindigo_SearchAndExport_Extend_XenForo_Model_Search
{

    /**
     *
     * @param array $search
     * @return array
     */
    public function decodeSearchResultsForExport(array $search)
    {
        if (!isset($search['searchResults'])) {
            $search['searchResults'] = $this->_decodeSearchTableData($search['search_results'], true);
        }

        return $search['searchResults'];
    } /* END decodeSearchResultsForExport */

    /**
     *
     * @param array $results
     * @return array
     */
    public function prepareSearchResultsForExport(array $results, array $columns, array $serializedColumns)
    {
        $xenOptions = XenForo_Application::get('options');

        $return = array();

        foreach ($results as $result) {
            $return[$result[self::CONTENT_ID]] = $result['content'];
        }

        $results = $return;

        if ($columns) {
            foreach ($results as $resultKey => $result) {
                $array = array();
                foreach ($columns as $key => $type) {
                    if (!empty($serializedColumns[$key])) {
                        foreach (array_keys($serializedColumns[$key]) as $newKey) {
                            $groupedKey = $key . '[' . $newKey . ']';
                            $array[$groupedKey] = empty($result[$groupedKey]) ? '' : $result[$groupedKey];
                        }
                    } else {
                        $array[$key] = empty($result[$key]) ? '' : $result[$key];
                    }
                }
                $results[$resultKey] = $array;
            }
        }

        return $results;
    } /* END prepareSearchResultsForExport */ /* END prepareSearchResultsGroupedForExport */

    /**
     * Gets the search results ready for export (using the handlers).
     * The results (in the returned "results" key) have extra, type-specific
     * data
     * included with them.
     *
     * @param array $search
     * @param array $results Search results ([] => array(content type, content
     * id)
     * @param array|null $viewingUser Information about the viewing user (keys:
     * user_id, permission_combination_id, permissions) or null for visitor
     *
     * @return array Keys: results, handlers
     */
    public function getSearchResultsForExport(array $search, array $results, array $viewingUser = null)
    {
        $resultsGrouped = $this->groupSearchResultsByType($results);
        $exportHandlers = $this->getSearchExportHandlers(array_keys($resultsGrouped));
        $handlers = $this->getSearchDataHandlers(array_keys($resultsGrouped));

        foreach ($exportHandlers as $contentTypeId => $exportHandler) {
            if (!empty($handlers[$contentTypeId])) {
                $exportHandler->updateDataHandler($handlers[$contentTypeId], $search);
            }
        }

        $resultsForDisplay = $this->getSearchResultsForDisplay($results, $viewingUser);


        return array_merge($resultsForDisplay, array(
            'exportHandlers' => $exportHandlers
        ));
    } /* END getSearchResultsForExport */

    /**
     * Gets the list of content types that have search export handlers.
     *
     * @return array Format: [content type] => search_export_handler_class
     */
    public function getSearchExportContentTypes()
    {
        return $this->getContentTypesWithField('search_export_handler_class');
    } /* END getSearchExportContentTypes */

    /**
     * Creates search export handler objects for the specified content types.
     *
     * @param array|null $handlerContentTypes List of content types. If null,
     * get all
     *
     * @return array Format: [content type] =>
     * Waindigo_SearchAndExport_Search_ExportHandler_Abstract object
     */
    public function getSearchExportHandlers(array $handlerContentTypes = null)
    {
        $contentTypes = $this->getSearchExportContentTypes();
        $handlers = array();
        if ($handlerContentTypes === null) {
            $handlerContentTypes = array_keys($contentTypes);
        }

        foreach ($handlerContentTypes as $contentType) {
            if (isset($contentTypes[$contentType])) {
                if (!class_exists($contentTypes[$contentType])) {
                    continue;
                }

                $handlers[$contentType] = Waindigo_SearchAndExport_Search_ExportHandler_Abstract::create(
                    $contentTypes[$contentType]);
            }
        }

        return $handlers;
    } /* END getSearchExportHandlers */

    /**
     * Determines if permissions are sufficient to export search results.
     *
     * @param array $user User being viewed
     * @param string $errorPhraseKey Returned by ref. Phrase key of more
     * specific error
     * @param array|null $viewingUser Viewing user ref
     *
     * @return boolean
     */
    public function canExportSearchResults(&$errorPhraseKey = '', array $viewingUser = null)
    {
        $this->standardizeViewingUserReference($viewingUser);

        return ($viewingUser['user_id'] &&
             XenForo_Permission::hasPermission($viewingUser['permissions'], 'general', 'exportSearch'));
    } /* END canExportSearchResults */
}