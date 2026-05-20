<?php

class Waindigo_SearchAndExport_Listener_LoadClass extends Waindigo_Listener_LoadClass
{

    protected function _getExtendedClasses()
    {
        return array(
            'Waindigo_SearchAndExport' => array(
                'controller' => array(
                    'XenForo_ControllerPublic_Search'
                ), /* END 'controller' */
                'model' => array(
                    'XenForo_Model_Search'
                ), /* END 'model' */
                'search_data' => array(
                    'Waindigo_UserSearch_Search_DataHandler_User'
                ), /* END 'search_data' */
            ), /* END 'Waindigo_SearchAndExport' */
        );
    } /* END _getExtendedClasses */

    public static function loadClassController($class, array &$extend)
    {
        $loadClassController = new Waindigo_SearchAndExport_Listener_LoadClass($class, $extend, 'controller');
        $extend = $loadClassController->run();
    } /* END loadClassController */

    public static function loadClassModel($class, array &$extend)
    {
        $loadClassModel = new Waindigo_SearchAndExport_Listener_LoadClass($class, $extend, 'model');
        $extend = $loadClassModel->run();
    } /* END loadClassModel */

    public static function loadClassSearchData($class, array &$extend)
    {
        $loadClassSearchData = new Waindigo_SearchAndExport_Listener_LoadClass($class, $extend, 'search_data');
        $extend = $loadClassSearchData->run();
    } /* END loadClassSearchData */
}