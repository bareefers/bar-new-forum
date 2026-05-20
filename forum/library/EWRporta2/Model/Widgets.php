<?php

class EWRporta2_Model_Widgets extends XenForo_Model
{
	public function getWidgetOptionsList()
	{
		$options = array();
		$widgets = $this->getAllWidgets();
		
		foreach ($widgets AS $widget)
		{
			$options[$widget['widget_id']] = $widget['widget_title'];
		}

		return $options;
	}

	public function getAllWidgets()
	{
		return $this->fetchAllKeyed('
			SELECT *
				FROM EWRporta2_widgets
			ORDER BY widget_title
		', 'widget_id');
	}
	
	public function getWidgetById($widgetID)
	{
		if (!$widget = $this->_getDb()->fetchRow("
			SELECT * FROM EWRporta2_widgets WHERE widget_id = ?
		", $widgetID))
		{
			return false;
		}
		
		$widget['options'] = @unserialize($widget['widget_values']);

		return $widget;
	}

	public function updateWidget($input, $original)
	{
		$dw = XenForo_DataWriter::create('EWRporta2_DataWriter_Widgets');

		if ($original)
		{
			$dw->setExistingData($original);
		}
		
		$dw->bulkSet($input);
		$dw->set('cdate', 0);
		$dw->save();
		
		return $dw->getMergedData();
	}

	public function deleteWidget($input, $rebuild = true)
	{
		$dw = XenForo_DataWriter::create('EWRporta2_DataWriter_Widgets');
		$dw->setExistingData($input);
		$dw->delete();
		
		if ($rebuild)
		{
			XenForo_Application::defer('Atomic', array('simple' => array('Phrase', 'TemplateReparse', 'Template')), 'widgetRebuild', true);
		}
		
		return true;
	}
	
	public function toggleWidget($widgetID, $toggle)
	{
		$dw = XenForo_DataWriter::create('EWRporta2_DataWriter_Widgets');
		$dw->setExistingData($widgetID);
		$dw->set('active', $toggle);
		$dw->set('cdate', 0);
		$dw->save();
		
		return true;
	}
	
	public function clearWidgetCache($input)
	{
		$dw = XenForo_DataWriter::create('EWRporta2_DataWriter_Widgets');
		$dw->setExistingData($input);
		$dw->set('cdate', 0);
		$dw->save();
		
		$db = $this->_getDb();
		$db->update('EWRporta2_widopts', array('cdate' => 0), 'widget_id = ' . $db->quote($input['widget_id']));
		
		return true;
	}
	
	public function exportWidget($widget)
	{
		$document = new DOMDocument('1.0', 'utf-8');
		$document->formatOutput = true;

		$rootNode = $document->createElement('widget');
		$rootNode->setAttribute('widget_id', $widget['widget_id']);
		$rootNode->setAttribute('widget_title', $widget['widget_title']);
		$rootNode->setAttribute('widget_desc', $widget['widget_desc']);
		$rootNode->setAttribute('widget_string', $widget['widget_string']);
		$rootNode->setAttribute('widget_version', $widget['widget_version']);
		$rootNode->setAttribute('widget_url', $widget['widget_url']);
		$rootNode->setAttribute('widget_install_class', $widget['widget_install_class']);
		$rootNode->setAttribute('widget_install_method', $widget['widget_install_method']);
		$rootNode->setAttribute('widget_uninstall_class', $widget['widget_uninstall_class']);
		$rootNode->setAttribute('widget_uninstall_method', $widget['widget_uninstall_method']);
		$rootNode->setAttribute('ctime', $widget['ctime']);
		$document->appendChild($rootNode);
		
		$dataNode = $rootNode->appendChild($document->createElement('listeners'));
		$this->getModelFromCache('EWRporta2_Model_Listeners')->appendListenersXml($dataNode, $widget['widget_id']);
		
		$dataNode = $rootNode->appendChild($document->createElement('options'));
		$this->getModelFromCache('EWRporta2_Model_Options')->appendOptionsXml($dataNode, $widget['widget_id']);
		
		$dataNode = $rootNode->appendChild($document->createElement('phrases'));
		$this->getModelFromCache('EWRporta2_Model_Phrases')->appendPhrasesXml($dataNode, $widget['widget_id']);
		
		$dataNode = $rootNode->appendChild($document->createElement('templates'));
		$this->getModelFromCache('EWRporta2_Model_Templates')->appendTemplatesXml($dataNode, $widget['widget_id']);
		
		return $document;
	}
	
	public function installWidget($fileName, $widgetID = false, $rebuild = true)
	{
		if (!file_exists($fileName) || !is_readable($fileName))
		{
			throw new XenForo_Exception(new XenForo_Phrase('please_enter_valid_file_name_requested_file_not_read'), true);
		}

		try
		{
			$document = new SimpleXMLElement($fileName, 0, true);
		}
		catch (Exception $e)
		{
			throw new XenForo_Exception(new XenForo_Phrase('provided_file_was_not_valid_xml_file'), true);
		}

		$widget = $this->installWidgetXml($document, $widgetID);
		
		if ($rebuild)
		{
			XenForo_Application::defer('Atomic', array('simple' => array('Phrase', 'TemplateReparse', 'Template')), 'widgetRebuild', true);
		}
		
		return $widget;
	}
	
	public function installWidgetXml(SimpleXMLElement $xml, $widgetID = false)
	{
		if ($xml->getName() != 'widget')
		{
			throw new XenForo_Exception(new XenForo_Phrase('porta2_provided_file_is_not_a_widget_xml_file'), true);
		}

		$widgetData = array(
			'widget_id' => (string)$xml['widget_id'],
			'widget_title' => (string)$xml['widget_title'],
			'widget_desc' => (string)$xml['widget_desc'],
			'widget_string' => (string)$xml['widget_string'],
			'widget_version' => (int)$xml['widget_version'],
			'widget_url' => (string)$xml['widget_url'],
			'widget_install_class' => (string)$xml['widget_install_class'],
			'widget_install_method' => (string)$xml['widget_install_method'],
			'widget_uninstall_class' => (string)$xml['widget_uninstall_class'],
			'widget_uninstall_method' => (string)$xml['widget_uninstall_method'],
			'ctime' => (string)$xml['ctime'],
		);

		$existingWidget = $this->verifyWidgetInstallable($widgetData, $widgetID);

		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		if ($widgetData['widget_install_class'] && $widgetData['widget_install_method'])
		{
			call_user_func(
				array($widgetData['widget_install_class'], $widgetData['widget_install_method']),
				$existingWidget,
				$widgetData,
				$xml
			);
		}

		$dw = XenForo_DataWriter::create('EWRporta2_DataWriter_Widgets');
		if ($existingWidget)
		{
			$dw->setExistingData($existingWidget, true);
		}
		$dw->bulkSet($widgetData);
		$dw->save();

		$this->getModelFromCache('EWRporta2_Model_Listeners')->importListenersXml($xml->listeners, $widgetData['widget_id']);
		$this->getModelFromCache('EWRporta2_Model_Options')->importOptionsXml($xml->options, $widgetData['widget_id']);
		$this->getModelFromCache('EWRporta2_Model_Phrases')->importPhrasesXml($xml->phrases, $widgetData['widget_id']);
		$this->getModelFromCache('EWRporta2_Model_Templates')->importTemplatesXml($xml->templates, $widgetData['widget_id']);

		XenForo_Db::commit($db);
		
		return $dw->getMergedData();
	}
	
	public function verifyWidgetInstallable($widgetData, $widgetID)
	{
		if (empty($widgetData['widget_id']))
		{
			throw new XenForo_Exception(new XenForo_Phrase('porta2_widget_xml_does_not_specify_valid_widget_id_and_cannot_be_installed'), true);
		}
		
		$existingWidget = $this->getWidgetById($widgetData['widget_id']);
		if ($existingWidget)
		{
			if ($widgetID === false)
			{
				throw new XenForo_Exception(new XenForo_Phrase('porta2_specified_widget_is_already_installed'), true);
			}
			else if ($existingWidget['widget_id'] != $widgetID)
			{
				throw new XenForo_Exception(new XenForo_Phrase('porta2_specified_widget_does_not_match_widget_you_chose_to_upgrade'), true);
			}

			if ($widgetData['widget_version'] < $existingWidget['widget_version'])
			{
				throw new XenForo_Exception(new XenForo_Phrase('porta2_specified_widget_is_older_than_install_version'), true);
			}
		}

		if ($widgetID !== false && !$existingWidget)
		{
			throw new XenForo_Exception(new XenForo_Phrase('porta2_specified_widget_does_not_match_widget_you_chose_to_upgrade'), true);
		}

		if ($widgetData['widget_install_class'] && $widgetData['widget_install_method'])
		{
			if (!XenForo_Application::autoload($widgetData['widget_install_class'])
				|| !method_exists($widgetData['widget_install_class'], $widgetData['widget_install_method'])
			)
			{
				throw new XenForo_Exception(new XenForo_Phrase('porta2_files_associated_with_widget_not_found'), true);
			}
		}
		if ($widgetData['widget_uninstall_class'] && $widgetData['widget_uninstall_method'])
		{
			if (!XenForo_Application::autoload($widgetData['widget_uninstall_class'])
				|| !method_exists($widgetData['widget_uninstall_class'], $widgetData['widget_uninstall_method'])
			)
			{
				throw new XenForo_Exception(new XenForo_Phrase('porta2_files_associated_with_widget_not_found'), true);
			}
		}

		return $existingWidget;
	}
}