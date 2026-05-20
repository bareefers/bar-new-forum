<?php

class EWRporta2_Model_Layouts extends XenForo_Model
{
	public function getAllLayouts()
	{
		return $this->fetchAllKeyed('
			SELECT *
				FROM EWRporta2_layouts
			ORDER BY layout_title
		', 'layout_id');
	}
	
	public function getLayoutById($layoutID)
	{
		if (!$layout = $this->_getDb()->fetchRow("
			SELECT * FROM EWRporta2_layouts WHERE layout_id = ?
		", $layoutID))
		{
			return false;
		}

		return $layout;
	}
	
	public function getLayoutByTemplate($template, $fbTemplate = false)
	{
		if (!$layout = $this->_getDb()->fetchAll("
			SELECT *
				FROM EWRporta2_layouts
			WHERE (layout_template = ?
				" . ($fbTemplate ? "OR layout_template = " . $this->_getDb()->quote($fbTemplate) : '') . ")
				AND active = 1
			ORDER BY layout_priority ASC
		", $template))
		{
			return false;
		}

		return $layout;
	}

	public function updateLayout($input, $original)
	{
		$dw = XenForo_DataWriter::create('EWRporta2_DataWriter_Layouts');

		if (!empty($original) && $layout = $this->getLayoutById($original))
		{
			if ($layout['layout_protected'])
			{
				throw new XenForo_Exception(new XenForo_Phrase('porta2_protected_layout_can_not_be_edited'), true);
			}
			
			$dw->setExistingData($layout);
		}
		
		$dw->bulkSet($input);
		$dw->save();
		
		return $dw->getMergedData();
	}

	public function deleteLayout($input)
	{
		if ($input['layout_protected'])
		{
			throw new XenForo_Exception(new XenForo_Phrase('porta2_protected_layout_can_not_be_edited'), true);
		}
		
		$dw = XenForo_DataWriter::create('EWRporta2_DataWriter_Layouts');
		$dw->setExistingData($input);
		$dw->delete();
		
		return true;
	}
	
	public function toggleLayout($layoutID, $toggle)
	{
		$dw = XenForo_DataWriter::create('EWRporta2_DataWriter_Layouts');
		$dw->setExistingData($layoutID);
		$dw->set('active', $toggle);
		$dw->save();
		
		return true;
	}
}