<?php

class EWRporta2_Route_Porta2 implements XenForo_Route_Interface
{
	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		$actions = explode('/', $routePath);
		
		switch ($actions[0])
		{
			case 'categories':	$controller = '_Categories';	unset($actions[0]);		break;
			case 'layouts':		$controller = '_Layouts';		unset($actions[0]);		break;
			case 'options':		$controller = '_Options';		unset($actions[0]);		break;
			case 'widgets':		$controller = '_Widgets';		unset($actions[0]);		break;
			case 'widlinks':	$controller = '_Widlinks';		unset($actions[0]);		break;
			case 'widopts':		$controller = '_Widopts';		unset($actions[0]);		break;
			default:			$controller = '_Porta2';
		}
		
		$action = implode('/', $actions);
		$action = $router->resolveActionWithStringParam($action, $request, 'string_id');
		return $router->getRouteMatch('EWRporta2_ControllerAdmin'.$controller, $action, 'porta2');
	}

	public function buildLink($originalPrefix, $outputPrefix, $action, $extension, $data, array &$extraParams)
	{
		$origin = $originalPrefix;
	
		if (is_array($data))
		{
			$actions = explode('/', $action);
			$outputPrefix .= '/' . $actions[0];
			$origin = $actions[0];
			
			unset($actions[0]);
			$action = implode('/', $actions);
		}
		
		switch ($origin)
		{
			case 'categories':	$strParams = 'category_id';		break;
			case 'layouts':		$strParams = 'layout_id';		break;
			case 'options':		$strParams = 'option_id';		break;
			case 'widgets':		$strParams = 'widget_id';		break;
			case 'widlinks':	$strParams = 'widlink_id';		break;
			case 'widopts':		$strParams = 'widopt_id';		break;
			default:			$strParams = '';
		}

		return XenForo_Link::buildBasicLinkWithStringParam($outputPrefix, $action, $extension, $data, $strParams);
	}
}