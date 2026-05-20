<?php

class Waindigo_SearchAndExport_Search_ExportHandler_User
{

    public function updateDataHandler(XenForo_Search_DataHandler_Abstract $handler, array $search)
    {
        if (!empty($search['searchConstraints'])) {
            $handler->setExportSearchContstraints($search['searchConstraints']);
        }
    } /* END updateDataHandler */
}