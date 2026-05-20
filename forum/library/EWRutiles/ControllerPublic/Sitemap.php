<?php

class EWRutiles_ControllerPublic_Sitemap extends XenForo_ControllerPublic_Abstract
{
	public function actionIndex()
	{
		$file = XenForo_Helper_File::getExternalDataPath().'/sitemaps/index.xml';
		echo file_get_contents($file);
		exit;
	}
}