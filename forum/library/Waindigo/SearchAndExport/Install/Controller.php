<?php

/**
 * Installer for Search and Export by Waindigo.
 */
class Waindigo_SearchAndExport_Install_Controller extends Waindigo_Install
{

    protected function _getContentTypeFields()
    {
        return array(
            'user' => array(
                'search_export_handler_class' => 'Waindigo_SearchAndExport_Search_ExportHandler_User', /* END 'search_export_handler_class' */
            ), /* END 'user' */
        );
    } /* END _getContentTypeFields */

    /**
     * Gets the tables (with fields) to be created for this add-on.
     * See parent for explanation.
     *
     * @return array Format: [table name] => fields
     */
    protected function _getTables()
    {
        return array(
            'xf_search_export_profile_waindigo' => array(
                'search_export_profile_id' => 'int UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY', /* END 'search_export_profile_id' */
                'title' => 'varchar(255) NOT NULL', /* END 'title' */
                'content_type' => 'varchar(25) NOT NULL COMMENT \'eg: post\'', /* END 'content_type' */
                'columns' => 'mediumblob NOT NULL', /* END 'columns' */
            ), /* END 'xf_search_export_profile_waindigo' */
        );
    } /* END _getTables */
}