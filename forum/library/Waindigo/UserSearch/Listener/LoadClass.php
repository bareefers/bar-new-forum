<?php

class Waindigo_UserSearch_Listener_LoadClass extends Waindigo_Listener_LoadClass
{

    protected function _getExtendedClasses()
    {
        return array(
            'Waindigo_UserSearch' => array(
                'datawriter' => array(
                    'XenForo_DataWriter_User'
                ), /* END 'datawriter' */
                'model' => array(
                    'XenForo_Model_User'
                ), /* END 'model' */
                'controller' => array(
                    'XenForo_ControllerPublic_Search'
                ), /* END 'controller' */
            ), /* END 'Waindigo_UserSearch' */
        );
    } /* END _getExtendedClasses */

    public static function loadClassDataWriter($class, array &$extend)
    {
        $extend = self::createAndRun('Waindigo_UserSearch_Listener_LoadClass', $class, $extend, 'datawriter');
    } /* END loadClassDataWriter */

    public static function loadClassModel($class, array &$extend)
    {
        $extend = self::createAndRun('Waindigo_UserSearch_Listener_LoadClass', $class, $extend, 'model');
    } /* END loadClassModel */

    public static function loadClassController($class, array &$extend)
    {
        $extend = self::createAndRun('Waindigo_UserSearch_Listener_LoadClass', $class, $extend, 'controller');
    } /* END loadClassController */
}