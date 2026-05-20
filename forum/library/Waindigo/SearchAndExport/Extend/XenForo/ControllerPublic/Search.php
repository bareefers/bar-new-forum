<?php

/**
 *
 * @see XenForo_ControllerPublic_Search
 */
class Waindigo_SearchAndExport_Extend_XenForo_ControllerPublic_Search extends XFCP_Waindigo_SearchAndExport_Extend_XenForo_ControllerPublic_Search
{

    /**
     *
     * @see XenForo_ControllerPublic_Search::actionResults()
     */
    public function actionResults()
    {
        $response = parent::actionResults();

        if ($response instanceof XenForo_ControllerResponse_View) {
            $response->params['canExportSearchResults'] = $this->_getSearchModel()->canExportSearchResults();
        }

        return $response;
    } /* END actionResults */

    /**
     * Export the results of a search.
     *
     * @return XenForo_ControllerResponse_Abstract
     */
    public function actionExport()
    {
        /* @var $searchModel XenForo_Model_Search */
        $searchModel = $this->_getSearchModel();

        if (!$searchModel->canExportSearchResults()) {
            return $this->responseNoPermission();
        }

        $searchId = $this->_input->filterSingle('search_id', XenForo_Input::UINT);
        $searchQuery = $this->_input->filterSingle('q', XenForo_Input::STRING);

        $search = $searchModel->getSearchById($searchId);

        $maxResults = XenForo_Application::get('options')->maximumSearchResults;

        if (!$search) {
            $extendSearch = true;
        } elseif ($search['user_id'] != XenForo_Visitor::getUserId()) {
            if ($search['search_query'] === '' || $search['search_query'] !== $searchQuery) {
                // just browsing searches without having query
                return $this->responseError(new XenForo_Phrase('requested_search_not_found'), 404);
            }

            $extendSearch = true;
        } elseif ($search['result_count'] == $maxResults && empty($search['searchConstraints']['extended'])) {
            $extendSearch = true;
        } else {
            $extendSearch = false;
        }

        if ($extendSearch) {
            $extendInput = $this->_input->filter(
                array(
                    'q' => XenForo_Input::STRING,
                    't' => XenForo_Input::STRING,
                    'o' => XenForo_Input::STRING,
                    'g' => XenForo_Input::UINT,
                    'c' => XenForo_Input::ARRAY_SIMPLE
                ));
            $search = array(
                'search_query' => $extendInput['q'],
                'search_type' => $extendInput['t'],
                'search_order' => $extendInput['o'],
                'search_grouping' => $extendInput['g']
            );

            $extendedSearch = $this->runExtendedSearch($search, $extendInput['c']);
            return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS,
                XenForo_Link::buildPublicLink('search/export', $extendedSearch));
        }

        $search = $searchModel->prepareSearch($search);

        $page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
        $perPage = XenForo_Application::get('options')->searchResultsPerPage;

        $resultIds = $searchModel->decodeSearchResultsForExport($search);
        $results = $searchModel->getSearchResultsForExport($search, $resultIds);
        if (!$results) {
            return $this->getNoSearchResultsResponse($search);
        }

        $format = $this->_input->filterSingle('format', XenForo_Input::STRING);
        $contentType = $this->_input->filterSingle('content_type', XenForo_Input::STRING);
        $searchExportProfileId = $this->_input->filterSingle('search_export_profile_id', XenForo_Input::UINT);

        $contentTypes = array();
        $contentTypePhrases = array();
        foreach ($results['handlers'] as $_contentType => $handler) {
            $contentTypes[] = $_contentType;
            $contentTypePhrases[$_contentType] = $handler->getSearchContentTypePhrase();
        }

        if (!$contentType) {
            $contentType = reset($contentTypes);
        }

        $searchExportProfiles = array();
        if ($contentType) {
            $searchExportProfileModel = $this->_getSearchExportProfileModel();

            $searchExportProfiles = $searchExportProfileModel->getSearchExportProfiles(array('content_type' => $contentType));
            $searchExportProfiles = $searchExportProfileModel->prepareSearchExportProfiles($searchExportProfiles);
        }

        $viewParams = array(
            'search' => $search,
            'results' => $results,

            'format' => $format,

            'contentType' => $contentType,
            'contentTypes' => $contentTypes,
            'contentTypePhrases' => $contentTypePhrases,

            'searchExportProfileId' => $searchExportProfileId,
            'searchExportProfiles' => $searchExportProfiles
        );

        if ($format == 'csv') {
            $this->_routeMatch->setResponseType('raw');
            return $this->responseView('Waindigo_SearchAndExport_ViewPublic_Search_Export', '', $viewParams);
        }

        return $this->responseView('Waindigo_SearchAndExport_ViewPublic_Search_Export', 'waindigo_search_export_searchandexport', $viewParams);
    } /* END actionExport */

    /**
     * Reruns the given search with higher maximum.
     * If errors occur, a response exception will be thrown.
     *
     * @param array $search Search info (does not need search_id, constraints,
     * results, or warnings)
     * @param array $constraints Array of search constraints
     *
     * @return array New search
     */
    public function runExtendedSearch(array $search, array $constraints)
    {
        if (!XenForo_Visitor::getInstance()->canSearch()) {
            throw $this->getNoPermissionResponseException();
        }

        $visitorUserId = XenForo_Visitor::getUserId();
        $searchModel = $this->_getSearchModel();

        $typeHandler = null;
        if ($search['search_type']) {
            $typeHandler = $searchModel->getSearchDataHandler($search['search_type']);
        }

        $searcher = new XenForo_Search_Searcher($searchModel);

        $maxResults = XenForo_Application::get('options')->waindigo_searchAndExport_maximumSearchResults;

        if ($typeHandler) {
            $results = $searcher->searchType($typeHandler, $search['search_query'], $constraints,
                $search['search_order'], $search['search_grouping'], $maxResults);
        } else {
            $search['search_type'] = '';

            $results = $searcher->searchGeneral($search['search_query'], $constraints, $search['search_order'],
                $maxResults);
        }

        if (!$results) {
            throw $this->responseException($this->getNoSearchResultsResponse($searcher));
        }

        $constraints['extended'] = true;

        return $searchModel->insertSearch($results, $search['search_type'], $search['search_query'], $constraints,
            $search['search_order'], $search['search_grouping'], array(), $searcher->getWarnings(), $visitorUserId);
    } /* END runExtendedSearch */

    /**
     * @return Waindigo_SearchAndExport_Model_SearchExportProfile
     */
    protected function _getSearchExportProfileModel()
    {
        return $this->getModelFromCache('Waindigo_SearchAndExport_Model_SearchExportProfile');
    } /* END _getSearchExportProfileModel */
}