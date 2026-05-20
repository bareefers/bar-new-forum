<?php

class EWRutiles_Route_Sitemap implements XenForo_Route_Interface
{
	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		return $router->getRouteMatch('EWRutiles_ControllerPublic_Sitemap', 'index', 'sitemap', $routePath);
	}
}