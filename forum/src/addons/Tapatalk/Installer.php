<?php
class Tapatalk_Installer
{
    protected static $table = array(
    'createQuery' => 'CREATE TABLE IF NOT EXISTS `xf_tapatalk_users` (
                `userid` INT( 10 ) NOT NULL,
                `announcement` SMALLINT( 5 ) NOT NULL DEFAULT 1,
                `pm` SMALLINT( 5 ) NOT NULL DEFAULT 1,
                `subscribe` SMALLINT ( 5 ) NOT NULL DEFAULT 1,
                `quote` SMALLINT ( 5 ) NOT NULL DEFAULT 1,
                `liked` SMALLINT ( 5 ) NOT NULL DEFAULT 1,
                `tag` SMALLINT ( 5 ) NOT NULL DEFAULT 1,
                `updated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`userid`)
                )
            ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;',
    'dropQuery' => "DROP TABLE IF EXISTS `xf_tapatalk_users`",
    'dropTapatalkConnect' => "DELETE FROM xf_user_external_auth WHERE provider = 'tapatalk';"
    );

    /**
     * This is the function to create a table in the database so our addon will work.
     *
     * @since Version 1.0.0
     * @version 1.0.0
     * @author Euhow
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
     * @author Euhow
     */
    public static function uninstall()
    {
        $db = XenForo_Application::get('db');
        $db->query(self::$table['dropQuery']);
        $db->query(self::$table['dropTapatalkConnect']);
        self::dropColumnIfExist($db, 'xf_user_profile', 'tapatalk_auth_id');
    }

    public static function addColumnIfNotExist($db, $table, $field, $attr)
    {
        if ($db->fetchRow('SHOW COLUMNS FROM ' . $table . ' WHERE Field = ?', $field))
        {
            return;
        }

        return $db->query('ALTER TABLE ' . $table . ' ADD ' . $field . ' ' . $attr);
    }
    public static function dropColumnIfExist($db, $table, $field)
    {
        if ($db->fetchRow('SHOW COLUMNS FROM ' . $table . ' WHERE Field = ?', $field))
        {
            return $db->query('ALTER TABLE ' . $table . ' DROP COLUMN ' . $field);
        }
        return;
    }
}
