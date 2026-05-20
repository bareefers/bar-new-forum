<?php

class EWRporta2_Model_Options extends XenForo_Model
{
	public function getOptionById($optionID)
	{
		if (!$option = $this->_getDb()->fetchRow("
			SELECT *
				FROM EWRporta2_options
			WHERE option_id = ?
		", $optionID))
		{
			return false;
		}

		return $option;
	}
	
	public function getOptionsByIds($keys)
	{
		return $this->_getDb()->fetchAll("
			SELECT *
				FROM EWRporta2_options
			WHERE option_id IN (" . $this->_getDb()->quote($keys) . ")
		");
	}
	
	public function getOptionsByWidgetId($widgetID)
	{
		return $this->fetchAllKeyed('
			SELECT *
				FROM EWRporta2_options
			WHERE widget_id = ?
			ORDER by display_order
		', 'option_id', $widgetID);
	}
	
	public function getOptionsByWidopt($widopt)
	{
		$options = $this->getOptionsByWidgetId($widopt['widget_id']);
		$values = @unserialize($widopt['widopt_values']);
		return $this->replaceOptions($values, $options);
	}
	
	public function replaceOptions($values, $options)
	{
		foreach ($values AS $key => $value)
		{
			if (!empty($options[$key]))
			{
				if ($options[$key]['data_type'] == 'array')
				{
					$value = @serialize($value);
				}
				
				$options[$key]['option_value'] = $value;
			}
		}
		
		return $options;
	}

	public function prepareOptions($options)
	{
		foreach ($options AS &$option)
		{
			$option['formatParams'] = $this->getModelFromCache('XenForo_Model_Option')->prepareOptionFormatParams($option['edit_format'], $option['edit_format_params']);
			
			if ($option['data_type'] == 'array')
			{
				$option['option_value'] = @unserialize($option['option_value']);
				if (!is_array($option['option_value']))
				{
					$option['option_value'] = array();
				}
			}
		}

		return $options;
	}

	public function updateOption($input, $original)
	{
		$dw = XenForo_DataWriter::create('EWRporta2_DataWriter_Options');

		if ($original)
		{
			$dw->setExistingData($original);
		}
		
		$dw->bulkSet($input);
		$dw->save();
		
		return $dw->getMergedData();
	}

	public function deleteOption($input)
	{
		$dw = XenForo_DataWriter::create('EWRporta2_DataWriter_Options');
		$dw->setExistingData($input);
		$dw->delete();
		
		return true;
	}
	
	public function updateOptions(array $options, $widgetID)
	{
		if (empty($options)) { return true; }
	
		$dbOptions = $this->getOptionsByIds(array_keys($options));

		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		foreach ($dbOptions AS $dbOption)
		{
			$newValue = $options[$dbOption['option_id']];

			$dw = XenForo_DataWriter::create('EWRporta2_DataWriter_Options');
			$dw->setExistingData($dbOption, true);
			$dw->setOption(XenForo_DataWriter_Option::OPTION_REBUILD_CACHE, false);
			if ($dw->get('data_type') == 'array' && !is_array($newValue))
			{
				$newValue = array();
			}
			$dw->set('option_value', $newValue);
			$dw->save();
		}
		
		$this->rebuildOptionCache($widgetID);
		XenForo_Db::commit($db);
		
		return true;
	}
	
	public function rebuildOptionCache($widgetID)
	{
		$options = $this->getOptionsByWidgetId($widgetID);
		$optionArray = array();
		
		foreach ($options AS $option)
		{
			if ($option['data_type'] == 'array')
			{
				$optionArray[$option['option_id']] = @unserialize($option['option_value']);
				if (!is_array($optionArray[$option['option_id']]))
				{
					$optionArray[$option['option_id']] = array();
				}
			}
			else
			{
				$optionArray[$option['option_id']] = $option['option_value'];
			}
		}
		
		$dw = XenForo_DataWriter::create('EWRporta2_DataWriter_Widgets');
		$dw->setExistingData(array('widget_id' => $widgetID));
		$dw->set('widget_values', $optionArray);
		$dw->save();
		
		return true;
	}
	
	public function updateOptionOrders($orders)
	{
		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);
		
		foreach ($orders AS $key => $value)
		{
			$dw = XenForo_DataWriter::create('EWRporta2_DataWriter_Options');
			$dw->setExistingData($key, true);
			$dw->setOption(XenForo_DataWriter_Option::OPTION_REBUILD_CACHE, false);
			$dw->set('display_order', $value);
			$dw->save();
		}
		
		XenForo_Db::commit($db);
		
		return true;
	}

	public function appendOptionsXml(DOMElement $rootNode, $widgetID)
	{
		$document = $rootNode->ownerDocument;
		$options = $this->getOptionsByWidgetId($widgetID);
		
		foreach ($options AS $option)
		{
			$optionNode = $document->createElement('option');
			$optionNode->setAttribute('option_id', $option['option_id']);
			$optionNode->setAttribute('edit_format', $option['edit_format']);
			$optionNode->setAttribute('data_type', $option['data_type']);
			$optionNode->setAttribute('display_order', $option['display_order']);

			if ($option['validation_class'])
			{
				$optionNode->setAttribute('validation_class', $option['validation_class']);
				$optionNode->setAttribute('validation_method', $option['validation_method']);
			}

			XenForo_Helper_DevelopmentXml::createDomElements($optionNode, array(
				'default_value' => str_replace("\r\n", "\n", $option['default_value']),
				'edit_format_params' => str_replace("\r\n", "\n", $option['edit_format_params']),
				'sub_options' => str_replace("\r\n", "\n", $option['sub_options']),
				'title' => str_replace("\r\n", "\n", $option['title'])
			));

			$explainNode = $optionNode->appendChild($document->createElement('explain'));
			$explainNode->appendChild($document->createCDATASection($option['explain']));

			$rootNode->appendChild($optionNode);
		}
	}
	
	public function importOptionsXml(SimpleXMLElement $xml, $widgetID)
	{
		$existingOptions = $this->getOptionsByWidgetId($widgetID);
		$options = XenForo_Helper_DevelopmentXml::fixPhpBug50670($xml->option);
		
		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);
		
		foreach ($options AS $option)
		{
			$optionId = (string)$option['option_id'];

			$dw = XenForo_DataWriter::create('EWRporta2_DataWriter_Options');
			if (isset($existingOptions[$optionId]))
			{
				$dw->setExistingData($existingOptions[$optionId], true);
				unset($existingOptions[$optionId]);
			}
			$dw->setOption(XenForo_DataWriter_Option::OPTION_REBUILD_CACHE, false);
			$dw->bulkSet(array(
				'widget_id' => $widgetID,
				'option_id' => $optionId,
				'default_value' => (string)$option->default_value,
				'title' => (string)$option->title,
				'explain' => (string)$option->explain,
				'edit_format' => (string)$option['edit_format'],
				'edit_format_params' => (string)$option->edit_format_params,
				'data_type' => (string)$option['data_type'],
				'sub_options' => (string)$option->sub_options,
				'validation_class' => (string)$option['validation_class'],
				'validation_method' => (string)$option['validation_method'],
				'display_order' => (int)$option['display_order'],
			));
			$dw->save();
		}
		
		foreach ($existingOptions AS $option)
		{
			$dw = XenForo_DataWriter::create('EWRporta2_DataWriter_Options');
			$dw->setExistingData($option);
			$dw->delete();
		}

		$this->rebuildOptionCache($widgetID);
		XenForo_Db::commit($db);

		return;
	}
}