<?php

class EWRatendo_Install
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
			CREATE TABLE IF NOT EXISTS `EWRatendo_events` (
				`thread_id`				int(10) unsigned						NOT NULL,
				`user_id`				int(10)									NOT NULL,
				`username`				varchar(50)								NOT NULL,
				`event_id`				int(10) unsigned						NOT NULL AUTO_INCREMENT,
				`event_strtime`			int(10) unsigned						NOT NULL,
				`event_endtime`			int(10) unsigned						NOT NULL,
				`event_timezone`		varchar(50)								NOT NULL,
				`event_title`			varchar(255)							NOT NULL,
				`event_venue`			varchar(255)							NOT NULL,
				`event_address`			varchar(255)							NOT NULL,
				`event_citystate`		varchar(255)							NOT NULL,
				`event_zipcode`			varchar(255)							NOT NULL,
				`event_rsvp`			int(1) unsigned							NOT NULL DEFAULT '1',
				`event_guests`			int(5) unsigned							NOT NULL DEFAULT '0',
				`event_recur_count`		int(5) unsigned							NOT NULL DEFAULT '0',
				`event_recur_units`		enum('none','days','weeks','months')	NOT NULL DEFAULT 'none',
				`event_recur_expire`	int(10) unsigned						NOT NULL DEFAULT '0',
				`event_state`			enum('visible','moderated','deleted')	NOT NULL DEFAULT 'visible',
				PRIMARY KEY (`event_id`)
			) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
		");
		
		$this->addColumnIfNotExist("EWRatendo_events", "event_recur_count", "int(5) unsigned NOT NULL DEFAULT '0'");
		$this->addColumnIfNotExist("EWRatendo_events", "event_recur_units", "enum('none','days','weeks','months') NOT NULL DEFAULT 'none'");
		$this->addColumnIfNotExist("EWRatendo_events", "event_recur_expire", "int(10) unsigned NOT NULL DEFAULT '0'");
		$this->addColumnIfNotExist("EWRatendo_events", "event_description", "MEDIUMTEXT NOT NULL");
		$this->addColumnIfNotExist("EWRatendo_events", "event_state", "enum('visible','moderated','deleted') NOT NULL DEFAULT 'visible'");
		$this->addColumnIfNotExist("EWRatendo_events", "event_zipcode", "varchar(255) NOT NULL AFTER event_citystate");
		
 		$this->_getDb()->query("
			CREATE TABLE IF NOT EXISTS `EWRatendo_recurs` (
				`event_id`				int(10) unsigned		NOT NULL,
				`event_strtime`			int(10) unsigned		NOT NULL,
				`event_endtime`			int(10) unsigned		NOT NULL,
				INDEX (`event_id`)
			) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
		");
		
 		$this->_getDb()->query("
			CREATE TABLE IF NOT EXISTS `EWRatendo_rsvps` (
				`event_id`				int(10) unsigned			NOT NULL,
				`user_id`				int(10) unsigned			NOT NULL,
				`rsvp_id`				int(10) unsigned			NOT NULL AUTO_INCREMENT,
				`rsvp_state`			enum('yes','maybe','no')	NOT NULL DEFAULT 'no',
				`rsvp_date`				int(10) unsigned			NOT NULL DEFAULT '0',
				`rsvp_guests`			int(10) unsigned			NOT NULL DEFAULT '0',
				`rsvp_message`			text						NOT NULL,
				PRIMARY KEY (`rsvp_id`),
				UNIQUE KEY `UNIQUE` (`event_id`,`user_id`),
				INDEX (`event_id`)
			) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
		");

		$this->_getDb()->query("INSERT IGNORE INTO `xf_content_type` (`content_type`, `addon_id`, `fields`) VALUES ('event', 'EWRatendo', '')");
		$this->_getDb()->query("INSERT IGNORE INTO `xf_content_type_field` (`content_type`, `field_name`, `field_value`) VALUES ('event', 'moderation_queue_handler_class', 'EWRatendo_ModerationQueueHandler_Event')");
		XenForo_Model::create('XenForo_Model_ContentType')->rebuildContentTypeCache();
	}
	
	protected function _install_50()
	{
 		$this->_getDb()->query("DROP TABLE IF EXISTS `EWRatendo_services`");
		
		$this->dropColumnIfExist("EWRatendo_events", "service_id");
		$this->dropColumnIfExist("EWRatendo_events", "service_value");
		$this->dropColumnIfExist("EWRatendo_events", "service_value2");
		$this->dropColumnIfExist("EWRatendo_events", "event_stream");
		$this->dropColumnIfExist("EWRatendo_events", "event_thumb");
		$this->dropColumnIfExist("EWRatendo_events", "event_247");
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
				`EWRatendo_events`,
				`EWRatendo_recurs`,
				`EWRatendo_rsvps`
		");

		$this->_getDb()->query("DELETE IGNORE FROM `xf_content_type` WHERE content_type = 'event'");
		$this->_getDb()->query("DELETE IGNORE FROM `xf_content_type_field` WHERE content_type = 'event'");
		XenForo_Model::create('XenForo_Model_ContentType')->rebuildContentTypeCache();
	}

	public function addColumnIfNotExist($table, $field, $attr)
	{
		if ($this->_getDb()->fetchRow('SHOW columns FROM `'.$table.'` WHERE Field = ?', $field))
		{
			return false;
		}
		
		return $this->_getDb()->query("ALTER TABLE `".$table."` ADD `".$field."` ".$attr);
	}

	public function dropColumnIfExist($table, $field)
	{
		if ($this->_getDb()->fetchRow('SHOW columns FROM `'.$table.'` WHERE Field = ?', $field))
		{
			return $this->_getDb()->query("ALTER TABLE `".$table."` DROP COLUMN `".$field."`");
		}
		
		return false;
	}
}