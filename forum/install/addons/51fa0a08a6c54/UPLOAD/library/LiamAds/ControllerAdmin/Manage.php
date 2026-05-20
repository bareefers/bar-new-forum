<?php

class LiamAds_ControllerAdmin_Manage extends XenForo_ControllerAdmin_Abstract
{

	protected function _preDispatch($action)
	{
		$this->assertAdminPermission("liam_ads_manageads");
	}

	public function actionIndex()
	{
		$adsModel = $this->_getAdsModel();



		$viewParams = array(
				'adverts' => $adsModel->getAllAds()
		);

		return $this->responseView('XenForo_ViewAdmin', 'liamads_list', $viewParams);
	}

	public function actionNew()
	{

		$defaultoptions = array('ad_above_content',
				'ad_above_top_breadcrumb',
				'ad_below_bottom_breadcrumb',
				'ad_below_content',
				'ad_below_top_breadcrumb',
				'ad_forum_view_above_node_list',
				'ad_forum_view_above_thread_list',
				'ad_header',
				'ad_member_view_above_messages',
				'ad_member_view_below_avatar',
				'ad_member_view_sidebar_bottom',
				'ad_message_below',
				'ad_message_body',
				'ad_sidebar_below_visitor_panel',
				'ad_sidebar_bottom',
				'ad_sidebar_top',
				'ad_thread_list_below_stickies',
				'ad_thread_view_above_messages','Other');
		$viewParams = array('userCriteriaData' => XenForo_Helper_Criteria::getDataForUserCriteriaSelection(),'pageCriteriaData' => XenForo_Helper_Criteria::getDataForPageCriteriaSelection(),

				'showInactiveCriteria' => true, 'defaulthooks' => $defaultoptions);

		return $this->responseView('XenForo_ViewAdmin', 'liamads_modify', $viewParams);

	}

	public function actionEdit()
	{
		if (!$advertId = $this->_input->filterSingle("advert_id", XenForo_Input::UINT))
		{
			return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildAdminLink("adverts/new"));
		}

		$advert = $this->_getAdsModel()->getAdvertById($advertId);
		$defaultoptions = array('ad_above_content',
				'ad_above_top_breadcrumb',
				'ad_below_bottom_breadcrumb',
				'ad_below_content',
				'ad_below_top_breadcrumb',
				'ad_forum_view_above_node_list',
				'ad_forum_view_above_thread_list',
				'ad_header',
				'ad_member_view_above_messages',
				'ad_member_view_below_avatar',
				'ad_member_view_sidebar_bottom',
				'ad_message_below',
				'ad_message_body',
				'ad_sidebar_below_visitor_panel',
				'ad_sidebar_bottom',
				'ad_sidebar_top',
				'ad_thread_list_below_stickies',
				'ad_thread_view_above_messages','Other');

		$viewParams = array(
				'advert' => $advert,
				'userCriteria' => XenForo_Helper_Criteria::prepareCriteriaForSelection($advert['user_criteria']),
				'userCriteriaData' => XenForo_Helper_Criteria::getDataForUserCriteriaSelection(),

				'pageCriteria' => XenForo_Helper_Criteria::prepareCriteriaForSelection($advert['page_criteria']),
				'pageCriteriaData' => XenForo_Helper_Criteria::getDataForPageCriteriaSelection(),

				'showInactiveCriteria' => true,
				'defaulthooks' => $defaultoptions
		);

		return $this->responseView('XenForo_ViewAdmin', 'liamads_modify', $viewParams);
	}

	public function actionSave()
	{
		$this->_assertPostOnly();

		$advertId = $this->_input->filterSingle('advert_id', XenForo_Input::UINT);
		$dwData = $this->_input->filter(array(
				'advert_name' => XenForo_Input::STRING,
				'user_criteria' => XenForo_Input::ARRAY_SIMPLE,
				'page_criteria' => XenForo_Input::ARRAY_SIMPLE,
				'advert_code' => XenForo_Input::STRING,
				'mass_click' => XenForo_Input::INT,
				'advert_location' => XenForo_Input::STRING,
				'advert_location_other' => XenForo_Input::STRING
		));

		if ($dwData['advert_location'] == 'Other')
		{
			$dwData['advert_location'] = $dwData['advert_location_other'];
			unset($dwData['advert_location_other']);
		}
		else
		{
			unset($dwData['advert_location_other']);
				
		}

		$dw = XenForo_DataWriter::create('LiamAds_DataWriter_Adverts');
		if ($advertId)
		{
			$dw->setExistingData($advertId);
		}
		$dw->bulkSet($dwData);

		$dw->save();

		return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('adverts'), "Advert Saved Succesfully"
		);
	}

	public function actionDelete()
	{
		if ($this->isConfirmedPost())
		{
			return $this->_deleteData(
					'LiamAds_DataWriter_Adverts', 'advert_id',
					XenForo_Link::buildAdminLink('adverts')
			);
		}
		else
		{
			$advertId = $this->_input->filterSingle('advert_id', XenForo_Input::UINT);
			$advert = $this->_getAdsModel()->getAdvertById($advertId);

			$viewParams = array(
					'advert' => $advert
			);

			return $this->responseView('XenForo_ViewAdmin', 'liamads_delete', $viewParams);


		}
	}

	/**
	 * @return XenForo_ControllerHelper_UserCriteria
	 */
	protected function _getCriteriaHelper()
	{
		return $this->getHelper('UserCriteria');
	}

	protected function _getAdsModel()
	{
		return $this->getModelFromCache("LiamAds_Model_Adverts");
	}

}