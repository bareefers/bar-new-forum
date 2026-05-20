<?php

class Waindigo_UserSearch_Install_Controller extends Waindigo_Install
{

    protected $_resourceManagerUrl = 'https://xenforo.com/community/resources/user-search-by-waindigo.2341/';

    protected function _getContentTypeFields()
    {
        return array(
            'user' => array(
                'search_handler_class' => 'Waindigo_UserSearch_Search_DataHandler_User', /* END 'search_handler_class' */
            ), /* END 'user' */
        );
    } /* END _getContentTypeFields */

    protected function _getPermissionEntries()
    {
        return array(
            'general' => array(
                'userSearch' => array(
                    'permission_group_id' => 'general', /* END 'permission_group_id' */
                    'permission_id' => 'search', /* END 'permission_id' */
                ), /* END 'userSearch' */
            ), /* END 'general' */
        );
    } /* END _getPermissionEntries */
}