<?php

/**
 *
 * @see XenForo_ControllerPublic_Search
 */
class Waindigo_UserSearch_Extend_XenForo_ControllerPublic_Search extends XFCP_Waindigo_UserSearch_Extend_XenForo_ControllerPublic_Search
{

    public function actionIndex()
    {
        $response = parent::actionIndex();

        if ($response instanceof XenForo_ControllerResponse_View) {
            $response->params['canUserSearch'] = $this->_getUserModel()->canUserSearch();
        }

        return $response;
    } /* END actionIndex */
}