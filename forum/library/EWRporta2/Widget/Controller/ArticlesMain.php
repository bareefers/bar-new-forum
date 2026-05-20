<?php

class EWRporta2_Widget_Controller_ArticlesMain extends XFCP_EWRporta2_Widget_Controller_ArticlesMain
{
	public function actionArticlesMain()
	{
		$wid = $this->_input->filterSingle('wid', XenForo_Input::UINT);
		
		if (!$widlink = $this->getModelFromCache('EWRporta2_Model_Widlinks')->getWidlinkDetailsById($wid))
		{
			return $this->responseView('EWRporta2_Widget_ViewPublic_ArticlesMain', 'EWRwidget_ArticlesMain', array('wUncached' => array()));
		}
		
		$start = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
		$stop = $widlink['options']['articlesmain_limit'];
		
		$params = array(
			'category' => $widlink['options']['articlesmain_category'][0] != 0 ? $widlink['options']['articlesmain_category'] : false,
			'author' => $widlink['options']['articlesmain_author'][0] != 0 ? $widlink['options']['articlesmain_author'] : false,
		);
		
		$count = $this->getModelFromCache('EWRporta2_Model_Articles')->getArticlesCount($params);
		$articles = $this->getModelFromCache('EWRporta2_Model_Articles')->getArticles($start, $stop, $params);
		$articles = $this->getModelFromCache('EWRporta2_Model_Articles')->prepareArticles($articles);
		$widlink['options']['count'] = $count;
		
		$viewParams = array(
			'perms' => $this->getModelFromCache('EWRporta2_Model_Perms')->getPermissions(),
			'wUncached' => $articles,
			'wOptions' => $widlink['options'],
		);
		
		return $this->responseView('EWRporta2_Widget_ViewPublic_ArticlesMain', 'EWRwidget_ArticlesMain', $viewParams);
	}
}