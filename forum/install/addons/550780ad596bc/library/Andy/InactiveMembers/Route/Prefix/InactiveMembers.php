<?php

class Andy_InactiveMembers_Route_Prefix_InactiveMembers implements XenForo_Route_Interface
{
	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		return $router->getRouteMatch('Andy_InactiveMembers_ControllerPublic_InactiveMembers', $routePath);
	}
}