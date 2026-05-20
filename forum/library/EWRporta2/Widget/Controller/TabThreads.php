<?php

class EWRporta2_Widget_Controller_TabThreads extends XFCP_EWRporta2_Widget_Controller_TabThreads
{
	public function actionTabThreads()
	{
		$wid = $this->_input->filterSingle('wid', XenForo_Input::UINT);
		$tab = $this->_input->filterSingle('tab', XenForo_Input::UINT);
		$pos = $this->_input->filterSingle('pos', XenForo_Input::STRING);
		
		if (!$widlink = $this->getModelFromCache('EWRporta2_Model_Widlinks')->getWidlinkDetailsById($wid))
		{
			return $this->responseView('EWRporta2_Widget_ViewPublic_TabThreads', 'EWRwidget_TabThreads_Simple');
		}
		
		switch ($tab)
		{
			case 5:		$sources = $widlink['options']['tabthreads_source5'];	break;
			case 4:		$sources = $widlink['options']['tabthreads_source4'];	break;
			case 3:		$sources = $widlink['options']['tabthreads_source3'];	break;
			case 2:		$sources = $widlink['options']['tabthreads_source2'];	break;
			default:	$sources = $widlink['options']['tabthreads_source1'];	break;
		}
		
		$widlink['options']['tabthreads_source'] = $sources;
		
		$viewParams = array(
			'wTitle' => $widlink['widlink_title'],
			'wWidget' => $widlink['widget_id'],
			'wWidlink' => $widlink['widlink_id'],
			'wPosition' => $widlink['widlink_position'],
			'wDateTime' => $widlink['cdate'],
			'wOptions' => $widlink['options'],
			'wScale' => $pos,
			'wUncached' => $this->getModelFromCache('EWRporta2_Widget_TabThreads')->getTab($widlink, $widlink['options'])
		);
		
		return $this->responseView('EWRporta2_Widget_ViewPublic_TabThreads', 'EWRwidget_TabThreads_Simple', $viewParams);
	}
}