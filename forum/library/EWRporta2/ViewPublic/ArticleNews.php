<?php

class EWRporta2_ViewPublic_ArticleNews extends XenForo_ViewPublic_Base
{
	public function renderRss()
	{
		$languageModel = XenForo_Model::create('XenForo_Model_Language');
		$sitemapModel = XenForo_Model::create('EWRporta2_Model_Sitemap');
		$language = $languageModel->getLanguageById(XenForo_Application::get('options')->defaultLanguageId);
		
		echo $sitemapModel->getSitemapPreamble();
		foreach($this->_params['articles'] AS $article)
		{
			echo $sitemapModel->buildSitemapEntry($article, $language);
		}
		echo $sitemapModel->getSitemapSuffix();
		
		exit;
	}
}