<?php

class EWRporta2_ControllerAdmin_Widopts extends XenForo_ControllerAdmin_Abstract
{
	private $xp2perms;
	
	public function actionIndex()
	{
		$this->canonicalizeRequestUrl(XenForo_Link::buildAdminLink('porta2/widopts'));
		
		$viewParams = array(
			'widopts' => $this->getModelFromCache('EWRporta2_Model_Widopts')->getAllWidopts()
		);

		return $this->responseView('EWRporta2_ViewAdmin_WidoptList', 'EWRporta2_Widopt_List', $viewParams);
	}
	
	public function actionOptions()
	{
		$widoptID = $this->_input->filterSingle('string_id', XenForo_Input::STRING);

		if (!$widopt = $this->getModelFromCache('EWRporta2_Model_Widopts')->getWidoptById($widoptID))
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('porta2_requested_widopt_not_found'), 404));
		}
		
		$options = $this->getModelFromCache('EWRporta2_Model_Options')->getOptionsByWidopt($widopt);
		
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
				'widopt_id' => $widopt['widopt_id'],
				'widopt_values' => array(),
				'locked' => '0',
				'display' => 'show',
				'groups' => '',
				'ctime' => '',
			);
			
			foreach ($input['options_listed'] AS $key)
			{
				if (!empty($options[$key]))
				{
					if (!isset($input['options'][$key]))
					{
						$input['options'][$key] = '';
					}
					
					$dw = XenForo_DataWriter::create('EWRporta2_DataWriter_Options');
					$dw->setExistingData($options[$key], true);
					$value = $dw->testValidation($input['options'][$key]);
					
					if ($options[$key]['data_type'] == 'array')
					{
						$value = @unserialize($value);
						if (!is_array($value))
						{
							$value = array();
						}
					}
					
					$update['widopt_values'][$key] = $value;
				}
			}
			
			$update['locked'] = $input['locked'];
			$update['display'] = $input['display'];
			$update['groups'] = implode(',', $input['groups']);
			$update['ctime'] = $input['cache'] ? '+'.$input['cache_time'].' '.$input['cache_unit'] : '';
			
			$widopt = $this->getModelFromCache('EWRporta2_Model_Widopts')->updateWidopt($update);
			return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('porta2/widopts') . $this->getLastHash($widopt['widopt_id'])
			);
		}

		if (!empty($widopt['ctime']))
		{
			preg_match('#\+(\d+)\s(\w+)#i', $widopt['ctime'], $matches);
			$widopt['cached'] = true;
			$widopt['cache_time'] = $matches[1];
			$widopt['cache_unit'] = $matches[2];
		}
		
		$cache = false;
		$class = 'EWRporta2_Widget_'.$widopt['widget_id'];
		if (XenForo_Application::autoload($class))
		{
			$model = new $class;
			$cache = method_exists($model, 'getCachedData');
		}
		
		$viewParams = array(
			'cache' => $cache,
			'widopt' => $widopt,
			'options' => $options,
			'groups' => $this->getModelFromCache('XenForo_Model_UserGroup')->getUserGroupOptions($widopt['groups']),
			'preparedOptions' => $this->getModelFromCache('EWRporta2_Model_Options')->prepareOptions($options),
		);
		
		return $this->responseView('EWRporta2_ViewAdmin_WidoptOptions', 'EWRporta2_Widopt_Options', $viewParams);
	}
	
	public function actionClear()
	{
		$widoptID = $this->_input->filterSingle('string_id', XenForo_Input::STRING);

		if (!$widopt = $this->getModelFromCache('EWRporta2_Model_Widopts')->getWidoptById($widoptID))
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('porta2_requested_widopt_not_found'), 404));
		}
		
		if ($this->_request->isPost())
		{
			$this->getModelFromCache('EWRporta2_Model_Widopts')->clearWidoptCache($widopt);
			
			return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildAdminLink('porta2/widopts'));
		}
		else
		{
			$viewParams = array(
				'widopt' => $widopt
			);

			return $this->responseView('EWRporta2_ViewAdmin_WidoptClear', 'EWRporta2_Widopt_Clear', $viewParams);
		}
	}

	public function actionAdd()
	{
		$viewParams = array(
			'widgets' => $this->getModelFromCache('EWRporta2_Model_Widgets')->getWidgetOptionsList(),
		);

		return $this->responseView('EWRporta2_ViewAdmin_WidoptEdit', 'EWRporta2_Widopt_Edit', $viewParams);
	}
	
	public function actionEdit()
	{
		$widoptID = $this->_input->filterSingle('string_id', XenForo_Input::STRING);

		if (!$widopt = $this->getModelFromCache('EWRporta2_Model_Widopts')->getWidoptById($widoptID))
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('porta2_requested_widopt_not_found'), 404));
		}

		$viewParams = array(
			'widopt' => $widopt,
			'widget' => $this->getModelFromCache('EWRporta2_Model_Widgets')->getWidgetById($widopt['widget_id']),
		);

		return $this->responseView('EWRporta2_ViewAdmin_WidoptEdit', 'EWRporta2_Widopt_Edit', $viewParams);
	}
	
	public function actionSave()
	{
		$this->_assertPostOnly();
		
		$input = $this->_input->filter(array(
			'widget_id' => XenForo_Input::STRING,
			'widopt_id' => XenForo_Input::UINT,
			'widopt_title' => XenForo_Input::STRING,
		));
		
		if (empty($input['widopt_id']) && $widget = $this->getModelFromCache('EWRporta2_Model_Widgets')->getWidgetById($input['widget_id']))
		{
			$input['widopt_values'] = $widget['widget_values'];
			$input['display'] = $widget['display'];
			$input['groups'] = $widget['groups'];
			$input['ctime'] = $widget['ctime'];
		}
		
		$widopt = $this->getModelFromCache('EWRporta2_Model_Widopts')->updateWidopt($input);
		
		return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildAdminLink('porta2/widopts/options', $widopt));
	}
	
	public function actionDelete()
	{
		$widoptID = $this->_input->filterSingle('string_id', XenForo_Input::STRING);

		if (!$widopt = $this->getModelFromCache('EWRporta2_Model_Widopts')->getWidoptById($widoptID))
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('porta2_requested_widopt_not_found'), 404));
		}
		
		if ($this->_request->isPost())
		{
			$this->getModelFromCache('EWRporta2_Model_Widopts')->deleteWidopt($widopt);
			
			return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildAdminLink('porta2/widopts'));
		}
		else
		{
			$viewParams = array(
				'widopt' => $widopt
			);

			return $this->responseView('EWRporta2_ViewAdmin_WidoptDelete', 'EWRporta2_Widopt_Delete', $viewParams);
		}
	}

	protected function _preDispatch($action)
	{
		parent::_preDispatch($action);

		$this->xp2perms = $this->getModelFromCache('EWRporta2_Model_Perms')->getPermissions();
		
		if (!$this->xp2perms['admin']) { return $this->responseNoPermission(); }
	}
}