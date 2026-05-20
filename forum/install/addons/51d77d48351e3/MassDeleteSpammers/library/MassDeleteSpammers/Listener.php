<?php
class MassDeleteSpammers_Listener
{
    public static function load_class_controller($class, array &$extend)
    {
        if ($class == 'XenForo_ControllerAdmin_User')
        {
            $extend[] = 'MassDeleteSpammers_AdminController';
        }
    }

    public static function load_class_model($class, array &$extend)
    {
        if ($class == 'XenForo_Model_User')
        {
            $extend[] = 'MassDeleteSpammers_ModelUser';
        }
    }
}
?>
