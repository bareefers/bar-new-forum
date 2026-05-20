<?php

class EWRutiles_Route_Utiles implements XenForo_Route_Interface
{
	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		$components = explode('/', $routePath);
		$subPrefix = strtolower(array_shift($components));
		$subSplits = explode('.', $subPrefix);

		$controllerName = '';
		$action = '';
		$intParams = '';
		$strParams = '';
		$slice = false;

		switch ($subPrefix)
		{
			case 'banning':			$controllerName = '_Banning';									$slice = true;	break;
			case 'spamlogs':		$controllerName = '_SpamLogs';	$intParams = 'spamlog_id';		$slice = true;	break;
			case 'user':			$controllerName = '_User';		$intParams = 'user_id';			$slice = true;	break;
			default: 				$controllerName = '_Utiles';
		}

		if (!empty($components[1]) && is_numeric($components[1])) { unset($components[1]); }
		$routePathAction = ($slice ? implode('/', array_slice($components, 0, 2)) : $routePath);

		if ($strParams)
		{
			$action = $router->resolveActionWithStringParam($routePathAction, $request, $strParams);
		}
		else
		{
			$action = $router->resolveActionWithIntegerParam($routePathAction, $request, $intParams);
		}

		$action = $router->resolveActionAsPageNumber($action, $request);
		$action = (int) $action > 0 ? '' : $action;

		return $router->getRouteMatch('EWRutiles_ControllerAdmin'.$controllerName, $action, 'EWRutiles', $routePath);
	}

	public function buildLink($originalPrefix, $outputPrefix, $action, $extension, $data, array &$extraParams)
	{
		$components = explode('/', $action);
		$subPrefix = strtolower(array_shift($components));

		$intParams = '';
		$strParams = '';
		$title = '';
		$slice = false;

		switch ($subPrefix)
		{
			case 'banning':																	$slice = true;	break;
			case 'spamlogs':	$intParams = 'spamlog_id';									$slice = true;	break;
			case 'user':		$intParams = 'user_id';			$title = 'username';		$slice = true;	break;
		}

		if ($slice)
		{
			$outputPrefix .= '/'.$subPrefix;
			$action = implode('/', $components);
		}

		$action = XenForo_Link::getPageNumberAsAction($action, $extraParams);

		if ($strParams)
		{
			return XenForo_Link::buildBasicLinkWithStringParam($outputPrefix, $action, $extension, $data, $strParams);
		}
		else
		{
			return XenForo_Link::buildBasicLinkWithIntegerParam($outputPrefix, $action, $extension, $data, $intParams, $title);
		}
	}
}