<?php

class EWRporta2_ControllerAdmin_Widgets extends XenForo_ControllerAdmin_Abstract
{
	private $xp2perms;
	
	public function actionIndex()
	{
		$this->canonicalizeRequestUrl(XenForo_Link::buildAdminLink('porta2/widgets'));
		
		$viewParams = array(
			'widgets' => $this->getModelFromCache('EWRporta2_Model_Widgets')->getAllWidgets()
		);

		return $this->responseView('EWRporta2_ViewAdmin_WidgetList', 'EWRporta2_Widget_List', $viewParams);
	}
	
	public function actionOptions()
	{
		$widgetID = $this->_input->filterSingle('string_id', XenForo_Input::STRING);

		if (!$widget = $this->getModelFromCache('EWRporta2_Model_Widgets')->getWidgetById($widgetID))
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('porta2_requested_widget_not_found'), 404));
		}
		
		if ($this->_request->isPost())
		{
			$input = $this->_input->filter(array(
				'locked' => XenForo_Input::UINT,
				'display' => XenForo_Input::STRING,
				'groups' => array(XenForo_Input::UINT, array('array' => true)),
				'cache' => XenForo_Input::UINT,
				'cache_time' => XenForo_Input::UINT,
				'cache_unit' => XenForo_Input::STRING,
				'options' => XenForo_Input::ARRAY_SIMPLE,
				'options_listed' => array(XenForo_Input::STRING, array('array' => true))
			));
			
			$update = array(
				'locked' => $input['locked'],
				'display' => $input['display'],
				'groups' => implode(',', $input['groups']),
				'ctime' => $input['cache'] ? '+'.$input['cache_time'].' '.$input['cache_unit'] : '',
			);

			foreach ($input['options_listed'] AS $optionName)
			{
				if (!isset($input['options'][$optionName]))
				{
					$input['options'][$optionName] = '';
				}
			}
			
			$this->getModelFromCache('EWRporta2_Model_Options')->updateOptions($input['options'], $widgetID);
			$this->getModelFromCache('EWRporta2_Model_Widgets')->updateWidget($update, $widget);
			
			return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildAdminLink('porta2/widgets/options', $widget));
		}

		if (!empty($widget['ctime']))
		{
			preg_match('#\+(\d+)\s(\w+)#i', $widget['ctime'], $matches);
			$widget['cached'] = true;
			$widget['cache_time'] = $matches[1];
			$widget['cache_unit'] = $matches[2];
		}
		
		$options = $this->getModelFromCache('EWRporta2_Model_Options')->getOptionsByWidgetId($widget['widget_id']);
		
		$cache = false;
		$class = 'EWRporta2_Widget_'.$widget['widget_id'];
		if (XenForo_Application::autoload($class))
		{
			$model = new $class;
			$cache = method_exists($model, 'getCachedData');
		}
		
		$viewParams = array(
			'cache' => $cache,
			'widget' => $widget,
			'options' => $options,
			'groups' => $this->getModelFromCache('XenForo_Model_UserGroup')->getUserGroupOptions($widget['groups']),
			'template' => $this->getModelFromCache('XenForo_Model_Template')->getTemplateInStyleByTitle('EWRwidget_'.$widget['widget_id']),
			'preparedOptions' => $this->getModelFromCache('EWRporta2_Model_Options')->prepareOptions($options),
			'widgets' => $this->getModelFromCache('EWRporta2_Model_Widgets')->getAllWidgets(),
		);
		
		return $this->responseView('EWRporta2_ViewAdmin_WidgetOptions', 'EWRporta2_Widget_Options', $viewParams);
	}
	
	public function actionOrder()
	{
		$widgetID = $this->_input->filterSingle('string_id', XenForo_Input::STRING);

		if (!$widget = $this->getModelFromCache('EWRporta2_Model_Widgets')->getWidgetById($widgetID))
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('porta2_requested_widget_not_found'), 404));
		}
		
		$options = $this->getModelFromCache('EWRporta2_Model_Options')->getOptionsByWidgetId($widget['widget_id']);
		
		if ($this->_request->isPost())
		{
			$orders = $this->_input->filterSingle('option', array(XenForo_Input::UINT, 'array' => true));
			$this->getModelFromCache('EWRporta2_Model_Options')->updateOptionOrders($orders);
			
			return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildAdminLink('porta2/widgets/options', $widget));
		}
		
		$viewParams = array(
			'widget' => $widget,
			'options' => $options,
			'preparedOptions' => $this->getModelFromCache('EWRporta2_Model_Options')->prepareOptions($options)
		);
			
		return $this->responseView('EWRporta2_ViewAdmin_WidgetOrder', 'EWRporta2_Widget_Order', $viewParams);
	}
	
	public function actionClear()
	{
		$widgetID = $this->_input->filterSingle('string_id', XenForo_Input::STRING);

		if (!$widget = $this->getModelFromCache('EWRporta2_Model_Widgets')->getWidgetById($widgetID))
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('porta2_requested_widget_not_found'), 404));
		}
		
		if ($this->_request->isPost())
		{
			$this->getModelFromCache('EWRporta2_Model_Widgets')->clearWidgetCache($widget);
			
			return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildAdminLink('porta2/widgets'));
		}
		else
		{
			$viewParams = array(
				'widget' => $widget
			);

			return $this->responseView('EWRporta2_ViewAdmin_WidgetClear', 'EWRporta2_Widget_Clear', $viewParams);
		}
	}

	public function actionAdd()
	{
		$viewParams = array(
			'widget' => array('active' => true)
		);

		return $this->responseView('EWRporta2_ViewAdmin_WidgetEdit', 'EWRporta2_Widget_Edit', $viewParams);
	}
	
	public function actionEdit()
	{
		$widgetID = $this->_input->filterSingle('string_id', XenForo_Input::STRING);

		if (!$widget = $this->getModelFromCache('EWRporta2_Model_Widgets')->getWidgetById($widgetID))
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('porta2_requested_widget_not_found'), 404));
		}

		$viewParams = array(
			'widget' => $widget
		);

		return $this->responseView('EWRporta2_ViewAdmin_WidgetEdit', 'EWRporta2_Widget_Edit', $viewParams);
	}
	
	public function actionSave()
	{
		$this->_assertPostOnly();

		$originalID = $this->_input->filterSingle('original_id', XenForo_Input::STRING);

		$input = $this->_input->filter(array(
			'widget_id' => XenForo_Input::STRING,
			'widget_title' => XenForo_Input::STRING,
			'widget_desc' => XenForo_Input::STRING,
			'widget_string' => XenForo_Input::STRING,
			'widget_version' => XenForo_Input::UINT,
			'widget_install_class' => XenForo_Input::STRING,
			'widget_install_method' => XenForo_Input::STRING,
			'widget_uninstall_class' => XenForo_Input::STRING,
			'widget_uninstall_method' => XenForo_Input::STRING,
			'widget_url' => XenForo_Input::STRING,
			'active' => XenForo_Input::UINT,
		));
		
		$widget = $this->getModelFromCache('EWRporta2_Model_Widgets')->updateWidget($input, $originalID);

		return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('porta2/widgets') . $this->getLastHash($widget['widget_id'])
		);
	}
	
	public function actionDelete()
	{
		$widgetID = $this->_input->filterSingle('string_id', XenForo_Input::STRING);

		if (!$widget = $this->getModelFromCache('EWRporta2_Model_Widgets')->getWidgetById($widgetID))
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('porta2_requested_widget_not_found'), 404));
		}
		
		if ($this->_request->isPost())
		{
			$this->getModelFromCache('EWRporta2_Model_Widgets')->deleteWidget($widget);
			
			return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildAdminLink('porta2/widgets'));
		}
		else
		{
			$viewParams = array(
				'widget' => $widget
			);

			return $this->responseView('EWRporta2_ViewAdmin_WidgetDelete', 'EWRporta2_Widget_Delete', $viewParams);
		}
	}
	
	public function actionEnable()
	{
		$this->_checkCsrfFromToken($this->_input->filterSingle('_xfToken', XenForo_Input::STRING));

		$widgetID = $this->_input->filterSingle('string_id', XenForo_Input::STRING);
		$this->getModelFromCache('EWRporta2_Model_Widgets')->toggleWidget($widgetID, 1);
		
		return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('porta2/widgets') . $this->getLastHash($widgetID)
		);
	}
	
	public function actionDisable()
	{
		$this->_checkCsrfFromToken($this->_input->filterSingle('_xfToken', XenForo_Input::STRING));

		$widgetID = $this->_input->filterSingle('string_id', XenForo_Input::STRING);
		$this->getModelFromCache('EWRporta2_Model_Widgets')->toggleWidget($widgetID, 0);
		
		return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('porta2/widgets') . $this->getLastHash($widgetID)
		);
	}
	
	public function actionToggle()
	{
		return $this->_getToggleResponse(
			$this->getModelFromCache('EWRporta2_Model_Widgets')->getAllWidgets(),
			'EWRporta2_DataWriter_Widgets',
			'porta2/widgets');
	}

	public function actionExport()
	{
		$widgetID = $this->_input->filterSingle('string_id', XenForo_Input::STRING);

		if (!$widget = $this->getModelFromCache('EWRporta2_Model_Widgets')->getWidgetById($widgetID))
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('porta2_requested_widget_not_found'), 404));
		}

		$this->_routeMatch->setResponseType('xml');

		$viewParams = array(
			'widget' => $widget,
			'xml' => $this->getModelFromCache('EWRporta2_Model_Widgets')->exportWidget($widget),
		);

		return $this->responseView('EWRporta2_ViewAdmin_WidgetExport', '', $viewParams);
	}
	
	public function actionUpgrade()
	{
		$widgetID = $this->_input->filterSingle('string_id', XenForo_Input::STRING);

		if (!$widget = $this->getModelFromCache('EWRporta2_Model_Widgets')->getWidgetById($widgetID))
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('porta2_requested_widget_not_found'), 404));
		}
		
		$viewParams = array(
			'widget' => $widget
		);
			
		return $this->responseView('EWRporta2_ViewAdmin_WidgetInstall', 'EWRporta2_Widget_Install', $viewParams);
	}
	
	public function actionInstall()
	{
		$files = scandir($xmlDir = XenForo_Application::getInstance()->getRootDir() . '/library/EWRporta2/Widget/XML');
		$widgetsModel = XenForo_Model::create('EWRporta2_Model_Widgets');
		$widgets = $widgetsModel->getAllWidgets();
		
		$installs = array();
		
		foreach ($files AS $file)
		{
			if (stristr($file,'xml'))
			{
				$widgetID = str_ireplace('.xml', '', $file);

				if (!isset($widgets[$widgetID]))
				{
					$installs[$widgetID] = $xmlDir.'/'.$file;
				}
			}
		}
		
		$viewParams = array(
			'widgets' => $installs
		);
			
		return $this->responseView('EWRporta2_ViewAdmin_WidgetInstall', 'EWRporta2_Widget_Install', $viewParams);
	}
	
	public function actionInstallConfirm()
	{
		$this->_assertPostOnly();
		$widgetID = $this->_input->filterSingle('widget_id', XenForo_Input::STRING);
		$widgetID = !empty($widgetID) ? $widgetID : false;
		
		$fileTransfer = new Zend_File_Transfer_Adapter_Http();
		if ($fileTransfer->isUploaded('upload_file'))
		{
			$fileInfo = $fileTransfer->getFileInfo('upload_file');
			$fileName = $fileInfo['upload_file']['tmp_name'];
		}
		else if (!$fileName = $this->_input->filterSingle('widget_file', XenForo_Input::STRING))
		{
			$fileName = $this->_input->filterSingle('server_file', XenForo_Input::STRING);
		}

		$widget = $this->getModelFromCache('EWRporta2_Model_Widgets')->installWidget($fileName, $widgetID);
		
		return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('porta2/widgets') . $this->getLastHash($widget['widget_id'])
		);
	}

	protected function _preDispatch($action)
	{
		parent::_preDispatch($action);

		$this->xp2perms = $this->getModelFromCache('EWRporta2_Model_Perms')->getPermissions();
		
		if (!$this->xp2perms['admin']) { return $this->responseNoPermission(); }
	}
}