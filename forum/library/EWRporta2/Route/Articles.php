<?php

class EWRporta2_Route_Articles implements XenForo_Route_Interface
{
	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		$action = $router->resolveActionWithIntegerParam($routePath, $request, 'action_id');		
		$action = $router->resolveActionAsPageNumber($action, $request);
		return $router->getRouteMatch('EWRporta2_ControllerPublic_Articles', $action, 'articles');
	}

	public function buildLink($originalPrefix, $outputPrefix, $action, $extension, $data, array &$extraParams)
	{
		$actions = explode('/', $action);
		
		switch ($actions[0])
		{
			case 'category':	$intParams = 'category_id';		$strParams = 'category_name';	break;
			case 'author':		$intParams = 'user_id';			$strParams = 'username';		break;
			default:			$intParams = '';				$strParams = '';				break;
		}
		
		$action = XenForo_Link::getPageNumberAsAction($action, $extraParams);
		return XenForo_Link::buildBasicLinkWithIntegerParam($outputPrefix, $action, $extension, $data, $intParams, $strParams);
	}
}