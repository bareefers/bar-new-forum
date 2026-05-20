<?php

abstract class Waindigo_SearchAndExport_Search_ExportHandler_Abstract
{

    /**
     * Creates the specified data handler.
     *
     * @param string $class Object to create
     *
     * @return Waindigo_SearchAndExport_Search_ExportHandler_Abstract
     */
    public static function create($class)
    {
        $class = XenForo_Application::resolveDynamicClass($class, 'search_export_waindigo');
        return new $class();
    } /* END create */
}