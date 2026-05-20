<?php

/**
 * Admin controller for handling actions on search export profiles.
 */
class Waindigo_SearchAndExport_ControllerAdmin_SearchExportProfile extends XenForo_ControllerAdmin_Abstract
{

    /**
     * Shows a list of search export profiles.
     *
     * @return XenForo_ControllerResponse_View
     */
    public function actionIndex()
    {
        $searchExportProfileModel = $this->_getSearchExportProfileModel();
        $searchExportProfiles = $searchExportProfileModel->getSearchExportProfiles();
        $viewParams = array(
            'searchExportProfiles' => $searchExportProfiles
        );
        return $this->responseView('Waindigo_SearchAndExport_ViewAdmin_SearchExportProfile_List',
            'waindigo_search_export_profile_list_searchandexpor', $viewParams);
    } /* END actionIndex */

    /**
     * Helper to get the search export profile add/edit form controller
     * response.
     *
     * @param array $searchExportProfile
     *
     * @return XenForo_ControllerResponse_View
     */
    protected function _getSearchExportProfileAddEditResponse(array $searchExportProfile)
    {
        $searchExportProfile = $this->_getSearchExportProfileModel()->prepareSearchExportProfile($searchExportProfile);

        /* @var $searchModel XenForo_Model_Search */
        $searchModel = XenForo_Model::create('XenForo_Model_Search');

        $searchContentTypeOptions = array();
        foreach ($searchModel->getSearchDataHandlers() as $contentType => $handler) {
            $searchContentTypeOptions[$contentType] = $handler->getSearchContentTypePhrase();
        }

        $viewParams = array(
            'searchExportProfile' => $searchExportProfile,

            'searchContentTypeOptions' => $searchContentTypeOptions,

            'nextCounter' => count($searchExportProfile['columns']),
        );

        return $this->responseView('Waindigo_SearchAndExport_ViewAdmin_SearchExportProfile_Edit',
            'waindigo_search_export_profile_edit_searchandexpor', $viewParams);
    } /* END _getSearchExportProfileAddEditResponse */

    /**
     * Displays a form to add a new search export profile.
     *
     * @return XenForo_ControllerResponse_View
     */
    public function actionAdd()
    {
        $searchExportProfile = $this->_getSearchExportProfileModel()->getDefaultSearchExportProfile();

        return $this->_getSearchExportProfileAddEditResponse($searchExportProfile);
    } /* END actionAdd */

    /**
     * Displays a form to edit an existing search export profile.
     *
     * @return XenForo_ControllerResponse_Abstract
     */
    public function actionEdit()
    {
        $searchExportProfileId = $this->_input->filterSingle('search_export_profile_id', XenForo_Input::STRING);

        if (!$searchExportProfileId) {
            return $this->responseReroute('Waindigo_SearchAndExport_ControllerAdmin_SearchExportProfile', 'add');
        }

        $searchExportProfile = $this->_getSearchExportProfileOrError($searchExportProfileId);

        return $this->_getSearchExportProfileAddEditResponse($searchExportProfile);
    } /* END actionEdit */

    /**
     * Inserts a new search export profile or updates an existing one.
     *
     * @return XenForo_ControllerResponse_Abstract
     */
    public function actionSave()
    {
        $this->_assertPostOnly();

        $searchExportProfileId = $this->_input->filterSingle('search_export_profile_id', XenForo_Input::STRING);

        $input = $this->_input->filter(
            array(
                'title' => XenForo_Input::STRING,
                'content_type' => XenForo_Input::STRING,
                'columns' => XenForo_Input::ARRAY_SIMPLE
            ));

        $writer = XenForo_DataWriter::create('Waindigo_SearchAndExport_DataWriter_SearchExportProfile');
        if ($searchExportProfileId) {
            $writer->setExistingData($searchExportProfileId);
        }
        $writer->bulkSet($input);
        $writer->save();

        if ($this->_input->filterSingle('reload', XenForo_Input::STRING)) {
            return $this->responseRedirect(XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED,
                XenForo_Link::buildAdminLink('search-export-profiles/edit', $writer->getMergedData()));
        } else {
            return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS,
                XenForo_Link::buildAdminLink('search-export-profiles') .
                     $this->getLastHash($writer->get('search_export_profile_id')));
        }
    } /* END actionSave */

    /**
     * Deletes a search export profile.
     *
     * @return XenForo_ControllerResponse_Abstract
     */
    public function actionDelete()
    {
        $searchExportProfileId = $this->_input->filterSingle('search_export_profile_id', XenForo_Input::STRING);
        $searchExportProfile = $this->_getSearchExportProfileOrError($searchExportProfileId);

        $writer = XenForo_DataWriter::create('Waindigo_SearchAndExport_DataWriter_SearchExportProfile');
        $writer->setExistingData($searchExportProfile);

        if ($this->isConfirmedPost()) { // delete search export profile
            $writer->delete();

            return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS,
                XenForo_Link::buildAdminLink('search-export-profiles'));
        } else { // show delete confirmation prompt
            $writer->preDelete();
            $errors = $writer->getErrors();
            if ($errors) {
                return $this->responseError($errors);
            }

            $viewParams = array(
                'searchExportProfile' => $searchExportProfile
            );

            return $this->responseView('Waindigo_SearchAndExport_ViewAdmin_SearchExportProfile_Delete',
                'waindigo_search_export_profile_delete_searchandexp', $viewParams);
        }
    } /* END actionDelete */

    /**
     * Gets a valid search export profile or throws an exception.
     *
     * @param string $searchExportProfileId
     *
     * @return array
     */
    protected function _getSearchExportProfileOrError($searchExportProfileId)
    {
        $searchExportProfile = $this->_getSearchExportProfileModel()->getSearchExportProfileById($searchExportProfileId);
        if (!$searchExportProfile) {
            throw $this->responseException(
                $this->responseError(
                    new XenForo_Phrase('waindigo_requested_search_export_profile_not_found_searchandexport'), 404));
        }

        return $searchExportProfile;
    } /* END _getSearchExportProfileOrError */

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