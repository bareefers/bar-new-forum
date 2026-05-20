<?php

class EWRporta2_ControllerAdmin_Options extends XenForo_ControllerAdmin_Abstract
{
	private $xp2perms;
	
	public function actionIndex()
	{
		return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildAdminLink('porta2/widgets'));
	}

	public function actionAdd()
	{
		$widgetID = $this->_input->filterSingle('widget', XenForo_Input::STRING);
		
		$viewParams = array(
			'widget' => !empty($widgetID) ? $widgetID : '',
			'widgets' => $this->getModelFromCache('EWRporta2_Model_Widgets')->getWidgetOptionsList(),
			'newOption' => strtolower($widgetID).'_',
			'option' => array('edit_format' => 'textbox', 'data_type' => 'string'),
		);

		return $this->responseView('EWRporta2_ViewAdmin_OptionEdit', 'EWRporta2_Option_Edit', $viewParams);
	}
	
	public function actionEdit()
	{
		$optionID = $this->_input->filterSingle('string_id', XenForo_Input::STRING);

		if (!$option = $this->getModelFromCache('EWRporta2_Model_Options')->getOptionById($optionID))
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_option_not_found'), 404));
		}

		$viewParams = array(
			'widget' => $option['widget_id'],
			'widgets' => $this->getModelFromCache('EWRporta2_Model_Widgets')->getWidgetOptionsList(),
			'option' => $option,
		);

		return $this->responseView('EWRporta2_ViewAdmin_OptionEdit', 'EWRporta2_Option_Edit', $viewParams);
	}
	
	public function actionSave()
	{
		$this->_assertPostOnly();

		$originalID = $this->_input->filterSingle('original_id', XenForo_Input::STRING);

		$input = $this->_input->filter(array(
			'widget_id' => XenForo_Input::STRING,
			'option_id' => XenForo_Input::STRING,
			'default_value' => XenForo_Input::STRING,
			'title' => XenForo_Input::STRING,
			'explain' => XenForo_Input::STRING,
			'edit_format' => XenForo_Input::STRING,
			'edit_format_params' => XenForo_Input::STRING,
			'data_type' => XenForo_Input::STRING,
			'sub_options' => XenForo_Input::STRING,
			'validation_class' => XenForo_Input::STRING,
			'validation_method' => XenForo_Input::STRING,
			'display_order' => XenForo_Input::UINT,
		));
		
		if (!$widget = $this->getModelFromCache('EWRporta2_Model_Widgets')->getWidgetById($input['widget_id']))
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('porta2_requested_widget_not_found'), 404));
		}
		
		$this->getModelFromCache('EWRporta2_Model_Options')->updateOption($input, $originalID);

		return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildAdminLink('porta2/widgets/options', $widget));
	}
	
	public function actionDelete()
	{
		$optionID = $this->_input->filterSingle('string_id', XenForo_Input::STRING);

		if (!$option = $this->getModelFromCache('EWRporta2_Model_Options')->getOptionById($optionID))
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_option_not_found'), 404));
		}
		
		if ($this->_request->isPost())
		{
			$this->getModelFromCache('EWRporta2_Model_Options')->deleteOption($option);
			
			return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildAdminLink('porta2/widgets/options', $option));
		}
		else
		{
			$viewParams = array(
				'option' => $option
			);

			return $this->responseView('EWRporta2_ViewAdmin_OptionDelete', 'EWRporta2_Option_Delete', $viewParams);
		}
	}

	protected function _preDispatch($action)
	{
		parent::_preDispatch($action);

		$this->xp2perms = $this->getModelFromCache('EWRporta2_Model_Perms')->getPermissions();
		
		if (!$this->xp2perms['admin']) { return $this->responseNoPermission(); }
	}
}