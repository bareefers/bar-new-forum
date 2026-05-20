<?php

class EWRporta2_ControllerAdmin_Categories extends XenForo_ControllerAdmin_Abstract
{
	private $xp2perms;
	
	public function actionIndex()
	{
		$this->canonicalizeRequestUrl(XenForo_Link::buildAdminLink('porta2/categories'));
		
		$viewParams = array(
			'categories' => $this->getModelFromCache('EWRporta2_Model_Categories')->getAllCategories()
		);

		return $this->responseView('EWRporta2_ViewAdmin_CategoryList', 'EWRporta2_Category_List', $viewParams);
	}

	public function actionAdd()
	{
		$styles = $this->getModelFromCache('XenForo_Model_Style')->getStylesForOptionsTag();
		
		foreach ($styles AS &$style)
		{
			$style['indent'] = '';
			for ($i = 0; $i < $style['depth']; $i++)
			{
				$style['indent'] .= '&nbsp; &nbsp; ';
			}
		}
		
		$viewParams = array(
			'category' => array('category_type' => 'tag'),
			'styles' => $styles,
		);

		return $this->responseView('EWRporta2_ViewAdmin_CategoryEdit', 'EWRporta2_Category_Edit', $viewParams);
	}
	
	public function actionEdit()
	{
		$categoryID = $this->_input->filterSingle('string_id', XenForo_Input::STRING);

		if (!$category = $this->getModelFromCache('EWRporta2_Model_Categories')->getCategoryById($categoryID))
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('porta2_requested_category_not_found'), 404));
		}
		
		$styles = $this->getModelFromCache('XenForo_Model_Style')->getStylesForOptionsTag();
		
		foreach ($styles AS &$style)
		{
			$style['indent'] = '';
			for ($i = 0; $i < $style['depth']; $i++)
			{
				$style['indent'] .= '&nbsp; &nbsp; ';
			}
		}

		$viewParams = array(
			'category' => $category,
			'styles' => $styles,
		);

		return $this->responseView('EWRporta2_ViewAdmin_CategoryEdit', 'EWRporta2_Category_Edit', $viewParams);
	}
	
	public function actionSave()
	{
		$this->_assertPostOnly();

		$input = $this->_input->filter(array(
			'category_id' => XenForo_Input::UINT,
			'category_name' => XenForo_Input::STRING,
			'category_desc' => XenForo_Input::STRING,
			'category_type' => XenForo_Input::STRING,
			'style_id' => XenForo_Input::UINT,
		));
		
		$this->getModelFromCache('EWRporta2_Model_Categories')->updateCategory($input);

		return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('porta2/categories') . $this->getLastHash($input['category_id'])
		);
	}
	
	public function actionDelete()
	{
		$categoryID = $this->_input->filterSingle('string_id', XenForo_Input::STRING);

		if (!$category = $this->getModelFromCache('EWRporta2_Model_Categories')->getCategoryById($categoryID))
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('porta2_requested_category_not_found'), 404));
		}
		
		if ($this->_request->isPost())
		{
			$this->getModelFromCache('EWRporta2_Model_Categories')->deleteCategory($category);
			
			return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildAdminLink('porta2/categories'));
		}
		else
		{
			$viewParams = array(
				'category' => $category
			);

			return $this->responseView('EWRporta2_ViewAdmin_CategoryDelete', 'EWRporta2_Category_Delete', $viewParams);
		}
	}

	protected function _preDispatch($action)
	{
		parent::_preDispatch($action);

		$this->xp2perms = $this->getModelFromCache('EWRporta2_Model_Perms')->getPermissions();
		
		if (!$this->xp2perms['admin']) { return $this->responseNoPermission(); }
	}
}