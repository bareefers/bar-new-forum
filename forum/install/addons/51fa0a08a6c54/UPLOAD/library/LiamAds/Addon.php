<?php

class LiamAds_Addon
{

	/**
	 * Install & uninstall SQL
	 * @var unknown
	 */
	private static $sql = array('install' => 'CREATE TABLE IF NOT EXISTS `liamads_adverts` (
			`advert_id` int(10) NOT NULL AUTO_INCREMENT,
			`advert_name` text NOT NULL,
			`advert_code` longblob NOT NULL,
			`user_criteria` blob NOT NULL,
			`page_criteria` blob NOT NULL,
			`mass_click` tinyint(1) NOT NULL,
			`advert_location` text NOT NULL,
			PRIMARY KEY (`advert_id`),
			UNIQUE KEY `advert_id` (`advert_id`)
	) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=29;', 'uninstall' => 'DROP TABLE IF EXISTS `liamads_adverts`;', 'alter_table' => 'ALTER TABLE `liamads_adverts` CHANGE `advert_code` `advert_code` LONGBLOB NOT NULL, CHANGE `user_criteria` `user_criteria` BLOB NOT NULL, CHANGE `page_criteria` `page_criteria` BLOB NOT NULL;'
	);


	public static function install($installed)
	{

		$version = is_array($installed) ? $installed['version_id'] : 0;
		if ($version == 0)
		{
			$db = XenForo_Application::getDb();
			$db->query(self::$sql['install']);
		}
		else if ($version < 3)
		{
			$db = XenForo_Application::getDb();
			$db->query(self::$sql['alter_table']);
		}
	}

	public static function uninstall()
	{
		$db = XenForo_Application::getDb();
		$db->query(self::$sql['uninstall']);

	}

	public static function hook($hookName, &$contents, array $hookParams, XenForo_Template_Abstract $template)
	{

		$adverts = XenForo_Model::create('LiamAds_Model_Adverts')->getAllAds();

		if (sizeof($adverts) <= 0)
			return;

		$group = array_reduce($adverts, function ($a, $b) {
			$a[$b['advert_location']][] = $b;
			return $a;
		});

			$showads = array();
			foreach($group as $adverts) {
				$showads[] = $adverts[mt_rand(0, count($adverts) - 1)];
			}


			foreach ($showads as $adr)
			{
				if ($adr['advert_location'] == $hookName && XenForo_Helper_Criteria::userMatchesCriteria($adr['user_criteria'], true) && XenForo_Helper_Criteria::pageMatchesCriteria($adr['page_criteria'], true, $template->getParams(), array()))
				{
					$contents .= $adr['advert_code'];
				}
			}

			// BRANDING. CAN ONLY BE REMOVED AFTER A FEE HAS BEEN PAID   //
			if ($hookName == 'page_container_breadcrumb_bottom')
			{
				$contents = "<div class=\"breadBoxBottom\"><center><a href=\"http://xenforo.com/community/resources/advertisement-manager.2053\" class=\"concealed\"</a>Advertisement Manager by Liam W</a></center></div>".$contents;
			}
			///////////////////////////////////////////////////////////////

	}
}