<?php

class EWRporta2_Model_Listeners extends XenForo_Model
{
	public function getListenersByWidgetId($widgetID)
	{
		$title = 'EWRwidget_'.$widgetID;
		
		return $this->_getDb()->fetchAll('
			SELECT *
				FROM xf_code_event_listener
			WHERE description LIKE ?
		', $title.'%');
	}

	public function appendListenersXml(DOMElement $rootNode, $widgetID)
	{
		$document = $rootNode->ownerDocument;

		$listeners = $this->getListenersByWidgetId($widgetID);
		foreach ($listeners AS $listener)
		{
			$listenerNode = $document->createElement('listener');
			$listenerNode->setAttribute('description', $listener['description']);
			$listenerNode->setAttribute('event_id', $listener['event_id']);
			$listenerNode->setAttribute('execute_order', $listener['execute_order']);
			$listenerNode->setAttribute('callback_class', $listener['callback_class']);
			$listenerNode->setAttribute('callback_method', $listener['callback_method']);
			$listenerNode->setAttribute('active', $listener['active']);

			$rootNode->appendChild($listenerNode);
		}
	}
	
	public function importListenersXml(SimpleXMLElement $xml, $widgetID)
	{
		$listeners = XenForo_Helper_DevelopmentXml::fixPhpBug50670($xml->listener);
		
		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);
		$db->delete('xf_code_event_listener', 'description LIKE ' . $db->quote('EWRwidget_'.$widgetID.'%'));
		
		foreach ($listeners AS $event)
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_CodeEventListener');
			$dw->setOption(XenForo_DataWriter_CodeEventListener::OPTION_REBUILD_CACHE, false);
			$dw->bulkSet(array(
				'event_id' => (string)$event['event_id'],
				'execute_order' => (string)$event['execute_order'],
				'callback_class' => (string)$event['callback_class'],
				'callback_method' => (string)$event['callback_method'],
				'active' => (string)$event['active'],
				'description' => (string)$event['description'],
				'hint' => (string)$event['hint']
			));
			$dw->save();
		}

		$this->getModelFromCache('XenForo_Model_CodeEvent')->rebuildEventListenerCache();
		XenForo_Db::commit($db);

		return;
	}
}