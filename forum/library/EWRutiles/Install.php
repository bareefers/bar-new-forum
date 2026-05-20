<?php

class EWRutiles_Install
{
	private static $_instance;
	protected $_db;

	public static final function getInstance()
	{
		if (!self::$_instance)
		{
			self::$_instance = new self;
		}

		return self::$_instance;
	}

	protected function _getDb()
	{
		if ($this->_db === null)
		{
			$this->_db = XenForo_Application::get('db');
		}

		return $this->_db;
	}

	public static function installCode($existingAddOn, $addOnData)
	{
		$endVersion = $addOnData['version_id'];
		$strVersion = $existingAddOn ? ($existingAddOn['version_id'] + 1) : 1;

		$install = self::getInstance();

		for ($i = $strVersion; $i <= $endVersion; $i++)
		{
			$method = '_install_'.$i;

			if (method_exists($install, $method))
			{
				$install->$method();
			}
		}
	}
	
	protected function _install_1()
	{
 		$this->_getDb()->query("
			CREATE TABLE IF NOT EXISTS `EWRutiles_downvotes` (
				`thread_id`					int(10) unsigned	NOT NULL,
				`user_id`					int(10) unsigned	NOT NULL,
				`vote_id`					int(10) unsigned	NOT NULL AUTO_INCREMENT,
				`vote_date`					int(10) unsigned	NOT NULL,
				PRIMARY KEY (`vote_id`),
				UNIQUE KEY `UNIQUE` (`thread_id`,`user_id`),
				KEY `thread_id` (`thread_id`)
			) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
		");

 		$this->_getDb()->query("DROP TABLE IF EXISTS `EWRutiles_reglogs`");
 		$this->_getDb()->query("
			CREATE TABLE IF NOT EXISTS `EWRutiles_spamlogs` (
				`spamlog_id`				int(10) unsigned	NOT NULL AUTO_INCREMENT,
				`spamlog_date`				int(10) unsigned	NOT NULL,
				`spamlog_engine`			varchar(20)			NOT NULL,
				`spamlog_username`			varchar(50)			NOT NULL,
				`spamlog_username_match`	int(1) unsigned		NOT NULL,
				`spamlog_email`				varchar(120)		NOT NULL,
				`spamlog_email_match`		int(1) unsigned		NOT NULL,
				`spamlog_ip`				varchar(15)			NOT NULL,
				`spamlog_ip_match`			int(1) unsigned		NOT NULL,
				`spamlog_time`				int(5) unsigned		NOT NULL,
				PRIMARY KEY (`spamlog_id`)
			) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
		");

		$targetLoc = XenForo_Helper_File::getExternalDataPath()."/sitemaps";
		if (!is_dir($targetLoc)) { mkdir($targetLoc, 0777); }
	}
	
	protected function _install_22()
	{
		self::addColumnIfNotExist($this->_getDb(), "EWRutiles_spamlogs", "spamlog_time", "int(5) unsigned NOT NULL");
				
		$this->_getDb()->query("
			CREATE TABLE IF NOT EXISTS `EWRutiles_usernames` (
				`username_id` 				int(10) unsigned 	NOT NULL AUTO_INCREMENT,
				`user_id` 					int(10) unsigned 	NOT NULL,
				`username_date` 			int(10) unsigned 	NOT NULL,
				`username_old` 				varchar(50) 		NOT NULL,
				`username_new` 				varchar(50) 		NOT NULL,
				`username_self` 			int(1) unsigned 	NOT NULL,
				PRIMARY KEY (`username_id`),
				KEY `user_id` (`user_id`)
			) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
		");
	}
	
	public static function uninstallCode()
	{
		$uninstall = self::getInstance();
		$uninstall->_uninstall_0();
	}

	protected function _uninstall_0()
	{
 		$this->_getDb()->query("
			DROP TABLE IF EXISTS
				`EWRutiles_downvotes`,
				`EWRutiles_spamlogs`,
				`EWRutiles_usernames`
		");


		$targetLoc = glob(XenForo_Helper_File::getExternalDataPath()."/sitemaps/*.xml");
		foreach ($targetLoc AS $file) { unlink($file); }

		$targetLoc = XenForo_Helper_File::getExternalDataPath()."/sitemaps";
		if (is_dir($targetLoc)) { rmdir($targetLoc); }
	}

	public static function addColumnIfNotExist($db, $table, $field, $attr)
	{
		if ($db->fetchRow('SHOW columns FROM `'.$table.'` WHERE Field = ?', $field))
		{
			return false;
		}
		
		return $db->query("ALTER TABLE `".$table."` ADD `".$field."` ".$attr);
	}
}