<?php

/**
 *
 * @see XenForo_DataWriter_User
 */
class Waindigo_UserSearch_Extend_XenForo_DataWriter_User extends XFCP_Waindigo_UserSearch_Extend_XenForo_DataWriter_User
{

    /**
     * Option that controls whether the user should be indexed for search.
     * Defaults to true.
     *
     * @var string
     */
    const OPTION_INDEX_FOR_SEARCH = 'indexForSearch';

    /**
     *
     * @see XenForo_DataWriter_User::_getDefaultOptions()
     */
    protected function _getDefaultOptions()
    {
        $defaultOptions = parent::_getDefaultOptions();

        $defaultOptions[self::OPTION_INDEX_FOR_SEARCH] = true;

        return $defaultOptions;
    } /* END _getDefaultOptions */

    /**
     * Post-save handling.
     */
    protected function _postSave()
    {
        parent::_postSave();

        if ($this->getOption(self::OPTION_INDEX_FOR_SEARCH)) {
            $this->_insertOrUpdateSearchIndex();
        }
    } /* END _postSave */

    /**
     * Inserts or updates a record in the search index for this user.
     */
    protected function _insertOrUpdateSearchIndex()
    {
        $dataHandler = XenForo_Search_DataHandler_Abstract::create('Waindigo_UserSearch_Search_DataHandler_User');

        $indexer = new XenForo_Search_Indexer();
        $dataHandler->insertIntoIndex($indexer, $this->getMergedData());
    } /* END _insertOrUpdateSearchIndex */

    /**
     * Post-delete handling.
     */
    protected function _postDelete()
    {
        parent::_postDelete();

        if ($this->getOption(self::OPTION_INDEX_FOR_SEARCH)) {
            $this->_deleteFromSearchIndex();
        }
    } /* END _postDelete */

    /**
     * Deletes this record from the search index.
     */
    protected function _deleteFromSearchIndex()
    {
        $user = $this->getMergedData();

        $dataHandler = new Waindigo_UserSearch_Search_DataHandler_User();

        $indexer = new XenForo_Search_Indexer();
        $dataHandler->deleteFromIndex($indexer, $user);
    } /* END _deleteFromSearchIndex */
}