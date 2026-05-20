<?php

class EWRporta2_Model_Templates extends XenForo_Model
{
	public function getTemplatesByWidgetId($widgetID, $styleId = 0)
	{
		$title = 'EWRwidget_'.$widgetID;

		return $this->fetchAllKeyed('
			SELECT *
				FROM xf_template
			WHERE title LIKE ?
				AND style_id = ?
		', 'title', array($title.'%', $styleId));
	}

	public function appendTemplatesXml(DOMElement $rootNode, $widgetID)
	{
		$document = $rootNode->ownerDocument;
		$templates = $this->getTemplatesByWidgetId($widgetID);
		
		foreach ($templates AS $template)
		{
			$templateNode = $document->createElement('template');
			$templateNode->setAttribute('title', $template['title']);
			$templateNode->appendChild(XenForo_Helper_DevelopmentXml::createDomCdataSection($document, $template['template']));

			$rootNode->appendChild($templateNode);
		}
	}
	
	public function importTemplatesXml(SimpleXMLElement $xml, $widgetID)
	{
		$existingTemplates = $this->getTemplatesByWidgetId($widgetID);
		$templates = XenForo_Helper_DevelopmentXml::fixPhpBug50670($xml->template);
		
		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		foreach ($templates AS $template)
		{
			$title = (string)$template['title'];

			$dw = XenForo_DataWriter::create('XenForo_DataWriter_Template');
			if (isset($existingTemplates[$title]))
			{
				$dw->setExistingData($existingTemplates[$title], true);
				unset($existingTemplates[$title]);
			}
			$dw->setOption(XenForo_DataWriter_Template::OPTION_DEV_OUTPUT_DIR, '');
			$dw->setOption(XenForo_DataWriter_Template::OPTION_FULL_COMPILE, false);
			$dw->setOption(XenForo_DataWriter_Template::OPTION_TEST_COMPILE, false);
			$dw->setOption(XenForo_DataWriter_Template::OPTION_CHECK_DUPLICATE, false);
			$dw->setOption(XenForo_DataWriter_Template::OPTION_REBUILD_TEMPLATE_MAP, false);
			$dw->bulkSet(array(
				'style_id' => '0',
				'title' => $title,
				'template' => XenForo_Helper_DevelopmentXml::processSimpleXmlCdata($template),
			));
			$dw->save();
		}
		
		foreach ($existingTemplates AS $template)
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_Template');
			$dw->setExistingData($template);
			$dw->setOption(XenForo_DataWriter_Template::OPTION_DEV_OUTPUT_DIR, '');
			$dw->setOption(XenForo_DataWriter_Template::OPTION_FULL_COMPILE, false);
			$dw->setOption(XenForo_DataWriter_Template::OPTION_TEST_COMPILE, false);
			$dw->setOption(XenForo_DataWriter_Template::OPTION_CHECK_DUPLICATE, false);
			$dw->setOption(XenForo_DataWriter_Template::OPTION_REBUILD_TEMPLATE_MAP, false);
			$dw->delete();
		}

		XenForo_Db::commit($db);

		return;
	}
}