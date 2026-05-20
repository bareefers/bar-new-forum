<?php

class EWRutiles_CronEntry_Sitemap
{
	public static function build()
	{
		XenForo_Model::create('EWRutiles_Model_Sitemap')->buildIndex();
	}
}