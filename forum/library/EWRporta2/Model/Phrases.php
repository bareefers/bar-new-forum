<?php

class EWRporta2_Model_Phrases extends XenForo_Model
{
	public function getPhrasesByWidgetId($widgetID, $languageId = 0)
	{
		$title = 'EWRwidget_'.$widgetID;
		
		return $this->fetchAllKeyed('
			SELECT *
				FROM xf_phrase
			WHERE title LIKE ?
				AND language_id = ?
		', 'title', array($title.'%', $languageId));
	}

	public function appendPhrasesXml(DOMElement $rootNode, $widgetID)
	{
		$document = $rootNode->ownerDocument;
		$phrases = $this->getPhrasesByWidgetId($widgetID);
		
		foreach ($phrases AS $phrase)
		{
			$phraseNode = $document->createElement('phrase');
			$phraseNode->setAttribute('title', $phrase['title']);
			$phraseNode->appendChild($document->createCDATASection($phrase['phrase_text']));

			$rootNode->appendChild($phraseNode);
		}
	}
	
	public function importPhrasesXml(SimpleXMLElement $xml, $widgetID)
	{
		$existingPhrases = $this->getPhrasesByWidgetId($widgetID);
		$phrases = XenForo_Helper_DevelopmentXml::fixPhpBug50670($xml->phrase);
		
		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);
		
		foreach ($phrases AS $phrase)
		{
			$title = (string)$phrase['title'];

			$dw = XenForo_DataWriter::create('XenForo_DataWriter_Phrase');
			if (isset($existingPhrases[$title]))
			{
				$dw->setExistingData($existingPhrases[$title], true);
				unset($existingPhrases[$title]);
			}
			$dw->bulkSet(array(
				'language_id' => '0',
				'title' => $title,
				'phrase_text' => (string)$phrase
			));
			$dw->save();
		}
		
		foreach ($existingPhrases AS $phrase)
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_Phrase');
			$dw->setExistingData($phrase);
			$dw->delete();
		}

		XenForo_Db::commit($db);

		return;
	}
}