<?php

class EWRporta2_Model_Widopts extends XenForo_Model
{
	public function getWidoptOptionsList($widgetID)
	{
		$options = array();
		$widopts = $this->fetchAllKeyed('
			SELECT *
				FROM EWRporta2_widopts
			WHERE widget_id = ?
			ORDER by widopt_title
		', 'widopt_id', $widgetID);
		
		foreach ($widopts AS $widopt)
		{
			$options[$widopt['widopt_id']] = $widopt['widopt_title'];
		}

		return $options;
	}
	
	public function getWidoptById($widoptID)
	{
		if (!$widopt = $this->_getDb()->fetchRow("
			SELECT * FROM EWRporta2_widopts WHERE widopt_id = ?
		", $widoptID))
		{
			return false;
		}
		
		return $widopt;
	}
	
	public function getWidoptDetailsById($widoptID)
	{
		if (!$widopt = $this->_getDb()->fetchRow('
			SELECT EWRporta2_widopts.*, EWRporta2_widgets.widget_values
			FROM EWRporta2_widopts
				INNER JOIN EWRporta2_widgets ON (EWRporta2_widgets.widget_id = EWRporta2_widopts.widget_id)
			WHERE EWRporta2_widopts.widopt_id = ?
		', $widoptID))
		{
			return false;
		}
		
		$widopt['options'] = @unserialize($widopt['widopt_values']) + @unserialize($widopt['widget_values']);
		
		return $widopt;
	}

	public function getAllWidopts()
	{
		return $this->fetchAllKeyed('
			SELECT EWRporta2_widopts.*, EWRporta2_widgets.*
			FROM EWRporta2_widopts
				INNER JOIN EWRporta2_widgets ON (EWRporta2_widgets.widget_id = EWRporta2_widopts.widget_id)
			ORDER BY EWRporta2_widopts.widget_id, EWRporta2_widopts.widopt_title
		', 'widopt_id');
	}

	public function updateWidopt($input)
	{
		$dw = XenForo_DataWriter::create('EWRporta2_DataWriter_Widopts');

		if (!empty($input['widopt_id']) && $widopt = $this->getWidoptById($input['widopt_id']))
		{
			$dw->setExistingData($widopt);
		}
		
		$dw->bulkSet($input);
		$dw->set('cdate', 0);
		$dw->save();
		
		return $dw->getMergedData();
	}

	public function deleteWidopt($input)
	{
		$dw = XenForo_DataWriter::create('EWRporta2_DataWriter_Widopts');
		$dw->setExistingData($input);
		$dw->delete();
		
		return true;
	}
	
	public function clearWidoptCache($input)
	{
		$dw = XenForo_DataWriter::create('EWRporta2_DataWriter_Widopts');
		$dw->setExistingData($input);
		$dw->set('cdate', 0);
		$dw->save();
		
		return true;
	}
}