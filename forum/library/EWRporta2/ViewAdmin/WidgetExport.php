<?php

class EWRporta2_ViewAdmin_WidgetExport extends XenForo_ViewAdmin_Base
{
	public function renderXml()
	{
		$this->setDownloadFileName($this->_params['widget']['widget_id'] . '.xml');
		return $this->_params['xml']->saveXml();
	}
}