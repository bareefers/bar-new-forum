<?php
class DBTC_Route_Prefix_NodeEntry implements XenForo_Route_Interface
{
    public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
    {
		return $router->getRouteMatch('DBTC_ControllerPublic_NodeEntry', $routePath);
    }
}
?>