<?php

class EWRporta2_ControllerAdmin_Layouts extends XenForo_ControllerAdmin_Abstract
{
	private $xp2perms;
	
	public function actionIndex()
	{
		$this->canonicalizeRequestUrl(XenForo_Link::buildAdminLink('porta2/layouts'));
		
		$viewParams = array(
			'layouts' => $this->getModelFromCache('EWRporta2_Model_Layouts')->getAllLayouts()
		);

		return $this->responseView('EWRporta2_ViewAdmin_LayoutList', 'EWRporta2_Layout_List', $viewParams);
	}
	
	public function actionLinks()
	{
		$layoutID = $this->_input->filterSingle('string_id', XenForo_Input::STRING);

		if (!$layout = $this->getModelFromCache('EWRporta2_Model_Layouts')->getLayoutById($layoutID))
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('porta2_requested_layout_not_found'), 404));
		}
		
		if ($this->_request->isPost())
		{
			$positions = $this->_input->filterSingle('positions', XenForo_Input::ARRAY_SIMPLE);
			$this->getModelFromCache('EWRporta2_Model_Widlinks')->updateWidlinkPositions($positions);
			
			return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildAdminLink('porta2/layouts/links', $layout));
		}
		
		$widlinks = $this->getModelFromCache('EWRporta2_Model_Widlinks')->getWidlinksByLayoutId($layoutID);
		
		$viewParams = array(
			'layout' => $layout,
			'widlinks' => $this->getModelFromCache('EWRporta2_Model_Widlinks')->sortWidlinksToLayout($widlinks),
		);
		
		return $this->responseView('EWRporta2_ViewAdmin_LayoutLinks', 'EWRporta2_Layout_Links', $viewParams);
	}

	public function actionAdd()
	{
		$layouts = $this->getModelFromCache('EWRporta2_Model_Layouts')->getAllLayouts();
		$options = array();
		
		foreach ($layouts AS $layout)
		{
			$options[] = array(
				'label' => $layout['layout_title'],
				'value' => $layout['layout_id']
			);
		}
	
		$viewParams = array(
			'layout' => array('active' => true),
			'layouts' => $options
		);

		return $this->responseView('EWRporta2_ViewAdmin_LayoutEdit', 'EWRporta2_Layout_Edit', $viewParams);
	}
	
	public function actionEdit()
	{
		$layoutID = $this->_input->filterSingle('string_id', XenForo_Input::STRING);

		if (!$layout = $this->getModelFromCache('EWRporta2_Model_Layouts')->getLayoutById($layoutID))
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('porta2_requested_layout_not_found'), 404));
		}

		$viewParams = array(
			'layout' => $layout
		);

		return $this->responseView('EWRporta2_ViewAdmin_LayoutEdit', 'EWRporta2_Layout_Edit', $viewParams);
	}
	
	public function actionSave()
	{
		$this->_assertPostOnly();

		$originalID = $this->_input->filterSingle('original_id', XenForo_Input::STRING);

		$input = $this->_input->filter(array(
			'layout_id' => XenForo_Input::STRING,
			'layout_title' => XenForo_Input::STRING,
			'layout_template' => XenForo_Input::STRING,
			'layout_eval' => XenForo_Input::STRING,
			'layout_priority' => XenForo_Input::UINT,
			'layout_sidebar' => XenForo_Input::UINT,
			'active' => XenForo_Input::UINT,
		));
		
		$layout = $this->getModelFromCache('EWRporta2_Model_Layouts')->updateLayout($input, $originalID);
		
		if ($source = $this->_input->filterSingle('source', XenForo_Input::STRING))
		{
			$widlinks = $this->getModelFromCache('EWRporta2_Model_Widlinks')->getWidlinksByLayoutId($source);
			
			foreach ($widlinks AS $widlink)
			{
				$input = array(
					'layout_id' => $layout['layout_id'],
					'widget_id' => $widlink['widget_id'],
					'widopt_id' => $widlink['widopt_id'],
					'widlink_title' => $widlink['widlink_title'],
					'widlink_position' => $widlink['widlink_position'],
					'widlink_order' => $widlink['widlink_order'],
				);
				
				$this->getModelFromCache('EWRporta2_Model_Widlinks')->updateWidlink($input);
			}
		}

		return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('porta2/layouts') . $this->getLastHash($layout['layout_id'])
		);
	}
	
	public function actionDelete()
	{
		$layoutID = $this->_input->filterSingle('string_id', XenForo_Input::STRING);

		if (!$layout = $this->getModelFromCache('EWRporta2_Model_Layouts')->getLayoutById($layoutID))
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('porta2_requested_layout_not_found'), 404));
		}
		
		if ($this->_request->isPost())
		{
			$this->getModelFromCache('EWRporta2_Model_Layouts')->deleteLayout($layout);
			
			return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildAdminLink('porta2/layouts'));
		}
		else
		{
			$viewParams = array(
				'layout' => $layout
			);

			return $this->responseView('EWRporta2_ViewAdmin_LayoutDelete', 'EWRporta2_Layout_Delete', $viewParams);
		}
	}
	
	public function actionEnable()
	{
		$this->_checkCsrfFromToken($this->_input->filterSingle('_xfToken', XenForo_Input::STRING));

		$layoutID = $this->_input->filterSingle('string_id', XenForo_Input::STRING);
		$this->getModelFromCache('EWRporta2_Model_Layouts')->toggleLayout($layoutID, 1);
		
		return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('porta2/layouts') . $this->getLastHash($layoutID)
		);
	}
	
	public function actionDisable()
	{
		$this->_checkCsrfFromToken($this->_input->filterSingle('_xfToken', XenForo_Input::STRING));

		$layoutID = $this->_input->filterSingle('string_id', XenForo_Input::STRING);
		$this->getModelFromCache('EWRporta2_Model_Layouts')->toggleLayout($layoutID, 0);
		
		return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('porta2/layouts') . $this->getLastHash($layoutID)
		);
	}
	
	public function actionToggle()
	{
		return $this->_getToggleResponse(
			$this->getModelFromCache('EWRporta2_Model_Layouts')->getAllLayouts(),
			'EWRporta2_DataWriter_Layouts',
			'porta2/layouts');
	}

	protected function _preDispatch($action)
	{
		parent::_preDispatch($action);

		$this->xp2perms = $this->getModelFromCache('EWRporta2_Model_Perms')->getPermissions();
		
		if (!$this->xp2perms['admin']) { return $this->responseNoPermission(); }
	}
}