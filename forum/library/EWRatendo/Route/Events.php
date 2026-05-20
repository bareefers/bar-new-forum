<?php

class EWRatendo_Route_Events implements XenForo_Route_Interface
{
	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		$components = explode('/', $routePath);
		$subPrefix = strtolower(array_shift($components));
		$subSplits = explode('.', $subPrefix);

		$controllerName = '';
		$action = '';
		$intParams = 'event_id';
		$strParams = '';
		$slice = false;

		switch ($subPrefix)
		{
			case 'monthly':		$controllerName = '_Monthly';	$strParams = 'date_select';		$slice = true;	break;
			case 'weekly':		$controllerName = '_Weekly';	$strParams = 'date_select';		$slice = true;	break;
			case 'daily':		$controllerName = '_Daily';		$strParams = 'date_select';		$slice = true;	break;
			case 'birthdays':	$controllerName = '_Birthdays';	$strParams = 'date_select';		$slice = true;	break;
			default : 			
				if (is_numeric(end($subSplits))) { $controllerName = '_Event'; }
		}

		$routePathAction = ($slice ? implode('/', array_slice($components, 0, 2)) : $routePath).'/';
		$routePathAction = str_replace('//', '/', $routePathAction);
		$routePathAction = preg_replace('#create/\d+#i', 'create/', $routePathAction);

		if ($strParams)
		{
			$action = $router->resolveActionWithStringParam($routePathAction, $request, $strParams);
		}
		elseif ($intParams)
		{
			$action = $router->resolveActionWithIntegerParam($routePathAction, $request, $intParams);
		}

		$action = $router->resolveActionAsPageNumber($action, $request);
		return $router->getRouteMatch('EWRatendo_ControllerPublic_Events'.$controllerName, $action, 'events', $routePath);
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
			case 'monthly':		$strParams = 'strParam';		$title = 'month';		$slice = true;	$data['strParam'] = $data['month'].'.'.$data['year'];	break;
			case 'weekly':		$strParams = 'strParam';		$title = 'week';		$slice = true;	$data['strParam'] = $data['week'].'.'.$data['wYear'];	break;
			case 'daily':		$strParams = 'strParam';		$title = 'daynum';		$slice = true;	$data['strParam'] = $data['daynum'].'.'.$data['year'];	break;
			case 'birthdays':	$strParams = 'strParam';		$title = 'day';			$slice = true;	$data['strParam'] = $data['day'].'.'.$data['month'];	break;
			default:			$intParams = 'event_id';		$title = 'event_title';
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