<?php

class EWRporta2_Install
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
		if (XenForo_Application::$versionId < 1030070)
		{
			throw new XenForo_Exception('This add-on requires XenForo 1.3.0 or higher.', true);
		}
		
		$endVersion = $addOnData['version_id'];
		$strVersion = $existingAddOn ? ($existingAddOn['version_id'] + 1) : 0;

		$install = self::getInstance();

		for ($i = $strVersion; $i <= $endVersion; $i++)
		{
			$method = '_install_'.$i;
		
			if (method_exists($install, $method))
			{
				$install->$method();
			}
		}
		
		if ($existingAddOn)
		{
			$xmlDir = XenForo_Application::getInstance()->getRootDir() . '/library/EWRporta2/Widget/XML';
			$widgetsModel = XenForo_Model::create('EWRporta2_Model_Widgets');
			$widgets = $widgetsModel->getAllWidgets();

			if ($handle = opendir($xmlDir))
			{
				while (false !== ($file = readdir($handle)))
				{
					if (stristr($file,'xml'))
					{
						$widgetID = str_ireplace('.xml', '', $file);

						if (isset($widgets[$widgetID]))
						{
							$widgetsModel->installWidget($xmlDir.'/'.$file, $widgetID, false);
						}
					}
				}
				opendir($xmlDir);
			}
		}
	}

	protected function _install_0()
	{
 		$this->_getDb()->query("
			CREATE TABLE IF NOT EXISTS `EWRporta2_articles` (
				`thread_id`				int(10) unsigned	NOT NULL,
				`article_date`			int(10) unsigned	NOT NULL,
				`article_title`			varchar(255)		NOT NULL,
				`article_break`			text				NOT NULL,
				`article_excerpt`		mediumtext			NOT NULL,
				`article_icon`			blob				NOT NULL,
				`article_custom`		int(1) unsigned		NOT NULL,
				`article_options`		blob				NOT NULL,
				`article_exclude`		int(1) unsigned		NOT NULL,
				PRIMARY KEY (`thread_id`),
				KEY `article_date` (`article_date`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;
		");
		
 		$this->_getDb()->query("
			CREATE TABLE IF NOT EXISTS `EWRporta2_authors` (
				`user_id`				int(10) unsigned	NOT NULL,
				`author_time`			int(10) unsigned	NOT NULL,
				`author_name`			varchar(100)		NOT NULL,
				`author_byline`			text				NOT NULL,
				`author_status`			varchar(255)		NOT NULL,
				`author_order`			int(10) unsigned	NOT NULL,
				PRIMARY KEY (`user_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;
		");
		
 		$this->_getDb()->query("
			CREATE TABLE IF NOT EXISTS `EWRporta2_categories` (
				`style_id`				int(10) unsigned	NOT NULL,
				`category_id`			int(10) unsigned	NOT NULL AUTO_INCREMENT,
				`category_name`			varchar(255)		NOT NULL,
				`category_desc`			text				NOT NULL,
				`category_type`			enum('category','tag')	NOT NULL,
				PRIMARY KEY (`category_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;
		");
		
 		$this->_getDb()->query("
			CREATE TABLE IF NOT EXISTS `EWRporta2_catlinks` (
				`thread_id`				int(10) unsigned	NOT NULL,
				`user_id`				int(10) unsigned	NOT NULL,
				`category_id`			int(10) unsigned	NOT NULL,
				`catlink_id`			int(10) unsigned	NOT NULL AUTO_INCREMENT,
				PRIMARY KEY (`catlink_id`),
				KEY `thread_id` (`thread_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;
		");
		
 		$this->_getDb()->query("
			CREATE TABLE IF NOT EXISTS `EWRporta2_features` (
				`thread_id`				int(10) unsigned	NOT NULL,
				`feature_date`			int(10) unsigned	NOT NULL,
				`feature_time`			int(10) unsigned	NOT NULL,
				`feature_title`			varchar(255)		NOT NULL,
				`feature_excerpt`		text				NOT NULL,
				`feature_custom`		int(1) unsigned		NOT NULL,
				`feature_exclude`		int(1) unsigned		NOT NULL,
				PRIMARY KEY (`thread_id`),
				KEY `feature_date` (`feature_date`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;
		");
		
 		$this->_getDb()->query("
			CREATE TABLE IF NOT EXISTS `EWRporta2_layouts` (
				`layout_id`				varchar(100)		NOT NULL,
				`layout_title`			varchar(100)		NOT NULL,
				`layout_template`		varchar(100)		NOT NULL,
				`layout_eval`			varchar(100)		NOT NULL,
				`layout_priority`		int(10) unsigned	NOT NULL,
				`layout_sidebar`		int(1) unsigned		NOT NULL,
				`layout_protected`		int(1) unsigned		NOT NULL,
				`active`				int(1) unsigned		NOT NULL,
				PRIMARY KEY (`layout_id`),
				KEY `layout_template` (`layout_template`),
				KEY `layout_priority` (`layout_priority`),
				KEY `layout_title` (`layout_title`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;
		");
		
 		$this->_getDb()->query("
			CREATE TABLE IF NOT EXISTS `EWRporta2_options` (
				`widget_id`				varchar(100)		NOT NULL,
				`option_id`				varchar(100)		NOT NULL,
				`option_value`			blob				NOT NULL,
				`default_value`			blob				NOT NULL,
				`title`					varchar(100)		NOT NULL,
				`explain`				text				NOT NULL,
				`edit_format`			enum('textbox','spinbox','onoff','radio','select','checkbox','template','callback') NOT NULL,
				`edit_format_params`	text				NOT NULL,
				`data_type`				enum('string','integer','numeric','array','boolean','positive_integer','unsigned_integer','unsigned_numeric') NOT NULL,
				`sub_options`			text				NOT NULL,
				`validation_class`		varchar(75)			NOT NULL,
				`validation_method`		varchar(75)			NOT NULL,
				`display_order`			int(10) unsigned	NOT NULL,
				PRIMARY KEY (`option_id`),
				KEY `widget_id` (`widget_id`),
				KEY `display_order` (`display_order`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;
		");
		
 		$this->_getDb()->query("
			CREATE TABLE IF NOT EXISTS `EWRporta2_settings` (
				`user_id`				int(10) unsigned	NOT NULL,
				`setting_filter`		mediumblob			NOT NULL,
				`setting_arrange`		mediumblob			NOT NULL,
				`setting_options`		mediumblob			NOT NULL,
				PRIMARY KEY (`user_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;
		");
		
 		$this->_getDb()->query("
			CREATE TABLE IF NOT EXISTS `EWRporta2_widgets` (
				`widget_id`				varchar(100)		NOT NULL,
				`widget_title`			varchar(100)		NOT NULL,
				`widget_desc`			text				NOT NULL,
				`widget_string`			varchar(30)			NOT NULL,
				`widget_version`		int(10) unsigned	NOT NULL,
				`widget_url`			varchar(100)		NOT NULL,
				`widget_install_class`	varchar(75)			NOT NULL,
				`widget_install_method`	varchar(75)			NOT NULL,
				`widget_uninstall_class` varchar(75)		NOT NULL,
				`widget_uninstall_method` varchar(75)		NOT NULL,
				`widget_values`			mediumblob			NOT NULL,
				`locked`				int(1) unsigned		NOT NULL,
				`display`				enum('show','hide')	NOT NULL,
				`groups`				varchar(255)		NOT NULL,
				`ctime`					varchar(100)		NOT NULL,
				`cdate`					int(10) unsigned	NOT NULL,
				`cache`					mediumblob			NOT NULL,
				`active`				int(1) unsigned		NOT NULL,
				PRIMARY KEY (`widget_id`),
				KEY `widget_title` (`widget_title`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;
		");
		
 		$this->_getDb()->query("
			CREATE TABLE IF NOT EXISTS `EWRporta2_widlinks` (
				`layout_id`				varchar(100)		NOT NULL,
				`widget_id`				varchar(100)		NOT NULL,
				`widopt_id`				int(10) unsigned	NOT NULL,
				`widlink_id`			int(10) unsigned	NOT NULL AUTO_INCREMENT,
				`widlink_title`			varchar(100)		NOT NULL,
				`widlink_position`		enum('sidebar','header','footer','left','above','below','b-left','b-right','disabled') NOT NULL,
				`widlink_order`			int(10) unsigned	NOT NULL,
				PRIMARY KEY (`widlink_id`),
				KEY `layout_id` (`layout_id`),
				KEY `widget_id` (`widget_id`),
				KEY `widlink_order` (`widlink_order`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;
		");
		
 		$this->_getDb()->query("
			CREATE TABLE IF NOT EXISTS `EWRporta2_widopts` (
				`widget_id`				varchar(100)		NOT NULL,
				`widopt_id`				int(10) unsigned	NOT NULL AUTO_INCREMENT,
				`widopt_title`			varchar(100)		NOT NULL,
				`widopt_values`			mediumblob			NOT NULL,
				`locked`				int(1) unsigned		NOT NULL,
				`display`				enum('show','hide')	NOT NULL,
				`groups`				varchar(255)		NOT NULL,
				`ctime`					varchar(100)		NOT NULL,
				`cdate`					int(10) unsigned	NOT NULL,
				`cache`					mediumblob			NOT NULL,
				PRIMARY KEY (`widopt_id`),
				KEY `widget_id` (`widget_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;
		");
		
		$this->_getDb()->query("INSERT IGNORE INTO `xf_content_type` (`content_type`, `addon_id`, `fields`) VALUES ('article', 'EWRporta2', '')");
		$this->_getDb()->query("INSERT IGNORE INTO `xf_content_type_field` (`content_type`, `field_name`, `field_value`) VALUES ('article', 'alert_handler_class', 'EWRporta2_AlertHandler_Threads')");
		XenForo_Model::create('XenForo_Model_ContentType')->rebuildContentTypeCache();

		$targetLoc = XenForo_Helper_File::getExternalDataPath()."/authors";
		if (!is_dir($targetLoc)) { mkdir($targetLoc, 0777); }

		$targetLoc = XenForo_Helper_File::getExternalDataPath()."/features";
		if (!is_dir($targetLoc)) { mkdir($targetLoc, 0777); }
		
		$xmlDir = XenForo_Application::getInstance()->getRootDir() . '/library/EWRporta2/Widget/XML';
		$widgetsModel = XenForo_Model::create('EWRporta2_Model_Widgets');
			
		$widgetsModel->installWidget($xmlDir.'/ForumStats.xml', false, false);
		$widgetsModel->installWidget($xmlDir.'/SharePage.xml', false, false);
		$widgetsModel->installWidget($xmlDir.'/StatusUpdates.xml', false, false);
		$widgetsModel->installWidget($xmlDir.'/Threads.xml', false, false);
		$widgetsModel->installWidget($xmlDir.'/UsersOnline.xml', false, false);
		
		$this->_getDb()->query("INSERT IGNORE INTO `EWRporta2_layouts` (`layout_id`, `layout_title`, `layout_template`, `layout_priority`, `layout_protected`, `active`)
			VALUES
				('article_list', 'Article List', 'EWRporta2_ArticleList', '100', '1', '1')");
		
		$this->_getDb()->query("INSERT IGNORE INTO `EWRporta2_widlinks` (`layout_id`, `widget_id`, `widlink_title`, `widlink_position`, `widlink_order`)
			VALUES
				('article_list', 'Threads', 'Recent Threads', 'above', '1'),
				('article_list', 'StatusUpdates', 'New Profile Posts', 'sidebar', '1'),
				('article_list', 'UsersOnline', 'Members Online Now', 'sidebar', '2'),
				('article_list', 'ForumStats', 'Forum Statistics', 'sidebar', '3'),
				('article_list', 'SharePage', 'Share This Page', 'sidebar', '4')");
	}
	
	protected function _install_1()
	{
		$this->addColumnIfNotExist("EWRporta2_layouts", "layout_eval", "varchar(100) NOT NULL");
		
		$this->dropColumnIfExist("EWRporta2_layouts", "layout_param");
		$this->dropColumnIfExist("EWRporta2_layouts", "layout_key");
		$this->dropColumnIfExist("EWRporta2_layouts", "layout_node");
	}
	
	protected function _install_4()
	{
		$this->addColumnIfNotExist("EWRporta2_authors", "author_time", "int(10) unsigned NOT NULL");
		$this->addColumnIfNotExist("EWRporta2_features", "feature_time", "int(10) unsigned NOT NULL");
		$this->addColumnIfNotExist("EWRporta2_layouts", "layout_sidebar", "int(1) unsigned NOT NULL");
	}
	
	protected function _install_7()
	{
		$this->addColumnIfNotExist("EWRporta2_articles", "article_exclude", "int(1) unsigned NOT NULL");
		$this->addColumnIfNotExist("EWRporta2_features", "feature_exclude", "int(1) unsigned NOT NULL");
	}

	public static function uninstallCode()
	{
		$uninstall = self::getInstance();
		$uninstall->_uninstall_0();
	}

	protected function _uninstall_0()
	{
		$widgets = XenForo_Model::create('EWRporta2_Model_Widgets')->getAllWidgets();

		foreach ($widgets AS $widget)
		{
			XenForo_Model::create('EWRporta2_Model_Widgets')->deleteWidget($widget, false);
		}
		
 		$this->_getDb()->query("
			DROP TABLE IF EXISTS
				`EWRporta2_articles`,
				`EWRporta2_authors`,
				`EWRporta2_categories`,
				`EWRporta2_catlinks`,
				`EWRporta2_features`,
				`EWRporta2_layouts`,
				`EWRporta2_options`,
				`EWRporta2_settings`,
				`EWRporta2_widgets`,
				`EWRporta2_widlinks`,
				`EWRporta2_widopts`
		");

		$targetLoc = glob(XenForo_Helper_File::getExternalDataPath()."/authors/*.jpg");
		foreach ($targetLoc AS $file) { unlink($file); }

		$targetLoc = XenForo_Helper_File::getExternalDataPath()."/authors";
		if (is_dir($targetLoc)) { rmdir($targetLoc); }

		$targetLoc = glob(XenForo_Helper_File::getExternalDataPath()."/features/*.jpg");
		foreach ($targetLoc AS $file) { unlink($file); }

		$targetLoc = XenForo_Helper_File::getExternalDataPath()."/features";
		if (is_dir($targetLoc)) { rmdir($targetLoc); }
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