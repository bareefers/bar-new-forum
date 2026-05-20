<?php
class DBTC_Installer
{
    protected static $table = array(
        'createQuery' => 'CREATE TABLE IF NOT EXISTS `dbtc_catalog` (             
                    `dbtc_id` INT( 11 ) NOT NULL AUTO_INCREMENT,
					`dbtc_thread_id` INT( 11 ) UNSIGNED,
					`dbtc_contributer_id` INT ( 11 ),
					`dbtc_type_id` INT( 11 ) UNSIGNED,
                    `dbtc_description` VARCHAR( 50 ),
                    `dbtc_date` INT( 11 ) UNSIGNED,
                    PRIMARY KEY (`dbtc_id`)
                    )
                ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;',
        'dropQuery' => 'DROP TABLE IF EXISTS `xf_dbtc_catalog`'
		
    );
 
    /**
    * This is the function to create a table in the database so our addon will work.
    *
    * @since Version 1.0.0
    * @version 1.0.0
    * @author Fuhrmann
    */
    public static function install()
    {
        $db = XenForo_Application::get('db');
        $db->query(self::$table['createQuery']);
    }
 
 
    /**
    * This is the function to DELETE the table of our addon in the database.
    *
    * @since Version 1.0.0
    * @version 1.0.0
    * @author Fuhrmann
    */
    public static function uninstall()
    {
        $db = XenForo_Application::get('db');
        $db->query(self::$table['dropQuery']);
    }
}
?>