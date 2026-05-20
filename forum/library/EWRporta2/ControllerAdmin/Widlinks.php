<?php

class EWRporta2_ControllerAdmin_Widlinks extends XenForo_ControllerAdmin_Abstract
{
	private $xp2perms;
	
	public function actionIndex()
	{
		return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildAdminLink('porta2/layouts'));
	}

	public function actionAdd()
	{
		$layoutID = $this->_input->filterSingle('layout', XenForo_Input::STRING);

		if (!$layout = $this->getModelFromCache('EWRporta2_Model_Layouts')->getLayoutById($layoutID))
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('porta2_requested_layout_not_found'), 404));
		}

		$viewParams = array(
			'layout' => $layout,
			'widgets' => $this->getModelFromCache('EWRporta2_Model_Widgets')->getWidgetOptionsList(),
		);

		return $this->responseView('EWRporta2_ViewAdmin_WidlinkEdit', 'EWRporta2_Widlink_Edit', $viewParams);
	}
	
	public function actionEdit()
	{
		$widlinkID = $this->_input->filterSingle('string_id', XenForo_Input::STRING);

		if (!$widlink = $this->getModelFromCache('EWRporta2_Model_Widlinks')->getWidlinkById($widlinkID))
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('porta2_requested_widlink_not_found'), 404));
		}

		$viewParams = array(
			'widlink' => $widlink,
			'layout' => $this->getModelFromCache('EWRporta2_Model_Layouts')->getLayoutById($widlink['layout_id']),
			'widget' => $this->getModelFromCache('EWRporta2_Model_Widgets')->getWidgetById($widlink['widget_id']),
			'widopts' => $this->getModelFromCache('EWRporta2_Model_Widopts')->getWidoptOptionsList($widlink['widget_id']),
		);

		return $this->responseView('EWRporta2_ViewAdmin_WidlinkEdit', 'EWRporta2_Widlink_Edit', $viewParams);
	}
	
	public function actionSave()
	{
		$this->_assertPostOnly();
		
		$input = $this->_input->filter(array(
			'layout_id' => XenForo_Input::STRING,
			'widget_id' => XenForo_Input::STRING,
			'widopt_id' => XenForo_Input::UINT,
			'widlink_id' => XenForo_Input::UINT,
			'widlink_title' => XenForo_Input::STRING,
		));
		
		$widlink = $this->getModelFromCache('EWRporta2_Model_Widlinks')->updateWidlink($input);
		
		if ($this->_noRedirect())
		{
			$viewParams = array(
				'widlink' => $widlink,
			);

			return $this->responseView('EWRporta2_ViewAdmin_WidlinkSave', 'EWRporta2_Widlink_Bit', $viewParams);
		}
		
		return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildAdminLink('porta2/layouts', $widlink));
	}
	
	public function actionDelete()
	{
		$widlinkID = $this->_input->filterSingle('string_id', XenForo_Input::STRING);

		if (!$widlink = $this->getModelFromCache('EWRporta2_Model_Widlinks')->getWidlinkById($widlinkID))
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('porta2_requested_widlink_not_found'), 404));
		}
		
		if ($this->_request->isPost())
		{
			$this->getModelFromCache('EWRporta2_Model_Widlinks')->deleteWidlink($widlink);
			
			if ($this->_noRedirect())
			{
				return $this->responseMessage(new XenForo_Phrase('redirect_changes_saved_successfully'));
			}
			
			return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildAdminLink('porta2/layouts', $widlink));
		}
		else
		{
			$viewParams = array(
				'widlink' => $widlink,
				'layout' => $this->getModelFromCache('EWRporta2_Model_Layouts')->getLayoutById($widlink['layout_id']),
			);

			return $this->responseView('EWRporta2_ViewAdmin_WidlinkDelete', 'EWRporta2_Widlink_Delete', $viewParams);
		}
	}

	protected function _preDispatch($action)
	{
		parent::_preDispatch($action);

		$this->xp2perms = $this->getModelFromCache('EWRporta2_Model_Perms')->getPermissions();
		
		if (!$this->xp2perms['admin']) { return $this->responseNoPermission(); }
	}
}