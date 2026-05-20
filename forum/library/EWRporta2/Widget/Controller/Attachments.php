<?php

class EWRporta2_Widget_Controller_Attachments extends XFCP_EWRporta2_Widget_Controller_Attachments
{
	public function actionAttachments()
	{
		$opt = $this->_input->filterSingle('opt', XenForo_Input::UINT);
		
		if ($opt)
		{
			$widget = $this->getModelFromCache('EWRporta2_Model_Widopts')->getWidoptDetailsById($opt);
			
			if (!$widget || $widget['widget_id'] != 'Attachments')
			{
				return $this->responseView('EWRporta2_Widget_ViewPublic_Attachments', 'EWRwidget_Attachments_Simple', array('attachments' => array()));
			}
		}
		else
		{
			$widget = $this->getModelFromCache('EWRporta2_Model_Widgets')->getWidgetById('Attachments');
		}
		
		$start = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
		$stop = $widget['options']['attachments_limit'];
		
		$forumCheck = '';
		if ($widget['options']['attachments_source'][0] != 0)
		{
			$forums = implode(',', $widget['options']['attachments_source']);
			$forumCheck = "AND xf_thread.node_id IN (".$forums.")";
		}
		
		$viewParams = array(
			'linkParams' => array('opt' => $opt),
			'start' => $start,
			'stop' => $stop,
			'count' => $this->getModelFromCache('EWRporta2_Widget_Attachments')->getAttachmentsCount($forumCheck),
			'attachments' => $this->getModelFromCache('EWRporta2_Widget_Attachments')->getAttachments($start, $stop, $forumCheck),
			'wOptions' => $widget['options'],
		);
		
		return $this->responseView('EWRporta2_Widget_ViewPublic_Attachments', 'EWRwidget_Attachments_Simple', $viewParams);
	}
}