<?php
namespace Tapatalk\Listener;

class LoadClassModel
{
    public static function loadClassListener($class, &$extend)
    {
        $options = XenForo_Application::get('options');

        if ($class == 'XenForo_Model_Node')
        {
            $extend[] = 'Tapatalk_Model_Node';
        }
        elseif ($class == 'XenForo_Model_Search')
        {
            $extend[] = 'Tapatalk_Model_Search';
        }
        //if ($class == 'XenForo_ControllerPublic_Register')
        //{
        //    $extend[] = 'Tapatalk_ControllerPublic_Register';
        //}

        //if ($class == 'XenForo_ControllerPublic_Account')
        //{
        //    $extend[] = 'Tapatalk_ControllerPublic_Account';
        //}
    }
}
